<?php
/**
 * Public Experience Slot helpers.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'reactwoo_register_experience_slot' ) ) {
	/**
	 * @param array<string, mixed> $data Slot data.
	 * @return array{slot: RWGC_Contract_Experience_Slot, regenerated: bool, previous_id: string}|false
	 */
	function reactwoo_register_experience_slot( array $data ) {
		try {
			return RWGC_Experience_Slot_Registry::register( $data );
		} catch ( RWGC_Contract_Exception $e ) {
			return false;
		}
	}
}

if ( ! function_exists( 'reactwoo_get_experience_slot' ) ) {
	/**
	 * @param string $id Slot ID.
	 * @return RWGC_Contract_Experience_Slot|null
	 */
	function reactwoo_get_experience_slot( $id ) {
		return RWGC_Experience_Slot_Registry::get( $id );
	}
}

if ( ! function_exists( 'reactwoo_render_experience_slot' ) ) {
	/**
	 * @param string                    $slot_id Slot ID.
	 * @param callable|string           $default_content Default HTML.
	 * @param RWGC_Decision_Result|null $decision Decision.
	 * @return string
	 */
	function reactwoo_render_experience_slot( $slot_id, $default_content, $decision = null ) {
		return RWGC_Experience_Slot_Renderer::render( $slot_id, $default_content, $decision );
	}
}
