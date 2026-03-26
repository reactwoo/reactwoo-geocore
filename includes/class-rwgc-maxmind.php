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
	 * Download/update MaxMind database.
	 *
	 * This is a best-effort implementation; failures should be surfaced as WP_Error
	 * but must not fatal.
	 *
	 * @return true|\WP_Error
	 */
	public static function update_database() {
		$account_id = trim( (string) RWGC_Settings::get( 'maxmind_account_id', '' ) );
		$license    = trim( (string) RWGC_Settings::get( 'maxmind_license_key', '' ) );
		if ( '' === $account_id || '' === $license ) {
			$error = new WP_Error( 'rwgc_no_credentials', __( 'MaxMind Account ID or License Key is not configured.', 'reactwoo-geocore' ) );
			self::record_error( $error );
			return $error;
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

		@unlink( $tmp );

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
			return new WP_Error( 'rwgc_extract_failed', sprintf( __( 'Failed to extract MaxMind archive: %s', 'reactwoo-geocore' ), $e->getMessage() ) );
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
			'path'          => self::get_db_path(),
			'last_updated'  => RWGC_Settings::get( 'db_last_updated', '' ),
			'is_stale'      => self::db_is_stale(),
			'has_license'   => (bool) trim( (string) RWGC_Settings::get( 'maxmind_license_key', '' ) ),
			'last_error'    => RWGC_Settings::get( 'db_last_error', '' ),
		);
	}

	/**
	 * Record last DB error and log when debug mode is enabled.
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

		if ( RWGC_Settings::get( 'debug_mode', 0 ) && function_exists( 'error_log' ) ) {
			error_log( sprintf( 'ReactWoo Geo Core MaxMind error [%s]: %s', $error->get_error_code(), $error->get_error_message() ) );
		}
	}
}

