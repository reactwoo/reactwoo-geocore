<?php
/**
 * Elementor editor AJAX context helpers (widgets-config LiteSpeed safety).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects heavy Elementor admin-ajax payloads so control stacks stay small.
 *
 * Mirrors the WHMCS Bridge pattern: during get_widgets_config do not inject
 * Geo Visibility / Experience Slot control trees into every widget stack.
 */
class RWGC_Elementor_Ajax {

	/**
	 * @var bool|null
	 */
	private static $is_elementor_ajax = null;

	/**
	 * @var bool|null
	 */
	private static $is_bulk_config = null;

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
	 * True while Elementor builds the Elements panel / document config payload.
	 *
	 * Omits Geo Visibility / Experience Slot sections from bulk stacks. Single-widget
	 * `editor_get_widget_config` alone keeps the full editor UI.
	 *
	 * @return bool
	 */
	public static function is_bulk_widgets_config() {
		if ( null !== self::$is_bulk_config ) {
			return self::$is_bulk_config;
		}

		self::$is_bulk_config = false;
		if ( ! self::is_elementor_ajax() ) {
			return false;
		}

		$raw = self::actions_payload_string();
		if ( '' === $raw ) {
			// Fail-safe: opaque elementor_ajax → light path (LiteSpeed 503 mitigation).
			self::$is_bulk_config = true;
			return true;
		}

		$has_bulk = (bool) preg_match( '/get_widgets_config|get_document_config|refresh_widgets_config/i', $raw );
		if ( $has_bulk ) {
			self::$is_bulk_config = true;
			return true;
		}

		$single_only = (bool) preg_match( '/editor_get_widget_config/i', $raw );
		if ( $single_only ) {
			self::$is_bulk_config = false;
			return false;
		}

		// Unknown elementor_ajax action → heavy (Elementor 4.2+ batch names).
		self::$is_bulk_config = true;
		return true;
	}

	/**
	 * Alias used by control builders (filterable).
	 *
	 * @return bool
	 */
	public static function is_heavy_elementor_ajax() {
		$heavy = self::is_bulk_widgets_config();
		/**
		 * Filter whether Geo Core should omit large option lists from Elementor stacks.
		 *
		 * @param bool $heavy Heavy / bulk widgets-config path.
		 */
		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'rwgc_heavy_elementor_ajax', $heavy );
		}
		return (bool) $heavy;
	}

	/**
	 * @return string
	 */
	private static function actions_payload_string() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_REQUEST['actions'] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$actions = wp_unslash( $_REQUEST['actions'] );
		if ( is_array( $actions ) ) {
			$json = wp_json_encode( $actions );
			return is_string( $json ) ? $json : '';
		}
		return is_string( $actions ) ? $actions : '';
	}

	/**
	 * Reset request caches (tests).
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$is_elementor_ajax = null;
		self::$is_bulk_config    = null;
	}
}
