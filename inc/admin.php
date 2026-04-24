<?php
/**
 * Admin integration for the Ultimate AI Connector for Compatible Endpoints plugin.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the connector script module on the Connectors admin page.
 *
 * The `connectors-wp-admin_init` action fires only on the Settings > Connectors
 * page, so the module is loaded only where it is needed.
 */
function enqueue_connector_module(): void {
	wp_register_script_module(
		'ultimate-ai-connector-compatible-endpoints',
		plugins_url( 'build/connector.js', __DIR__ ),
		[
			[
				'id'     => '@wordpress/connectors',
				'import' => 'static',
			],
		],
		ULTIMATE_AI_CONNECTOR_COMPATIBLE_ENDPOINTS_VERSION
	);
	wp_enqueue_script_module( 'ultimate-ai-connector-compatible-endpoints' );
}
