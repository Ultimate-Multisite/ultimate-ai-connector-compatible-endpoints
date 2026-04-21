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
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
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
			'ultimate-ai-connector-compatible-endpoints-' . static::$providerId,
			$name,
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey(),
			__( 'Connect to Ollama, LM Studio, or any AI endpoint using the standard chat completions API format.', 'ultimate-ai-connector-compatible-endpoints' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new CompatibleEndpointModelDirectory();
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
	 * Class name prefix for dynamic classes.
	 */
	private const CLASS_PREFIX = 'CompatibleEndpointProvider_';

	/**
	 * Create a provider class for a config.
	 *
	 * @param array $config Provider configuration.
	 * @return string Class name.
	 */
	public static function createProviderClass( array $config ): string {
		$id   = $config['id'] ?? '';
		$name = $config['name'] ?? '';

		if ( isset( self::$registeredProviders[ $id ] ) ) {
			return self::$registeredProviders[ $id ];
		}

		$class_name = self::CLASS_PREFIX . self::sanitize_class_name( $id );

		// Define the dynamic class only if not already defined.
		if ( ! class_exists( $class_name, false ) ) {
			$endpoint_url = $config['endpoint_url'] ?? '';
			$timeout     = (int) ( $config['timeout'] ?? 360 );

			// Escape for PHP single-quoted string.
			$escaped_id           = addcslashes( $id, "'\\" );
			$escaped_name         = addcslashes( $name, "'\\" );
			$escaped_endpoint_url = addcslashes( $endpoint_url, "'\\" );

			// Create a dynamic subclass.
			eval(
				"class {$class_name} extends DynamicCompatibleEndpointProvider {
					public static string \$providerId = '{$escaped_id}';
					public static string \$providerName = '{$escaped_name}';
					public static string \$endpointUrl = '{$escaped_endpoint_url}';
					public static int \$timeout = {$timeout};
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
	 * @return bool True if registered.
	 */
	public static function registerProvider( array $config ): bool {
		if ( ! class_exists( AiClient::class ) ) {
			return false;
		}

		$id = $config['id'] ?? '';
		if ( empty( $id ) || empty( $config['endpoint_url'] ) ) {
			return false;
		}

		$class_name = self::createProviderClass( $config );
		$registry  = AiClient::defaultRegistry();

		if ( $registry->hasProvider( $class_name ) ) {
			return true;
		}

		$registry->registerProvider( $class_name );

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
		foreach ( $providers as $provider ) {
			self::registerProvider( $provider );
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