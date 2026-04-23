<?php
/**
 * Provider class for a compatible AI endpoint.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Common\Exception\RuntimeException;

/**
 * Provider class for a compatible AI endpoint.
 *
 * The base URL is read from plugin settings and stored in a static property
 * so that it is available to the SDK's static `baseUrl()` method.
 */
class CompatibleEndpointProvider extends AbstractApiProvider {

	/**
	 * Configured endpoint URL. Set from options before registration.
	 *
	 * @var string
	 */
	public static string $endpointUrl = '';

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return rtrim( self::$endpointUrl, '/' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new CompatibleEndpointModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			'Unsupported model capabilities: ' . esc_html( implode( ', ', $capabilities ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'ultimate-ai-connector-compatible-endpoints',
			'Compatible Endpoint',
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
			__( 'Connect to Ollama, LM Studio, or any AI endpoint using the standard chat completions API format.', 'ultimate-ai-connector-compatible-endpoints' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Returns a configuration-based availability check rather than a live HTTP
	 * request. Using ListModelsApiBasedProviderAvailability here would fire a
	 * blocking HTTP request to the /models endpoint on every page load where
	 * isConfigured() is called (e.g. Connectors settings page, AI feature
	 * discovery). On sites without a persistent object cache the SDK's internal
	 * PSR-16 cache is cold on every request, causing 7+ second latency spikes.
	 *
	 * Actual endpoint reachability is validated lazily the first time the user
	 * makes an AI generation request.
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new class implements ProviderAvailabilityInterface {
			/**
			 * Checks whether the endpoint URL is configured.
			 *
			 * @return bool True when the endpoint URL option is non-empty.
			 */
			public function isConfigured(): bool {
				return ! empty( CompatibleEndpointProvider::$endpointUrl );
			}
		};
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new CompatibleEndpointModelDirectory();
	}
}
