<?php
/**
 * Plugin Name: Ultimate AI Connector for Compatible Endpoints
 * Description: Registers an AI Client provider for Ollama, LM Studio, or any AI endpoint using the standard chat completions API format.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 2.2.0
 * Author: Ultimate Multisite Community
 * Author URI: https://ultimatemultisite.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultimate-ai-connector-compatible-endpoints
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */


namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

define( 'ULTIMATE_AI_CONNECTOR_COMPATIBLE_ENDPOINTS_VERSION', '2.2.0' );

// ---------------------------------------------------------------------------
// Load function files (no SDK dependency).
// ---------------------------------------------------------------------------

require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/rest-api.php';
require_once __DIR__ . '/inc/http-filters.php';
require_once __DIR__ . '/inc/compat-openai-connector.php';

// ---------------------------------------------------------------------------
// Load SDK-dependent class files only when the AI Client SDK is available.
// These files extend SDK abstract classes and will fatal if loaded without it.
//
// On WordPress 7.0+ the SDK ships in core, so it's available at plugin
// file-include time. On WordPress 6.9 the SDK arrives via another plugin
// (e.g. superdav-ai-agent's bundled wordpress/php-ai-client) which may load
// AFTER us due to alphabetic plugin load order. We therefore defer the
// SDK-class load to `plugins_loaded:5` — late enough for any provider
// plugin to have registered the SDK autoloader, but early enough that our
// `init:5` provider registration still runs.
// ---------------------------------------------------------------------------

/**
 * Loads SDK-dependent class files when the AI Client SDK is available.
 *
 * Safe to call multiple times: each include uses require_once, and the SDK
 * presence check short-circuits when the SDK never becomes available.
 *
 * @return void
 */
function load_sdk_dependent_classes(): void {
	if ( ! class_exists( 'WordPress\\AiClient\\Providers\\ApiBasedImplementation\\AbstractApiProvider' ) ) {
		return;
	}

	require_once __DIR__ . '/inc/class-provider.php';
	require_once __DIR__ . '/inc/class-model.php';
	require_once __DIR__ . '/inc/class-model-directory.php';
	// Image generation was added to the SDK after the text generation APIs.
	// Keep the connector loadable on WordPress installations with older SDKs.
	if ( class_exists( 'WordPress\\AiClient\\Providers\\OpenAiCompatibleImplementation\\AbstractOpenAiCompatibleImageGenerationModel' ) ) {
		require_once __DIR__ . '/inc/class-image-model.php';
	}
	require_once __DIR__ . '/inc/class-provider-factory.php';
	require_once __DIR__ . '/inc/provider-registration.php';

	// Provider registration only runs when SDK classes are loaded.
	if ( function_exists( __NAMESPACE__ . '\\register_provider' ) ) {
		add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );
	}

	// Consolidate the auto-discovered per-provider connector cards into a
	// single card managed by our React component. Runs on wp_connectors_init,
	// which fires after Gutenberg's default-connectors registration (init:15).
	if ( function_exists( __NAMESPACE__ . '\\consolidate_connector_card' ) ) {
		add_action( 'wp_connectors_init', __NAMESPACE__ . '\\consolidate_connector_card' );
	}

	// Tell the AI plugin (and any future consumer of this filter) that this
	// connector has valid credentials when at least one provider is configured.
	if ( function_exists( __NAMESPACE__ . '\\filter_has_ai_credentials' ) ) {
		add_filter( 'wpai_has_ai_credentials', __NAMESPACE__ . '\\filter_has_ai_credentials' );
	}
}

// Try once at file-load time (WP 7.0+ in-core SDK path), and again at
// plugins_loaded:5 to catch SDKs registered by later-loading plugins on WP 6.9.
load_sdk_dependent_classes();
add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_sdk_dependent_classes', 5 );

// ---------------------------------------------------------------------------
// Hook registrations (SDK-independent).
// ---------------------------------------------------------------------------

// Settings.
add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_settings' );

add_action( 'options-connectors-wp-admin_init', __NAMESPACE__ . '\\enqueue_connector_module' );

// REST API.
add_action( 'rest_api_init', __NAMESPACE__ . '\\register_models_route' );

// HTTP filters.
add_filter( 'http_request_args', __NAMESPACE__ . '\\increase_timeout', 10, 2 );
add_filter( 'http_allowed_safe_ports', __NAMESPACE__ . '\\allow_endpoint_port' );
add_filter( 'http_request_host_is_external', __NAMESPACE__ . '\\allow_endpoint_host', 10, 2 );
