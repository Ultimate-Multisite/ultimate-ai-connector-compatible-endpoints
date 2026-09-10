<?php
/**
 * Test multi-provider routing of model-listing requests.
 *
 * Regression coverage for the v2.0.0 multi-provider bug where every
 * OpenAI-compatible provider returned the same (primary) model list because
 * \UltimateAiConnectorCompatibleEndpoints\rest_list_models() always fell back
 * to get_primary_provider() when the request did not carry an explicit
 * endpoint URL.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 * @license GPL-2.0-or-later
 */

namespace UltimateAiConnectorCompatibleEndpoints\Tests;

use WP_UnitTestCase;
use WP_REST_Request;

use function UltimateAiConnectorCompatibleEndpoints\get_provider_by_sdk_id;
use function UltimateAiConnectorCompatibleEndpoints\sdk_provider_id_for_index;

/**
 * Multi-provider routing tests.
 */
class MultiProviderRoutingTest extends WP_UnitTestCase {

	/**
	 * Captured outgoing HTTP request URLs, keyed by call order.
	 *
	 * Populated by the pre_http_request short-circuit filter so tests can
	 * assert that the correct per-provider /models URL was requested.
	 *
	 * @var list<string>
	 */
	private array $captured_urls = [];

	/**
	 * Captured outgoing HTTP request headers, keyed by request URL.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $captured_headers = [];

	/**
	 * Stub /models response payload returned by the http filter.
	 *
	 * Distinct payload per endpoint URL so tests can prove the right one
	 * was hit and the right body was returned to the caller.
	 *
	 * @var array<string, list<array{id: string, name: string}>>
	 */
	private array $stub_responses = [];

	/**
	 * Tear down: clear options and remove HTTP filter.
	 */
	public function tear_down(): void {
		delete_option( 'ultimate_ai_connector_providers' );
		delete_option( 'ultimate_ai_connector_provider_order' );
		delete_option( 'ultimate_ai_connector_endpoint_url' );
		delete_option( 'ultimate_ai_connector_api_key' );
		remove_all_filters( 'pre_http_request' );
		$this->captured_urls    = [];
		$this->captured_headers = [];
		$this->stub_responses   = [];
		parent::tear_down();
	}

	/**
	 * Configure two distinct providers and install the stub HTTP filter.
	 */
	private function set_up_two_providers(): void {
		update_option(
			'ultimate_ai_connector_providers',
			[
				[
					'id'            => 'provider_alpha',
					'name'          => 'Alpha (Ollama)',
					'endpoint_url'  => 'http://alpha.example.test/v1',
					'api_key'       => 'alpha-key',
					'default_model' => '',
					'timeout'       => 360,
					'enabled'       => true,
				],
				[
					'id'            => 'provider_beta',
					'name'          => 'Beta (Synthetic)',
					'endpoint_url'  => 'https://beta.example.test/v1',
					'api_key'       => 'beta-key',
					'default_model' => 'beta-model',
					'timeout'       => 360,
					'enabled'       => true,
				],
			]
		);

		// Distinct payloads so equality assertions prove the right host was hit.
		$this->stub_responses = [
			'http://alpha.example.test/v1/models' => [
				[
					'id'   => 'alpha-model-1',
					'name' => 'alpha-model-1',
				],
				[
					'id'   => 'alpha-model-2',
					'name' => 'alpha-model-2',
				],
			],
			'https://beta.example.test/v1/models' => [
				[
					'id'   => 'beta-only-model',
					'name' => 'beta-only-model',
				],
			],
		];

		add_filter( 'pre_http_request', [ $this, 'short_circuit_http' ], 10, 3 );
	}

