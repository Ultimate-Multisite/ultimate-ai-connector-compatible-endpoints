<?php
declare(strict_types=1);

/**
 * Ordered routing for the canonical plugin provider.
 *
 * @package UltimateAiConnectorCompatibleEndpoints
 */

namespace UltimateAiConnectorCompatibleEndpoints;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Discovers models from the first enabled endpoint that answers successfully.
 */
class OrderedProviderModelDirectory implements ModelMetadataDirectoryInterface, WithHttpTransporterInterface, WithRequestAuthenticationInterface {

	use WithHttpTransporterTrait;
	use WithRequestAuthenticationTrait;

	/** @var array<string, ModelMetadata>|null */
	private ?array $modelMetadataMap = null;

	/**
	 * {@inheritDoc}
	 */
	public function listModelMetadata(): array {
		return array_values( $this->getModelMetadataMap() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasModelMetadata( string $modelId ): bool {
		return isset( $this->getModelMetadataMap()[ $modelId ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function getModelMetadata( string $modelId ): ModelMetadata {
		$models = $this->getModelMetadataMap();
		if ( isset( $models[ $modelId ] ) ) {
			return $models[ $modelId ];
		}

		throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
			sprintf( 'No model with ID %s was found in the provider', $modelId )
		);
	}

	/**
	 * Tries enabled providers in configured order until one returns models.
	 *
	 * @return array<string, ModelMetadata>
	 */
	private function getModelMetadataMap(): array {
		if ( null !== $this->modelMetadataMap ) {
			return $this->modelMetadataMap;
		}

		$last_exception = null;
		foreach ( ordered_enabled_providers() as $provider ) {
			$directory = new CompatibleEndpointModelDirectory(
				(string) $provider['endpoint_url'],
				(string) ( $provider['default_model'] ?? '' ),
				(string) ( $provider['image_protocol'] ?? 'none' ),
				(string) ( $provider['image_model'] ?? '' )
			);
			$directory->setHttpTransporter( $this->getHttpTransporter() );
			$directory->setRequestAuthentication(
				new ApiKeyRequestAuthentication( provider_api_key( $provider ) )
			);

			try {
				$models = $directory->listModelMetadata();
				if ( ! empty( $models ) ) {
					$this->modelMetadataMap = [];
					foreach ( $models as $model ) {
						$this->modelMetadataMap[ $model->getId() ] = $model;
					}
					return $this->modelMetadataMap;
				}
			} catch ( \Throwable $exception ) {
				$last_exception = $exception;
			}
		}

		if ( null !== $last_exception ) {
			throw $last_exception;
		}

		$this->modelMetadataMap = [];
		return $this->modelMetadataMap;
	}
}

/**
 * Returns enabled providers in their configured fallback order.
 *
 * @param string|null $endpoint_type Optional endpoint type to retain.
 * @return array<int, array<string, mixed>>
 */
function ordered_enabled_providers( ?string $endpoint_type = null ): array {
	return array_values(
		array_filter(
			get_providers_ordered(),
			static function ( array $provider ) use ( $endpoint_type ): bool {
				if ( empty( $provider['endpoint_url'] ) || ! ( $provider['enabled'] ?? true ) ) {
					return false;
				}

				return null === $endpoint_type
					|| (string) ( $provider['endpoint_type'] ?? 'generic' ) === $endpoint_type;
			}
		)
	);
}

/**
 * Returns the endpoint type used to prepare canonical provider requests.
 *
 * @return string Canonical endpoint type.
 */
function canonical_endpoint_type(): string {
	$primary = get_primary_provider();
	return (string) ( $primary['endpoint_type'] ?? 'generic' );
}

/**
 * Returns the endpoint key or the SDK-compatible local-server placeholder.
 *
 * @param array<string, mixed> $provider Provider configuration.
 */
function provider_api_key( array $provider ): string {
	$api_key = (string) ( $provider['api_key'] ?? '' );
	return '' !== $api_key ? $api_key : '[redacted-credential]';
}
