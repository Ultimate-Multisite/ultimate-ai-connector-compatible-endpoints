<?php
/**
 * Text generation model for a compatible AI endpoint.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Text generation model that forwards requests to the configured endpoint
 * using the standard chat/completions format.
 */
class CompatibleEndpointModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Per-SDK-provider-ID endpoint URL registry.
	 *
	 * Populated at provider registration time so each dynamic provider's model
	 * can resolve its own endpoint URL at request time.
	 *
	 * @var array<string, string>
	 */
	private static array $endpointUrls = [];

	/**
	 * Register an endpoint URL for a given SDK provider ID.
	 *
	 * Called by ProviderFactory when a dynamic provider class is registered
	 * so that model instances can resolve the correct endpoint at request time.
	 *
	 * @param string $sdk_provider_id SDK-level provider ID (e.g. 'ai-provider-for-any-openai-compatible').
	 * @param string $endpoint_url    Base endpoint URL.
	 */
	public static function registerEndpointUrl( string $sdk_provider_id, string $endpoint_url ): void {
		self::$endpointUrls[ $sdk_provider_id ] = rtrim( $endpoint_url, '/' );
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
		$provider_id = $this->providerMetadata()->getId();
		$base_url    = self::$endpointUrls[ $provider_id ]
			?? rtrim( CompatibleEndpointProvider::$endpointUrl, '/' );

		return new Request(
			$method,
			$base_url . '/' . ltrim( $path, '/' ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
