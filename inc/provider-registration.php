<?php
/**
 * Provider registration with the WordPress AI Client.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Connector ID rendered by our JS module (src/index.jsx SLUG constant).
 *
 * This single slug represents the plugin in the Settings > Connectors UI.
 * Each per-provider entry the SDK auto-discovers under
 * `ai-provider-for-any-openai-compatible[-N]` is collapsed into this card.
 */
const CONNECTOR_SLUG = 'ultimate-ai-connector-compatible-endpoints';

/**
 * Prefix used by all SDK provider IDs this plugin registers.
 *
 * Mirrors sdk_provider_id_for_index() in settings.php — the primary provider
 * uses this exact ID and additional providers append `-2`, `-3`, etc.
 */
const SDK_PROVIDER_ID_PREFIX = 'ai-provider-for-any-openai-compatible';

/**
 * Registers the provider(s) with the AI Client on init.
 *
 * Runs at priority 5 so the provider is available before most plugins act on
 * `init` (default priority 10).
 *
 * This function registers:
 * 1. New multi-provider config (v2.0.0+) if any providers are configured
 * 2. Legacy single-provider config (v1.x) for backwards compatibility
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	// The WordPress AI plugin applies its built-in model preferences before the
	// SDK's normal first-model fallback. Put configured connector defaults ahead
	// of those preferences so the Default Model setting is actually honoured.
	add_filter( 'wpai_preferred_text_models', __NAMESPACE__ . '\\filter_preferred_text_models', 5 );

	// Try multi-provider config first (v2.0.0+).
	$providers = get_providers();
	if ( ! empty( $providers ) ) {
		ProviderFactory::registerAllProviders();
		mark_connector_configured();
		return;
	}

	// Fall back to legacy single-provider config (v1.x).
	$endpoint_url = get_option( 'ultimate_ai_connector_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return;
	}

	// Set the base URL and default model before any SDK method can call baseUrl().
	CompatibleEndpointProvider::$endpointUrl = $endpoint_url;
	CompatibleEndpointProvider::$defaultModel = (string) get_option( 'ultimate_ai_connector_default_model', '' );
	CompatibleEndpointProvider::$imageProtocol = (string) get_option( 'ultimate_ai_connector_image_protocol', 'none' );
	CompatibleEndpointProvider::$imageModel = (string) get_option( 'ultimate_ai_connector_image_model', '' );
	register_image_endpoint_url( 'ultimate-ai-connector-compatible-endpoints', $endpoint_url );

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( CompatibleEndpointProvider::class ) ) {
		return;
	}

	$registry->registerProvider( CompatibleEndpointProvider::class );

	// Inject the API key (or a placeholder for servers that don't need one).
	$api_key = get_option( 'ultimate_ai_connector_api_key', '' );
	if ( empty( $api_key ) ) {
		$api_key = 'no-key';
	}

	$registry->setProviderRequestAuthentication(
		CompatibleEndpointProvider::class,
		new ApiKeyRequestAuthentication( $api_key )
	);

	// Mark as configured so the WP 7.0 connector system and AI plugin
	// recognise this connector as having valid credentials.
	mark_connector_configured();
}

/**
 * Registers an endpoint URL with the optional image model implementation.
 *
 * The image-generation abstraction is unavailable in older AI Client SDK
 * versions. Keep text-only provider registration working in that environment.
 *
 * @param string $sdk_provider_id SDK provider ID.
 * @param string $endpoint_url Endpoint base URL.
 */
function register_image_endpoint_url( string $sdk_provider_id, string $endpoint_url ): void {
	if ( ! class_exists( CompatibleEndpointImageModel::class ) ) {
		return;
	}

	CompatibleEndpointImageModel::registerEndpointUrl( $sdk_provider_id, $endpoint_url );
}

/**
 * Collapses per-provider connector cards into a single card for this plugin.
 *
 * The Connectors UI (Gutenberg `_gutenberg_register_default_ai_providers`,
 * priority 15 on `init`) iterates `AiClient::defaultRegistry()` and registers
 * one connector card per registered SDK provider. Because this plugin
 * registers a dedicated SDK provider for every endpoint the user configures
 * (ollama, synthetic, etc.), every configured endpoint surfaces as its own
 * "new connector to configure" card — which is misleading: those providers
 * are already managed inside this plugin's single card.
 *
 * This callback runs on `wp_connectors_init` (fired at the end of
 * `_gutenberg_connectors_init`, priority 15). We:
 *
 * 1. Unregister every connector whose ID starts with the SDK provider prefix
 *    used by this plugin (`ai-provider-for-any-openai-compatible[-N]`).
 * 2. Register a single canonical connector keyed by the slug our React
 *    component is registered under in `src/index.jsx` — this lets the JS
 *    `registerConnector( SLUG, CONFIG )` actually override the default render
 *    and present the multi-provider management UI.
 *
 * The SDK registry itself is left untouched: text generation and fallback
 * routing across all configured providers continue to work.
 *
 * @param \WP_Connector_Registry $registry Connector registry instance.
 */
