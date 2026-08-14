<?php
/**
 * Elementor editor AJAX context detection.
 *
 * Geo Core does not classify Elementor AJAX actions as heavy or light, does not
 * replace Elementor AJAX actions, and does not alter another plugin's
 * registration. This helper only answers "is this an Elementor editor request",
 * which Geo Core uses to defer its own admin-screen bootstrap.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only Elementor AJAX context detection.
 */
class RWGC_Elementor_Ajax {

	/**
	 * @var bool|null
	 */
	private static $is_elementor_ajax = null;

	/**
	 * True for any Elementor editor admin-ajax round-trip.
	 *
	 * @return bool
	 */
	public static function is_elementor_ajax() {
		if ( null !== self::$is_elementor_ajax ) {
			return self::$is_elementor_ajax;
		}

		self::$is_elementor_ajax = false;
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only context detect.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		self::$is_elementor_ajax = ( 'elementor_ajax' === $action );
		return self::$is_elementor_ajax;
	}

	/**
	 * Reset request caches (tests).
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$is_elementor_ajax = null;
	}
}
