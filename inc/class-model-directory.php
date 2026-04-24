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

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Lists available models from the configured endpoint's /models resource.
 *
 * Accepts an optional endpoint URL so that each dynamic provider can pass its
 * own URL rather than falling back to the legacy single-provider static.
 */
class CompatibleEndpointModelDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

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
	 * @param string $endpointUrl Base URL of the AI endpoint (no trailing slash).
	 */
	public function __construct( string $endpointUrl = '' ) {
		$this->endpointUrl = $endpointUrl !== ''
			? rtrim( $endpointUrl, '/' )
			: rtrim( CompatibleEndpointProvider::$endpointUrl, '/' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest(
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
	 * {@inheritDoc}
	 *
	 * Wraps the parent HTTP request with a WordPress transient so the /models
	 * endpoint is only called once per 24 hours (or until the transient expires
	 * or is deleted). The SDK's built-in PSR-16 object-cache layer is per-
	 * request on sites without Redis/Memcached, making it useless in standard
	 * php-fpm or FrankenPHP environments.
	 *
	 * Raw model data (id + name) is stored rather than serialised ModelMetadata
	 * objects to avoid recreating AbstractEnum instances that fail the strict
	 * (===) singleton identity checks used by the SDK's PromptBuilder internals.
	 *
	 * @return array<string, ModelMetadata> Map of model ID to model metadata.
	 */
	protected function sendListModelsRequest(): array {
		$endpoint_url = $this->endpointUrl;
		$cache_key    = 'ult_ai_connector_models_' . md5( $endpoint_url );

		/** @var list<array{id: string, name: string}>|false $cached */
		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $this->buildModelMetadataMapFromRaw( $cached );
		}

		// Cache miss: make the live HTTP request via the parent implementation.
		$map = parent::sendListModelsRequest();

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
		$capabilities = [
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		];

		$options = [
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
				$map[ $id ] = new ModelMetadata( $id, $name, $capabilities, $options );
			}
		}

		return $map;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @phpstan-type ModelsResponseData array{data?: list<array{id: string, name?: string}>}
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
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

		$capabilities = [
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		];

		$options = [
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
				static function ( array $modelData ) use ( $capabilities, $options ): ModelMetadata {
					$id   = $modelData['id'] ?? $modelData['name'] ?? 'unknown';
					$name = $modelData['name'] ?? $modelData['id'] ?? $id;

					return new ModelMetadata( $id, $name, $capabilities, $options );
				},
				$modelsData
			)
		);
	}
}
