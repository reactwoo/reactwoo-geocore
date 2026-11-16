<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transient cache for a visitor country lookup.
 *
 * The transient name is an HMAC of the address with the site auth salt, the
 * same salt RWGC_AB_Tracker uses for the A/B event rate limit. The address is
 * not stored in the transient. Older unsalted md5 names are deleted in batches
 * after an update and otherwise expire on their own.
 */
class RWGC_Cache {

	/**
	 * Prefix for HMAC keys. Distinct from LEGACY_PREFIX so the one-off delete
	 * cannot remove a key written by this version.
	 */
	const KEY_PREFIX = 'rwgc_geo_h';

	/**
	 * Prefix used by versions that keyed the cache with md5( IP ).
	 */
	const LEGACY_PREFIX = 'rwgc_geo_v';

	/**
	 * option_name is varchar(191). `_transient_timeout_` is 19 characters.
	 */
	const TRANSIENT_NAME_MAX = 172;

	const PURGE_HOOK = 'rwgc_purge_legacy_geo_cache';

	const PURGE_OPTION = 'rwgc_geo_cache_legacy_purged';

	const PURGE_CURSOR = 'rwgc_geo_cache_legacy_purge_cursor';

	const PURGE_BATCH = 100;

	/**
	 * Schedule the one-off delete of md5-keyed transients.
	 *
	 * The options query does not run on the visitor lookup path. It runs from
	 * wp-admin and from the scheduled event, one batch at a time.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'schedule_legacy_purge' ), 30 );
		add_action( 'admin_init', array( __CLASS__, 'purge_one_legacy_batch' ) );
		add_action( self::PURGE_HOOK, array( __CLASS__, 'purge_one_legacy_batch' ) );
	}

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
	 * @param string $ip IP address. Hashed into the name and not stored.
	 * @return string
	 */
	private static function key( $ip ) {
		$ver = (int) get_option( 'rwgc_cache_version', 1 );
		if ( $ver < 1 ) {
			$ver = 1;
		}
		$prefix = self::KEY_PREFIX . $ver . '_';
		$digest = hash_hmac( 'sha256', 'geo|' . (string) $ip, self::site_secret() );
		$room   = self::TRANSIENT_NAME_MAX - strlen( $prefix );
		if ( $room < 16 ) {
			$prefix = 'rh' . $ver . '_';
			$room   = self::TRANSIENT_NAME_MAX - strlen( $prefix );
		}
		$take = $room < 32 ? $room : 32;
		if ( $take < 1 ) {
			$take = 1;
		}
		return $prefix . substr( $digest, 0, $take );
	}

	/**
	 * Site secret mixed into the cache name.
	 *
	 * Defaults to wp_salt( 'auth' ), including the empty-salt fallback used by
	 * RWGC_AB_Tracker::rate_transient_key(). rwgc_geo_cache_site_secret can
	 * replace that salt. An empty replacement is ignored.
	 *
	 * @return string
	 */
	private static function site_secret() {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : '';
		if ( '' === $salt ) {
			$salt = 'rwgc-ab-event';
		}
		$filtered = apply_filters( 'rwgc_geo_cache_site_secret', $salt );
		if ( ! is_string( $filtered ) || '' === $filtered ) {
			return $salt;
		}
		return $filtered;
	}

	/**
	 * Get cached geo data for an IP.
	 *
	 * The stored transient has no IP. The returned array includes `ip` set to
	 * the address passed in, so existing callers keep the same shape.
	 *
	 * @param string $ip IP address.
	 * @return array|null
	 */
	public static function get( $ip ) {
		if ( ! self::is_enabled() ) {
			return null;
		}
		$data = get_transient( self::key( $ip ) );
		if ( ! is_array( $data ) || array() === $data ) {
			return null;
		}
		$data = self::payload_without_ip( (string) $ip, $data );
		if ( array() === $data ) {
			return null;
		}
		$data['ip'] = (string) $ip;
		return $data;
	}

	/**
	 * Cache geo data for an IP.
	 *
	 * @param string $ip   IP address. Used only to build the HMAC name.
	 * @param array  $data Geo payload. An `ip` field is not written.
	 * @return void
	 */
	public static function set( $ip, $data ) {
		if ( ! self::is_enabled() || ! is_array( $data ) ) {
			return;
		}
		$stored = self::payload_without_ip( (string) $ip, $data );
		if ( array() === $stored ) {
			return;
		}
		set_transient( self::key( $ip ), $stored, self::get_ttl() );
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
		$ver = (int) get_option( 'rwgc_cache_version', 1 );
		update_option( 'rwgc_cache_version', $ver + 1 );
	}

