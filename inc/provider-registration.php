<?php
/**
 * Provider registration with the WordPress AI Client.
 *
 * @package AiProviderCompatibleEndpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

declare(strict_types=1);

namespace AiProviderCompatibleEndpoint;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Registers the provider with the AI Client on init.
 *
 * Runs at priority 5 so the provider is available before most plugins act on
 * `init` (default priority 10).
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$endpoint_url = get_option( 'ai_provider_endpoint_url', '' );
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
	$api_key = get_option( 'ai_provider_api_key', '' );
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
	return (string) get_option( 'ai_provider_default_model', '' );
}
