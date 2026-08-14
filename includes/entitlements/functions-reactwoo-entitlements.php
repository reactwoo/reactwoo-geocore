<?php
/**
 * Public entitlement helpers.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'reactwoo_entitlements_allows' ) ) {
	/**
	 * @param string $key Entitlement key.
	 * @return bool
	 */
	function reactwoo_entitlements_allows( $key ) {
		return RWGC_Entitlements::allows( $key );
	}
}

if ( ! function_exists( 'reactwoo_entitlements_limit' ) ) {
	/**
	 * @param string $key Entitlement key.
	 * @return mixed
	 */
	function reactwoo_entitlements_limit( $key ) {
		return RWGC_Entitlements::limit( $key );
	}
}
