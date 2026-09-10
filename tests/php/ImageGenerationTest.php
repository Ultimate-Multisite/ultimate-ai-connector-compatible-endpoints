<?php
/**
 * Tests for compatible endpoint image generation configuration and parsing.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace UltimateAiConnectorCompatibleEndpoints\Tests;

use WP_UnitTestCase;

/**
 * Image generation tests.
 */
class ImageGenerationTest extends WP_UnitTestCase {

	/**
	 * Text-only SDK versions must be able to load the registration helpers.
	 */
	public function test_registration_helper_ignores_missing_image_sdk_abstraction(): void {
		$registration_file = dirname( __DIR__, 2 ) . '/inc/provider-registration.php';
		$script            = sprintf(
			'define( "ABSPATH", __DIR__ ); require %s; \\UltimateAiConnectorCompatibleEndpoints\\register_image_endpoint_url( "test-provider", "https://example.test/v1" );',
			var_export( $registration_file, true )
		);
		$command           = escapeshellarg( PHP_BINARY ) . ' -d display_errors=1 -r ' . escapeshellarg( $script ) . ' 2>&1';
		$output            = [];
		$exit_code         = 0;

		exec( $command, $output, $exit_code );

		$this->assertSame( 0, $exit_code, implode( PHP_EOL, $output ) );
	}

	/**
	 * Text capabilities from older SDKs do not expose isImageGeneration().
	 */
	public function test_older_sdk_capability_is_not_treated_as_image_generation(): void {
		$old_sdk_capability = new class() {
		};

		$this->assertFalse(
			\UltimateAiConnectorCompatibleEndpoints\is_image_generation_capability( $old_sdk_capability )
		);
	}

	/**
	 * Invalid protocols are disabled while valid values and model IDs survive sanitization.
	 */
	public function test_image_protocol_sanitization(): void {
		$valid = \UltimateAiConnectorCompatibleEndpoints\sanitize_provider_config(
			[
				'endpoint_url'  => 'https://images.example.test/v1',
				'image_protocol' => 'chat_completions',
				'image_model'    => 'image-model',
			]
		);
		$this->assertSame( 'chat_completions', $valid['image_protocol'] );
		$this->assertSame( 'image-model', $valid['image_model'] );

		$invalid = \UltimateAiConnectorCompatibleEndpoints\sanitize_provider_config(
			[
				'endpoint_url'  => 'https://images.example.test/v1',
				'image_protocol' => 'vendor_auto_detect',
			]
		);
		$this->assertSame( 'none', $invalid['image_protocol'] );
	}

