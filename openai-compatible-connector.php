<?php
/**
 * Plugin Name: OpenAI-Compatible Connector
 * Plugin URI: https://github.com/Ultimate-Multisite/openai-compatible-connector
 * Description: Registers an AI Client provider for any OpenAI-compatible endpoint (Ollama, LM Studio, OpenRouter, etc.).
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: Ultimate Multisite Community
 * Author URI: https://ultimatemultisite.com
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: openai-compatible-connector
 *
 * @package OpenAiCompatibleConnector
 */

declare(strict_types=1);

namespace OpenAiCompatibleConnector;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\Enums\ModalityEnum;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// ---------------------------------------------------------------------------
// Provider
// ---------------------------------------------------------------------------

/**
 * Provider class for an OpenAI-compatible endpoint.
 *
 * The base URL is read from plugin settings and stored in a static property
 * so that it is available to the SDK's static `baseUrl()` method.
 */
class OpenAiCompatProvider extends AbstractApiProvider {

	/**
	 * Configured endpoint URL. Set from options before registration.
	 *
	 * @var string
	 */
	public static string $endpointUrl = '';

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		return rtrim( self::$endpointUrl, '/' );
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
				return new OpenAiCompatModel( $modelMetadata, $providerMetadata );
			}
		}

		throw new RuntimeException(
			'Unsupported model capabilities: ' . esc_html( implode( ', ', $capabilities ) )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'openai-compat',
			'OpenAI Compatible',
			ProviderTypeEnum::server(),
			null,
			RequestAuthenticationMethod::apiKey()
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
		return new OpenAiCompatModelDirectory();
	}
}

// ---------------------------------------------------------------------------
// Text Generation Model
// ---------------------------------------------------------------------------

/**
 * Text generation model that forwards requests to the configured endpoint
 * using the standard OpenAI chat/completions format.
 */
class OpenAiCompatModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		return new Request(
			$method,
			OpenAiCompatProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}

// ---------------------------------------------------------------------------
// Model Metadata Directory
// ---------------------------------------------------------------------------

/**
 * Lists available models from the configured endpoint's /models resource.
 */
class OpenAiCompatModelDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		return new Request(
			$method,
			OpenAiCompatProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @phpstan-type ModelsResponseData array{data?: list<array{id: string, name?: string}>}
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		/** @var ModelsResponseData $responseData */
		$responseData = $response->getData();

		$modelsData = [];
		if ( isset( $responseData['data'] ) && is_array( $responseData['data'] ) ) {
			$modelsData = $responseData['data'];
		}

		// Fallback: some servers (e.g. Ollama < 0.5) return {models: [...]} instead of {data: [...]}.
		if ( empty( $modelsData ) && isset( $responseData['models'] ) && is_array( $responseData['models'] ) ) {
			$modelsData = $responseData['models'];
		}

		if ( empty( $modelsData ) ) {
			return [];
		}

		$capabilities = [
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		];

		$options = [
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption(
				OptionEnum::inputModalities(),
				[
					[ ModalityEnum::text() ],
					[ ModalityEnum::text(), ModalityEnum::image() ],
				]
			),
			new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
			new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
			new SupportedOption( OptionEnum::outputSchema() ),
		];

		return array_values(
			array_map(
				static function ( array $modelData ) use ( $capabilities, $options ): ModelMetadata {
					$id   = $modelData['id'] ?? $modelData['name'] ?? 'unknown';
					$name = $modelData['name'] ?? $modelData['id'] ?? $id;

					return new ModelMetadata( $id, $name, $capabilities, $options );
				},
				$modelsData
			)
		);
	}
}

// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------

/**
 * Registers the settings, admin menu, and provider.
 */
