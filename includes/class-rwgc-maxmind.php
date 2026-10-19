<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MaxMind database lifecycle for ReactWoo Geo Core.
 *
 * Responsible for download path and basic status reporting.
 */
class RWGC_MaxMind {

	/**
	 * WP-Cron hook for the GeoLite2 download.
	 */
	const CRON_HOOK = 'rwgc_maxmind_auto_update';

	/**
	 * Custom schedule: MaxMind publishes GeoLite2 about twice a week.
	 */
	const CRON_SCHEDULE = 'rwgc_twice_weekly';

	/**
	 * Plugin version the schedule was last checked for.
	 */
	const SCHEDULE_VERSION_OPTION = 'rwgc_maxmind_schedule_version';

	/**
	 * Register the cron callback and schedule.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_update' ) );
		// Activation hooks do not run on a plugin auto-update. Check on every load instead.
		add_action( 'init', array( __CLASS__, 'maybe_sync_schedule' ), 20 );
	}

	/**
	 * Keep the schedule in line with the settings without waiting for activation
	 * or a credentials save.
	 *
	 * Runs a full sync after any plugin version change. Otherwise it only reads
	 * the autoloaded settings and cron options, and syncs when an event is missing
	 * while automatic updates are on, or present while they are off.
	 *
	 * @return void
	 */
	public static function maybe_sync_schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'get_option' ) ) {
			return;
		}
		$version = defined( 'RWGC_VERSION' ) ? (string) RWGC_VERSION : '';
		if ( (string) get_option( self::SCHEDULE_VERSION_OPTION, '' ) !== $version ) {
			self::sync_schedule();
			if ( function_exists( 'update_option' ) ) {
				update_option( self::SCHEDULE_VERSION_OPTION, $version );
			}
			return;
		}
		$wanted = self::auto_update_wanted();
		$count  = self::scheduled_count();
		if ( ( $wanted && 1 !== $count ) || ( ! $wanted && $count > 0 ) ) {
			self::sync_schedule();
		}
	}

	/**
	 * Automatic updates are on and both credentials are saved.
	 *
	 * @return bool
	 */
	public static function auto_update_wanted() {
		$settings = class_exists( 'RWGC_Settings', false ) ? RWGC_Settings::get_settings() : array();
		$account  = self::normalize_credential( isset( $settings['maxmind_account_id'] ) ? $settings['maxmind_account_id'] : '' );
		$license  = self::normalize_credential( isset( $settings['maxmind_license_key'] ) ? $settings['maxmind_license_key'] : '' );
		return ! empty( $settings['auto_update_db'] ) && '' !== $account && '' !== $license;
	}

	/**
	 * Number of queued events for the download hook.
	 *
	 * @return int
	 */
	public static function scheduled_count() {
		if ( function_exists( '_get_cron_array' ) ) {
			$crons = _get_cron_array();
			$count = 0;
			if ( is_array( $crons ) ) {
				foreach ( $crons as $events ) {
					if ( is_array( $events ) && isset( $events[ self::CRON_HOOK ] ) && is_array( $events[ self::CRON_HOOK ] ) ) {
						$count += count( $events[ self::CRON_HOOK ] );
					}
				}
			}
			return $count;
		}
		return function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( self::CRON_HOOK ) ? 1 : 0;
	}

	/**
	 * Twice-weekly interval with a display name.
	 *
	 * @param array<string, array<string, mixed>> $schedules Schedules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$day = defined( 'DAY_IN_SECONDS' ) ? (int) DAY_IN_SECONDS : 86400;
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => (int) ( 3.5 * $day ),
			'display'  => __( 'Twice Weekly', 'reactwoo-geocore' ),
		);
		return $schedules;
	}

	/**
	 * Seconds before the first run. One hour plus up to six hours of jitter.
	 *
	 * @return int
	 */
	public static function initial_cron_delay() {
		$hour   = defined( 'HOUR_IN_SECONDS' ) ? (int) HOUR_IN_SECONDS : 3600;
		$jitter = function_exists( 'wp_rand' ) ? (int) wp_rand( 0, 6 * $hour ) : 0;
		if ( function_exists( 'apply_filters' ) ) {
			$jitter = (int) apply_filters( 'rwgc_maxmind_cron_jitter', $jitter );
		}
		return $hour + max( 0, $jitter );
	}

	/**
	 * Schedule the download when automatic updates and credentials are on.
	 *
	 * @return void
	 */
	public static function sync_schedule() {
		if ( ! self::auto_update_wanted() ) {
			self::unschedule();
			return;
		}
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		$next = wp_next_scheduled( self::CRON_HOOK );
		$same = function_exists( 'wp_get_schedule' ) ? wp_get_schedule( self::CRON_HOOK ) === self::CRON_SCHEDULE : (bool) $next;
		// Exactly one event on the right schedule. Two requests racing can queue a second one; collapse it.
		if ( $next && $same && self::scheduled_count() <= 1 ) {
			return;
		}
		self::unschedule();
		wp_schedule_event( time() + self::initial_cron_delay(), self::CRON_SCHEDULE, self::CRON_HOOK );
	}

	/**
	 * Remove every scheduled automatic download.
	 *
	 * @return void
	 */
	public static function unschedule() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_unschedule_event' ) ) {
			return;
		}
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback. Does nothing when the setting is off or no credentials are saved.
	 *
	 * @return void
	 */
	public static function run_scheduled_update() {
		if ( ! self::auto_update_wanted() ) {
			return;
		}
		self::update_database();
	}

	/**
	 * Database product name used in the download URL and the archive.
	 *
	 * @return string
	 */
	public static function database_edition() {
		$edition = 'GeoLite2-Country';
		if ( function_exists( 'apply_filters' ) ) {
			$edition = (string) apply_filters( 'rwgc_maxmind_database_edition', $edition );
		}
		$edition = preg_replace( '/[^A-Za-z0-9_-]/', '', $edition );
		return is_string( $edition ) && '' !== $edition ? $edition : 'GeoLite2-Country';
	}

	/**
	 * Add the query arg WordPress needs before it will show a saved-settings notice.
	 *
	 * @param string $url Redirect target.
	 * @return string
	 */
	public static function credentials_saved_redirect( $url ) {
		$url = (string) $url;
		if ( '' === $url ) {
			$url = 'admin.php?page=rwgc-integrations-maxmind';
		}
		if ( preg_match( '/(?:\?|&)settings-updated=/', $url ) ) {
			return $url;
		}
		$join = false === strpos( $url, '?' ) ? '?' : '&';
		return $url . $join . 'settings-updated=true';
	}

	/**
	 * Show only the last four characters of a saved license key.
	 *
	 * @param string $key Stored key.
	 * @return string
	 */
	public static function mask_license_key( $key ) {
		$key = (string) $key;
		$len = strlen( $key );
		if ( $len < 1 ) {
			return '';
		}
		if ( $len <= 4 ) {
			return str_repeat( '•', $len );
		}
		return str_repeat( '•', $len - 4 ) . substr( $key, -4 );
	}

	/**
	 * Keep the stored key when the field is empty or still shows the mask.
	 *
	 * @param string $posted Value from the form after credential sanitizing.
	 * @param string $stored Stored license key.
	 * @return string
	 */
	public static function license_key_from_post( $posted, $stored ) {
		$posted = self::normalize_credential( $posted );
		$stored = self::normalize_credential( $stored );
		if ( '' === $posted ) {
			return $stored;
		}
		if ( '' !== $stored && $posted === self::mask_license_key( $stored ) ) {
			return $stored;
		}
		return $posted;
	}

	/**
	 * Ensure storage directory exists.
	 *
	 * @return void
	 */
	public static function ensure_storage_dir() {
		$dir = self::get_storage_dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
	}

	/**
	 * Get storage directory for MaxMind DB.
	 *
	 * @return string
	 */
	public static function get_storage_dir() {
		$base = '';
		if ( function_exists( 'wp_get_upload_dir' ) ) {
			$upload_dir = wp_get_upload_dir();
			if ( is_array( $upload_dir ) && ! empty( $upload_dir['basedir'] ) ) {
				$base = (string) $upload_dir['basedir'];
			}
		}
		if ( '' === $base ) {
			$base = sys_get_temp_dir();
		}
		return rtrim( $base, '/\\' ) . '/reactwoo-geocore/';
	}

	/**
	 * Get expected DB file path.
	 *
	 * @return string
	 */
	public static function get_db_path() {
		$settings   = RWGC_Settings::get_settings();
		$configured = isset( $settings['db_file_path'] ) && is_string( $settings['db_file_path'] ) ? $settings['db_file_path'] : '';
		if ( '' !== $configured && is_file( $configured ) ) {
			return $configured;
		}

		$canonical = self::get_canonical_db_path();
		if ( is_file( $canonical ) ) {
			return $canonical;
		}

		/*
		 * Geo Core 1.9.12 and earlier extracted the MaxMind archive as it is,
		 * so the database sits at uploads/reactwoo-geocore/GeoLite2-Country_YYYYMMDD/GeoLite2-Country.mmdb
		 * and was found only through db_file_path. When that path is empty or
		 * stale, use the newest valid file in a dated folder and copy it to the
		 * canonical path once.
		 */
		$legacy = self::find_legacy_db_path();
		if ( null !== $legacy ) {
			return self::adopt_legacy_db( $legacy, $canonical );
		}

		return '' !== $configured ? $configured : $canonical;
	}

	/**
	 * Single-file location that downloads (1.9.13+) and manual uploads write to.
	 *
	 * @return string
	 */
	public static function get_canonical_db_path() {
		return self::get_storage_dir() . 'GeoLite2-Country.mmdb';
	}

	/**
	 * Newest usable GeoLite2-Country.mmdb in a dated folder under the storage directory.
	 *
	 * @param string|null $storage_dir Directory to scan. Null uses get_storage_dir().
	 * @return string|null Absolute path, or null when none is usable.
	 */
	public static function find_legacy_db_path( $storage_dir = null ) {
		$dir = rtrim( null === $storage_dir ? self::get_storage_dir() : (string) $storage_dir, '/\\' );
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return null;
		}
		$entries = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- An unreadable uploads folder means no legacy database, not a warning on every request.
		if ( ! is_array( $entries ) ) {
			return null;
		}
		$filename   = 'GeoLite2-Country.mmdb';
		$candidates = array();
		foreach ( $entries as $entry ) {
			if ( ! preg_match( '/^GeoLite2-Country_(\d{8})$/', (string) $entry, $matches ) ) {
				continue;
			}
			$path = $dir . '/' . $entry . '/' . $filename;
			if ( is_file( $path ) ) {
				$candidates[ $path ] = (int) $matches[1];
			}
		}
		arsort( $candidates, SORT_NUMERIC );
		foreach ( array_keys( $candidates ) as $path ) {
			if ( self::db_is_usable( $path ) ) {
				return $path;
			}
		}
		return null;
	}

	/**
	 * Copy a legacy dated database to the canonical path and remember it.
	 *
	 * The dated folder is left in place. When the copy cannot be written, the
	 * dated path itself is saved so later requests do not scan again.
	 *
	 * @param string $legacy    Usable dated database.
	 * @param string $canonical Canonical path.
	 * @return string Path to use for lookups.
	 */
	private static function adopt_legacy_db( $legacy, $canonical ) {
		$use = $legacy;
		if ( wp_is_writable( dirname( $canonical ) ) ) {
			// A unique sibling per request, so two first visitors cannot rename a half-written copy.
			$tmp = $canonical . '.' . bin2hex( random_bytes( 4 ) ) . '.new';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Same-directory copy then rename is the atomic replace.
			if ( @copy( $legacy, $tmp ) && filesize( $tmp ) === filesize( $legacy ) && @rename( $tmp, $canonical ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- A failed copy falls back to the dated path.
				$use = $canonical;
			}
			self::delete_file( $tmp );
		}
		$settings = RWGC_Settings::get_settings_fresh();
		if ( ! isset( $settings['db_file_path'] ) || $settings['db_file_path'] !== $use ) {
			$settings['db_file_path'] = $use;
			if ( empty( $settings['db_last_updated'] ) ) {
				$mtime                       = filemtime( $legacy );
				$settings['db_last_updated'] = gmdate( 'c', $mtime ? $mtime : time() );
			}
			RWGC_Settings::update( $settings );
			// Lookups cached while the path was missing hold the fallback country.
			self::flush_lookup_cache();
		}
		return $use;
	}

	/**
	 * Whether DB file exists.
	 *
	 * @return bool
	 */
	public static function db_exists() {
		$path = self::get_db_path();
		return is_string( $path ) && $path !== '' && file_exists( $path );
	}

	/**
	 * Whether a path is a readable MaxMind .mmdb (metadata marker present).
	 *
	 * A license key is not required. An uploaded test or production database counts.
	 *
	 * @param string|null $path Absolute path. Null uses the configured database path.
	 * @return bool
	 */
	public static function db_is_usable( $path = null ) {
		if ( null === $path ) {
			$path = self::get_db_path();
		}
		if ( ! is_string( $path ) || '' === $path || ! is_readable( $path ) ) {
			return false;
		}
		if ( 'mmdb' !== strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return false;
		}
		$size = filesize( $path );
		if ( false === $size || $size < 128 ) {
			return false;
		}
		$tail_len = (int) min( 131072, $size );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read only the MaxMind metadata tail. WP_Filesystem is not available for this binary check.
		$tail = file_get_contents( $path, false, null, max( 0, $size - $tail_len ), $tail_len );
		return is_string( $tail ) && false !== strpos( $tail, "\xab\xcd\xefMaxMind.com" );
	}

	/**
	 * Decide the MaxMind admin notice.
	 *
	 * A usable database suppresses the license-key warning. The key is required
	 * only when there is nothing on disk to look up, or for automatic downloads.
	 *
	 * @param bool $usable      Readable MaxMind database.
	 * @param bool $has_license License key saved.
	 * @param bool $is_stale    Database is past the refresh window.
	 * @return string Empty when no notice is needed. Otherwise auto_update_hint, stale, no_database, or missing_file.
	 */
	public static function admin_notice_code( $usable, $has_license, $is_stale ) {
		if ( $usable ) {
			if ( ! $has_license ) {
				return 'auto_update_hint';
			}
			if ( $is_stale ) {
				return 'stale';
			}
			return '';
		}
		if ( ! $has_license ) {
			return 'no_database';
		}
		return 'missing_file';
	}

	/**
	 * Whether DB appears stale (older than 35 days).
	 *
	 * @return bool
	 */
	public static function db_is_stale() {
		if ( ! self::db_exists() ) {
			return true;
		}
		$settings = RWGC_Settings::get_settings();
		$ts       = ! empty( $settings['db_last_updated'] ) ? strtotime( $settings['db_last_updated'] ) : 0;
		if ( ! $ts ) {
			$path = self::get_db_path();
			$ts   = filemtime( $path );
		}
		if ( ! $ts ) {
			return true;
		}
		// Consider stale after 35 days.
		return ( time() - $ts ) > 35 * DAY_IN_SECONDS;
	}

	/**
	 * Remove characters that can never be part of a MaxMind credential.
	 *
	 * Mobile copy/paste often carries a non-breaking space or a zero-width
	 * character. trim() and sanitize_text_field() keep both, and MaxMind then
	 * answers 401 for a key that looks right on screen.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function normalize_credential( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value   = (string) $value;
		$cleaned = preg_replace( '/[\s\p{Z}\p{Cc}\p{Cf}]+/u', '', $value );
		if ( null === $cleaned ) {
			// Invalid UTF-8: fall back to ASCII whitespace only.
			$cleaned = preg_replace( '/\s+/', '', $value );
		}
		return (string) $cleaned;
	}

	/**
	 * Check the shape of MaxMind credentials before calling MaxMind.
	 *
	 * Account IDs are numeric. License keys use letters, digits and underscores.
	 *
	 * @param string $account_id Normalized account ID.
	 * @param string $license    Normalized license key.
	 * @return true|\WP_Error
	 */
	public static function validate_credentials( $account_id, $license ) {
		if ( ! preg_match( '/^[0-9]+$/', (string) $account_id ) ) {
			return new WP_Error( 'rwgc_bad_account_id', __( 'The MaxMind Account ID must be the number shown under Account Information on maxmind.com, not your email address or username.', 'reactwoo-geocore' ) );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', (string) $license ) ) {
			return new WP_Error( 'rwgc_bad_license_key', __( 'The MaxMind license key contains characters MaxMind never uses. Paste the key again from your MaxMind account.', 'reactwoo-geocore' ) );
		}
		return true;
	}

	/**
	 * Download/update MaxMind database.
	 *
	 * This is a best-effort implementation; failures should be surfaced as WP_Error
	 * but must not fatal.
	 *
	 * @return true|\WP_Error
	 */
	public static function update_database() {
		$account_id = self::normalize_credential( RWGC_Settings::get( 'maxmind_account_id', '' ) );
		$license    = self::normalize_credential( RWGC_Settings::get( 'maxmind_license_key', '' ) );
		if ( '' === $account_id || '' === $license ) {
			$error = new WP_Error( 'rwgc_no_credentials', __( 'MaxMind Account ID or License Key is not configured.', 'reactwoo-geocore' ) );
			self::record_error( $error );
			return $error;
		}
		$valid = self::validate_credentials( $account_id, $license );
		if ( is_wp_error( $valid ) ) {
			self::record_error( $valid );
			return $valid;
		}

		self::ensure_storage_dir();

		// Step 1: Hit MaxMind download endpoint with Basic Auth (AccountID:LicenseKey).
		// This returns a redirect to a presigned R2/S3 URL where authentication is
		// handled via the query string signature – we must NOT forward our Authorization
		// header to that second host.
		$url = 'https://download.maxmind.com/geoip/databases/' . self::database_edition() . '/download?suffix=tar.gz';

		$auth_headers = array(
			'Authorization' => 'Basic ' . base64_encode( $account_id . ':' . $license ),
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 60,
				'headers'     => $auth_headers,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::record_error( $response );
			return $response;
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$message = wp_remote_retrieve_response_message( $response );

		// Follow one redirect manually to the presigned storage URL, this time without
		// forwarding our Authorization header (the signature is in the URL itself).
		if ( in_array( (int) $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( ! $location ) {
				$error = new WP_Error( 'rwgc_no_location', __( 'MaxMind returned a redirect without a Location header.', 'reactwoo-geocore' ) );
				self::record_error( $error );
				return $error;
			}

			$response = wp_remote_get(
				$location,
				array(
					'timeout' => 60,
				)
			);

			if ( is_wp_error( $response ) ) {
				self::record_error( $response );
				return $response;
			}

			$code    = wp_remote_retrieve_response_code( $response );
			$message = wp_remote_retrieve_response_message( $response );
		}

		if ( 200 !== (int) $code ) {
			$body_snippet = substr( trim( wp_strip_all_tags( wp_remote_retrieve_body( $response ) ) ), 0, 200 );
			$error_msg    = sprintf(
				/* translators: 1: HTTP status code, 2: HTTP reason, 3: short body snippet */
				__( 'MaxMind download failed (HTTP %1$d %2$s). Response: %3$s', 'reactwoo-geocore' ),
				(int) $code,
				(string) $message,
				$body_snippet
			);
			if ( 401 === (int) $code ) {
				$error_msg .= ' ' . sprintf(
					/* translators: 1: first six characters of the saved license key, 2: saved key length. */
					__( 'MaxMind rejected the saved pair. Check that the Account ID is the number shown under Account Information on maxmind.com (not your email or username), that the key starting "%1$s" (%2$d characters) was created under that same account, and wait a few minutes if the key is brand new.', 'reactwoo-geocore' ),
					substr( $license, 0, 6 ),
					strlen( $license )
				);
			}
			$error = new WP_Error( 'rwgc_http_error', $error_msg );
			self::record_error( $error );
			return $error;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			$error = new WP_Error( 'rwgc_empty_body', __( 'MaxMind download returned empty body.', 'reactwoo-geocore' ) );
			self::record_error( $error );
			return $error;
		}

		$tmp = wp_tempnam( 'rwgc-maxmind' );
		if ( ! $tmp ) {
			$error = new WP_Error( 'rwgc_tmp_failed', __( 'Unable to create temporary file for MaxMind download.', 'reactwoo-geocore' ) );
			self::record_error( $error );
			return $error;
		}

		file_put_contents( $tmp, $body );

		// Extract .mmdb from the tar.gz. A failure here must stay a WP_Error.
		$dest_dir = self::get_storage_dir();
		try {
			$db_path = self::extract_database_archive( $tmp, $dest_dir );
		} catch ( \Throwable $e ) {
			$db_path = new WP_Error(
				'rwgc_extract_failed',
				sprintf(
					/* translators: %s: archive extraction error message. */
					__( 'Failed to extract MaxMind archive: %s', 'reactwoo-geocore' ),
					$e->getMessage()
				)
			);
		} finally {
			self::delete_file( $tmp );
		}

		if ( is_wp_error( $db_path ) ) {
			self::record_error( $db_path );
			return $db_path;
		}

		self::record_download_success( $db_path );

		return true;
	}

	/**
	 * Save a successful download and drop cached lookups made with the old file.
	 *
	 * @param string $db_path Installed database.
	 * @return array<string, mixed> Saved settings.
	 */
	public static function record_download_success( $db_path ) {
		$settings                       = RWGC_Settings::get_settings_fresh();
		$settings['db_file_path']       = (string) $db_path;
		$settings['db_last_updated']    = gmdate( 'c' );
		$settings['db_last_success_at'] = $settings['db_last_updated'];
		$settings['db_last_error']      = '';
		$settings['db_last_failure_at'] = '';
		RWGC_Settings::update( $settings );
		self::flush_lookup_cache();

		/**
		 * Fires when the MaxMind DB has been updated.
		 *
		 * @param array $settings Updated settings.
		 */
		do_action( 'rwgc_db_updated', $settings );

		return $settings;
	}

	/**
	 * Save a manually uploaded database and drop cached lookups.
	 *
	 * @param string $db_path Installed database.
	 * @return array<string, mixed> Saved settings.
	 */
	public static function record_manual_upload( $db_path ) {
		$settings                    = RWGC_Settings::get_settings_fresh();
		$settings['db_file_path']    = (string) $db_path;
		$settings['db_last_updated']    = gmdate( 'c' );
		$settings['db_last_error']      = '';
		$settings['db_last_failure_at'] = '';
		RWGC_Settings::update( $settings );
		self::flush_lookup_cache();
		return $settings;
	}

	/**
	 * Apply a MaxMind credentials form post to the settings array.
	 *
	 * Saving makes no request to MaxMind; the download is the only check. Any
	 * stored download error describes an earlier attempt, so the save clears the
	 * message and the screen stops showing an old 401 as if the save had failed.
	 * The failure time stays under "Last failed download" until a download succeeds.
	 *
	 * @param array<string, mixed> $settings Current settings.
	 * @param string|null          $account  Sanitized posted Account ID, or null when not posted.
	 * @param string|null          $key      Sanitized posted license key, or null when not posted.
	 * @param bool                 $auto     Automatic updates checkbox.
	 * @return array<string, mixed>
	 */
	public static function apply_credentials_save( array $settings, $account, $key, $auto ) {
		if ( null !== $account ) {
			$settings['maxmind_account_id'] = self::normalize_credential( $account );
		}
		if ( null !== $key ) {
			$stored                          = isset( $settings['maxmind_license_key'] ) ? (string) $settings['maxmind_license_key'] : '';
			$settings['maxmind_license_key'] = self::license_key_from_post( (string) $key, $stored );
		}
		$settings['auto_update_db'] = $auto ? 1 : 0;
		$settings['db_last_error']  = '';
		return $settings;
	}

	/**
	 * Run a MaxMind screen maintenance action.
	 *
	 * @param string $action update_db or clear_cache.
	 * @return array{type: string, code: string, message: string}
	 */
	public static function run_maintenance_action( $action ) {
		if ( 'clear_cache' === $action ) {
			self::flush_lookup_cache();
			return array(
				'type'    => 'updated',
				'code'    => 'rwgc_cache_cleared',
				'message' => __( 'Geo cache cleared.', 'reactwoo-geocore' ),
			);
		}
		if ( 'update_db' === $action ) {
			$result = self::update_database();
			if ( is_wp_error( $result ) ) {
				return array(
					'type'    => 'error',
					'code'    => (string) $result->get_error_code(),
					'message' => (string) $result->get_error_message(),
				);
			}
			return array(
				'type'    => 'updated',
				'code'    => 'rwgc_db_updated',
				'message' => __( 'MaxMind country database updated.', 'reactwoo-geocore' ),
			);
		}
		return array(
			'type'    => 'error',
			'code'    => 'rwgc_unknown_action',
			'message' => '',
		);
	}

	/**
	 * Invalidate every per-IP lookup cached by RWGC_Cache.
	 *
	 * @return void
	 */
	public static function flush_lookup_cache() {
		if ( class_exists( 'RWGC_Cache', false ) ) {
			RWGC_Cache::clear_all();
			return;
		}
		if ( function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
			$version = (int) get_option( 'rwgc_cache_version', 1 );
			update_option( 'rwgc_cache_version', max( 1, $version ) + 1 );
		}
	}

	/**
	 * Extract .mmdb file from MaxMind tar.gz archive.
	 *
	 * Directory listing is inside the same try as unpacking. Any failure,
	 * including an unreadable temp directory, becomes a WP_Error and does not
	 * replace the live database. Copies and the work directory are removed
	 * on every path.
	 *
	 * @param string $archive_path Local tar.gz path.
	 * @param string $dest_dir     Destination directory.
	 * @return string|\WP_Error    DB file path or error.
	 */
	public static function extract_database_archive( $archive_path, $dest_dir ) {
		$work     = '';
		$tar_copy = '';
		$tar_path = '';
		try {
			if ( ! class_exists( 'PharData' ) ) {
				return new WP_Error( 'rwgc_no_phar', __( 'Unable to extract MaxMind archive on this server (PharData missing). Please extract manually and set the DB path.', 'reactwoo-geocore' ) );
			}

			$made = self::make_extract_dir();
			if ( is_wp_error( $made ) ) {
				return $made;
			}
			$work = $made;

			$open = (string) $archive_path;
			if ( substr( $open, -7 ) !== '.tar.gz' ) {
				$tar_copy = $open . '.tar.gz';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- PharData needs a .tar.gz suffix.
				if ( ! copy( $open, $tar_copy ) ) {
					return new WP_Error( 'rwgc_extract_failed', __( 'Failed to prepare the MaxMind archive for extraction.', 'reactwoo-geocore' ) );
				}
				$open = $tar_copy;
			}

			$phar = new PharData( $open );
			$phar->decompress();
			unset( $phar );
			$tar_path = substr( $open, 0, -3 );
			$phar     = new PharData( $tar_path );
			$phar->extractTo( $work, null, true );
			unset( $phar );

			$edition  = self::database_edition();
			$relative = array();
			foreach ( self::list_files( $work ) as $absolute ) {
				$relative[] = ltrim( substr( $absolute, strlen( $work ) ), '/\\' );
			}
			$chosen = self::select_archive_mmdb( $relative, $edition );
			if ( null === $chosen ) {
				return new WP_Error( 'rwgc_mmdb_not_found', __( 'MaxMind database file (.mmdb) not found after extraction.', 'reactwoo-geocore' ) );
			}

			$source = $work . '/' . $chosen;
			$valid  = self::validate_database_file( $source );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$dest = rtrim( (string) $dest_dir, '/\\' ) . '/' . $edition . '.mmdb';
			return self::install_database_file( $source, $dest );
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'rwgc_extract_failed',
				sprintf(
					/* translators: %s: archive extraction error message. */
					__( 'Failed to extract MaxMind archive: %s', 'reactwoo-geocore' ),
					$e->getMessage()
				)
			);
		} finally {
			if ( '' !== $work ) {
				self::remove_tree( $work );
			}
			if ( '' !== $tar_path ) {
				self::delete_file( $tar_path );
			}
			if ( '' !== $tar_copy ) {
				self::delete_file( $tar_copy );
			}
		}
	}

	/**
	 * Choose edition.mmdb from the newest dated directory in the archive.
	 *
	 * Paths are relative to the extracted archive. An older database already
	 * on disk is not in this list.
	 *
	 * @param array<int, string> $relative_paths Archive-relative paths.
	 * @param string             $edition        Database edition, such as GeoLite2-Country.
	 * @return string|null Relative path, or null when the edition is absent.
	 */
	public static function select_archive_mmdb( array $relative_paths, $edition ) {
		$edition  = (string) $edition;
		$filename = $edition . '.mmdb';
		$best     = null;
		$best_date = -1;
		foreach ( $relative_paths as $path ) {
			$path = str_replace( '\\', '/', (string) $path );
			if ( basename( $path ) !== $filename ) {
				continue;
			}
			$date = 0;
			$pattern = '/(?:^|\/)' . preg_quote( $edition, '/' ) . '_(\d{8})(?:\/|$)/';
			if ( preg_match( $pattern, $path, $matches ) ) {
				$date = (int) $matches[1];
			}
			if ( null === $best || $date > $best_date ) {
				$best      = $path;
				$best_date = $date;
			}
		}
		return $best;
	}

	/**
	 * Open the file with the MaxMind reader before it replaces the live database.
	 *
	 * @param string $path Candidate .mmdb.
	 * @return true|\WP_Error
	 */
	public static function validate_database_file( $path ) {
		if ( ! self::db_is_usable( $path ) ) {
			return new WP_Error( 'rwgc_mmdb_invalid', __( 'The downloaded file is not a MaxMind database.', 'reactwoo-geocore' ) );
		}
		if ( ! class_exists( '\GeoIp2\Database\Reader' ) && defined( 'RWGC_PATH' ) && is_readable( RWGC_PATH . 'vendor/autoload.php' ) ) {
			require_once RWGC_PATH . 'vendor/autoload.php';
		}
		if ( ! class_exists( '\GeoIp2\Database\Reader' ) ) {
			return new WP_Error( 'rwgc_no_reader', __( 'The MaxMind reader is not available to check this database.', 'reactwoo-geocore' ) );
		}
		try {
			$reader  = new \GeoIp2\Database\Reader( $path );
			$meta    = $reader->metadata();
			$reader->close();
			$type    = isset( $meta->databaseType ) ? (string) $meta->databaseType : '';
			$edition = self::database_edition();
			if ( '' !== $type && false === stripos( $type, $edition ) ) {
				return new WP_Error(
					'rwgc_mmdb_edition',
					sprintf(
						/* translators: 1: database type inside the file, 2: expected edition. */
						__( 'The downloaded database is %1$s, not %2$s.', 'reactwoo-geocore' ),
						$type,
						$edition
					)
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'rwgc_mmdb_invalid',
				sprintf(
					/* translators: %s: reader error message. */
					__( 'The downloaded database could not be opened: %s', 'reactwoo-geocore' ),
					$e->getMessage()
				)
			);
		}
		return true;
	}

	/**
	 * Replace the live database by writing a sibling file and renaming it.
	 *
	 * @param string $source Validated database.
	 * @param string $dest   Live path.
	 * @return string|\WP_Error Destination path.
	 */
	public static function install_database_file( $source, $dest ) {
		$dest = (string) $dest;
		$dir  = dirname( $dest );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$tmp = $dest . '.new';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Same-directory rename is the atomic replace.
		if ( ! copy( $source, $tmp ) ) {
			return new WP_Error( 'rwgc_install_failed', __( 'Could not write the new MaxMind database.', 'reactwoo-geocore' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replace on the same filesystem.
		if ( ! rename( $tmp, $dest ) ) {
			self::delete_file( $tmp );
			return new WP_Error( 'rwgc_install_failed', __( 'Could not replace the MaxMind database.', 'reactwoo-geocore' ) );
		}
		return $dest;
	}

	/**
	 * Private work directory under the uploads folder, or the system temp directory.
	 *
	 * @return string|\WP_Error
	 */
	private static function make_extract_dir() {
		if ( function_exists( 'apply_filters' ) ) {
			$forced = apply_filters( 'rwgc_maxmind_extract_work_dir', '' );
			if ( is_string( $forced ) && '' !== $forced ) {
				return $forced;
			}
		}

		$parent = self::resolve_extract_parent();
		if ( is_wp_error( $parent ) ) {
			return $parent;
		}
		$dir = rtrim( (string) $parent, '/\\' ) . '/tmp-' . bin2hex( random_bytes( 4 ) );
		if ( ! self::ensure_dir( $dir ) || ! self::directory_is_usable( $dir ) ) {
			self::remove_tree( $dir );
			return new WP_Error( 'rwgc_extract_failed', __( 'Could not create a temporary directory for the MaxMind archive.', 'reactwoo-geocore' ) );
		}
		return $dir;
	}

	/**
	 * Uploads directory first, then get_temp_dir(). Both must be writable and listable.
	 *
	 * @return string|\WP_Error
	 */
	private static function resolve_extract_parent() {
		$candidates = array(
			self::get_storage_dir(),
			self::system_temp_dir(),
		);
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgc_maxmind_extract_parents', $candidates );
			if ( is_array( $filtered ) ) {
				$candidates = $filtered;
			}
		}
		foreach ( $candidates as $dir ) {
			if ( ! is_string( $dir ) || '' === $dir ) {
				continue;
			}
			if ( self::ensure_dir( $dir ) && self::directory_is_usable( $dir ) ) {
				return $dir;
			}
		}
		return new WP_Error( 'rwgc_extract_failed', __( 'No writable temporary directory is available for the MaxMind archive.', 'reactwoo-geocore' ) );
	}

	/**
	 * @return string
	 */
	private static function system_temp_dir() {
		if ( function_exists( 'get_temp_dir' ) ) {
			$dir = get_temp_dir();
			if ( is_string( $dir ) && '' !== $dir ) {
				return $dir;
			}
		}
		return sys_get_temp_dir();
	}

	/**
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function ensure_dir( $dir ) {
		if ( is_dir( $dir ) ) {
			return true;
		}
		return (bool) wp_mkdir_p( $dir );
	}

	/**
	 * Writable and listable. Listing is checked without letting the iterator throw.
	 *
	 * @param string $dir Directory.
	 * @return bool
	 */
	private static function directory_is_usable( $dir ) {
		if ( ! is_string( $dir ) || '' === $dir || ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return false;
		}
		try {
			$listed = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A denied temp directory must be a false result, not an exception.
			return is_array( $listed );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * @param string $dir Directory.
	 * @return string[]
	 */
	private static function list_files( $dir ) {
		$out = array();
		if ( ! is_dir( $dir ) ) {
			return $out;
		}
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$out[] = $file->getPathname();
			}
		}
		return $out;
	}

	/**
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function remove_tree( $dir ) {
		if ( ! is_string( $dir ) || '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		try {
			$filesystem = self::direct_filesystem();
			if ( $filesystem ) {
				$filesystem->rmdir( $dir, true );
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Best-effort cleanup.
			unset( $e );
		}
	}

	/**
	 * Direct filesystem for temp directories this process created.
	 *
	 * Cron and REST must not call request_filesystem_credentials(), which can
	 * print an FTP form. Extract work stays on the local disk.
	 *
	 * @return WP_Filesystem_Direct|null
	 */
	private static function direct_filesystem() {
		if ( ! class_exists( 'WP_Filesystem_Direct', false ) ) {
			$base   = ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			$direct = ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			if ( is_readable( $base ) && is_readable( $direct ) ) {
				require_once $base;
				require_once $direct;
			}
		}
		if ( ! class_exists( 'WP_Filesystem_Direct', false ) ) {
			return null;
		}
		return new WP_Filesystem_Direct( null );
	}

	/**
	 * @param string $path File.
	 * @return void
	 */
	private static function delete_file( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! file_exists( $path ) ) {
			return;
		}
		wp_delete_file( $path );
	}

	/**
	 * Get human-readable status summary.
	 *
	 * @return array
	 */
	public static function get_status() {
		$exists = self::db_exists();
		return array(
			'exists'        => $exists,
			'usable'        => self::db_is_usable(),
			'path'          => self::get_db_path(),
			'last_updated'  => RWGC_Settings::get( 'db_last_updated', '' ),
			'is_stale'      => self::db_is_stale(),
			'has_license'   => (bool) trim( (string) RWGC_Settings::get( 'maxmind_license_key', '' ) ),
			'last_error'      => RWGC_Settings::get( 'db_last_error', '' ),
			'last_success_at' => RWGC_Settings::get( 'db_last_success_at', '' ),
			'last_failure_at' => RWGC_Settings::get( 'db_last_failure_at', '' ),
		);
	}

	/**
	 * Record the last database error for the MaxMind status screen.
	 *
	 * @param \WP_Error $error Error object.
	 * @return void
	 */
	private static function record_error( $error ) {
		if ( ! ( $error instanceof WP_Error ) ) {
			return;
		}
		$settings                       = RWGC_Settings::get_settings_fresh();
		$settings['db_last_error']      = $error->get_error_message();
		$settings['db_last_failure_at'] = gmdate( 'c' );
		RWGC_Settings::update( $settings );
	}
}

