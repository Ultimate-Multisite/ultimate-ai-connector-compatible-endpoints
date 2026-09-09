<?php
/**
 * Model metadata directory for a compatible AI endpoint.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Lists available models from the configured endpoint's /models resource.
 *
 * Accepts an optional endpoint URL so that each dynamic provider can pass its
 * own URL rather than falling back to the legacy single-provider static.
 */
class CompatibleEndpointModelDirectory implements ModelMetadataDirectoryInterface, WithHttpTransporterInterface, WithRequestAuthenticationInterface {

	use WithHttpTransporterTrait;
	use WithRequestAuthenticationTrait;

	/**
	 * The base URL for this directory instance.
	 *
	 * Set via constructor for dynamic multi-provider support; falls back to
	 * the legacy CompatibleEndpointProvider static when not provided.
	 *
	 * @var string
	 */
	private string $endpointUrl;

	/**
	 * The default model ID for this provider, if any.
	 *
	 * When set, listModelMetadata() sorts the results so this model appears first,
	 * giving it priority during SDK auto-discovery.
	 *
	 * @var string
	 */
	private string $defaultModel = '';

	/** @var string Image generation protocol for the explicitly selected model. */
	private string $imageProtocol = 'none';

	/** @var string Explicitly configured image generation model ID. */
	private string $imageModel = '';

	/**
	 * Request-local model metadata cache.
	 *
	 * This intentionally stays in PHP memory only. The SDK parent directory
	 * persists ModelMetadata DTOs into the WordPress object cache, which can be
	 * deserialized as __PHP_Incomplete_Class by some persistent cache backends or
	 * after AI Client class-loading/version changes. Persist raw model IDs/names in
	 * the plugin transient instead and rebuild fresh DTOs for each request.
	 *
	 * @var array<string, ModelMetadata>|null
	 */
	private ?array $modelMetadataMap = null;

	/**
	 * @param string $endpointUrl  Base URL of the AI endpoint (no trailing slash).
	 * @param string $defaultModel  Default model ID to sort first in listings.
	 * @param string $imageProtocol Image generation protocol for $imageModel.
	 * @param string $imageModel    Explicit image generation model ID.
	 */
	public function __construct( string $endpointUrl = '', string $defaultModel = '', string $imageProtocol = 'none', string $imageModel = '' ) {
		$this->endpointUrl = $endpointUrl !== ''
			? rtrim( $endpointUrl, '/' )
			: rtrim( CompatibleEndpointProvider::$endpointUrl, '/' );
		$this->defaultModel = $defaultModel;
		$this->imageProtocol = $imageProtocol;
		$this->imageModel    = $imageModel;
	}