	/**
	 * Copy of a lookup payload with the visitor address removed.
	 *
	 * @param string               $ip   Address that must not appear in the copy.
	 * @param array<string, mixed> $data Lookup payload.
	 * @return array<string, mixed>
	 */
	private static function payload_without_ip( $ip, array $data ) {
		$clean = self::strip_ip_field( $data );
		$ip    = trim( (string) $ip );
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $clean;
		}
		return self::scrub_address( $clean, $ip );
	}

	/**
	 * @param mixed $value Payload or nested list.
	 * @return mixed
	 */
	private static function strip_ip_field( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		unset( $value['ip'] );
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::strip_ip_field( $item );
			}
		}
		return $value;
	}

	/**
	 * @param mixed  $value Payload or nested list.
	 * @param string $ip    Validated IP address.
	 * @return mixed
	 */
	private static function scrub_address( $value, $ip ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ $key ] = self::scrub_address( $item, $ip );
			}
			return $out;
		}
		if ( is_string( $value ) && '' !== $value ) {
			return str_ireplace( $ip, '', $value );
		}
		return $value;
	}

	/**
	 * Queue the next batch unless the one-off delete has finished.
	 *
	 * @return void
	 */
	public static function schedule_legacy_purge() {
		if ( self::legacy_purge_finished() ) {
			return;
		}
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_single_event' ) ) {
			return;
		}
		if ( false !== wp_next_scheduled( self::PURGE_HOOK ) ) {
			return;
		}
		wp_schedule_single_event( time() + 60, self::PURGE_HOOK );
	}

	/**
	 * Delete one batch of md5-keyed geo transients.
	 *
	 * Rows live in the options table as `_transient_rwgc_geo_v…` and
	 * `_transient_timeout_rwgc_geo_v…`. `delete_transient()` removes an
	 * external object-cache entry and, without one, the options rows. With a
	 * persistent object cache it leaves the options rows, so those two option
	 * names are deleted directly as well. HMAC keys (`rwgc_geo_h`) are not
	 * selected.
	 *
	 * @param int|null $limit Rows to read this pass. Capped at 200.
	 * @return bool True when no matching rows remain.
	 */
	public static function purge_legacy_batch( $limit = null ) {
		global $wpdb;

		$limit = null === $limit ? self::PURGE_BATCH : (int) $limit;
		if ( $limit < 1 ) {
			$limit = self::PURGE_BATCH;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		if ( ! is_object( $wpdb ) || empty( $wpdb->options ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'esc_like' ) ) {
			return false;
		}

		$after        = function_exists( 'get_option' ) ? (int) get_option( self::PURGE_CURSOR, 0 ) : 0;
		$like_value   = $wpdb->esc_like( '_transient_' . self::LEGACY_PREFIX ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::LEGACY_PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off batched read of this plugin's legacy transient rows.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$wpdb->options} is the current site options table and cannot be a placeholder.
				"SELECT option_id, option_name FROM {$wpdb->options} WHERE option_id > %d AND ( option_name LIKE %s OR option_name LIKE %s ) ORDER BY option_id ASC LIMIT %d",
				$after,
				$like_value,
				$like_timeout,
				(int) $limit
			)
		);
		if ( ! is_array( $rows ) ) {
			return false;
		}
		if ( array() === $rows ) {
			return true;
		}

		$last_id = $after;
		$keys    = array();
		foreach ( $rows as $row ) {
			$id   = is_object( $row ) ? (int) $row->option_id : (int) $row['option_id'];
			$name = is_object( $row ) ? (string) $row->option_name : (string) $row['option_name'];
			if ( $id > $last_id ) {
				$last_id = $id;
			}
			$transient = self::transient_from_option_name( $name );
			if ( self::is_legacy_transient( $transient ) ) {
				$keys[ $transient ] = true;
			}
		}
		foreach ( array_keys( $keys ) as $key ) {
			if ( function_exists( 'delete_transient' ) ) {
				delete_transient( $key );
			}
			// External object caches skip the options table inside delete_transient().
			self::delete_legacy_option( '_transient_' . $key );
			self::delete_legacy_option( '_transient_timeout_' . $key );
		}
		if ( function_exists( 'update_option' ) && $last_id > $after ) {
			update_option( self::PURGE_CURSOR, $last_id );
		}
		return count( $rows ) < $limit;
	}

	/**
	 * Run one batch and remember when the scan is finished.
	 *
	 * @return void
	 */
	public static function purge_one_legacy_batch() {
		if ( self::legacy_purge_finished() ) {
			return;
		}
		if ( ! self::purge_legacy_batch( self::PURGE_BATCH ) ) {
			self::schedule_legacy_purge();
			return;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::PURGE_OPTION, 1 );
		}
		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::PURGE_CURSOR );
		}
	}

	/**
	 * @return bool
	 */
	private static function legacy_purge_finished() {
		if ( ! function_exists( 'get_option' ) ) {
			return true;
		}
		return (bool) get_option( self::PURGE_OPTION, false );
	}

	/**
	 * Transient name stored under an options row, without the WordPress prefix.
	 *
	 * @param string $option_name option_name column.
	 * @return string
	 */
	private static function transient_from_option_name( $option_name ) {
		$option_name = (string) $option_name;
		$timeout     = '_transient_timeout_';
		$value       = '_transient_';
		if ( 0 === strpos( $option_name, $timeout ) ) {
			return substr( $option_name, strlen( $timeout ) );
		}
		if ( 0 === strpos( $option_name, $value ) ) {
			return substr( $option_name, strlen( $value ) );
		}
		return '';
	}

	/**
	 * Remove one options-table row left by an old geo transient.
	 *
	 * @param string $option_name `_transient_…` or `_transient_timeout_…`.
	 * @return void
	 */
	private static function delete_legacy_option( $option_name ) {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Old cache names are `rwgc_geo_v{version}_{32 hex from md5}`.
	 *
	 * @param string $transient Transient name.
	 * @return bool
	 */
	private static function is_legacy_transient( $transient ) {
		return 1 === preg_match( '/^rwgc_geo_v[0-9]+_[a-f0-9]{32}$/', (string) $transient );
	}
}
