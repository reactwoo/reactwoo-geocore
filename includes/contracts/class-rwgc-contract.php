<?php
/**
 * Base helpers for immutable platform contract value objects.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared serialisation helpers. Subclasses are immutable after construction.
 */
abstract class RWGC_Contract {

	/**
	 * Unknown non-critical fields retained for forward compatibility.
	 *
	 * @var array<string, mixed>
	 */
	protected $extras = array();

	/**
	 * @return array<string, mixed>
	 */
	public function extras() {
		return $this->extras;
	}

	/**
	 * @return array<string, mixed>
	 */
	abstract public function to_array();

	/**
	 * JSON encode (associative arrays).
	 *
	 * @return string
	 * @throws RWGC_Contract_Exception When encoding fails.
	 */
	public function to_json() {
		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $this->to_array() )
			: json_encode( $this->to_array() );
		if ( ! is_string( $json ) || '' === $json ) {
			throw new RWGC_Contract_Exception( 'Failed to encode contract JSON.' );
		}
		return $json;
	}

	/**
	 * @param string $json JSON document.
	 * @return array<string, mixed>
	 * @throws RWGC_Contract_Exception When JSON is invalid.
	 */
	protected static function decode_json_object( $json ) {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			throw new RWGC_Contract_Exception( 'Invalid JSON object.' );
		}
		return $data;
	}

	/**
	 * Require a non-empty string field.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param string               $key  Key.
	 * @param string               $label Label for errors.
	 * @return string
	 * @throws RWGC_Contract_Exception When missing/invalid.
	 */
	protected static function require_string( array $data, $key, $label = '' ) {
		$label = '' !== $label ? $label : $key;
		if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
			throw new RWGC_Contract_Exception( sprintf( 'Missing required field: %s.', $label ) );
		}
		$value = trim( (string) $data[ $key ] );
		if ( '' === $value ) {
			throw new RWGC_Contract_Exception( sprintf( 'Missing required field: %s.', $label ) );
		}
		return $value;
	}

	/**
	 * Optional string.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param string               $key  Key.
	 * @param string               $default Default.
	 * @return string
	 */
	protected static function optional_string( array $data, $key, $default = '' ) {
		if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
			return $default;
		}
		return trim( (string) $data[ $key ] );
	}

	/**
	 * Merge extras onto an array for serialisation (extras last so known keys win).
	 *
	 * @param array<string, mixed> $core Core fields.
	 * @return array<string, mixed>
	 */
	protected function with_extras( array $core ) {
		if ( empty( $this->extras ) ) {
			return $core;
		}
		return array_merge( $this->extras, $core );
	}
}
