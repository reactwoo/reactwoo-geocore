<?php
/**
 * Shared Variant base.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds a contract instance.
 */
abstract class RWGC_Abstract_Variant implements RWGC_Variant_Interface {

	/** @var RWGC_Contract_Variant */
	protected $contract;

	/**
	 * @param RWGC_Contract_Variant $contract Contract.
	 */
	public function __construct( RWGC_Contract_Variant $contract ) {
		$this->contract = $contract;
	}

	/**
	 * @return string
	 */
	public function id() {
		return $this->contract->id();
	}

	/**
	 * @return string
	 */
	public function type() {
		return $this->contract->type();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function payload() {
		return $this->contract->payload();
	}

	/**
	 * @return RWGC_Contract_Variant
	 */
	public function contract() {
		return $this->contract;
	}

	/**
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @return bool
	 */
	public function is_compatible_with_slot( $slot = null ) {
		if ( ! $slot instanceof RWGC_Contract_Experience_Slot ) {
			return true;
		}
		$allowed = $slot->variant_types();
		if ( empty( $allowed ) ) {
			return true;
		}
		$type = $this->type();
		if ( RWGC_Contract_Variant::TYPE_DEFAULT === $type ) {
			return true;
		}
		return in_array( $type, $allowed, true );
	}
}
