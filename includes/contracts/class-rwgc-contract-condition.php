<?php
/**
 * Single condition clause contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One capability comparison (or a nested group via {@see RWGC_Contract_Condition_Group}).
 */
final class RWGC_Contract_Condition extends RWGC_Contract {

	/** @var string */
	private $capability;
	/** @var string */
	private $operator;
	/** @var mixed */
	private $value;

	/**
	 * @param string               $capability Capability ID.
	 * @param string               $operator Operator.
	 * @param mixed                $value Value.
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct( $capability, $operator, $value, array $extras ) {
		$this->capability = $capability;
		$this->operator   = $operator;
		$this->value      = $value;
		$this->extras     = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition( $data, array( 'capability', 'operator', 'value', 'type' ) );

		$raw_cap = isset( $core['capability'] ) ? $core['capability'] : ( isset( $core['type'] ) ? $core['type'] : '' );
		$capability = RWGC_Schema::normalize_capability_id( $raw_cap );
		if ( '' === $capability ) {
			throw new RWGC_Contract_Exception( 'Condition capability is required and must be a valid capability ID.' );
		}

		$operator = strtolower( self::optional_string( $core, 'operator', 'equals' ) );
		if ( '' === $operator || ! preg_match( '/^[a-z][a-z0-9_]*$/', $operator ) ) {
			throw new RWGC_Contract_Exception( 'Condition operator is invalid.' );
		}

		$value = array_key_exists( 'value', $core ) ? $core['value'] : null;

		return new self( $capability, $operator, $value, $extras );
	}

	/** @return string */
	public function capability() {
		return $this->capability;
	}

	/** @return string */
	public function operator() {
		return $this->operator;
	}

	/** @return mixed */
	public function value() {
		return $this->value;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'capability' => $this->capability,
				'operator'   => $this->operator,
				'value'      => $this->value,
			)
		);
	}
}