	/**
	 * Short-circuit /models HTTP requests with stub data.
	 *
	 * @param mixed  $pre  Filtered value (false to allow real request).
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 * @return array|mixed Stubbed wp_remote_get response or $pre.
	 */
	public function short_circuit_http( $pre, $args, $url ) {
		$this->captured_urls[]          = $url;
		$this->captured_headers[ $url ] = $args['headers'] ?? [];

		if ( ! isset( $this->stub_responses[ $url ] ) ) {
			return $pre;
		}

		return [
			'headers'  => [],
			'body'     => wp_json_encode( [ 'data' => $this->stub_responses[ $url ] ] ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * SDK index 0 maps to the bare ID; index N>0 gets a numeric suffix.
	 */
	public function test_sdk_provider_id_for_index_formula() {
		$this->assertSame( 'ai-provider-for-any-openai-compatible', sdk_provider_id_for_index( 0 ) );
		$this->assertSame( 'ai-provider-for-any-openai-compatible-2', sdk_provider_id_for_index( 1 ) );
		$this->assertSame( 'ai-provider-for-any-openai-compatible-3', sdk_provider_id_for_index( 2 ) );
	}

	/**
	 * get_provider_by_sdk_id() resolves the registration-order index.
	 */
	public function test_get_provider_by_sdk_id_resolves_each_provider() {
		$this->set_up_two_providers();

		$alpha = get_provider_by_sdk_id( 'ai-provider-for-any-openai-compatible' );
		$beta  = get_provider_by_sdk_id( 'ai-provider-for-any-openai-compatible-2' );

		$this->assertNotNull( $alpha );
		$this->assertSame( 'http://alpha.example.test/v1', $alpha['endpoint_url'] );
		$this->assertSame( 'alpha-key', $alpha['api_key'] );

		$this->assertNotNull( $beta );
		$this->assertSame( 'https://beta.example.test/v1', $beta['endpoint_url'] );
		$this->assertSame( 'beta-key', $beta['api_key'] );

		// Unknown SDK ID must return null, not silently fall back.
		$this->assertNull( get_provider_by_sdk_id( 'ai-provider-for-any-openai-compatible-99' ) );
		$this->assertNull( get_provider_by_sdk_id( '' ) );
	}

	/**
	 * Disabled providers are skipped, but registration-order indexing still
	 * matches what ProviderFactory::registerAllProviders() actually registers.
	 */
	public function test_get_provider_by_sdk_id_skips_disabled_providers() {
		update_option(
			'ultimate_ai_connector_providers',
			[
				[
					'id'           => 'p_disabled',
					'name'         => 'Disabled',
					'endpoint_url' => 'http://disabled.example.test/v1',
					'api_key'      => 'd-key',
					'enabled'      => false,
				],
				[
					'id'           => 'p_real_first',
					'name'         => 'Real first',
					'endpoint_url' => 'http://first.example.test/v1',
					'api_key'      => 'first-key',
					'enabled'      => true,
				],
				[
					'id'           => 'p_real_second',
					'name'         => 'Real second',
					'endpoint_url' => 'http://second.example.test/v1',
					'api_key'      => 'second-key',
					'enabled'      => true,
				],
			]
		);

		$first = get_provider_by_sdk_id( 'ai-provider-for-any-openai-compatible' );
		$this->assertNotNull( $first );
		$this->assertSame( 'p_real_first', $first['id'] );

		$second = get_provider_by_sdk_id( 'ai-provider-for-any-openai-compatible-2' );
		$this->assertNotNull( $second );
		$this->assertSame( 'p_real_second', $second['id'] );
	}

	/**
	 * REGRESSION: With provider_id set, each provider's request must hit
	 * its own endpoint and return its own model list.
	 *
	 * Pre-fix behaviour: both calls returned the primary provider's models
	 * because the param was ignored and get_primary_provider() always won.
	 */
	public function test_rest_list_models_routes_per_provider_id() {
		$this->set_up_two_providers();

		$req_alpha = new WP_REST_Request( 'GET' );
		$req_alpha->set_param( 'provider_id', 'ai-provider-for-any-openai-compatible' );
		$resp_alpha = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $req_alpha );

		$req_beta = new WP_REST_Request( 'GET' );
		$req_beta->set_param( 'provider_id', 'ai-provider-for-any-openai-compatible-2' );
		$resp_beta = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $req_beta );

		$this->assertNotInstanceOf( \WP_Error::class, $resp_alpha );
		$this->assertNotInstanceOf( \WP_Error::class, $resp_beta );

		$alpha_data = $resp_alpha->get_data();
		$beta_data  = $resp_beta->get_data();

		$alpha_ids = array_column( $alpha_data, 'id' );
		$beta_ids  = array_column( $beta_data, 'id' );

		// Each provider returns its OWN distinct model set.
		$this->assertSame( [ 'alpha-model-1', 'alpha-model-2' ], $alpha_ids );
		$this->assertSame( [ 'beta-only-model' ], $beta_ids );
		$this->assertSame( [ 'text_generation' ], $alpha_data[0]['capabilities'] );
		$this->assertSame( [ 'text_generation' ], $beta_data[0]['capabilities'] );

		// Each request hit its own /models URL.
		$this->assertContains( 'http://alpha.example.test/v1/models', $this->captured_urls );
		$this->assertContains( 'https://beta.example.test/v1/models', $this->captured_urls );
		$this->assertSame(
			'Bearer beta-key',
			$this->captured_headers['https://beta.example.test/v1/models']['Authorization'] ?? ''
		);
	}

	/**
	 * Model-list consumers can distinguish the configured image model from text models.
	 */
	public function test_rest_list_models_exposes_model_capabilities(): void {
		$this->set_up_two_providers();
		$providers                      = get_option( 'ultimate_ai_connector_providers' );
		$providers[0]['image_protocol'] = 'chat_completions';
		$providers[0]['image_model']    = 'alpha-model-1';
		update_option( 'ultimate_ai_connector_providers', $providers );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'provider_id', 'ai-provider-for-any-openai-compatible' );
		$response = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $request );
		$models   = $response->get_data();

