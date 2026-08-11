<?php
/**
 * NATIVE_REFERENCE variant — points at existing site design.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a native design reference via filter.
 */
final class RWGC_Native_Reference_Variant extends RWGC_Abstract_Variant {

	/**
	 * @return string
	 */
	public function reference() {
		$payload = $this->payload();
		if ( isset( $payload['reference'] ) ) {
			return trim( (string) $payload['reference'] );
		}
		if ( isset( $payload['native_reference'] ) ) {
			return trim( (string) $payload['native_reference'] );
		}
		return '';
	}

	/**
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @return bool
	 */
	public function is_compatible_with_slot( $slot = null ) {
		if ( ! parent::is_compatible_with_slot( $slot ) ) {
			return false;
		}
		return '' !== $this->reference();
	}
}