function register_settings(): void {
	register_setting(
		'openai_compat_connector',
		'openai_compat_endpoint_url',
		[
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		]
	);

	register_setting(
		'openai_compat_connector',
		'openai_compat_api_key',
		[
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		]
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\\register_settings' );

/**
 * Adds the settings page under Settings.
 */
function add_settings_page(): void {
	add_options_page(
		__( 'OpenAI Compatible', 'openai-compatible-connector' ),
		__( 'OpenAI Compatible', 'openai-compatible-connector' ),
		'manage_options',
		'openai-compat-connector',
		__NAMESPACE__ . '\\render_settings_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\\add_settings_page' );

/**
 * Renders the settings page.
 */
function render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'OpenAI Compatible Connector', 'openai-compatible-connector' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'openai_compat_connector' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="openai_compat_endpoint_url">
							<?php esc_html_e( 'Endpoint URL', 'openai-compatible-connector' ); ?>
						</label>
					</th>
					<td>
						<input
							type="url"
							id="openai_compat_endpoint_url"
							name="openai_compat_endpoint_url"
							value="<?php echo esc_attr( get_option( 'openai_compat_endpoint_url', '' ) ); ?>"
							class="regular-text"
							placeholder="http://localhost:11434/v1"
						/>
						<p class="description">
							<?php esc_html_e( 'Base URL for the OpenAI-compatible API (e.g. Ollama, LM Studio, OpenRouter).', 'openai-compatible-connector' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="openai_compat_api_key">
							<?php esc_html_e( 'API Key', 'openai-compatible-connector' ); ?>
						</label>
					</th>
					<td>
						<input
							type="password"
							id="openai_compat_api_key"
							name="openai_compat_api_key"
							value="<?php echo esc_attr( get_option( 'openai_compat_api_key', '' ) ); ?>"
							class="regular-text"
						/>
						<p class="description">
							<?php esc_html_e( 'Optional. Leave blank for servers that do not require authentication (e.g. local Ollama).', 'openai-compatible-connector' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// Increase HTTP timeout for inference requests
// ---------------------------------------------------------------------------

/**
 * Increases the HTTP timeout for requests to the configured endpoint.
 *
 * Local/self-hosted LLMs can take over 30 seconds to respond, especially
 * on CPU-only hardware. The default WordPress timeout of 30s is too short.
 *
 * @param array  $parsed_args HTTP request arguments.
 * @param string $url         Request URL.
 * @return array Modified arguments.
 */
function increase_timeout( array $parsed_args, string $url ): array {
	$endpoint_url = get_option( 'openai_compat_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return $parsed_args;
	}

	$endpoint_host = wp_parse_url( $endpoint_url, PHP_URL_HOST );
	$request_host  = wp_parse_url( $url, PHP_URL_HOST );

	if ( $endpoint_host && $request_host && $endpoint_host === $request_host ) {
		$parsed_args['timeout'] = max( (float) ( $parsed_args['timeout'] ?? 30 ), 120.0 );
	}

	return $parsed_args;
}
add_filter( 'http_request_args', __NAMESPACE__ . '\\increase_timeout', 10, 2 );

// ---------------------------------------------------------------------------
// Allow non-standard ports through wp_safe_remote_request
// ---------------------------------------------------------------------------

/**
 * Adds the configured endpoint port to the list of allowed HTTP ports.
 *
 * WordPress's wp_safe_remote_request() only allows ports 80, 443, and 8080
 * by default. Self-hosted inference servers typically run on other ports.
 *
 * @param int[] $ports Allowed ports.
 * @return int[] Modified allowed ports.
 */
function allow_endpoint_port( array $ports ): array {
	$endpoint_url = get_option( 'openai_compat_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return $ports;
	}

	$parsed = wp_parse_url( $endpoint_url );
	if ( ! empty( $parsed['port'] ) ) {
		$ports[] = (int) $parsed['port'];
	}

	return array_unique( $ports );
}
add_filter( 'http_allowed_safe_ports', __NAMESPACE__ . '\\allow_endpoint_port' );

// ---------------------------------------------------------------------------
// Provider registration
// ---------------------------------------------------------------------------

/**
 * Registers the provider with the AI Client on init.
 *
 * Runs at priority 5 so the provider is available before most plugins act on
 * `init` (default priority 10).
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$endpoint_url = get_option( 'openai_compat_endpoint_url', '' );
	if ( empty( $endpoint_url ) ) {
		return;
	}

	// Set the base URL before any SDK method can call baseUrl().
	OpenAiCompatProvider::$endpointUrl = $endpoint_url;

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( OpenAiCompatProvider::class ) ) {
		return;
	}

	$registry->registerProvider( OpenAiCompatProvider::class );

	// Inject the API key (or a placeholder for servers that don't need one).
	$api_key = get_option( 'openai_compat_api_key', '' );
	if ( empty( $api_key ) ) {
		$api_key = 'no-key';
	}

	$registry->setProviderRequestAuthentication(
		OpenAiCompatProvider::class,
		new ApiKeyRequestAuthentication( $api_key )
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );
