<?php
/**
 * Unified ReactWoo Geo admin app shell (module + section navigation).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders in-app navigation around existing admin page callbacks.
 */
class RWGC_Admin_App_Shell {

	/**
	 * @var bool
	 */
	private static $frame_open = false;

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'render_frame_open' ), 5 );
		add_action( 'admin_footer', array( __CLASS__, 'render_frame_close' ), 1 );
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! class_exists( 'RWGC_Admin_Platform', false ) || ! RWGC_Admin_Platform::is_hub_screen( $hook ) ) {
			return;
		}
		if ( ! self::should_render() ) {
			return;
		}

		wp_enqueue_style(
			'rwgc-app-shell',
			RWGC_URL . 'admin/css/rwgc-app-shell.css',
			array( 'rwgc-suite' ),
			RWGC_VERSION
		);
		wp_enqueue_script(
			'rwgc-app-shell',
			RWGC_URL . 'admin/js/rwgc-app-shell.js',
			array(),
			RWGC_VERSION,
			true
		);
	}

	/**
	 * @return bool
	 */
	public static function should_render() {
		if ( ! is_admin() || ! class_exists( 'RWGC_Admin_Platform', false ) ) {
			return false;
		}
		if ( ! RWGC_Admin_Platform::is_hub_screen() ) {
			return false;
		}

		$page = '';
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( in_array( $page, array( 'rwgcp-geocore-pro', 'rwgcp-settings' ), true ) ) {
			/**
			 * GeoCore Pro ships its own vertical nav until integrated into the shared shell.
			 *
			 * @param bool $render Default false on Pro pages.
			 */
			return (bool) apply_filters( 'rwgc_app_shell_render_on_pro', false );
		}

		/**
		 * @param bool $render Default true on hub screens.
		 */
		// Off by default until shell layout is validated on all admin screens.
		return (bool) apply_filters( 'rwgc_app_shell_render', false );
	}

	/**
	 * @return void
	 */
	public static function render_frame_open() {
		if ( ! self::should_render() || self::$frame_open ) {
			return;
		}
		if ( ! class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return;
		}
		self::$frame_open = true;

		$ctx     = RWGC_Admin_Route_Registry::get_current_context();
		$modules = RWGC_Admin_Route_Registry::get_modules();
		$routes  = RWGC_Admin_Route_Registry::get_routes_for_module( $ctx['module'] );

		echo '<div class="rwgc-app-shell" data-rwgc-module="' . esc_attr( $ctx['module'] ) . '">';
		echo '<div class="rwgc-app-shell__layout">';

		self::render_module_nav( $modules, $ctx );
		echo '<div class="rwgc-app-shell__workspace">';
		self::render_section_nav( $routes, $ctx );
		echo '<div class="rwgc-app-shell__content">';
	}

	/**
	 * @return void
	 */
	public static function render_frame_close() {
		if ( ! self::$frame_open ) {
			return;
		}
		echo '</div></div></div></div>';
		self::$frame_open = false;
	}

	/**
	 * @param array<string, array<string, mixed>> $modules Modules.
	 * @param array<string, string>               $ctx     Current context.
	 * @return void
	 */
	private static function render_module_nav( array $modules, array $ctx ) {
		echo '<aside class="rwgc-app-shell__module-nav" aria-label="' . esc_attr__( 'ReactWoo Geo modules', 'reactwoo-geocore' ) . '">';
		echo '<div class="rwgc-app-shell__brand">' . esc_html__( 'ReactWoo Geo', 'reactwoo-geocore' ) . '</div>';
		echo '<nav class="rwgc-app-shell__module-list">';

		foreach ( $modules as $module_id => $module ) {
			if ( ! RWGC_Admin_Route_Registry::is_module_visible( $module_id, $module ) ) {
				continue;
			}
			$url     = RWGC_Admin_Route_Registry::get_url( $module_id );
			$active  = ( $ctx['module'] === $module_id );
			$icon    = isset( $module['icon'] ) ? (string) $module['icon'] : 'dashicons-admin-generic';
			$classes = 'rwgc-app-shell__module-link' . ( $active ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '">';
			echo '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
			echo '<span class="rwgc-app-shell__module-label">' . esc_html( (string) ( $module['label'] ?? $module_id ) ) . '</span>';
			echo '</a>';
		}

		echo '</nav>';
		echo '</aside>';
	}

	/**
	 * @param array<string, array<string, mixed>> $routes Routes for active module.
	 * @param array<string, string>               $ctx    Current context.
	 * @return void
	 */
	private static function render_section_nav( array $routes, array $ctx ) {
		if ( count( $routes ) < 2 ) {
			return;
		}

		echo '<nav class="rwgc-app-shell__section-nav" aria-label="' . esc_attr__( 'Section navigation', 'reactwoo-geocore' ) . '">';
		echo '<div class="rwgc-app-shell__section-scroll">';

		foreach ( $routes as $slug => $route ) {
			$url     = admin_url( 'admin.php?page=' . rawurlencode( (string) $slug ) );
			$active  = ( $ctx['menu_slug'] === $slug );
			$classes = 'rwgc-app-shell__section-link' . ( $active ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '">';
			echo esc_html( (string) ( $route['label'] ?? $slug ) );
			echo '</a>';
		}

		echo '</div></nav>';
	}
}
