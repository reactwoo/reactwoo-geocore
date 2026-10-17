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
		$upload_dir = wp_get_upload_dir();
		$base       = trailingslashit( $upload_dir['basedir'] ) . 'reactwoo-geocore/';
		return $base;
	}

	/**
	 * Get expected DB file path.
	 *
	 * @return string
	 */
	public static function get_db_path() {
		$settings = RWGC_Settings::get_settings();
		if ( ! empty( $settings['db_file_path'] ) ) {
			return $settings['db_file_path'];
		}
		return self::get_storage_dir() . 'GeoLite2-Country.mmdb';
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
		$url = 'https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz';

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

		// Extract .mmdb from the tar.gz.
		$dest_dir = self::get_storage_dir();
		$db_path  = self::extract_mmdb_from_archive( $tmp, $dest_dir );

		wp_delete_file( $tmp );

		if ( is_wp_error( $db_path ) ) {
			self::record_error( $db_path );
			return $db_path;
		}

		$settings                    = RWGC_Settings::get_settings();
		$settings['db_file_path']    = $db_path;
		$settings['db_last_updated'] = gmdate( 'c' );
		$settings['db_last_error']   = '';
		RWGC_Settings::update( $settings );

		/**
		 * Fires when the MaxMind DB has been updated.
		 *
		 * @param array $settings Updated settings.
		 */
		do_action( 'rwgc_db_updated', $settings );

		return true;
	}

	/**
	 * Extract .mmdb file from MaxMind tar.gz archive.
	 *
	 * @param string $archive_path Local tar.gz path.
	 * @param string $dest_dir     Destination directory.
	 * @return string|\WP_Error    DB file path or error.
	 */
	private static function extract_mmdb_from_archive( $archive_path, $dest_dir ) {
		// Prefer PharData if available; otherwise require manual extraction by admin.
		if ( ! class_exists( 'PharData' ) ) {
			return new WP_Error( 'rwgc_no_phar', __( 'Unable to extract MaxMind archive on this server (PharData missing). Please extract manually and set the DB path.', 'reactwoo-geocore' ) );
		}

		try {
			$phar = new PharData( $archive_path );
			// Decompress first (tar.gz → tar).
			if ( substr( $archive_path, -7 ) === '.tar.gz' ) {
				$tar_path = substr( $archive_path, 0, -3 );
				$phar->decompress(); // creates .tar
				unset( $phar );
				$phar = new PharData( $tar_path );
			}
			$phar->extractTo( $dest_dir, null, true );
		} catch ( Exception $e ) {
			return new WP_Error(
				'rwgc_extract_failed',
				sprintf(
					/* translators: %s: archive extraction error message. */
					__( 'Failed to extract MaxMind archive: %s', 'reactwoo-geocore' ),
					$e->getMessage()
				)
			);
		}

		// Find first .mmdb file in dest_dir recursively.
		$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dest_dir ) );
		foreach ( $rii as $file ) {
			if ( $file->isDir() ) {
				continue;
			}
			if ( substr( $file->getFilename(), -5 ) === '.mmdb' ) {
				return $file->getPathname();
			}
		}

		return new WP_Error( 'rwgc_mmdb_not_found', __( 'MaxMind database file (.mmdb) not found after extraction.', 'reactwoo-geocore' ) );
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
			'last_error'    => RWGC_Settings::get( 'db_last_error', '' ),
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
		$settings                  = RWGC_Settings::get_settings();
		$settings['db_last_error'] = $error->get_error_message();
		RWGC_Settings::update( $settings );
	}
}

