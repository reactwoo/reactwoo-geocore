<?php
/**
 * Public helpers to register ReactWoo platform capabilities.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'reactwoo_register_capability' ) ) {
	/**
	 * Register any capability type.
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @return bool True on success.
	 */
	function reactwoo_register_capability( array $definition ) {
		$result = RWGC_Platform_Capability_Registry::register( $definition, false );
		return true === $result;
	}
}

if ( ! function_exists( 'reactwoo_register_condition' ) ) {
	/**
	 * @param string               $id Capability ID.
	 * @param array<string, mixed> $args Args (label required).
	 * @return bool
	 */
	function reactwoo_register_condition( $id, array $args = array() ) {
		$args['id']   = $id;
		$args['type'] = RWGC_Contract_Capability::TYPE_CONDITION;
		return reactwoo_register_capability( $args );
	}
}

if ( ! function_exists( 'reactwoo_register_action' ) ) {
	/**
	 * @param string               $id Capability ID.
	 * @param array<string, mixed> $args Args.
	 * @return bool
	 */
	function reactwoo_register_action( $id, array $args = array() ) {
		$args['id']   = $id;
		$args['type'] = RWGC_Contract_Capability::TYPE_ACTION;
		return reactwoo_register_capability( $args );
	}
}

if ( ! function_exists( 'reactwoo_register_context_provider' ) ) {
	/**
	 * @param string               $id Capability ID.
	 * @param array<string, mixed> $args Args.
	 * @return bool
	 */
	function reactwoo_register_context_provider( $id, array $args = array() ) {
		$args['id']   = $id;
		$args['type'] = RWGC_Contract_Capability::TYPE_CONTEXT;
		return reactwoo_register_capability( $args );
	}
}

if ( ! function_exists( 'reactwoo_register_goal' ) ) {
	/**
	 * @param string               $id Capability ID.
	 * @param array<string, mixed> $args Args.
	 * @return bool
	 */
	function reactwoo_register_goal( $id, array $args = array() ) {
		$args['id']   = $id;
		$args['type'] = RWGC_Contract_Capability::TYPE_GOAL;
		return reactwoo_register_capability( $args );
	}
}

if ( ! function_exists( 'reactwoo_get_capability' ) ) {
	/**
	 * @param string $id Capability ID.
	 * @return array<string, mixed>|null
	 */
	function reactwoo_get_capability( $id ) {
		return RWGC_Platform_Capability_Registry::get( $id );
	}
}

if ( ! function_exists( 'reactwoo_has_capability' ) ) {
	/**
	 * @param string $id Capability ID.
	 * @return bool
	 */
	function reactwoo_has_capability( $id ) {
		return RWGC_Platform_Capability_Registry::has( $id );
	}
}
