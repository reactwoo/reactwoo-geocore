<?php
/**
 * Variant engine interface.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executable variant (wraps contract data).
 */
interface RWGC_Variant_Interface {

	/**
	 * @return string
	 */
	public function id();

	/**
	 * @return string One of RWGC_Contract_Variant::TYPE_*
	 */
	public function type();

	/**
	 * @return array<string, mixed>
	 */
	public function payload();

	/**
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @return bool
	 */
	public function is_compatible_with_slot( $slot = null );
}
