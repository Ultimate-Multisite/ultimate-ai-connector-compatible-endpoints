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
	return [
		'id'            => sanitize_text_field( $config['id'] ?? '' ),
		'name'          => sanitize_text_field( $config['name'] ?? '' ),
		'endpoint_url'  => esc_url_raw( $config['endpoint_url'] ?? '' ),
		'api_key'      => sanitize_text_field( $config['api_key'] ?? '' ),
		'default_model' => sanitize_text_field( $config['default_model'] ?? '' ),
		'timeout'      => absint( $config['timeout'] ?? 360 ),
		'enabled'     => (bool) ( $config['enabled'] ?? true ),
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
