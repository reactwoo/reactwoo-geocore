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

		$known_light = (bool) preg_match( '/enqueue_google_fonts|introduction_viewed|dismissed_editor_notices|rwgc_get_widget_config/i', $raw );
		if ( $known_light ) {
			self::$is_bulk_config = false;
			return false;
		}

		// Unknown elementor_ajax action → heavy (Elementor 4.2+ batch names).
		self::$is_bulk_config = true;
		return true;
	}

	/**
	 * On-demand single-widget control fetch (not the bulk panel payload).
	 *
	 * @return bool
	 */
	public static function is_widget_hydrate_ajax() {
		if ( ! self::is_elementor_ajax() ) {
			return false;
		}
		return (bool) preg_match( '/rwgc_get_widget_config/i', self::actions_payload_string() );
	}

	/**
	 * Widget name requested by rwgc_get_widget_config.
	 *
	 * @return string
	 */
	public static function hydrate_widget_name() {
		if ( ! self::is_widget_hydrate_ajax() ) {
			return '';
		}
		foreach ( self::decoded_actions() as $row ) {
			if ( ! is_array( $row ) || ( $row['action'] ?? '' ) !== 'rwgc_get_widget_config' ) {
				continue;
			}
			$widget = isset( $row['data']['widget'] ) ? (string) $row['data']['widget'] : '';
			return function_exists( 'sanitize_key' ) ? sanitize_key( $widget ) : strtolower( $widget );
		}
		return '';
	}

	/**
	 * Skip Cloud / integrations boot (heavy panel payloads and single-widget hydrate).
	 *
	 * @return bool
	 */
	public static function is_constrained_elementor_ajax() {
		return self::is_heavy_elementor_ajax() || self::is_widget_hydrate_ajax();
	}

	/**
	 * Elementor-shaped success rows for an early widgets-config exit.
	 *
	 * Null when the batch includes document config, hydrate, or an unknown action.
	 *
	 * @return array<string, array{success: bool, code: int, data: mixed}>|null
	 */
	public static function early_widgets_config_responses() {
		$actions = self::decoded_actions();
		if ( array() === $actions ) {
			return null;
		}

		$empty_ok = array(
			'get_widgets_config'         => array(),
			'refresh_widgets_config'     => array(
				'widgets'    => array(),
				'categories' => array(),
			),
			'enqueue_google_fonts'       => array(),
			'introduction_viewed'        => array(),
			'dismissed_editor_notices'   => array(),
		);

		$has_widgets = false;
		$out         = array();
		foreach ( $actions as $id => $row ) {
			$name = '';
			if ( is_array( $row ) && isset( $row['action'] ) ) {
				$name = (string) $row['action'];
			} elseif ( is_string( $id ) ) {
				$name = (string) $id;
			}
			if ( ! isset( $empty_ok[ $name ] ) ) {
				return null;
			}
			if ( 'get_widgets_config' === $name || 'refresh_widgets_config' === $name ) {
				$has_widgets = true;
			}
			$out[ (string) $id ] = array(
				'success' => true,
				'code'    => 200,
				'data'    => $empty_ok[ $name ],
			);
		}

		return $has_widgets ? $out : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function decoded_actions() {
		$raw = self::actions_payload_string();
		if ( '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
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
