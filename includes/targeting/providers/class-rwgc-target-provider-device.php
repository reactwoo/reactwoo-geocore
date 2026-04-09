<?php
/**
 * Device / browser hints (lightweight; not a full UA database).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic device_type / OS / browser strings for targeting.
 */
class RWGC_Target_Provider_Device implements RWGC_Target_Provider_Interface {

	/**
	 * @inheritDoc
	 */
	public function get_provider_key() {
		return 'device';
	}

	/**
	 * @inheritDoc
	 */
	public function is_available() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function register_targets( RWGC_Target_Registry $registry ) {
		$ops = array( 'is', 'is_not', 'in', 'not_in' );
		foreach ( array( 'device_type', 'operating_system', 'browser' ) as $key ) {
			$registry->register_target_type(
				array(
					'key'           => $key,
					'label'         => $this->label_for( $key ),
					'group'         => 'device',
					'description'   => __( 'Derived from the User-Agent (best-effort).', 'reactwoo-geocore' ),
					'operators'     => $ops,
					'value_mode'    => 'single',
					'provider'      => $this->get_provider_key(),
					'supports_simulation' => true,
				)
			);
		}
	}

	/**
	 * @param string $key Key.
	 * @return string
	 */
	private function label_for( $key ) {
		$labels = array(
			'device_type'       => __( 'Device type', 'reactwoo-geocore' ),
			'operating_system'  => __( 'Operating system', 'reactwoo-geocore' ),
			'browser'           => __( 'Browser', 'reactwoo-geocore' ),
		);
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return array(
			'device_type'      => self::detect_device_type( $ua ),
			'operating_system' => self::detect_os( $ua ),
			'browser'          => self::detect_browser( $ua ),
		);
	}

	/**
	 * @param string $ua User agent.
	 * @return string mobile|tablet|desktop
	 */
	private static function detect_device_type( $ua ) {
		$ua = strtolower( $ua );
		if ( strpos( $ua, 'ipad' ) !== false || ( strpos( $ua, 'tablet' ) !== false ) ) {
			return 'tablet';
		}
		if ( function_exists( 'wp_is_mobile' ) && wp_is_mobile() ) {
			return 'mobile';
		}
		return 'desktop';
	}

	/**
	 * @param string $ua User agent.
	 * @return string
	 */
	private static function detect_os( $ua ) {
		$u = strtolower( $ua );
		if ( strpos( $u, 'windows' ) !== false ) {
			return 'windows';
		}
		if ( strpos( $u, 'android' ) !== false ) {
			return 'android';
		}
		if ( strpos( $u, 'iphone' ) !== false || strpos( $u, 'ipad' ) !== false || strpos( $u, 'ios' ) !== false ) {
			return 'ios';
		}
		if ( strpos( $u, 'mac os' ) !== false || strpos( $u, 'macintosh' ) !== false ) {
			return 'macos';
		}
		if ( strpos( $u, 'linux' ) !== false ) {
			return 'linux';
		}
		return 'unknown';
	}

	/**
	 * @param string $ua User agent.
	 * @return string
	 */
	private static function detect_browser( $ua ) {
		$u = strtolower( $ua );
		if ( strpos( $u, 'edg/' ) !== false ) {
			return 'edge';
		}
		if ( strpos( $u, 'chrome' ) !== false && strpos( $u, 'chromium' ) === false ) {
			return 'chrome';
		}
		if ( strpos( $u, 'firefox' ) !== false ) {
			return 'firefox';
		}
		if ( strpos( $u, 'safari' ) !== false && strpos( $u, 'chrome' ) === false ) {
			return 'safari';
		}
		return 'other';
	}

	/**
	 * @inheritDoc
	 */
	public function get_admin_status() {
		return array(
			'label'  => __( 'Device (User-Agent)', 'reactwoo-geocore' ),
			'state'  => 'ok',
			'detail' => __( 'Lightweight UA parsing; suitable for coarse targeting.', 'reactwoo-geocore' ),
		);
	}
}
