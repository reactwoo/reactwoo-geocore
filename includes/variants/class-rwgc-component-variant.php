<?php
/**
 * REACTWOO_COMPONENT variant.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders via Component System.
 */
final class RWGC_Component_Variant extends RWGC_Abstract_Variant {

	/**
	 * @return string
	 */
	public function component_type() {
		$payload = $this->payload();
		return isset( $payload['component'] ) ? strtolower( trim( (string) $payload['component'] ) ) : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function props() {
		$payload = $this->payload();
		return isset( $payload['props'] ) && is_array( $payload['props'] ) ? $payload['props'] : array();
	}

	/**
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @return bool
	 */
	public function is_compatible_with_slot( $slot = null ) {
		if ( ! parent::is_compatible_with_slot( $slot ) ) {
			return false;
		}
		$type = $this->component_type();
		if ( '' === $type ) {
			return false;
		}
		if ( ! function_exists( 'reactwoo_get_component_definition' ) ) {
			return false;
		}
		return null !== reactwoo_get_component_definition( $type );
	}
}
