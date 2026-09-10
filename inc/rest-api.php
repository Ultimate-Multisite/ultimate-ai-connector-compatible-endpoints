<?php
/**
 * REST API endpoint for listing models from the configured endpoint.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a REST route that proxies /models from the configured endpoint.
 *
 * This avoids browser CORS issues by fetching server-side.
 */
function register_models_route(): void {
	register_rest_route(
		'ultimate-ai-connector-compatible-endpoints/v1',
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
				'provider_id'  => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'config_id'    => [
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
	$api_key      = $request->get_param( 'api_key' );
	$provider_id  = $request->get_param( 'provider_id' );
	$config_id    = $request->get_param( 'config_id' );
	$resolved     = null;

	// The Connectors UI knows the stable config ID, while SDK consumers know
	// the registration-order provider ID. Support both without exposing saved
	// API keys to the browser or placing them in query strings.
	if ( ! empty( $config_id ) ) {
		$resolved = get_provider( (string) $config_id );
	} elseif ( ! empty( $provider_id ) ) {
		$resolved = get_provider_by_sdk_id( (string) $provider_id );
	}

	if ( empty( $endpoint_url ) ) {
		// Resolution order:
		// 1. If a config or SDK provider ID was requested, use that provider.
		//    This is the multi-provider path used by the AI Agent loop and the
		//    `wp sd-ai-agent models --provider=...` CLI command — without this,
		//    every OpenAI-compatible provider would resolve to the same primary
		//    config and the agent would see duplicate model lists.
		// 2. Otherwise, fall back to the highest-priority configured provider.
		// 3. Finally, fall back to the legacy single-provider option.
		if ( null === $resolved ) {
			$resolved = get_primary_provider();
		}
		if ( $resolved ) {
			$endpoint_url = $resolved['endpoint_url'] ?? '';
		} else {
			// Fall back to legacy single-provider option.
			$endpoint_url = get_option( 'ultimate_ai_connector_endpoint_url', '' );
		}
	}

	// Reuse a saved provider key only when the request targets that provider's
	// exact endpoint. This prevents a caller from pairing a config ID with an
	// arbitrary URL and forwarding the stored credential to another host.
	if (
		null === $api_key &&
		is_array( $resolved ) &&
		rtrim( (string) ( $resolved['endpoint_url'] ?? '' ), '/' ) === rtrim( (string) $endpoint_url, '/' )
	) {
		$api_key = $resolved['api_key'] ?? '';
	}

	if ( null === $api_key ) {
		$legacy_endpoint_url = (string) get_option( 'ultimate_ai_connector_endpoint_url', '' );
		if (
			'' !== $legacy_endpoint_url &&
			rtrim( $legacy_endpoint_url, '/' ) === rtrim( (string) $endpoint_url, '/' )
		) {
			$api_key = get_option( 'ultimate_ai_connector_api_key', '' );
		}
	}

	if ( empty( $endpoint_url ) ) {
		return new \WP_Error(
			'no_endpoint',
			__( 'No endpoint URL configured.', 'ultimate-ai-connector-compatible-endpoints' ),
			[ 'status' => 400 ]
		);
	}

	$models_url = rtrim( $endpoint_url, '/' ) . '/models';

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
				__( 'Upstream returned HTTP %d.', 'ultimate-ai-connector-compatible-endpoints' ),
				$code
			),
			[ 'status' => 502 ]
		);
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) ) {
		return new \WP_Error(
			'invalid_response',
			__( 'Could not parse models response.', 'ultimate-ai-connector-compatible-endpoints' ),
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

	$image_model = '';
	if ( is_array( $resolved ) && 'none' !== ( $resolved['image_protocol'] ?? 'none' ) ) {
		$image_model = (string) ( $resolved['image_model'] ?? '' );
	}

	$models = array_map(
		static function ( array $model ) use ( $image_model ): array {
			$id   = $model['id'] ?? $model['name'] ?? 'unknown';
			$name = $model['name'] ?? $model['id'] ?? $id;
			return [
				'id'           => $id,
				'name'         => $name,
				'capabilities' => [ $id === $image_model ? 'image_generation' : 'text_generation' ],
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
