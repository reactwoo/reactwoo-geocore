<?php
/**
 * Local AI snapshot build/sync status (Geo AI reads this in Phase 2).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists last built hash and timestamps for site intelligence snapshots.
 */
class RWGC_AI_Snapshot_Sync_Status {

	const OPTION_KEY = 'rwgc_ai_snapshot_sync_status';

	/**
	 * @return array<string, mixed>
	 */
	public static function get_status() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		return array_merge(
			array(
				'last_built_at_gmt'   => '',
				'last_built_hash'     => '',
				'last_synced_at_gmt'  => '',
				'last_synced_hash'    => '',
				'last_sync_status'    => '',
				'last_sync_error'     => '',
			),
			$raw
		);
	}

	/**
	 * Record a local snapshot build (hash from Geo Core builder).
	 *
	 * @param string $hash Snapshot hash.
	 * @return void
	 */
	public static function record_build( $hash ) {
		$hash = sanitize_text_field( (string) $hash );
		$status = self::get_status();
		$status['last_built_at_gmt'] = gmdate( 'c' );
		$status['last_built_hash']   = $hash;
		update_option( self::OPTION_KEY, $status, false );
	}

	/**
	 * Record a successful cloud sync (Geo AI Phase 2).
	 *
	 * @param string $hash Snapshot hash synced.
	 * @return void
	 */
	public static function record_sync_success( $hash ) {
		$hash = sanitize_text_field( (string) $hash );
		$status = self::get_status();
		$status['last_synced_at_gmt'] = gmdate( 'c' );
		$status['last_synced_hash']   = $hash;
		$status['last_sync_status']   = 'synced';
		$status['last_sync_error']    = '';
		update_option( self::OPTION_KEY, $status, false );
	}

	/**
	 * Record a failed cloud sync attempt.
	 *
	 * @param string $error Human-readable error (no tokens).
	 * @return void
	 */
	public static function record_sync_error( $error ) {
		$status = self::get_status();
		$status['last_sync_status'] = 'error';
		$status['last_sync_error']  = sanitize_text_field( (string) $error );
		update_option( self::OPTION_KEY, $status, false );
	}

	/**
	 * Whether the last built hash matches the last synced hash.
	 *
	 * @return bool
	 */
	public static function is_in_sync() {
		$status = self::get_status();
		$built  = isset( $status['last_built_hash'] ) ? (string) $status['last_built_hash'] : '';
		$synced = isset( $status['last_synced_hash'] ) ? (string) $status['last_synced_hash'] : '';
		return '' !== $built && $built === $synced;
	}
}
