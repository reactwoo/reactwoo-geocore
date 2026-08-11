<?php
/**
 * Goal contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What success means.
 */
final class RWGC_Contract_Goal extends RWGC_Contract {

	/** @var string */
	private $id;
	/** @var string */
	private $type;
	/** @var string */
	private $value;

	/**
	 * @param string               $id ID.
	 * @param string               $type Goal/event capability type.
	 * @param string               $value Value key (e.g. revenue).
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct( $id, $type, $value, array $extras ) {
		$this->id     = $id;
		$this->type   = $type;
		$this->value  = $value;
		$this->extras = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition( $data, array( 'id', 'type', 'value' ) );
		$id   = self::require_string( $core, 'id' );
		$type = RWGC_Schema::normalize_capability_id( isset( $core['type'] ) ? $core['type'] : '' );
		if ( '' === $type ) {
			// Allow goal.* style even if only one segment was passed via alias failure — require dotted.
			$raw = self::optional_string( $core, 'type' );
			if ( '' === $raw || ! preg_match( '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $raw ) ) {
				throw new RWGC_Contract_Exception( 'Goal type must be a dotted capability ID (e.g. commerce.purchase).' );
			}
			$type = $raw;
		}
		return new self( $id, $type, self::optional_string( $core, 'value' ), $extras );
	}

	/** @return string */
	public function id() {
		return $this->id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'id'    => $this->id,
				'type'  => $this->type,
				'value' => $this->value,
			)
		);
	}
}
