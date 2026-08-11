<?php
/**
 * Audience contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who: named condition tree.
 */
final class RWGC_Contract_Audience extends RWGC_Contract {

	/** @var string */
	private $id;
	/** @var string */
	private $name;
	/** @var RWGC_Contract_Condition_Group */
	private $conditions;

	/**
	 * @param string                        $id ID.
	 * @param string                        $name Name.
	 * @param RWGC_Contract_Condition_Group $conditions Conditions.
	 * @param array<string, mixed>          $extras Extras.
	 */
	private function __construct( $id, $name, RWGC_Contract_Condition_Group $conditions, array $extras ) {
		$this->id         = $id;
		$this->name       = $name;
		$this->conditions = $conditions;
		$this->extras     = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition( $data, array( 'id', 'name', 'conditions' ) );
		$id   = self::require_string( $core, 'id' );
		$name = self::require_string( $core, 'name' );
		if ( ! isset( $core['conditions'] ) || ! is_array( $core['conditions'] ) ) {
			throw new RWGC_Contract_Exception( 'Audience conditions are required.' );
		}
		$conditions = RWGC_Contract_Condition_Group::from_array( $core['conditions'] );
		return new self( $id, $name, $conditions, $extras );
	}

	/** @return string */
	public function id() {
		return $this->id;
	}

	/** @return RWGC_Contract_Condition_Group */
	public function conditions() {
		return $this->conditions;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'id'         => $this->id,
				'name'       => $this->name,
				'conditions' => $this->conditions->to_array(),
			)
		);
	}
}