		$this->assertSame( [ 'image_generation' ], $models[0]['capabilities'] );
		$this->assertSame( [ 'text_generation' ], $models[1]['capabilities'] );
	}

	/**
	 * The Connectors UI config ID must resolve the saved key without exposing it.
	 */
	public function test_rest_list_models_uses_saved_key_for_matching_config_endpoint(): void {
		$this->set_up_two_providers();

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'config_id', 'provider_beta' );
		$request->set_param( 'endpoint_url', 'https://beta.example.test/v1' );
		$response = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $request );

		$this->assertNotInstanceOf( \WP_Error::class, $response );
		$this->assertSame(
			'Bearer beta-key',
			$this->captured_headers['https://beta.example.test/v1/models']['Authorization'] ?? ''
		);
	}

	/**
	 * A config ID must never forward its saved key to a different endpoint.
	 */
	public function test_rest_list_models_does_not_send_saved_key_to_overridden_endpoint(): void {
		$this->set_up_two_providers();

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'config_id', 'provider_alpha' );
		$request->set_param( 'endpoint_url', 'https://beta.example.test/v1' );
		$response = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $request );

		$this->assertNotInstanceOf( \WP_Error::class, $response );
		$this->assertArrayNotHasKey(
			'Authorization',
			$this->captured_headers['https://beta.example.test/v1/models'] ?? []
		);
	}

	/**
	 * A legacy saved key must not be sent to a caller-selected endpoint.
	 */
	public function test_rest_list_models_does_not_send_legacy_key_to_overridden_endpoint(): void {
		$this->set_up_two_providers();
		update_option( 'ultimate_ai_connector_endpoint_url', 'http://legacy.example.test/v1' );
		update_option( 'ultimate_ai_connector_api_key', 'legacy-key' );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'endpoint_url', 'https://beta.example.test/v1' );
		$response = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $request );

		$this->assertNotInstanceOf( \WP_Error::class, $response );
		$this->assertArrayNotHasKey(
			'Authorization',
			$this->captured_headers['https://beta.example.test/v1/models'] ?? []
		);
	}

	/**
	 * Connector defaults must precede the AI plugin's built-in preferences.
	 */
	public function test_configured_defaults_precede_existing_model_preferences(): void {
		if ( ! function_exists( 'UltimateAiConnectorCompatibleEndpoints\\filter_preferred_text_models' ) ) {
			$this->markTestSkipped( 'AI Client SDK not available in this test environment.' );
		}

		$this->set_up_two_providers();

		$preferences = \UltimateAiConnectorCompatibleEndpoints\filter_preferred_text_models(
			[ [ 'openai', 'gpt-4.1' ] ]
		);

		$this->assertSame(
			[ 'ai-provider-for-any-openai-compatible-2', 'beta-model' ],
			$preferences[0]
		);
		$this->assertSame( [ 'openai', 'gpt-4.1' ], $preferences[1] );
	}

	/**
	 * The compat shim under \OpenAiCompatibleConnector\rest_list_models()
	 * — the function the AI Agent actually calls — must honour provider_id.
	 */
	public function test_compat_shim_routes_per_provider_id() {
		$this->set_up_two_providers();

		$this->assertTrue( function_exists( 'OpenAiCompatibleConnector\\rest_list_models' ) );

		$req = new WP_REST_Request( 'GET' );
		$req->set_param( 'provider_id', 'ai-provider-for-any-openai-compatible-2' );
		$resp = \OpenAiCompatibleConnector\rest_list_models( $req );

		$this->assertNotInstanceOf( \WP_Error::class, $resp );
		$this->assertSame( [ 'beta-only-model' ], array_column( $resp->get_data(), 'id' ) );
	}

	/**
	 * Backwards-compat: when no provider_id is supplied, behaviour is
	 * unchanged — the primary (highest-priority) provider wins.
	 *
	 * This protects callers that were already on the legacy contract.
	 */
	public function test_rest_list_models_falls_back_to_primary_when_no_provider_id() {
		$this->set_up_two_providers();

		$req  = new WP_REST_Request( 'GET' );
		$resp = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $req );

		$this->assertNotInstanceOf( \WP_Error::class, $resp );

		// Alpha is registered first → primary → its models are returned.
		$this->assertSame(
			[ 'alpha-model-1', 'alpha-model-2' ],
			array_column( $resp->get_data(), 'id' )
		);
	}

	/**
	 * Explicit endpoint_url in the request continues to take absolute precedence
	 * over both provider_id and primary fallback.
	 */
	public function test_explicit_endpoint_url_overrides_provider_id() {
		$this->set_up_two_providers();

		$req = new WP_REST_Request( 'GET' );
		$req->set_param( 'provider_id', 'ai-provider-for-any-openai-compatible' );
		$req->set_param( 'endpoint_url', 'https://beta.example.test/v1' );
		$resp = \UltimateAiConnectorCompatibleEndpoints\rest_list_models( $req );

		$this->assertNotInstanceOf( \WP_Error::class, $resp );
		$this->assertSame(
			[ 'beta-only-model' ],
			array_column( $resp->get_data(), 'id' )
		);
	}

	/**
	 * REGRESSION: The directory must not use the SDK's persistent DTO cache.
	 *
	 * Pre-fix behaviour: the SDK parent directory persisted ModelMetadata DTOs in
	 * the WordPress object cache. Some persistent cache backends, class-loading
	 * orders, or AI Client upgrades can hydrate those cached DTOs as
	 * __PHP_Incomplete_Class. The AI Client then passes them into
	 * ModelRequirements::areMetBy(), causing a TypeError during features such as
	 * ai/meta-description and ai/content-classification.
	 *
	 * The connector should persist only raw model IDs/names in its own transient
	 * and rebuild fresh ModelMetadata DTOs at read time.
	 */
	public function test_directory_ignores_poisoned_sdk_model_metadata_cache() {
		$sdk_cache_interface = 'WordPress\\AiClient\\Common\\Contracts\\CachesDataInterface';
		$model_metadata      = 'WordPress\\AiClient\\Providers\\Models\\DTO\\ModelMetadata';
		$ai_client_class     = 'WordPress\\AiClient\\AiClient';
		if ( ! interface_exists( $sdk_cache_interface ) || ! class_exists( $model_metadata ) || ! class_exists( $ai_client_class ) ) {
			$this->markTestSkipped( 'AI Client SDK not available in this test environment.' );
		}

		$endpoint        = 'http://alpha.example.test/v1';
		$directory_class = 'UltimateAiConnectorCompatibleEndpoints\\CompatibleEndpointModelDirectory';
		$directory       = new $directory_class( $endpoint );

		$this->assertNotInstanceOf(
			$sdk_cache_interface,
			$directory,
			'CompatibleEndpointModelDirectory must not opt into the SDK persistent DTO cache.'
		);

		$raw_cache_key = 'ult_ai_connector_models_' . md5( $endpoint );
		set_transient(
			$raw_cache_key,
			[
				[
					'id'   => 'alpha-model-1',
					'name' => 'Alpha Model 1',
				],
			],
			DAY_IN_SECONDS
		);

		$sdk_cache_key = 'ai_client_' . \WordPress\AiClient\AiClient::VERSION
			. '_' . md5( $directory_class )
			. '_' . md5( $endpoint )
			. '_models';
		$poisoned      = @unserialize( 'O:24:"Missing_Model_Metadata_X":0:{}' );
		wp_cache_set( $sdk_cache_key, [ 'poisoned' => $poisoned ], 'wp_ai_client', DAY_IN_SECONDS );

		try {
			$models = $directory->listModelMetadata();
		} finally {
			delete_transient( $raw_cache_key );
			wp_cache_delete( $sdk_cache_key, 'wp_ai_client' );
		}

		$this->assertCount( 1, $models );
		$this->assertInstanceOf( $model_metadata, $models[0] );
		$this->assertSame( 'alpha-model-1', $models[0]->getId() );
	}
}
