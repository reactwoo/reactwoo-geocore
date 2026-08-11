<?php
/**
 * Public Cloud Connector helpers.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'reactwoo_cloud_is_connected' ) ) {
	/**
	 * @return bool
	 */
	function reactwoo_cloud_is_connected() {
		return RWGC_Cloud_Connection::is_connected();
	}
}

if ( ! function_exists( 'reactwoo_cloud_pair' ) ) {
	/**
	 * @param string $pairing_token Token.
	 * @return array{ok: bool, error: string, site_id: string}
	 */
	function reactwoo_cloud_pair( $pairing_token ) {
		return RWGC_Cloud_Pairing::pair( $pairing_token );
	}
}

if ( ! function_exists( 'reactwoo_cloud_sync_manifest' ) ) {
	/**
	 * @return array{ok: bool, status: string, revision: int, error: string}
	 */
	function reactwoo_cloud_sync_manifest() {
		return RWGC_Cloud_Sync::sync_manifest();
	}
}

if ( ! function_exists( 'reactwoo_cloud_get_manifest' ) ) {
	/**
	 * Local cached manifest (never fetches Cloud).
	 *
	 * @return RWGC_Contract_Manifest|null
	 */
	function reactwoo_cloud_get_manifest() {
		return RWGC_Cloud_Manifest_Store::current();
	}
}