	/**
	 * The configured image model has only image capability; ordinary models remain text models.
	 */
	public function test_directory_marks_only_explicit_image_model_as_image_capable(): void {
		if ( ! class_exists( 'WordPress\\AiClient\\Providers\\Models\\Enums\\CapabilityEnum' ) ) {
			$this->markTestSkipped( 'AI Client SDK not available in this test environment.' );
		}

		$directory = new \UltimateAiConnectorCompatibleEndpoints\CompatibleEndpointModelDirectory(
			'https://images.example.test/v1',
			'',
			'openai',
			'image-model'
		);
		$method = new \ReflectionMethod( $directory, 'getCapabilitiesForModel' );
		$method->setAccessible( true );
		$options_method = new \ReflectionMethod( $directory, 'getOptionsForModel' );
		$options_method->setAccessible( true );
		$text_capabilities = [
			\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
			\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::chatHistory(),
		];

		$image_capabilities = $method->invoke( $directory, 'image-model', $text_capabilities );
		$normal_capabilities = $method->invoke( $directory, 'chat-model', $text_capabilities );
		$this->assertTrue( $image_capabilities[0]->isImageGeneration() );
		$this->assertTrue( $normal_capabilities[0]->isTextGeneration() );

		$image_options = $options_method->invoke( $directory, 'image-model', [] );
		$metadata      = new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
			'image-model',
			'Image model',
			$image_capabilities,
			$image_options
		);
		$model_config  = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		$model_config->setOutputModalities(
			[ \WordPress\AiClient\Messages\Enums\ModalityEnum::image() ]
		);
		$requirements  = \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
			\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration(),
			[
				new \WordPress\AiClient\Messages\DTO\UserMessage(
					[ new \WordPress\AiClient\Messages\DTO\MessagePart( 'Generate an image.' ) ]
				),
			],
			$model_config
		);

		$this->assertTrue( $requirements->areMetBy( $metadata ) );
	}

	/**
	 * Chat completion image payloads normalize URLs, data URIs, and base64 into image files.
	 */
	public function test_chat_image_response_parser_normalizes_supported_payloads(): void {
		if ( ! class_exists( 'WordPress\\AiClient\\Providers\\OpenAiCompatibleImplementation\\AbstractOpenAiCompatibleImageGenerationModel' ) ) {
			$this->markTestSkipped( 'Image generation SDK abstraction is not available in this test environment.' );
		}
		$chat_model = 'UltimateAiConnectorCompatibleEndpoints\\CompatibleEndpointChatImageModel';
		if ( ! class_exists( $chat_model ) ) {
			$this->markTestSkipped( 'AI Client SDK not available in this test environment.' );
		}

		$provider = new \WordPress\AiClient\Providers\DTO\ProviderMetadata(
			'compatible-images',
			'Compatible Images',
			\WordPress\AiClient\Providers\Enums\ProviderTypeEnum::server(),
			null,
			\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod::apiKey(),
			'Images'
		);
		$metadata = new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
			'image-model',
			'Image model',
			[ \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration() ],
			[]
		);
		$model = new $chat_model( $metadata, $provider );
		$response = new \WordPress\AiClient\Providers\Http\DTO\Response(
			200,
			[],
			wp_json_encode(
				[
					'id' => 'chat-image-result',
					'choices' => [
						[ 'message' => [ 'images' => [ 'https://images.example.test/a.png', 'data:image/jpeg;base64,aGVsbG8=', 'aGVsbG8=' ] ] ],
					],
				]
			)
		);
		$method = new \ReflectionMethod( $model, 'parseChatResponse' );
		$method->setAccessible( true );
		$result = $method->invoke( $model, $response );

		$this->assertCount( 3, $result->toImageFiles() );
		$this->assertTrue( $result->toImageFiles()[0]->isRemote() );
		$this->assertSame( 'image/jpeg', $result->toImageFiles()[1]->getMimeType() );
		$this->assertTrue( $result->toImageFiles()[2]->isInline() );
	}

	/**
	 * A missing image field is surfaced as an SDK response exception.
	 */
	public function test_chat_image_response_parser_rejects_missing_images(): void {
		if ( ! class_exists( 'WordPress\\AiClient\\Providers\\OpenAiCompatibleImplementation\\AbstractOpenAiCompatibleImageGenerationModel' ) ) {
			$this->markTestSkipped( 'Image generation SDK abstraction is not available in this test environment.' );
		}
		$chat_model = 'UltimateAiConnectorCompatibleEndpoints\\CompatibleEndpointChatImageModel';
		if ( ! class_exists( $chat_model ) ) {
			$this->markTestSkipped( 'AI Client SDK not available in this test environment.' );
		}

		$provider = new \WordPress\AiClient\Providers\DTO\ProviderMetadata(
			'compatible-images',
			'Compatible Images',
			\WordPress\AiClient\Providers\Enums\ProviderTypeEnum::server(),
			null,
			\WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod::apiKey(),
			'Images'
		);
		$metadata = new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
			'image-model',
			'Image model',
			[ \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration() ],
			[]
		);
		$model = new $chat_model( $metadata, $provider );
		$response = new \WordPress\AiClient\Providers\Http\DTO\Response( 200, [], wp_json_encode( [ 'choices' => [ [ 'message' => [] ] ] ] ) );
		$method = new \ReflectionMethod( $model, 'parseChatResponse' );
		$method->setAccessible( true );

		$this->expectException( \WordPress\AiClient\Providers\Http\Exception\ResponseException::class );
		$method->invoke( $model, $response );
	}
}
