<?php
/**
 * Decision context contract (visitor/session/request facts).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable bag of context values keyed by capability/context IDs.
 *
 * Distinct from {@see RWGC_Context_Snapshot}; this is the platform contract shape.
 */
final class RWGC_Contract_Context extends RWGC_Contract {

	/** @var array<string, mixed> */
	private $values;

	/**
	 * @param array<string, mixed> $values Values.
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct( array $values, array $extras = array() ) {
		$this->values = $values;
		$this->extras = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data. Supports `{ "values": {...} }` or a flat map.
	 * @return self
	 */
	public static function from_array( array $data ) {
		if ( isset( $data['values'] ) && is_array( $data['values'] ) ) {
			list( , $extras ) = RWGC_Schema::partition( $data, array( 'values', 'reactwoo_schema_version' ) );
			$values             = $data['values'];
		} else {
			$values = $data;
			$extras = array();
		}

		$normalized = array();
		foreach ( $values as $key => $value ) {
			$id = RWGC_Schema::normalize_capability_id( $key );
			if ( '' === $id ) {
				// Preserve unknown keys under extras for forward compatibility.
				$extras[ (string) $key ] = $value;
				continue;
			}
			$normalized[ $id ] = $value;
		}

		return new self( $normalized, $extras );
	}

	/**
	 * @param string $capability_id Capability ID (aliases accepted).
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( $capability_id, $default = null ) {
		$id = RWGC_Schema::normalize_capability_id( $capability_id );
		if ( '' === $id || ! array_key_exists( $id, $this->values ) ) {
			return $default;
		}
		return $this->values[ $id ];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function values() {
		return $this->values;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'reactwoo_schema_version' => RWGC_Schema::VERSION,
				'values'                  => $this->values,
			)
		);
	}
}
