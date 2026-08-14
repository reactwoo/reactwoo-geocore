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

if ( ! function_exists( 'reactwoo_cloud_record_event' ) ) {
	/**
	 * Queue a Cloud analytics event locally. Never performs HTTP.
	 *
	 * @param string               $type Type (e.g. variant.impression).
	 * @param array<string, mixed> $attrs Attributes.
	 * @return bool
	 */
	function reactwoo_cloud_record_event( $type, array $attrs = array() ) {
		if ( ! class_exists( 'RWGC_Cloud_Telemetry', false ) ) {
			return false;
		}
		return RWGC_Cloud_Telemetry::record( $type, $attrs );
	}
}

if ( ! function_exists( 'reactwoo_cloud_flush_events' ) ) {
	/**
	 * Upload queued events (cron/admin only).
	 *
	 * @return array<string, mixed>
	 */
	function reactwoo_cloud_flush_events() {
		if ( ! class_exists( 'RWGC_Cloud_Event_Queue', false ) ) {
			return array(
				'ok'     => false,
				'status' => 'unavailable',
				'error'  => 'unavailable',
			);
		}
		return RWGC_Cloud_Event_Queue::flush();
	}
}

if ( ! function_exists( 'reactwoo_cloud_migration_preview' ) ) {
	/**
	 * Local import preview. Never contacts Cloud.
	 *
	 * @return array<string, mixed>
	 */
	function reactwoo_cloud_migration_preview() {
		return RWGC_Cloud_Migration::preview();
	}
}

if ( ! function_exists( 'reactwoo_cloud_import' ) ) {
	/**
	 * Backup locally and POST supported resources to Cloud. Does not switch mode.
	 *
	 * @return array{ok: bool, error: string, preview: array<string, mixed>}
	 */
	function reactwoo_cloud_import() {
		return RWGC_Cloud_Migration::import();
	}
}

if ( ! function_exists( 'reactwoo_cloud_switch_management_mode' ) ) {
	/**
	 * Explicit management-mode switch after import.
	 *
	 * @param string $mode local|cloud.
	 * @return array{ok: bool, error: string, management_mode: string}
	 */
	function reactwoo_cloud_switch_management_mode( $mode ) {
		return RWGC_Cloud_Migration::switch_mode( $mode );
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
