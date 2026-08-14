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

	/** @var array<string, callable> */
	private $resolvers = array();

	/** @var array<string, mixed> Request-local resolved lazy values. */
	private $resolved = array();

	/** @var int */
	private $resolve_count = 0;

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
		if ( '' === $id ) {
			return $default;
		}
		if ( array_key_exists( $id, $this->values ) ) {
			return $this->values[ $id ];
		}
		if ( array_key_exists( $id, $this->resolved ) ) {
			return $this->resolved[ $id ];
		}
		if ( isset( $this->resolvers[ $id ] ) ) {
			++$this->resolve_count;
			$this->resolved[ $id ] = call_user_func( $this->resolvers[ $id ] );
			return $this->resolved[ $id ];
		}
		return $default;
	}

	/**
	 * Attach lazy capability resolvers (evaluated once per context, on first get).
	 *
	 * @param array<string, callable> $resolvers Map of capability ID → resolver.
	 * @return self
	 */
	public function with_resolvers( array $resolvers ) {
		$clone            = clone $this;
		$clone->resolvers = array();
		foreach ( $resolvers as $key => $cb ) {
			if ( ! is_callable( $cb ) ) {
				continue;
			}
			$id = RWGC_Schema::normalize_capability_id( $key );
			if ( '' !== $id ) {
				$clone->resolvers[ $id ] = $cb;
			}
		}
		return $clone;
	}

	/**
	 * @return int Lazy resolver invocations this request.
	 */
	public function resolve_count() {
		return $this->resolve_count;
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
