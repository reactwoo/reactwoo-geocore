<?php
/**
 * Public helpers for ReactWoo Components.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'reactwoo_register_component' ) ) {
	/**
	 * @param RWGC_Component_Definition $definition Definition.
	 * @return bool
	 */
	function reactwoo_register_component( RWGC_Component_Definition $definition ) {
		return RWGC_Component_Registry::register( $definition );
	}
}

if ( ! function_exists( 'reactwoo_render_component' ) ) {
	/**
	 * @param string               $type Type.
	 * @param array<string, mixed> $props Props.
	 * @param array<string, mixed> $context Context.
	 * @return string
	 */
	function reactwoo_render_component( $type, array $props = array(), array $context = array() ) {
		return RWGC_Component_Registry::render( $type, $props, $context );
	}
}

if ( ! function_exists( 'reactwoo_get_component_definition' ) ) {
	/**
	 * @param string $type Type.
	 * @return RWGC_Component_Definition|null
	 */
	function reactwoo_get_component_definition( $type ) {
		return RWGC_Component_Registry::get( $type );
	}
}