	/**
	 * {@inheritDoc}
	 */
	public function listModelMetadata(): array {
		$models = $this->getModelMetadataMap();

		// When a default model is configured, sort it first so SDK auto-discovery
		// picks it over other models with the same capabilities.
		if ( $this->defaultModel !== '' && isset( $models[ $this->defaultModel ] ) ) {
			$default = $models[ $this->defaultModel ];
			unset( $models[ $this->defaultModel ] );
			array_unshift( $models, $default );
		}

		return array_values( $models );
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasModelMetadata( string $modelId ): bool {
		$models_metadata = $this->getModelMetadataMap();

		return isset( $models_metadata[ $modelId ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function getModelMetadata( string $modelId ): ModelMetadata {
		$models_metadata = $this->getModelMetadataMap();

		if ( ! isset( $models_metadata[ $modelId ] ) ) {
			throw new InvalidArgumentException( sprintf( 'No model with ID %s was found in the provider', $modelId ) );
		}

		return $models_metadata[ $modelId ];
	}

	/**
	 * Returns a request-local map of model ID to model metadata.
	 *
	 * @return array<string, ModelMetadata> Map of model ID to model metadata.
	 */
	private function getModelMetadataMap(): array {
		if ( null === $this->modelMetadataMap ) {
			$this->modelMetadataMap = $this->sendListModelsRequest();
		}

		return $this->modelMetadataMap;
	}

	/**
	 * Creates a request for the endpoint API.
	 *
	 * @param HttpMethodEnum                 $method HTTP method.
	 * @param string                         $path API path relative to endpoint URL.
	 * @param array<string, string|string[]> $headers Request headers.
	 * @param string|array<string, mixed>|null $data Request data.
	 * @return Request Request DTO.
	 */
	private function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		return new Request(
			$method,
			$this->endpointUrl . '/' . ltrim( $path, '/' ),
			$headers,
			$data
		);
	}

	/**
	 * Sends the /models request and returns a map of model metadata.
	 *
	 * Wraps the HTTP request with a WordPress transient so the /models
	 * endpoint is only called once per 24 hours (or until the transient expires
	 * or is deleted).
	 *
	 * Raw model data (id + name) is stored rather than serialised ModelMetadata
	 * objects to avoid persistent object-cache deserialization into
	 * __PHP_Incomplete_Class and to avoid recreating AbstractEnum instances that
	 * fail the strict (===) singleton identity checks used by the SDK's
	 * PromptBuilder internals.
	 *
	 * @return array<string, ModelMetadata> Map of model ID to model metadata.
	 */
	private function sendListModelsRequest(): array {
		$endpoint_url = $this->endpointUrl;
		$cache_key    = 'ult_ai_connector_models_' . md5( $endpoint_url );

		/** @var list<array{id: string, name: string}>|false $cached */
		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $this->buildModelMetadataMapFromRaw( $cached );
		}

		// Cache miss: make the live HTTP request.
		$http_transporter = $this->getHttpTransporter();
		$request          = $this->createRequest( HttpMethodEnum::GET(), 'models' );
		$request          = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response         = $http_transporter->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		$map = [];
		foreach ( $this->parseResponseToModelMetadataList( $response ) as $metadata ) {
			$map[ $metadata->getId() ] = $metadata;
		}

		if ( ! empty( $map ) ) {
			$raw = [];
			foreach ( $map as $metadata ) {
				$raw[] = [
					'id'   => $metadata->getId(),
					'name' => $metadata->getName(),
				];
			}
			set_transient( $cache_key, $raw, DAY_IN_SECONDS );
		}

		return $map;
	}

	/**
	 * Reconstructs a model ID → ModelMetadata map from raw cached data.
	 *
	 * Creates fresh ModelMetadata instances (including fresh CapabilityEnum and
	 * OptionEnum singleton instances from their factory methods) to avoid the
	 * enum-identity problem that arises when deserialising stored objects.
	 *
	 * @param list<array{id: string, name: string}> $raw Cached raw model data.
	 * @return array<string, ModelMetadata> Map of model ID to model metadata.
	 */
	private function buildModelMetadataMapFromRaw( array $raw ): array {
		$text_capabilities = [
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		];
		$text_options = [
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			// Null accepted values — see parseResponseToModelMetadataList() comment.
			new SupportedOption( OptionEnum::inputModalities() ),
			new SupportedOption( OptionEnum::outputModalities() ),
			new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
			new SupportedOption( OptionEnum::outputSchema() ),
		];

		$map = [];
		foreach ( $raw as $item ) {
			$id   = (string) ( $item['id'] ?? '' );
			$name = (string) ( $item['name'] ?? $id );
			if ( '' !== $id ) {
				$map[ $id ] = new ModelMetadata( $id, $name, $this->getCapabilitiesForModel( $id, $text_capabilities ), $this->getOptionsForModel( $id, $text_options ) );
			}
		}

		return $map;
	}

	/**
	 * Parses the API models response into model metadata DTOs.
	 *
	 * @phpstan-type ModelsResponseData array{data?: list<array{id: string, name?: string}>}
	 * @param Response $response Response from the models endpoint.
	 * @return list<ModelMetadata> List of model metadata DTOs.
	 */
	private function parseResponseToModelMetadataList( Response $response ): array {
		/** @var ModelsResponseData $responseData */
		$responseData = $response->getData();

		$modelsData = [];
		if ( isset( $responseData['data'] ) && is_array( $responseData['data'] ) ) {
			$modelsData = $responseData['data'];
		}

		// Fallback: some servers (e.g. Ollama < 0.5) return {models: [...]} instead of {data: [...]}.
		if ( empty( $modelsData ) && isset( $responseData['models'] ) && is_array( $responseData['models'] ) ) {
			$modelsData = $responseData['models'];
		}

		if ( empty( $modelsData ) ) {
			return [];
		}

		$text_capabilities = [
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		];

		$text_options = [
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			// Don't restrict inputModalities/outputModalities to specific enum values.
			// The SDK caches ModelMetadata via PSR-16, which deserializes enum objects
			// into new instances that fail strict (===) identity checks against the
			// singletons used by the PromptBuilder's ModelRequirements. Passing null
			// (accept any value) avoids this SDK cache-deserialization bug.
			new SupportedOption( OptionEnum::inputModalities() ),
			new SupportedOption( OptionEnum::outputModalities() ),
			new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
			new SupportedOption( OptionEnum::outputSchema() ),
		];

		return array_values(
			array_map(
				function ( array $modelData ) use ( $text_capabilities, $text_options ): ModelMetadata {
					$id   = $modelData['id'] ?? $modelData['name'] ?? 'unknown';
					$name = $modelData['name'] ?? $modelData['id'] ?? $id;

					return new ModelMetadata( $id, $name, $this->getCapabilitiesForModel( $id, $text_capabilities ), $this->getOptionsForModel( $id, $text_options ) );
				},
				$modelsData
			)
		);
	}

	/**
	 * Returns the explicitly configured capabilities for a model.
	 *
	 * @param string $model_id          Model identifier.
	 * @param array  $text_capabilities Text model capabilities.
	 * @return array Model capabilities.
	 */
	private function getCapabilitiesForModel( string $model_id, array $text_capabilities ): array {
		if ( $model_id === $this->imageModel && 'none' !== $this->imageProtocol ) {
			return [ CapabilityEnum::imageGeneration() ];
		}

		return $text_capabilities;
	}

	/**
	 * Returns options appropriate for the configured model capability.
	 *
	 * @param string $model_id     Model identifier.
	 * @param array  $text_options Text model options.
	 * @return array Model options.
	 */
	private function getOptionsForModel( string $model_id, array $text_options ): array {
		if ( $model_id === $this->imageModel && 'none' !== $this->imageProtocol ) {
			return [
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::outputFileType() ),
				new SupportedOption( OptionEnum::outputMimeType() ),
				new SupportedOption( OptionEnum::outputMediaAspectRatio() ),
				new SupportedOption( OptionEnum::outputMediaOrientation() ),
				new SupportedOption( OptionEnum::customOptions() ),
			];
		}

		return $text_options;
	}
}