function consolidate_connector_card( \WP_Connector_Registry $registry ): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	// Drop the auto-discovered per-provider duplicates.
	$prefix      = SDK_PROVIDER_ID_PREFIX;
	$ai_registry = AiClient::defaultRegistry();
	foreach ( $ai_registry->getRegisteredProviderIds() as $sdk_provider_id ) {
		if ( 0 !== strpos( (string) $sdk_provider_id, $prefix ) ) {
			continue;
		}
		if ( $registry->is_registered( $sdk_provider_id ) ) {
			$registry->unregister( $sdk_provider_id );
		}
	}

	// Register a single canonical card matching the JS SLUG so the React
	// component in src/index.jsx renders in place of the default form.
	if ( ! $registry->is_registered( CONNECTOR_SLUG ) ) {
		// WP 7.0 and the AI plugin only recognise connectors whose
		// authentication method is 'api_key'. Using 'none' caused the
		// connector to appear as "connected" on the Connectors page but
		// be invisible to any plugin that queries has_ai_credentials().
		//
		// We point setting_name at a synthetic marker option that is set
		// to '1' during provider registration. Registering it here (before
		// WP 7.0's init:20 _wp_register_default_connector_settings) with
		// show_in_rest => false prevents the core REST dispatch filter from
		// masking or validating this value as if it were a real API key.
		$setting_name = 'ultimate_ai_connector_connected';

		if ( ! isset( get_registered_settings()[ $setting_name ] ) ) {
			register_setting(
				'ultimate_ai_connector',
				$setting_name,
				[
					'type'         => 'string',
					'default'      => '',
					'show_in_rest' => false,
				]
			);
		}

		$registry->register(
			CONNECTOR_SLUG,
			[
				'name'           => __( 'Compatible Endpoint', 'ultimate-ai-connector-compatible-endpoints' ),
				'description'    => __(
					'Connect to Ollama, LM Studio, or any AI endpoint using the standard chat completions API format.',
					'ultimate-ai-connector-compatible-endpoints'
				),
				'type'           => 'ai_provider',
				'authentication' => [
					'method'       => 'api_key',
					'setting_name' => $setting_name,
				],
			]
		);
	}
}

/**
 * Returns the configured default model ID, or empty string if none set.
 *
 * @return string
 */
function get_default_model(): string {
	return (string) get_option( 'ultimate_ai_connector_default_model', '' );
}

/**
 * Prepends configured connector defaults to the AI plugin's model preferences.
 *
 * Each preference is a provider/model tuple understood by the AI Client SDK.
 * Multi-provider SDK IDs follow enabled registration order, matching
 * ProviderFactory::registerAllProviders().
 *
 * @param array<int, mixed> $preferred_models Existing preferred models.
 * @return array<int, mixed> Connector defaults followed by existing preferences.
 */
function filter_preferred_text_models( array $preferred_models ): array {
	$connector_preferences = [];
	$providers             = get_providers_ordered();
	$index                 = 0;

	foreach ( $providers as $provider ) {
		if ( empty( $provider['endpoint_url'] ) || ! ( $provider['enabled'] ?? true ) ) {
			continue;
		}

		$default_model = (string) ( $provider['default_model'] ?? '' );
		if ( '' !== $default_model ) {
			$connector_preferences[] = [
				sdk_provider_id_for_index( $index ),
				$default_model,
			];
		}

		++$index;
	}

	if ( empty( $providers ) ) {
		$endpoint_url  = (string) get_option( 'ultimate_ai_connector_endpoint_url', '' );
		$default_model = (string) get_option( 'ultimate_ai_connector_default_model', '' );

		if ( '' !== $endpoint_url && '' !== $default_model ) {
			$connector_preferences[] = [ CONNECTOR_SLUG, $default_model ];
		}
	}

	return array_merge( $connector_preferences, $preferred_models );
}

/**
 * Get the ID of the provider currently being used for text generation.
 *
 * This can be hooked to implement custom routing logic.
 *
 * @return string|null Provider ID or null.
 */
function get_current_provider_id(): ?string {
	/**
	 * Filter the current provider ID for multi-provider setups.
	 *
	 * Use this to implement custom routing (e.g., based on model name,
	 * usage tracking, or request context).
	 *
	 * @param string|null $provider_id Current provider ID or null for auto.
	 * @return string|null Provider ID to use.
	 */
	return apply_filters( 'ultimate_ai_connector_current_provider_id', null );
}

/**
 * Switch to the next provider in the fallback chain.
 *
 * @param string $current_provider_id Current provider ID.
 * @return string|null Next provider ID or null if no more.
 */
function get_provider_fallback( string $current_provider_id ): ?string {
	$next = get_next_provider( $current_provider_id );
	return $next['id'] ?? null;
}

/**
 * Marks the connector as configured for the WP 7.0 connector system.
 *
 * Sets the synthetic `ultimate_ai_connector_connected` option to '1' so that
 * any code checking the connector's setting_name (e.g. the AI plugin's
 * `has_ai_credentials()`) sees a non-empty value and recognises this
 * connector as having valid credentials.
 *
 * This is necessary because many local AI servers (Ollama, llama.cpp, LM
 * Studio) do not require an API key, yet WP 7.0 and the AI plugin only
 * consider connectors with authentication method 'api_key' and a non-empty
 * setting value.
 */
function mark_connector_configured(): void {
	if ( '1' !== get_option( 'ultimate_ai_connector_connected' ) ) {
		update_option( 'ultimate_ai_connector_connected', '1', true );
	}
}

/**
 * Tells the AI plugin that this connector has valid credentials.
 *
 * Hooked to `wpai_has_ai_credentials` as a fallback in case the marker
 * option approach is insufficient (e.g. option caching edge cases).
 *
 * @param bool $has_credentials Current credential status.
 * @return bool True when at least one provider is configured.
 */
function filter_has_ai_credentials( bool $has_credentials ): bool {
	if ( $has_credentials ) {
		return $has_credentials;
	}

	// Check multi-provider config (v2.0.0+).
	$providers = get_providers();
	foreach ( $providers as $provider ) {
		if ( ! empty( $provider['endpoint_url'] ) && ( $provider['enabled'] ?? true ) ) {
			return true;
		}
	}

	// Check legacy single-provider config.
	return '' !== (string) get_option( 'ultimate_ai_connector_endpoint_url', '' );
}
