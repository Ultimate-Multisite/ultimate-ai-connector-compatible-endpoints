<?php
/**
 * Settings registration for the Ultimate AI Connector for Compatible Endpoints plugin.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default provider configuration structure.
 *
 * @param array $config Provider config array.
 * @return array Normalized config with defaults.
 */
function get_default_provider_config( array $config = [] ): array {
	return wp_parse_args(
		$config,
		[
			'id'            => '',
			'name'          => '',
			'endpoint_url'   => '',
			'api_key'       => '',
			'default_model' => '',
			'timeout'      => 360,
			'enabled'      => true,
			'endpoint_type' => 'generic',
			'image_protocol' => 'none',
			'image_model'    => '',
		]
	);
}

/**
 * Sanitize a single provider config.
 *
 * @param array $config Provider config.
 * @return array Sanitized config.
 */
function sanitize_provider_config( array $config ): array {
	$allowed_endpoint_types = [ 'generic', 'deepseek', 'ollama' ];
	$allowed_image_protocols = [ 'none', 'openai', 'chat_completions' ];
	$endpoint_type          = sanitize_text_field( $config['endpoint_type'] ?? 'generic' );
	$image_protocol         = sanitize_text_field( $config['image_protocol'] ?? 'none' );
	if ( ! in_array( $endpoint_type, $allowed_endpoint_types, true ) ) {
		$endpoint_type = 'generic';
	}
	if ( ! in_array( $image_protocol, $allowed_image_protocols, true ) ) {
		$image_protocol = 'none';
	}

	return [
		'id'            => sanitize_text_field( $config['id'] ?? '' ),
		'name'          => sanitize_text_field( $config['name'] ?? '' ),
		'endpoint_url'  => esc_url_raw( $config['endpoint_url'] ?? '' ),
		'api_key'      => sanitize_text_field( $config['api_key'] ?? '' ),
		'default_model' => sanitize_text_field( $config['default_model'] ?? '' ),
		'timeout'      => absint( $config['timeout'] ?? 360 ),
		'enabled'     => (bool) ( $config['enabled'] ?? true ),
		'endpoint_type' => $endpoint_type,
		'image_protocol' => $image_protocol,
		'image_model'    => sanitize_text_field( $config['image_model'] ?? '' ),
	];
}

/**
 * Sanitize the providers list.
 *
 * @param mixed $value Raw providers value.
 * @return array Sanitized providers list.
 */
function sanitize_providers_list( $value ): array {
	if ( ! is_array( $value ) ) {
		return [];
	}

	$sanitized = [];
	foreach ( $value as $provider ) {
		if ( is_array( $provider ) ) {
			$sanitized[] = sanitize_provider_config( $provider );
		}
	}

	return array_filter( $sanitized, static fn( $p ) => ! empty( $p['endpoint_url'] ) );
}

/**
 * Sanitize provider order array.
 *
 * @param mixed $value Raw order value.
 * @return array Sanitized order IDs.
 */
function sanitize_provider_order( $value ): array {
	if ( ! is_array( $value ) ) {
		return [];
	}

	$ids = array_map( 'sanitize_text_field', $value );
	return array_filter( $ids, static fn( $id ) => ! empty( $id ) );
}

/**
 * Registers the plugin settings for the REST API and admin.
 */
function register_settings(): void {
	// Main settings.
	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_endpoint_url',
		[
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
			'show_in_rest'      => true,
		]
	);

	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_api_key',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
			'show_in_rest'      => true,
		]
	);

	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_default_model',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
			'show_in_rest'      => true,
		]
	);

	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_timeout',
		[
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 360,
			'show_in_rest'      => true,
		]
	);

	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_image_protocol',
		[
			'type' => 'string',
			'sanitize_callback' => static function ( $value ): string {
				$value = sanitize_text_field( $value );
				return in_array( $value, [ 'none', 'openai', 'chat_completions' ], true ) ? $value : 'none';
			},
			'default' => 'none',
			'show_in_rest' => true,
		]
	);

	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_image_model',
		[
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
			'show_in_rest' => true,
		]
	);

	// Multi-provider settings (v2.0.0+).
	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_providers',
		[
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_providers_list',
			'default'           => [],
			'show_in_rest'      => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'            => [ 'type' => 'string' ],
							'name'          => [ 'type' => 'string' ],
							'endpoint_url'  => [ 'type' => 'string' ],
							'api_key'     => [ 'type' => 'string' ],
							'default_model' => [ 'type' => 'string' ],
							'timeout'     => [ 'type' => 'integer' ],
							'enabled'      => [ 'type' => 'boolean' ],
							'endpoint_type' => [
								'type' => 'string',
								'enum' => [ 'generic', 'deepseek', 'ollama' ],
							],
							'image_protocol' => [
								'type' => 'string',
								'enum' => [ 'none', 'openai', 'chat_completions' ],
							],
							'image_model' => [ 'type' => 'string' ],
						],
					],
				],
			],
		]
	);

	// Provider fallback order (array of provider IDs, first = highest priority).
	register_setting(
		'ultimate_ai_connector',
		'ultimate_ai_connector_provider_order',
		[
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_provider_order',
			'default'           => [],
			'show_in_rest'      => [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
		]
	);
}

