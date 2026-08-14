<?php
/**
 * Cloud connection state.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * States: disconnected | pairing | connected | error.
 */
final class RWGC_Cloud_Connection {

	const OPTION = 'rwgc_cloud_connection';

	const STATE_DISCONNECTED = 'disconnected';
	const STATE_PAIRING      = 'pairing';
	const STATE_CONNECTED    = 'connected';
	const STATE_ERROR        = 'error';

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$defaults = array(
			'state'            => self::STATE_DISCONNECTED,
			'site_id'          => '',
			'manifest_revision'=> 0,
			'last_sync_at'     => '',
			'last_heartbeat_at'=> '',
			'last_error'       => '',
			'management_mode'  => 'local',
			'paired_at'        => '',
		);
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * @param array<string, mixed> $patch Patch.
	 * @return array<string, mixed>
	 */
	public static function update( array $patch ) {
		$current = self::get();
		$next    = array_merge( $current, $patch );
		update_option( self::OPTION, $next, false );
		return $next;
	}

	/**
	 * @return string
	 */
	public static function state() {
		$row = self::get();
		return (string) $row['state'];
	}

	/**
	 * @return bool
	 */
	public static function is_connected() {
		return self::STATE_CONNECTED === self::state() && RWGC_Cloud_Credentials::has();
	}

	/**
	 * Disconnect credentials; retain cached manifests (WP content untouched).
	 *
	 * @return void
	 */
	public static function disconnect() {
		RWGC_Cloud_Credentials::clear();
		if ( class_exists( 'RWGC_Cloud_Entitlement_Store', false ) ) {
			RWGC_Cloud_Entitlement_Store::clear();
		}
		self::update(
			array(
				'state'           => self::STATE_DISCONNECTED,
				'site_id'         => '',
				'last_error'      => '',
				'management_mode' => 'local',
			)
		);
	}
}
