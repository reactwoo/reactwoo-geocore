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
		$allow_insecure = (bool) apply_filters( 'rwgc_cloud_allow_insecure_api_base', false, $base );
		$https          = ( 0 === strpos( $base, 'https://' ) );
		$http           = ( 0 === strpos( $base, 'http://' ) );
		if ( ! $https && ! ( $http && $allow_insecure ) ) {
			return false;
		}

		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $base ) : parse_url( $base );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}
		if ( self::is_blocked_host( (string) $parts['host'] ) && ! $allow_insecure ) {
			return false;
		}
		return true;
	}

	/**
	 * Block metadata and private/reserved hosts unless the insecure-local filter is on.
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	public static function is_blocked_host( $host ) {
		$host = strtolower( rtrim( (string) $host, '.' ) );
		if ( '' === $host ) {
			return true;
		}
		if ( 'localhost' === $host || (bool) preg_match( '/\.localhost$/', $host ) ) {
			return true;
		}
		if ( in_array( $host, array( 'metadata.google.internal', 'metadata' ), true ) ) {
			return true;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
			return false === filter_var( $host, FILTER_VALIDATE_IP, $flags );
		}
		return false;
	}
}
