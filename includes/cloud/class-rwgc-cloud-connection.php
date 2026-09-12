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
			'site_url'         => '',
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
		return self::STATE_CONNECTED === self::state()
			&& RWGC_Cloud_Credentials::has()
			&& self::paired_site_matches();
	}

	/**
	 * Whether stored pairing URL matches this WordPress home URL.
	 *
	 * Empty site_url (pairings stored before this field existed) is treated as
	 * a match so existing production connections keep working until the next pair.
	 *
	 * @return bool
	 */
	public static function paired_site_matches() {
		$row     = self::get();
		$stored  = isset( $row['site_url'] ) ? (string) $row['site_url'] : '';
		if ( '' === $stored ) {
			return true;
		}
		$current = function_exists( 'home_url' ) ? home_url( '/' ) : '';
		if ( '' === $current ) {
			return true;
		}
		return self::normalize_site_url( $stored ) === self::normalize_site_url( $current );
	}

	/**
	 * Compare hosts + path; ignore scheme and www.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_site_url( $url ) {
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( (string) $url ) : parse_url( (string) $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$host = isset( $parts['host'] ) ? strtolower( rtrim( (string) $parts['host'], '.' ) ) : '';
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}
		$path = isset( $parts['path'] ) ? strtolower( (string) $parts['path'] ) : '';
		if ( function_exists( 'untrailingslashit' ) ) {
			$path = untrailingslashit( $path );
		} else {
			$path = rtrim( $path, '/\\' );
		}
		if ( '/' === $path ) {
			$path = '';
		}
		return $host . $path;
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
				'site_url'        => '',
				'last_error'      => '',
				'management_mode' => 'local',
			)
		);
	}
}
