<?php
/**
 * Manifest sync, heartbeat, capability reporting (admin/cron only).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud outage must not affect visitor rendering — failures are soft.
 */
final class RWGC_Cloud_Sync {

	/**
	 * Sync manifest when connected. Safe no-op when disconnected.
	 *
	 * @return array{ok: bool, status: string, revision: int, error: string}
	 */
	public static function sync_manifest() {
		if ( ! RWGC_Cloud_Connection::is_connected() ) {
			return array(
				'ok'       => false,
				'status'   => 'not_connected',
				'revision' => 0,
				'error'    => 'not_connected',
			);
		}

		$creds = RWGC_Cloud_Credentials::get();
		if ( ! $creds ) {
			return array(
				'ok'       => false,
				'status'   => 'not_connected',
				'revision' => 0,
				'error'    => 'missing_credentials',
			);
		}

		$conn     = RWGC_Cloud_Connection::get();
		$revision = (int) $conn['manifest_revision'];
		$headers  = array();
		if ( $revision > 0 ) {
			$headers['If-None-Match'] = '"' . $revision . '"';
			$headers['X-ReactWoo-Manifest-Revision'] = (string) $revision;
		}

		$response = RWGC_Cloud_Http::request(
			'GET',
			'/sites/' . rawurlencode( $creds['site_id'] ) . '/manifest',
			array(),
			$headers,
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
			)
		);

		if ( 304 === (int) $response['status'] ) {
			RWGC_Cloud_Connection::update(
				array(
					'last_sync_at' => gmdate( 'c' ),
					'last_error'   => '',
				)
			);
			return array(
				'ok'       => true,
				'status'   => 'not_modified',
				'revision' => $revision,
				'error'    => '',
			);
		}

		if ( ! $response['ok'] || ! is_array( $response['body'] ) ) {
			// Retain previous known-good; do not clear.
			RWGC_Cloud_Connection::update(
				array(
					'last_error' => $response['error'] ? $response['error'] : 'sync_failed',
				)
			);
			return array(
				'ok'       => false,
				'status'   => 'error',
				'revision' => $revision,
				'error'    => $response['error'] ? $response['error'] : 'sync_failed',
			);
		}

		$install = RWGC_Cloud_Manifest_Store::install( $response['body'], $creds['site_id'] );
		if ( ! $install['ok'] ) {
			RWGC_Cloud_Connection::update(
				array(
					'last_error' => $install['reason'],
				)
			);
			return array(
				'ok'       => false,
				'status'   => 'rejected',
				'revision' => $revision,
				'error'    => $install['reason'],
			);
		}

		RWGC_Cloud_Connection::update(
			array(
				'manifest_revision' => $install['revision'],
				'last_sync_at'      => gmdate( 'c' ),
				'last_error'        => '',
				'state'             => RWGC_Cloud_Connection::STATE_CONNECTED,
			)
		);

		return array(
			'ok'       => true,
			'status'   => 'updated',
			'revision' => $install['revision'],
			'error'    => '',
		);
	}

	/**
	 * @return array{ok: bool, error: string}
	 */
	public static function heartbeat() {
		if ( ! RWGC_Cloud_Connection::is_connected() ) {
			return array(
				'ok'    => false,
				'error' => 'not_connected',
			);
		}
		$creds = RWGC_Cloud_Credentials::get();
		if ( ! $creds ) {
			return array(
				'ok'    => false,
				'error' => 'missing_credentials',
			);
		}

		$body = array(
			'timestamp'    => gmdate( 'c' ),
			'core_version' => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
			'revision'     => (int) RWGC_Cloud_Connection::get()['manifest_revision'],
			'brand_hints'  => self::brand_hints(),
		);

		$response = RWGC_Cloud_Http::request(
			'POST',
			'/sites/' . rawurlencode( $creds['site_id'] ) . '/heartbeat',
			$body,
			array(),
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
			)
		);

		if ( ! $response['ok'] ) {
			return array(
				'ok'    => false,
				'error' => $response['error'] ? $response['error'] : 'heartbeat_failed',
			);
		}

		if ( isset( $response['body']['entitlements'] ) && is_array( $response['body']['entitlements'] ) && class_exists( 'RWGC_Cloud_Entitlement_Store', false ) ) {
			RWGC_Cloud_Entitlement_Store::put( $response['body']['entitlements'] );
		}

		RWGC_Cloud_Connection::update(
			array(
				'last_heartbeat_at' => gmdate( 'c' ),
			)
		);
		return array(
			'ok'    => true,
			'error' => '',
		);
	}

	/**
	 * Report capabilities + plugin versions to Cloud.
	 *
	 * @return array{ok: bool, error: string}
	 */
	public static function report_capabilities() {
		if ( ! RWGC_Cloud_Connection::is_connected() ) {
			return array(
				'ok'    => false,
				'error' => 'not_connected',
			);
		}
		$creds = RWGC_Cloud_Credentials::get();
		if ( ! $creds ) {
			return array(
				'ok'    => false,
				'error' => 'missing_credentials',
			);
		}

		$capabilities = array();
		if ( class_exists( 'RWGC_Platform_Capability_Registry', false ) ) {
			if ( method_exists( 'RWGC_Platform_Capability_Registry', 'export_for_report' ) ) {
				$capabilities = RWGC_Platform_Capability_Registry::export_for_report();
			} else {
				$capabilities = array_values( RWGC_Platform_Capability_Registry::all() );
			}
		}

		$body = array(
			'capabilities' => $capabilities,
			'plugins'      => RWGC_Cloud_Pairing::plugin_report(),
		);

		$response = RWGC_Cloud_Http::request(
			'POST',
			'/sites/' . rawurlencode( $creds['site_id'] ) . '/capabilities',
			$body,
			array(),
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
			)
		);

		return array(
			'ok'    => (bool) $response['ok'],
			'error' => $response['ok'] ? '' : ( $response['error'] ? $response['error'] : 'capabilities_failed' ),
		);
	}

	/**
	 * Full maintenance tick: sync → heartbeat → capabilities.
	 *
	 * @return array<string, mixed>
	 */
	public static function run_maintenance() {
		if ( ! RWGC_Cloud_Connection::is_connected() ) {
			return array( 'skipped' => true );
		}
		return array(
			'manifest'     => self::sync_manifest(),
			'heartbeat'    => self::heartbeat(),
			'capabilities' => self::report_capabilities(),
			'events'       => RWGC_Cloud_Event_Queue::flush(),
		);
	}

	/**
	 * Theme colour/font guesses for Cloud Brand Profile. Suggestions only — never auto-applied.
	 *
	 * @return array<string, string>
	 */
	public static function brand_hints() {
		$hints = array(
			'source' => 'wordpress_theme',
		);
		$bg = get_theme_mod( 'background_color', '' );
		if ( is_string( $bg ) && preg_match( '/^[0-9a-fA-F]{3,6}$/', $bg ) ) {
			$hints['color_surface'] = '#' . strtolower( $bg );
		}
		$header = get_theme_mod( 'header_textcolor', '' );
		if ( is_string( $header ) && preg_match( '/^[0-9a-fA-F]{3,6}$/', $header ) && 'blank' !== $header ) {
			$hints['color_text'] = '#' . strtolower( $header );
		}
		return $hints;
	}
}
