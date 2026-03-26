<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple transient-based cache for geo data.
 */
class RWGC_Cache {

	/**
	 * Whether cache is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) RWGC_Settings::get( 'cache_enabled', 1 );
	}

	/**
	 * Get TTL in seconds.
	 *
	 * @return int
	 */
	public static function get_ttl() {
		$ttl = (int) RWGC_Settings::get( 'cache_ttl', 6 * HOUR_IN_SECONDS );
		if ( $ttl < 60 ) {
			$ttl = 60;
		}
		/**
		 * Filter TTL for geo cache.
		 *
		 * @param int $ttl TTL in seconds.
		 */
		return (int) apply_filters( 'rwgc_cache_ttl', $ttl );
	}

	/**
	 * Build cache key for an IP.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	private static function key( $ip ) {
		$ver = (int) get_option( 'rwgc_cache_version', 1 );
		if ( $ver < 1 ) {
			$ver = 1;
		}
		return 'rwgc_geo_v' . $ver . '_' . md5( (string) $ip );
	}

	/**
	 * Get cached geo data for an IP.
	 *
	 * @param string $ip IP address.
	 * @return array|null
	 */
	public static function get( $ip ) {
		if ( ! self::is_enabled() ) {
			return null;
		}
		$data = get_transient( self::key( $ip ) );
		return ( is_array( $data ) && ! empty( $data ) ) ? $data : null;
	}

	/**
	 * Cache geo data for an IP.
	 *
	 * @param string $ip   IP address.
	 * @param array  $data Geo payload.
	 * @return void
	 */
	public static function set( $ip, $data ) {
		if ( ! self::is_enabled() || ! is_array( $data ) ) {
			return;
		}
		set_transient( self::key( $ip ), $data, self::get_ttl() );
	}

	/**
	 * Delete cache for an IP.
	 *
	 * @param string $ip IP address.
	 * @return void
	 */
	public static function delete( $ip ) {
		delete_transient( self::key( $ip ) );
	}

	/**
	 * Clear all cache entries (best-effort).
	 *
	 * Note: Transients API doesn't support wildcard deletes,
	 * so we bump an internal version to invalidate subsequent reads.
	 *
	 * @return void
	 */
	public static function clear_all() {
		// Simple strategy: bump a version so integrators can incorporate into keys later if needed.
		$ver = (int) get_option( 'rwgc_cache_version', 1 );
		update_option( 'rwgc_cache_version', $ver + 1 );
	}
}

