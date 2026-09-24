<?php
/**
 * Text generation model for a compatible AI endpoint.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Text generation model that forwards requests to the configured endpoint
 * using the standard chat/completions format.
 */
class CompatibleEndpointModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * Per-SDK-provider-ID endpoint URL registry.
	 *
	 * Populated at provider registration time so each dynamic provider's model
	 * can resolve its own endpoint URL at request time.
	 *
	 * @var array<string, string>
	 */
	private static array $endpointUrls = [];

	/**
	 * Per-SDK-provider-ID endpoint type registry.
	 *
	 * Tracks which thinking-mode wire format (if any) should be used for each
	 * configured provider. Values are: 'generic', 'deepseek', 'ollama'.
	 *
	 * @var array<string, string>
	 */
	private static array $endpointTypes = [];

	/**
	 * Register an endpoint URL for a given SDK provider ID.
	 *
	 * Called by ProviderFactory when a dynamic provider class is registered
	 * so that model instances can resolve the correct endpoint at request time.
	 *
	 * @param string $sdk_provider_id SDK-level provider ID (e.g. 'ai-provider-for-any-openai-compatible').
	 * @param string $endpoint_url    Base endpoint URL.
	 */
	public static function registerEndpointUrl( string $sdk_provider_id, string $endpoint_url ): void {
		self::$endpointUrls[ $sdk_provider_id ] = rtrim( $endpoint_url, '/' );
	}

	/**
	 * Register an endpoint type for a given SDK provider ID.
	 *
	 * Controls whether thought-channel MessageParts are re-attached to
	 * outgoing assistant messages and which wire field name is used:
	 *   - 'generic'  — drop thought parts (standard OpenAI behaviour)
	 *   - 'deepseek' — re-attach as `reasoning_content`
	 *   - 'ollama'   — re-attach as `thinking`
	 *
	 * @param string $sdk_provider_id SDK-level provider ID.
	 * @param string $endpoint_type   One of 'generic', 'deepseek', 'ollama'.
	 */
	public static function registerEndpointType( string $sdk_provider_id, string $endpoint_type ): void {
		self::$endpointTypes[ $sdk_provider_id ] = $endpoint_type;
	}

	/**
	 * Wraps canonical-plugin requests with ordered endpoint failover.
	 *
	 * The per-endpoint SDK providers keep their direct transport. Only the
	 * canonical plugin provider retries enabled endpoints in configured order.
	 *
	 * @param HttpTransporterInterface $httpTransporter SDK HTTP transporter.
	 */
	public function setHttpTransporter( HttpTransporterInterface $httpTransporter ): void {
		if ( CONNECTOR_SLUG === $this->providerMetadata()->getId() ) {
			$httpTransporter = new OrderedProviderTransporter( $httpTransporter );
		}

		parent::setHttpTransporter( $httpTransporter );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = [],
		$data = null
	): Request {
		$provider_id = $this->providerMetadata()->getId();
		$base_url    = self::$endpointUrls[ $provider_id ]
			?? rtrim( CompatibleEndpointProvider::$endpointUrl, '/' );

		return new Request(
			$method,
			$base_url . '/' . ltrim( $path, '/' ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}

	/**
	 * Prepares a standards-compatible structured response format.
	 *
	 * The AI Client SDK passes the raw JSON Schema directly as `json_schema`.
	 * Strict OpenAI-compatible gateways, including LiteLLM, expect that value
	 * to be a named envelope whose `schema` member contains the raw schema.
	 *
	 * @param array<string, mixed>|null $outputSchema The requested output schema.
	 * @return array<string, mixed> The OpenAI-compatible response format.
	 */
	protected function prepareResponseFormatParam( ?array $outputSchema ): array {
		if ( ! is_array( $outputSchema ) ) {
			return parent::prepareResponseFormatParam( $outputSchema );
		}

		return [
			'type'        => 'json_schema',
			'json_schema' => [
				'name'   => 'wordpress_response',
				'schema' => $outputSchema,
			],
		];
	}

	/**
	 * Prepares the API request parameters with optional thinking-mode support.
	 *
	 * When the provider is configured for a thinking-aware endpoint type
	 * ('deepseek' or 'ollama'), this override re-attaches the thought-channel
	 * MessagePart content to prior assistant turns so the endpoint's contract
	 * is met:
	 *
	 * - DeepSeek thinking mode: adds `reasoning_content` on assistant entries.
	 *   Without it the API returns HTTP 400: "The reasoning_content in the
	 *   thinking mode must be passed back to the API."
	 * - Ollama thinking-capable models: adds `thinking` on assistant entries.
	 *   Without it subsequent turns show degraded reasoning quality.
	 *
	 * For the default 'generic' endpoint type the base-class behaviour is
	 * preserved (thought parts are stripped) so plain OpenAI Chat Completions
	 * clones continue to work identically.
	 *
	 * @param list<Message> $prompt The prompt to generate text for.
	 * @return array<string,mixed>        The parameters for the API request.
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$params = parent::prepareGenerateTextParams( $prompt );

		$provider_id   = $this->providerMetadata()->getId();
		$endpoint_type = self::$endpointTypes[ $provider_id ] ?? 'generic';

		if ( 'generic' === $endpoint_type ) {
			return $params;
		}

		if ( ! isset( $params['messages'] ) || ! is_array( $params['messages'] ) ) {
			return $params;
		}

		// Determine the wire field name for the thought content.
		$thought_field = 'deepseek' === $endpoint_type ? 'reasoning_content' : 'thinking';

		// Walk the input prompt and collect concatenated thought-channel text
		// for every model-role Message that will become a wire `assistant` entry.
		// Function-response-only model messages are excluded because the base
		// class turns them into `tool` entries, not `assistant` ones.
		$thoughts = [];
		foreach ( $prompt as $message ) {
			if ( ! $message instanceof Message ) {
				continue;
			}
			if ( $message->getRole() !== MessageRoleEnum::model() ) {
				continue;
			}

			$parts = $message->getParts();
			if ( count( $parts ) === 1 && $parts[0]->getType()->isFunctionResponse() ) {
				continue;
			}

			$thought = '';
			foreach ( $parts as $part ) {
				if ( $part->getType()->isText() && $part->getChannel()->isThought() ) {
					$thought .= $part->getText();
				}
			}
			$thoughts[] = $thought;
		}

		// Attach in order: the i-th collected thought belongs to the i-th wire
		// `assistant` entry. The two lists are the same length when the base
		// class behaves as documented; we guard against mismatch so a future
		// SDK change cannot crash the request.
		$idx = 0;
		foreach ( $params['messages'] as $wire_index => $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['role'] ) || 'assistant' !== $entry['role'] ) {
				continue;
			}
			if ( ! isset( $thoughts[ $idx ] ) ) {
				break;
			}

			$thought = $thoughts[ $idx ];
			++$idx;

			if ( '' === $thought ) {
				continue;
			}

			$params['messages'][ $wire_index ][ $thought_field ] = $thought;
		}

		return $params;
	}
}
