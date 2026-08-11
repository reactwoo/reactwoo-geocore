<?php
/**
 * Experiment contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allocation control for variants.
 */
final class RWGC_Contract_Experiment extends RWGC_Contract {

	/** @var string */
	private $id;
	/** @var string */
	private $control;
	/** @var list<array{id: string, allocation: int|float}> */
	private $variants;

	/**
	 * @param string                                           $id ID.
	 * @param string                                           $control Control variant ID.
	 * @param list<array{id: string, allocation: int|float}> $variants Variants.
	 * @param array<string, mixed>                             $extras Extras.
	 */
	private function __construct( $id, $control, array $variants, array $extras ) {
		$this->id       = $id;
		$this->control  = $control;
		$this->variants = $variants;
		$this->extras   = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition( $data, array( 'id', 'control', 'variants' ) );
		$id      = self::require_string( $core, 'id' );
		$control = self::require_string( $core, 'control' );
		if ( ! isset( $core['variants'] ) || ! is_array( $core['variants'] ) ) {
			throw new RWGC_Contract_Exception( 'Experiment variants are required.' );
		}
		$variants = array();
		foreach ( $core['variants'] as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				throw new RWGC_Contract_Exception( 'Experiment variant entries require id.' );
			}
			$variants[] = array(
				'id'         => trim( (string) $row['id'] ),
				'allocation' => isset( $row['allocation'] ) ? (float) $row['allocation'] : 0,
			);
		}
		return new self( $id, $control, $variants, $extras );
	}

	/** @return string */
	public function id() {
		return $this->id;
	}

	/** @return string */
	public function control() {
		return $this->control;
	}

	/**
	 * @return list<array{id: string, allocation: int|float}>
	 */
	public function variants() {
		return $this->variants;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'id'       => $this->id,
				'control'  => $this->control,
				'variants' => $this->variants,
			)
		);
	}
}
