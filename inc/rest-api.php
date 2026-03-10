<?php
/**
 * REST API endpoint for listing models from the configured endpoint.
 *
 * @package AiProviderCompatibleEndpoint
 */

declare(strict_types=1);

namespace AiProviderCompatibleEndpoint;

/**
 * Registers a REST route that proxies /models from the configured endpoint.
 *
 * This avoids browser CORS issues by fetching server-side.
 */
function register_models_route(): void {
	register_rest_route(
		'ai-provider-for-any-compatible-endpoint/v1',
		'/models',
		[
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\\rest_list_models',
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => [
				'endpoint_url' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
				],
				'api_key'      => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]
	);
}

/**
 * Fetches models from the configured endpoint and returns them.
 *
 * @param \WP_REST_Request $request REST request object.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_list_models( \WP_REST_Request $request ) {
	$endpoint_url = $request->get_param( 'endpoint_url' );
	if ( empty( $endpoint_url ) ) {
		$endpoint_url = get_option( 'ai_provider_endpoint_url', '' );
	}

	if ( empty( $endpoint_url ) ) {
		return new \WP_Error(
			'no_endpoint',
			__( 'No endpoint URL configured.', 'ai-provider-for-any-compatible-endpoint' ),
			[ 'status' => 400 ]
		);
	}

	$models_url = rtrim( $endpoint_url, '/' ) . '/models';

	$api_key = $request->get_param( 'api_key' );
	if ( null === $api_key ) {
		$api_key = get_option( 'ai_provider_api_key', '' );
	}

	$headers = [
		'Accept' => 'application/json',
	];

	if ( ! empty( $api_key ) ) {
		$headers['Authorization'] = 'Bearer ' . $api_key;
	}

	$response = wp_remote_get(
		$models_url,
		[
			'headers' => $headers,
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		return new \WP_Error(
			'request_failed',
			$response->get_error_message(),
			[ 'status' => 502 ]
		);
	}

	$code = wp_remote_retrieve_response_code( $response );

	if ( $code < 200 || $code >= 300 ) {
		return new \WP_Error(
			'upstream_error',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Upstream returned HTTP %d.', 'ai-provider-for-any-compatible-endpoint' ),
				$code
			),
			[ 'status' => 502 ]
		);
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) ) {
		return new \WP_Error(
			'invalid_response',
			__( 'Could not parse models response.', 'ai-provider-for-any-compatible-endpoint' ),
			[ 'status' => 502 ]
		);
	}

	// Standard format: { data: [...] }  Ollama format: { models: [...] }
	$models_data = [];
	if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
		$models_data = $body['data'];
	} elseif ( isset( $body['models'] ) && is_array( $body['models'] ) ) {
		$models_data = $body['models'];
	}

	$models = array_map(
		static function ( array $model ): array {
			$id   = $model['id'] ?? $model['name'] ?? 'unknown';
			$name = $model['name'] ?? $model['id'] ?? $id;
			return [
				'id'   => $id,
				'name' => $name,
			];
		},
		$models_data
	);

	// Sort by name.
	usort(
		$models,
		static function ( array $a, array $b ): int {
			return strcasecmp( $a['name'], $b['name'] );
		}
	);

	return rest_ensure_response( $models );
}
