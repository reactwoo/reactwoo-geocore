<?php
/**
 * Public Variant helpers.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'reactwoo_register_variant' ) ) {
	/**
	 * @param array<string, mixed> $data Variant data.
	 * @return RWGC_Variant_Interface|false
	 */
	function reactwoo_register_variant( array $data ) {
		return RWGC_Variant_Store::register( $data );
	}
}

if ( ! function_exists( 'reactwoo_get_variant' ) ) {
	/**
	 * @param string $id ID.
	 * @return RWGC_Variant_Interface|null
	 */
	function reactwoo_get_variant( $id ) {
		return RWGC_Variant_Store::get( $id );
	}
}

if ( ! function_exists( 'reactwoo_render_variant' ) ) {
	/**
	 * @param string                             $variant_id Variant ID.
	 * @param string                             $default_html Default HTML.
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @param array<string, mixed>               $context Context.
	 * @return string Always a string (default on failure).
	 */
	function reactwoo_render_variant( $variant_id, $default_html, $slot = null, array $context = array() ) {
		$html = RWGC_Variant_Renderer::render_id( $variant_id, $default_html, $slot, $context );
		return is_string( $html ) && '' !== $html ? $html : ( is_string( $default_html ) ? $default_html : '' );
	}
}
