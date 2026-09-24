<?php
declare(strict_types=1);

/**
 * Ordered request transport for the canonical plugin provider.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Retries a canonical provider request across enabled endpoints in list order.
 */
class OrderedProviderTransporter implements HttpTransporterInterface {

	private HttpTransporterInterface $transporter;

	/**
	 * @param HttpTransporterInterface $transporter SDK HTTP transporter.
	 */
	public function __construct( HttpTransporterInterface $transporter ) {
		$this->transporter = $transporter;
	}

	/** {@inheritDoc} */
	public function send( Request $request, ?RequestOptions $options = null ): Response {
		$providers = ordered_enabled_providers( $this->requestEndpointType( $request ) );
		if ( empty( $providers ) ) {
			return $this->transporter->send( $request, $options );
		}

		$path           = $this->requestPath( $request, (string) $providers[0]['endpoint_url'] );
		$last_response  = null;
		$last_exception = null;

		foreach ( $providers as $provider ) {
			$attempt = new Request(
				$request->getMethod(),
				rtrim( (string) $provider['endpoint_url'], '/' ) . $path,
				$request->getHeaders(),
				null !== $request->getData() ? $request->getData() : $request->getBody(),
				$request->getOptions()
			);
			$attempt = ( new ApiKeyRequestAuthentication( provider_api_key( $provider ) ) )->authenticateRequest( $attempt );

			try {
				$response = $this->transporter->send( $attempt, $options );
				if ( $response->isSuccessful() ) {
					return $response;
				}
				$last_response = $response;
			} catch ( \Throwable $exception ) {
				$last_exception = $exception;
			}
		}

		if ( null !== $last_response ) {
			return $last_response;
		}
		if ( null !== $last_exception ) {
			throw $last_exception;
		}

		return $this->transporter->send( $request, $options );
	}

	/**
	 * Restricts fallback only when the payload uses an endpoint-specific field.
	 *
	 * @return string|null Endpoint type restriction, or null for unrestricted fallback.
	 */
	private function requestEndpointType( Request $request ): ?string {
		$data = $request->getData();
		if ( null === $data && null !== $request->getBody() ) {
			$decoded = json_decode( $request->getBody(), true );
			$data    = is_array( $decoded ) ? $decoded : null;
		}

		if ( ! isset( $data['messages'] ) || ! is_array( $data['messages'] ) ) {
			return null;
		}

		foreach ( $data['messages'] as $message ) {
			if (
				is_array( $message )
				&& ( array_key_exists( 'reasoning_content', $message ) || array_key_exists( 'thinking', $message ) )
			) {
				return canonical_endpoint_type();
			}
		}

		return null;
	}

	/** Returns the operation suffix from the canonical request URL. */
	private function requestPath( Request $request, string $primary_endpoint ): string {
		$uri  = $request->getUri();
		$base = rtrim( $primary_endpoint, '/' );
		if ( 0 === strpos( $uri, $base ) ) {
			return '/' . ltrim( substr( $uri, strlen( $base ) ), '/' );
		}

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return '/' . ltrim( $path, '/' );
	}
}
