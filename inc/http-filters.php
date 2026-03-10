<?php
/**
 * HTTP filters for the AI Provider for Any Compatible Endpoint plugin.
 *
 * Handles timeout extension, non-standard port support, and localhost access.
 *
 * @package AiProviderCompatibleEndpoint
 */

declare(strict_types=1);

namespace AiProviderCompatibleEndpoint;

/**
 * Increases the HTTP timeout for requests to the configured endpoint.
 *
 * Local/self-hosted LLMs can take over 30 seconds to respond, especially
 * on CPU-only hardware. The default WordPress timeout of 30s is too short.
 *
 * @param array  $parsed_args HTTP request arguments.
 * @param string $url         Request URL.
 * @return array Modified arguments.
 */
function increase_timeout( array $parsed_args, string $url ): array {
	$endpoint_url = get_option( 'ai_provider_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return $parsed_args;
	}

	$endpoint_host = wp_parse_url( $endpoint_url, PHP_URL_HOST );
	$request_host  = wp_parse_url( $url, PHP_URL_HOST );

	if ( $endpoint_host && $request_host && $endpoint_host === $request_host ) {
		$timeout = (int) get_option( 'ai_provider_timeout', 360 );
		$parsed_args['timeout'] = max( (float) ( $parsed_args['timeout'] ?? 30 ), (float) $timeout );
	}

	return $parsed_args;
}

/**
 * Adds the configured endpoint port to the list of allowed HTTP ports.
 *
 * WordPress's wp_safe_remote_request() only allows ports 80, 443, and 8080
 * by default. Self-hosted inference servers typically run on other ports.
 *
 * @param int[] $ports Allowed ports.
 * @return int[] Modified allowed ports.
 */
function allow_endpoint_port( array $ports ): array {
	$endpoint_url = get_option( 'ai_provider_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return $ports;
	}

	$parsed = wp_parse_url( $endpoint_url );
	if ( ! empty( $parsed['port'] ) ) {
		$ports[] = (int) $parsed['port'];
	}

	return array_unique( $ports );
}

/**
 * Marks the configured endpoint host as "external" for wp_safe_remote_request().
 *
 * WordPress blocks requests to localhost and private IP ranges via
 * wp_safe_remote_request(). When the endpoint is a local inference server
 * (e.g. localhost, 127.0.0.1, or a LAN IP), the SDK's HTTP adapter would
 * reject the request. This filter allows those hosts only when the plugin
 * is explicitly configured to use them.
 *
 * @param bool   $is_external Whether the host is considered external.
 * @param string $host        The hostname being checked.
 * @return bool
 */
function allow_endpoint_host( bool $is_external, string $host ): bool {
	if ( $is_external ) {
		return $is_external;
	}

	$endpoint_url = get_option( 'ai_provider_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return $is_external;
	}

	$endpoint_host = wp_parse_url( $endpoint_url, PHP_URL_HOST );

	if ( $endpoint_host && $endpoint_host === $host ) {
		return true;
	}

	return $is_external;
}
