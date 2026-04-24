<?php
/**
 * HTTP filters for the Ultimate AI Connector for Compatible Endpoints plugin.
 *
 * Handles timeout extension, non-standard port support, and localhost access.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Increases the HTTP timeout for AI generation requests to the configured endpoint.
 *
 * The extended timeout is applied only to chat-completions requests. The /models
 * endpoint used for availability checks and model listing must NOT get the long
 * timeout: a slow or unreachable endpoint would otherwise block every page load
 * for up to 360 seconds while the SDK's isConfigured() check waits for a response.
 *
 * Local/self-hosted LLMs can take over 30 seconds to respond during generation,
 * especially on CPU-only hardware, so the long timeout remains for those paths.
 *
 * @param array  $parsed_args HTTP request arguments.
 * @param string $url         Request URL.
 * @return array Modified arguments.
 */
/**
 * Returns all configured endpoint URLs (multi-provider aware).
 *
 * @return array<array{url: string, timeout: int}> List of endpoint configs.
 */
function get_all_endpoint_configs(): array {
	$configs   = [];
	$providers = get_providers();

	if ( ! empty( $providers ) ) {
		foreach ( $providers as $provider ) {
			$url = $provider['endpoint_url'] ?? '';
			if ( ! empty( $url ) && ( $provider['enabled'] ?? true ) ) {
				$configs[] = [
					'url'     => $url,
					'timeout' => (int) ( $provider['timeout'] ?? 360 ),
				];
			}
		}
		return $configs;
	}

	// Legacy single-provider fallback.
	$url = get_option( 'ultimate_ai_connector_endpoint_url', '' );
	if ( ! empty( $url ) ) {
		$configs[] = [
			'url'     => $url,
			'timeout' => (int) get_option( 'ultimate_ai_connector_timeout', 360 ),
		];
	}

	return $configs;
}

function increase_timeout( array $parsed_args, string $url ): array {
	// Only extend timeout for chat completions, not model listing or other paths.
	if ( ! str_contains( $url, '/chat/completions' ) ) {
		return $parsed_args;
	}

	$request_host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $request_host ) {
		return $parsed_args;
	}

	foreach ( get_all_endpoint_configs() as $config ) {
		$endpoint_host = wp_parse_url( $config['url'], PHP_URL_HOST );
		if ( $endpoint_host && $endpoint_host === $request_host ) {
			$parsed_args['timeout'] = max(
				(float) ( $parsed_args['timeout'] ?? 30 ),
				(float) $config['timeout']
			);
			break;
		}
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
	foreach ( get_all_endpoint_configs() as $config ) {
		$parsed = wp_parse_url( $config['url'] );
		if ( ! empty( $parsed['port'] ) ) {
			$ports[] = (int) $parsed['port'];
		}
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

	foreach ( get_all_endpoint_configs() as $config ) {
		$endpoint_host = wp_parse_url( $config['url'], PHP_URL_HOST );
		if ( $endpoint_host && $endpoint_host === $host ) {
			return true;
		}
	}

	return $is_external;
}
