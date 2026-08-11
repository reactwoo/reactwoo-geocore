<?php
/**
 * Cloud Connector configuration.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API base URL and feature flags. Never used on the visitor render path.
 */
final class RWGC_Cloud_Config {

	const DEFAULT_API_BASE = 'https://cloud.reactwoo.com/api/v1';

	/**
	 * @return string
	 */
	public static function api_base() {
		$stored = get_option( 'rwgc_cloud_api_base', '' );
		$base   = is_string( $stored ) && '' !== $stored ? $stored : self::DEFAULT_API_BASE;
		/**
		 * Filter Cloud API base (tests / staging).
		 *
		 * @param string $base Base URL.
		 */
		$base = apply_filters( 'rwgc_cloud_api_base', $base );
		return untrailingslashit( (string) $base );
	}

	/**
	 * Whether the configured API base is acceptable (HTTPS in production).
	 *
	 * @param string $base Base.
	 * @return bool
	 */
	public static function is_secure_base( $base ) {
		$base = (string) $base;
		if ( 0 === strpos( $base, 'https://' ) ) {
			return true;
		}
		/**
		 * Allow non-TLS bases (local mocks).
		 *
		 * @param bool   $allow Allow.
		 * @param string $base Base.
		 */
		return (bool) apply_filters( 'rwgc_cloud_allow_insecure_api_base', false, $base );
	}
}
