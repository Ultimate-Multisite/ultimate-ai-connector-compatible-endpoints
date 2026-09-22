<?php
declare(strict_types=1);

/**
 * Chat-completions image generation model.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Image generation model for gateways that return images from chat completions.
 */
class CompatibleEndpointChatImageModel extends CompatibleEndpointImageModel {

	/** {@inheritDoc} */
	public function generateImageResult( array $prompt ): GenerativeAiResult {
		$params      = $this->prepareGenerateImageParams( $prompt );
		$chat_params = [
			'model'    => $params['model'],
			'messages' => [
				[
					'role'    => 'user',
					'content' => $params['prompt'],
				],
			],
		];
		foreach ( $params as $key => $value ) {
			if ( ! in_array( $key, [ 'model', 'prompt', 'response_format', 'output_format', 'size' ], true ) ) {
				$chat_params[ $key ] = $value;
			}
		}

		$request  = $this->createRequest( HttpMethodEnum::POST(), 'chat/completions', [ 'Content-Type' => 'application/json' ], $chat_params );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseChatResponse( $response );
	}

	/**
	 * Parses choices[].message.images into SDK image candidates.
	 *
	 * @param Response $response Chat completion response.
	 * @return GenerativeAiResult Normalized result.
	 */
	private function parseChatResponse( Response $response ): GenerativeAiResult {
		$data = $response->getData();
		if ( ! is_array( $data ) || ! isset( $data['choices'] ) || ! is_array( $data['choices'] ) || [] === $data['choices'] ) {
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'choices' );
		}

		$candidates = [];
		foreach ( $data['choices'] as $choice_index => $choice ) {
			$message = is_array( $choice ) && isset( $choice['message'] ) && is_array( $choice['message'] ) ? $choice['message'] : null;
			$images  = is_array( $message ) && isset( $message['images'] ) && is_array( $message['images'] ) ? $message['images'] : null;
			if ( null === $images || [] === $images ) {
				throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), "choices[{$choice_index}].message.images" );
			}
			foreach ( $images as $image_index => $image ) {
				$image_data = $this->normalizeImageData( $image );
				if ( null === $image_data ) {
					throw ResponseException::fromInvalidData( $this->providerMetadata()->getName(), "choices[{$choice_index}].message.images[{$image_index}]", 'Expected a URL, data URI, or base64 image value.' );
				}
				$candidates[] = new Candidate( new Message( MessageRoleEnum::model(), [ new MessagePart( new File( $image_data, $this->imageMimeType( $image_data ) ) ) ] ), FinishReasonEnum::stop() );
			}
		}

		$usage = is_array( $data['usage'] ?? null ) ? $data['usage'] : [];
		return new GenerativeAiResult( is_string( $data['id'] ?? null ) ? $data['id'] : '', $candidates, new TokenUsage( $usage['prompt_tokens'] ?? 0, $usage['completion_tokens'] ?? 0, $usage['total_tokens'] ?? 0 ), $this->providerMetadata(), $this->metadata(), [] );
	}

	/** @param mixed $image Image payload. @return string|null */
	private function normalizeImageData( $image ): ?string {
		if ( is_string( $image ) && '' !== $image ) {
			return $image;
		}
		if ( ! is_array( $image ) ) {
			return null;
		}
		foreach ( [ 'url', 'b64_json', 'data' ] as $key ) {
			if ( isset( $image[ $key ] ) && is_string( $image[ $key ] ) && '' !== $image[ $key ] ) {
				return $image[ $key ];
			}
		}
		return isset( $image['image_url']['url'] ) && is_string( $image['image_url']['url'] ) ? $image['image_url']['url'] : null;
	}

	/** @param string $image_data Normalized image data. @return string */
	private function imageMimeType( string $image_data ): string {
		if ( preg_match( '#^data:(image/[a-zA-Z0-9.+-]+);base64,#', $image_data, $matches ) ) {
			return $matches[1];
		}
		return 'image/png';
	}
}
