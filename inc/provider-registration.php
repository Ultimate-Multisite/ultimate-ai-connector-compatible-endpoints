<?php
/**
 * Provider registration with the WordPress AI Client.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Registers the provider(s) with the AI Client on init.
 *
 * Runs at priority 5 so the provider is available before most plugins act on
 * `init` (default priority 10).
 *
 * This function registers:
 * 1. New multi-provider config (v2.0.0+) if any providers are configured
 * 2. Legacy single-provider config (v1.x) for backwards compatibility
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	// Try multi-provider config first (v2.0.0+).
	$providers = get_providers();
	if ( ! empty( $providers ) ) {
		ProviderFactory::registerAllProviders();
		return;
	}

	// Fall back to legacy single-provider config (v1.x).
	$endpoint_url = get_option( 'ultimate_ai_connector_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return;
	}

	// Set the base URL before any SDK method can call baseUrl().
	CompatibleEndpointProvider::$endpointUrl = $endpoint_url;

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( CompatibleEndpointProvider::class ) ) {
		return;
	}

	$registry->registerProvider( CompatibleEndpointProvider::class );

	// Inject the API key (or a placeholder for servers that don't need one).
	$api_key = get_option( 'ultimate_ai_connector_api_key', '' );
	if ( empty( $api_key ) ) {
		$api_key = 'no-key';
	}

	$registry->setProviderRequestAuthentication(
		CompatibleEndpointProvider::class,
		new ApiKeyRequestAuthentication( $api_key )
	);
}

/**
 * Returns the configured default model ID, or empty string if none set.
 *
 * @return string
 */
function get_default_model(): string {
	return (string) get_option( 'ultimate_ai_connector_default_model', '' );
}

/**
 * Get the ID of the provider currently being used for text generation.
 *
 * This can be hooked to implement custom routing logic.
 *
 * @return string|null Provider ID or null.
 */
function get_current_provider_id(): ?string {
	/**
	 * Filter the current provider ID for multi-provider setups.
	 *
	 * Use this to implement custom routing (e.g., based on model name,
	 * usage tracking, or request context).
	 *
	 * @param string|null $provider_id Current provider ID or null for auto.
	 * @return string|null Provider ID to use.
	 */
	return apply_filters( 'ultimate_ai_connector_current_provider_id', null );
}

/**
 * Switch to the next provider in the fallback chain.
 *
 * @param string $current_provider_id Current provider ID.
 * @return string|null Next provider ID or null if no more.
 */
function get_provider_fallback( string $current_provider_id ): ?string {
	$next = get_next_provider( $current_provider_id );
	return $next['id'] ?? null;
}
