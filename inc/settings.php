<?php
/**
 * Settings registration for the Ultimate AI Connector for Compatible Endpoints plugin.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin settings for the REST API and admin.
 */
function register_settings(): void {
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
}
