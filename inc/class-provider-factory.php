<?php
/**
 * Provider factory for dynamic multi-provider support.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;

use WordPress\AiClient\Common\Exception\RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dynamic provider class generated for each configured provider.
 *
 * Each instance gets its own class so the SDK's static method
 * routing works correctly.
 */
class DynamicCompatibleEndpointProvider extends AbstractApiProvider {

	/**
	 * Provider ID from config.
	 *
	 * @var string
	 */
	public static string $providerId = '';

	/**
	 * Provider name for display.
	 *
	 * @var string
	 */
	public static string $providerName = '';

	/**
	 * Configured endpoint URL.
	 *
	 * @var string
	 */
	public static string $endpointUrl = '';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	public static int $timeout = 360;

	/**
	 * SDK provider ID emitted by createProviderMetadata().
	 *
	 * For the primary provider this is 'ai-provider-for-any-openai-compatible';
	 * for subsequent providers it gets a numeric suffix (-2, -3, …).
	 *
	 * @var string
	 */
	public static string $sdkProviderId = 'ai-provider-for-any-openai-compatible';

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return rtrim( static::$endpointUrl, '/' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new CompatibleEndpointModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$name = static::$providerName ?: 'Compatible Endpoint';
		return new ProviderMetadata(
			static::$sdkProviderId,
			$name,
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
			__( 'Connect to Ollama, LM Studio, or any AI endpoint using the standard chat completions API format.', 'ultimate-ai-connector-compatible-endpoints' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Returns a configuration-based availability check rather than a live HTTP
	 * request. See CompatibleEndpointProvider::createProviderAvailability() for
	 * full rationale — the same blocking-request hazard applies here because each
	 * dynamic subclass is registered independently with the SDK registry.
	 *
	 * The endpoint URL is captured at call time via late-static binding so that
	 * each dynamic subclass (CompatibleEndpointProvider_XYZ) returns its own
	 * URL rather than the base class's empty default.
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Capture via LSB — static:: resolves to the concrete dynamic subclass.
		$endpoint_url = static::$endpointUrl;

		return new class( $endpoint_url ) implements ProviderAvailabilityInterface {
			/**
			 * Endpoint URL captured at instantiation time.
			 *
			 * @var string
			 */
			private string $endpointUrl;

			/**
			 * Constructor.
			 *
			 * @param string $endpointUrl Endpoint URL from the dynamic provider class.
			 */
			public function __construct( string $endpointUrl ) {
				$this->endpointUrl = $endpointUrl;
			}

			/**
			 * Checks whether the endpoint URL is configured.
			 *
			 * @return bool True when the endpoint URL is non-empty.
			 */
			public function isConfigured(): bool {
				return ! empty( $this->endpointUrl );
			}
		};
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new CompatibleEndpointModelDirectory( static::$endpointUrl );
	}
}

/**
 * Factory for creating and managing dynamic provider classes.
 */
class ProviderFactory {

	/**
	 * Registered provider class names.
	 *
	 * @var array<string, string>
	 */
	private static array $registeredProviders = [];

	/**
	 * Global-namespace class name prefix for dynamic classes.
	 *
	 * Classes are created in the global namespace to avoid PHP eval()
	 * namespace-resolution issues. The prefix is long enough to be unique.
	 */
	private const CLASS_PREFIX = 'UAICCE_DynProvider_';

	/**
	 * Fully-qualified name of the base class, for use inside eval() strings.
	 */
	private const FQ_BASE_CLASS = '\\UltimateAiConnectorCompatibleEndpoints\\DynamicCompatibleEndpointProvider';

	/**
	 * Build the SDK provider ID for a given registration order index.
	 *
	 * The ai-agent plugin expects IDs that start with
	 * 'ai-provider-for-any-openai-compatible'. The primary provider gets the
	 * bare ID; subsequent providers get a numeric suffix.
	 *
	 * @param int $index 0-based registration index.
	 * @return string SDK provider ID.
	 */
	public static function sdkProviderIdForIndex( int $index ): string {
		return $index === 0
			? 'ai-provider-for-any-openai-compatible'
			: 'ai-provider-for-any-openai-compatible-' . ( $index + 1 );
	}

	/**
	 * Create a provider class for a config.
	 *
	 * Dynamic classes are placed in the global namespace because PHP's eval()
	 * does not inherit the calling namespace for class declarations.
	 *
	 * @param array $config Provider configuration.
	 * @param int   $index  0-based registration order index (determines SDK ID).
	 * @return string Fully-qualified class name (global namespace, no leading \).
	 */
	public static function createProviderClass( array $config, int $index = 0 ): string {
		$id   = $config['id'] ?? '';
		$name = $config['name'] ?? '';

		if ( isset( self::$registeredProviders[ $id ] ) ) {
			return self::$registeredProviders[ $id ];
		}

		// Global-namespace class name — unique by provider ID slug.
		$class_name    = self::CLASS_PREFIX . self::sanitize_class_name( $id );
		$sdk_provider_id = self::sdkProviderIdForIndex( $index );

		// Define the dynamic class only if not already defined.
		if ( ! class_exists( $class_name, false ) ) {
			$endpoint_url = $config['endpoint_url'] ?? '';
			$timeout      = (int) ( $config['timeout'] ?? 360 );

			// Escape values for embedding in a PHP single-quoted string.
			$escaped_id            = addcslashes( $id, "'\\" );
			$escaped_name          = addcslashes( $name, "'\\" );
			$escaped_endpoint_url  = addcslashes( $endpoint_url, "'\\" );
			$escaped_sdk_id        = addcslashes( $sdk_provider_id, "'\\" );

			$base = self::FQ_BASE_CLASS;

			// phpcs:ignore Squiz.PHP.Eval.Discouraged
			eval(
				"class {$class_name} extends {$base} {
					public static string \$providerId = '{$escaped_id}';
					public static string \$providerName = '{$escaped_name}';
					public static string \$endpointUrl = '{$escaped_endpoint_url}';
					public static int \$timeout = {$timeout};
					public static string \$sdkProviderId = '{$escaped_sdk_id}';
				}"
			);
		}

		self::$registeredProviders[ $id ] = $class_name;
		return $class_name;
	}

	/**
	 * Register a provider with the AI Client.
	 *
	 * @param array $config Provider configuration.
	 * @param int   $index  0-based registration order index.
	 * @return bool True if registered.
	 */
	public static function registerProvider( array $config, int $index = 0 ): bool {
		if ( ! class_exists( AiClient::class ) ) {
			return false;
		}

		$id = $config['id'] ?? '';
		if ( empty( $id ) || empty( $config['endpoint_url'] ) ) {
			return false;
		}

		$class_name = self::createProviderClass( $config, $index );
		$registry  = AiClient::defaultRegistry();

		if ( $registry->hasProvider( $class_name ) ) {
			return true;
		}

		$registry->registerProvider( $class_name );

		// Register the endpoint URL so CompatibleEndpointModel can resolve it at request time.
		$sdk_provider_id = self::sdkProviderIdForIndex( $index );
		CompatibleEndpointModel::registerEndpointUrl( $sdk_provider_id, $config['endpoint_url'] );

		// Set API key authentication.
		$api_key = $config['api_key'] ?? '';
		if ( empty( $api_key ) ) {
			$api_key = 'no-key';
		}

		$registry->setProviderRequestAuthentication(
			$class_name,
			new ApiKeyRequestAuthentication( $api_key )
		);

		return true;
	}

	/**
	 * Register all configured providers.
	 */
	public static function registerAllProviders(): void {
		$providers = get_providers_ordered();
		$index     = 0;
		foreach ( $providers as $provider ) {
			if ( ! empty( $provider['endpoint_url'] ) && ( $provider['enabled'] ?? true ) ) {
				self::registerProvider( $provider, $index );
				++$index;
			}
		}
	}

	/**
	 * Get provider class for a specific ID.
	 *
	 * @param string $id Provider ID.
	 * @return string|null Class name or null.
	 */
	public static function getProviderClass( string $id ): ?string {
		return self::$registeredProviders[ $id ] ?? null;
	}

	/**
	 * Get provider metadata for a provider.
	 *
	 * @param string $id Provider ID.
	 * @return array|null Provider config or null.
	 */
	public static function getProviderConfig( string $id ): ?array {
		return get_provider( $id );
	}

	/**
	 * Get the default provider class (highest priority).
	 *
	 * @return string|null Class name or null.
	 */
	public static function getDefaultProviderClass(): ?string {
		$primary = get_primary_provider();
		if ( ! $primary ) {
			return null;
		}
		return self::getProviderClass( $primary['id'] ?? '' );
	}

	/**
	 * Get the next provider after a given ID.
	 *
	 * @param string $after_id Provider ID to start after.
	 * @return string|null Class name or null.
	 */
	public static function getNextProviderClass( string $after_id = '' ): ?string {
		$next = get_next_provider( $after_id );
		if ( ! $next ) {
			return null;
		}
		return self::getProviderClass( $next['id'] ?? '' );
	}

	/**
	 * Sanitize a string for use as a class name.
	 *
	 * @param string $id ID to sanitize.
	 * @return string Sanitized class name part.
	 */
	private static function sanitize_class_name( string $id ): string {
		return preg_replace( '/[^a-zA-Z0-9_]/', '_', $id ) ?: $id;
	}
}