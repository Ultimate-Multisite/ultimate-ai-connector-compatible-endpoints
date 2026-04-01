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
 */
class CompatibleEndpointModelDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

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
			CompatibleEndpointProvider::url( $path ),
			$headers,
			$data
		);
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
