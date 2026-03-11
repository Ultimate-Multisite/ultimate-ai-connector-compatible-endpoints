<?php
/**
 * Settings registration for the AI Provider for Any Compatible Endpoint plugin.
 *
 * @package AiProviderCompatibleEndpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

declare(strict_types=1);

namespace AiProviderCompatibleEndpoint;

/**
 * Registers the plugin settings for the REST API and admin.
 */
function register_settings(): void {
	register_setting(
		'ai_provider_connector',
		'ai_provider_endpoint_url',
		[
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
			'show_in_rest'      => true,
		]
	);

	register_setting(
		'ai_provider_connector',
		'ai_provider_api_key',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
			'show_in_rest'      => true,
		]
	);

	register_setting(
		'ai_provider_connector',
		'ai_provider_default_model',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
			'show_in_rest'      => true,
		]
	);

	register_setting(
		'ai_provider_connector',
		'ai_provider_timeout',
		[
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 360,
			'show_in_rest'      => true,
		]
	);
}
