<?php
declare(strict_types=1);

/**
 * Image generation models for compatible AI endpoints.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;

/**
 * Standard OpenAI Images API model.
 */
class CompatibleEndpointImageModel extends AbstractOpenAiCompatibleImageGenerationModel {

	/** @var array<string, string> Endpoint URL by SDK provider ID. */
	private static array $endpointUrls = [];

	/**
	 * Registers an endpoint URL for an SDK provider ID.
	 *
	 * @param string $sdk_provider_id SDK provider ID.
	 * @param string $endpoint_url Endpoint base URL.
	 */
	public static function registerEndpointUrl( string $sdk_provider_id, string $endpoint_url ): void {
		self::$endpointUrls[ $sdk_provider_id ] = rtrim( $endpoint_url, '/' );
	}

	/**
	 * Wraps canonical-plugin requests with ordered endpoint failover.
	 *
	 * @param HttpTransporterInterface $httpTransporter SDK HTTP transporter.
	 */
	public function setHttpTransporter( HttpTransporterInterface $httpTransporter ): void {
		if ( CONNECTOR_SLUG === $this->providerMetadata()->getId() ) {
			$httpTransporter = new OrderedProviderTransporter( $httpTransporter );
		}

		parent::setHttpTransporter( $httpTransporter );
	}

	/** {@inheritDoc} */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		$provider_id = $this->providerMetadata()->getId();
		$base_url    = self::$endpointUrls[ $provider_id ] ?? rtrim( CompatibleEndpointProvider::$endpointUrl, '/' );

		return new Request( $method, $base_url . '/' . ltrim( $path, '/' ), $headers, $data, $this->getRequestOptions() );
	}
}