/**
 * Get all provider configurations.
 *
 * @return array List of provider configs.
 */
function get_providers(): array {
	$providers = get_option( 'ultimate_ai_connector_providers', [] );
	return is_array( $providers ) ? $providers : [];
}

/**
 * Get provider by ID.
 *
 * @param string $id Provider ID.
 * @return array|null Provider config or null.
 */
function get_provider( string $id ): ?array {
	$providers = get_providers();
	foreach ( $providers as $provider ) {
		if ( ( $provider['id'] ?? '' ) === $id ) {
			return $provider;
		}
	}
	return null;
}

/**
 * Compute the SDK-level provider ID for a given registration order index.
 *
 * Mirrors ProviderFactory::sdkProviderIdForIndex(). Duplicated here so it can
 * be called before SDK-dependent class files are loaded (settings.php loads
 * unconditionally; class-provider-factory.php is gated on SDK availability).
 *
 * @param int $index 0-based registration index.
 * @return string SDK provider ID.
 */
function sdk_provider_id_for_index( int $index ): string {
	return 0 === $index
		? 'ai-provider-for-any-openai-compatible'
		: 'ai-provider-for-any-openai-compatible-' . ( $index + 1 );
}

/**
 * Get a provider config by its SDK-level provider ID.
 *
 * SDK provider IDs are derived from registration order:
 * - Index 0: 'ai-provider-for-any-openai-compatible'
 * - Index N: 'ai-provider-for-any-openai-compatible-' . (N + 1)
 *
 * This mirrors ProviderFactory::sdkProviderIdForIndex() and only walks the
 * subset of providers that actually get registered (i.e. enabled and with a
 * non-empty endpoint_url), matching the iteration in
 * ProviderFactory::registerAllProviders().
 *
 * @param string $sdk_provider_id SDK-level provider ID.
 * @return array|null Provider config or null when no match.
 */
function get_provider_by_sdk_id( string $sdk_provider_id ): ?array {
	if ( '' === $sdk_provider_id ) {
		return null;
	}

	$ordered = get_providers_ordered();
	$index   = 0;
	foreach ( $ordered as $provider ) {
		if ( empty( $provider['endpoint_url'] ) || ! ( $provider['enabled'] ?? true ) ) {
			continue;
		}

		$candidate_sdk_id = sdk_provider_id_for_index( $index );
		if ( $candidate_sdk_id === $sdk_provider_id ) {
			return $provider;
		}
		++$index;
	}

	return null;
}

/**
 * Get providers in fallback order (first = highest priority).
 *
 * @return array Ordered list of provider configs.
 */
function get_providers_ordered(): array {
	$providers = get_providers();
	$order     = get_option( 'ultimate_ai_connector_provider_order', [] );

	if ( empty( $order ) ) {
		return $providers;
	}

	// Create map for quick lookup.
	$by_id = [];
	foreach ( $providers as $provider ) {
		$by_id[ $provider['id'] ?? '' ] = $provider;
	}

	// Reorder based on order array.
	$ordered = [];
	foreach ( $order as $id ) {
		if ( isset( $by_id[ $id ] ) ) {
			$ordered[] = $by_id[ $id ];
			unset( $by_id[ $id ] );
		}
	}

	// Append any un-ordered providers.
	return array_merge( $ordered, array_values( $by_id ) );
}

/**
 * Get the highest-priority enabled provider.
 *
 * @return array|null Provider config or null.
 */
function get_primary_provider(): ?array {
	$ordered = get_providers_ordered();
	foreach ( $ordered as $provider ) {
		if ( ! empty( $provider['endpoint_url'] ) && ( $provider['enabled'] ?? true ) ) {
			return $provider;
		}
	}
	return null;
}

/**
 * Get the next available provider after a given provider.
 *
 * @param string $after_id Provider ID to start after.
 * @return array|null Next provider or null.
 */
function get_next_provider( string $after_id = '' ): ?array {
	$ordered = get_providers_ordered();
	$found   = empty( $after_id );

	foreach ( $ordered as $provider ) {
		$id = $provider['id'] ?? '';
		if ( ! $found ) {
			if ( $id === $after_id ) {
				$found = true;
			}
			continue;
		}
		if ( ! empty( $provider['endpoint_url'] ) && ( $provider['enabled'] ?? true ) ) {
			return $provider;
		}
	}
	return null;
}
