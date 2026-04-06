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

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Text generation model that forwards requests to the configured endpoint
 * using the standard chat/completions format.
 */
class CompatibleEndpointModel extends AbstractOpenAiCompatibleTextGenerationModel {

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
			CompatibleEndpointProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Strict JSON Schema validators (e.g. Hugging Face Inference router) reject
	 * function declarations whose `parameters.properties` serialize to a JSON
	 * array (`[]`) instead of an object (`{}`). PHP empty arrays encode as `[]`,
	 * so we recursively coerce empty `properties` to `stdClass` before the SDK
	 * hands the payload to `wp_json_encode`.
	 */
	protected function prepareToolsParam( array $functionDeclarations ): array {
		$tools = parent::prepareToolsParam( $functionDeclarations );

		foreach ( $tools as &$tool ) {
			if ( isset( $tool['function']['parameters'] ) && is_array( $tool['function']['parameters'] ) ) {
				$tool['function']['parameters'] = self::normalize_json_schema( $tool['function']['parameters'] );
			}
		}
		unset( $tool );

		return $tools;
	}

	/**
	 * Recursively coerce empty `properties` arrays into stdClass so they
	 * serialize as JSON objects rather than JSON arrays.
	 *
	 * @param array<string, mixed> $schema A JSON Schema fragment.
	 * @return array<string, mixed>
	 */
	private static function normalize_json_schema( array $schema ): array {
		// `default` at the schema root is rejected by strict validators (HF Inference router).
		// Defaults belong inside individual property definitions, not on the parent schema.
		unset( $schema['default'] );

		// Drop empty `required` arrays — `"required": []` is invalid per JSON Schema.
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) && empty( $schema['required'] ) ) {
			unset( $schema['required'] );
		}

		// Normalize `properties`: empty → stdClass, otherwise recurse into each property.
		if ( array_key_exists( 'properties', $schema ) ) {
			if ( is_array( $schema['properties'] ) ) {
				if ( empty( $schema['properties'] ) ) {
					$schema['properties'] = new \stdClass();
				} else {
					$normalized = array();
					foreach ( $schema['properties'] as $key => $value ) {
						$normalized[ $key ] = is_array( $value ) ? self::normalize_json_schema( $value ) : $value;
					}
					$schema['properties'] = $normalized;
				}
			}
		}

		// `items` must be an object schema (not an empty array). Coerce or drop.
		if ( array_key_exists( 'items', $schema ) ) {
			if ( is_array( $schema['items'] ) ) {
				if ( empty( $schema['items'] ) ) {
					// Empty items is meaningless; drop the constraint.
					unset( $schema['items'] );
				} else {
					$schema['items'] = self::normalize_json_schema( $schema['items'] );
				}
			}
		}

		// `additionalProperties` may be bool or schema object — recurse only if it's a schema.
		if ( isset( $schema['additionalProperties'] ) && is_array( $schema['additionalProperties'] ) ) {
			$schema['additionalProperties'] = empty( $schema['additionalProperties'] )
				? false
				: self::normalize_json_schema( $schema['additionalProperties'] );
		}

		// Recurse into combinator branches.
		foreach ( [ 'oneOf', 'anyOf', 'allOf' ] as $combinator ) {
			if ( isset( $schema[ $combinator ] ) && is_array( $schema[ $combinator ] ) ) {
				foreach ( $schema[ $combinator ] as $idx => $sub ) {
					if ( is_array( $sub ) ) {
						$schema[ $combinator ][ $idx ] = self::normalize_json_schema( $sub );
					}
				}
			}
		}

		return $schema;
	}
}
