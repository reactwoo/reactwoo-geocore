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
		add_action( 'admin_init', array( __CLASS__, 'suppress_foreign_admin_notices' ), 9999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'render_frame_open' ), 5 );
		add_action( 'admin_footer', array( __CLASS__, 'render_frame_close' ), 1 );
	}

	/**
	 * Strip third-party admin_notices on ReactWoo Geo hub screens (Elementor licence, etc.).
	 *
	 * @return void
	 */
	public static function suppress_foreign_admin_notices() {
		if ( ! class_exists( 'RWGC_Admin_Platform', false ) || ! RWGC_Admin_Platform::is_hub_screen() ) {
			return;
		}
		if ( ! self::should_render() ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );

		/**
		 * ReactWoo Geo platform notices inside the app shell (Geo Core diagnostics, etc.).
		 */
		do_action( 'rwgc_platform_admin_notices_register' );
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

		if ( ! wp_style_is( 'rwgc-design-system', 'enqueued' ) ) {
			wp_enqueue_style(
				'rwgc-design-system',
				RWGC_URL . 'admin/css/rwgc-design-system.css',
				array(),
				RWGC_VERSION
			);
		}

		wp_enqueue_style(
			'rwgc-app-shell',
			RWGC_URL . 'admin/css/rwgc-app-shell.css',
			array( 'rwgc-design-system', 'rwgc-suite' ),
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
		/**
		 * @param bool $render Default true on hub screens (unified platform UX).
		 */
		$render = (bool) apply_filters( 'rwgc_app_shell_render', true );

		if ( in_array( $page, array( 'rwgcp-geocore-pro', 'rwgcp-settings' ), true ) ) {
			/**
			 * GeoCore Pro uses the shared shell; Pro tabs render via {@see rwgc_app_shell_context_links}.
			 *
			 * @param bool $render Filtered shell default.
			 */
			return (bool) apply_filters( 'rwgc_app_shell_render_on_pro', $render );
		}

		return $render;
	}

	/**
	 * Whether the app shell frame is open for the current request.
	 *
	 * @return bool
	 */
	public static function is_frame_active() {
		return self::$frame_open;
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

		$ctx      = RWGC_Admin_Route_Registry::get_current_context();
		$sections = RWGC_Admin_Route_Registry::get_sections();
		$routes   = RWGC_Admin_Route_Registry::get_routes_for_section( $ctx['section'] );

		echo '<div class="rwgc-app-shell" data-rwgc-section="' . esc_attr( $ctx['section'] ) . '" data-rwgc-module="' . esc_attr( $ctx['module'] ) . '">';
		echo '<div class="rwgc-app-shell__layout">';

		self::render_primary_nav( $sections, $ctx );
		echo '<div class="rwgc-app-shell__workspace">';
		self::render_topbar( $ctx );
		self::render_context_nav( $routes, $ctx );
		self::render_settings_subnav( $ctx );
		echo '<div class="rwgc-app-shell__platform-notices" role="complementary" aria-label="' . esc_attr__( 'Platform notices', 'reactwoo-geocore' ) . '">';
		do_action( 'rwgc_platform_admin_notices' );
		echo '</div>';
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
	 * Primary goal-based navigation (Overview, Targeting, Commerce, …).
	 *
	 * @param array<string, array<string, mixed>> $sections Sections.
	 * @param array<string, string>               $ctx      Current context.
	 * @return void
	 */
	private static function render_primary_nav( array $sections, array $ctx ) {
		echo '<aside class="rwgc-app-shell__module-nav" aria-label="' . esc_attr__( 'ReactWoo Geo', 'reactwoo-geocore' ) . '">';
		echo '<div class="rwgc-app-shell__brand">' . esc_html__( 'ReactWoo Geo', 'reactwoo-geocore' ) . '</div>';
		echo '<nav class="rwgc-app-shell__module-list">';

		foreach ( $sections as $section_id => $section ) {
			if ( ! RWGC_Admin_Route_Registry::is_section_visible( $section_id, $section ) ) {
				continue;
			}
			$url     = RWGC_Admin_Route_Registry::get_url( $section_id );
			$active  = ( $ctx['section'] === $section_id );
			$icon    = isset( $section['icon'] ) ? (string) $section['icon'] : 'dashicons-admin-generic';
			$classes = 'rwgc-app-shell__module-link' . ( $active ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '">';
			echo '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
			echo '<span class="rwgc-app-shell__module-label">' . esc_html( (string) ( $section['label'] ?? $section_id ) ) . '</span>';
			echo '</a>';
		}

		echo '</nav>';
		echo '</aside>';
	}

	/**
	 * Contextual tabs within the active section.
	 *
	 * @param array<string, array<string, mixed>> $routes Routes for active section.
	 * @param array<string, string>               $ctx    Current context.
	 * @return void
	 */
	private static function render_topbar( array $ctx ) {
		$section_label = $ctx['label'] ?? '';
		if ( class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			$sections = RWGC_Admin_Route_Registry::get_sections();
			$sid      = isset( $ctx['section'] ) ? (string) $ctx['section'] : '';
			if ( isset( $sections[ $sid ]['label'] ) ) {
				$section_label = (string) $sections[ $sid ]['label'];
			}
		}

		$user      = wp_get_current_user();
		$user_name = $user instanceof WP_User && $user->exists() ? $user->display_name : '';

		echo '<header class="rwgc-app-shell__topbar" role="banner">';
		echo '<div class="rwgc-app-shell__topbar-context">';
		echo '<span class="rwgc-app-shell__topbar-kicker">' . esc_html__( 'ReactWoo Geo', 'reactwoo-geocore' ) . '</span>';
		if ( '' !== $section_label ) {
			echo '<span class="rwgc-app-shell__topbar-title">' . esc_html( $section_label ) . '</span>';
		}
		echo '</div>';
		echo '<div class="rwgc-app-shell__topbar-actions">';

		$sync_label = __( 'Sync', 'reactwoo-geocore' );
		$sync_hint  = __( 'Platform sync status', 'reactwoo-geocore' );
		/**
		 * @param string $label Sync control label.
		 * @param array  $ctx   Current route context.
		 */
		$sync_label = (string) apply_filters( 'rwgc_app_shell_sync_label', $sync_label, $ctx );
		/**
		 * @param string $hint Sync tooltip / aria description.
		 * @param array  $ctx  Current route context.
		 */
		$sync_hint = (string) apply_filters( 'rwgc_app_shell_sync_hint', $sync_hint, $ctx );

		$sync_variant = 'neutral';
		$sync_url     = '';
		if ( class_exists( 'RWGC_Platform_Sync_Status', false ) ) {
			$snap         = RWGC_Platform_Sync_Status::get_snapshot();
			$sync_variant = isset( $snap['variant'] ) ? sanitize_key( (string) $snap['variant'] ) : 'neutral';
			$sync_url     = isset( $snap['url'] ) ? (string) $snap['url'] : '';
		}
		if ( ! in_array( $sync_variant, array( 'success', 'warning', 'neutral' ), true ) ) {
			$sync_variant = 'neutral';
		}

		$pill_class = 'rwgc-app-shell__topbar-pill rwgc-app-shell__topbar-pill--' . $sync_variant;
		if ( '' !== $sync_url ) {
			echo '<a class="' . esc_attr( $pill_class ) . '" href="' . esc_url( $sync_url ) . '" title="' . esc_attr( $sync_hint ) . '">';
		} else {
			echo '<span class="' . esc_attr( $pill_class ) . '" title="' . esc_attr( $sync_hint ) . '">';
		}
		echo '<span class="dashicons dashicons-update" aria-hidden="true"></span>';
		echo esc_html( $sync_label );
		echo '' !== $sync_url ? '</a>' : '</span>';

		$help_url = admin_url( 'admin.php?page=rwgc-getting-started' );
		/**
		 * @param string $url Help link URL.
		 * @param array  $ctx Current route context.
		 */
		$help_url = (string) apply_filters( 'rwgc_app_shell_help_url', $help_url, $ctx );
		echo '<a class="rwgc-app-shell__topbar-link" href="' . esc_url( $help_url ) . '">';
		echo '<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>';
		echo esc_html__( 'Help', 'reactwoo-geocore' );
		echo '</a>';

		if ( '' !== $user_name ) {
			echo '<span class="rwgc-app-shell__topbar-user">';
			echo '<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>';
			echo esc_html( $user_name );
			echo '</span>';
		}

		/**
		 * Extra topbar actions (integrations, notifications, etc.).
		 *
		 * @param array $ctx Current route context.
		 */
		do_action( 'rwgc_app_shell_topbar', $ctx );

		echo '</div></header>';
	}

	/**
	 * Contextual tabs within the active section.
	 *
	 * @param array<string, array<string, mixed>> $routes Routes for active section.
	 * @param array<string, string>               $ctx    Current context.
	 * @return void
	 */
	private static function render_context_nav( array $routes, array $ctx ) {
		if ( 'settings' === ( $ctx['section'] ?? '' ) && class_exists( 'RWGC_Admin_Settings_Nav', false ) ) {
			self::render_settings_provider_nav( $ctx );
			return;
		}

		/**
		 * Extra horizontal links when a screen uses in-page tabs (e.g. GeoCore Pro).
		 *
		 * @param array<int, array{url:string,label:string,active?:bool}> $links
		 * @param array<string, string>                                    $ctx
		 */
		$extra = apply_filters( 'rwgc_app_shell_context_links', array(), $ctx );

		$tabs = array();

		foreach ( $routes as $slug => $route ) {
			$tabs[] = array(
				'url'    => admin_url( 'admin.php?page=' . rawurlencode( (string) $slug ) ),
				'label'  => (string) ( $route['label'] ?? $slug ),
				'active' => ( $ctx['menu_slug'] === $slug ),
			);
		}

		foreach ( $extra as $link ) {
			if ( empty( $link['url'] ) || empty( $link['label'] ) ) {
				continue;
			}
			$tabs[] = array(
				'url'    => (string) $link['url'],
				'label'  => (string) $link['label'],
				'active' => ! empty( $link['active'] ),
			);
		}

		if ( count( $tabs ) < 2 ) {
			return;
		}

		echo '<nav class="rwgc-app-shell__section-nav" aria-label="' . esc_attr__( 'Section navigation', 'reactwoo-geocore' ) . '">';
		echo '<div class="rwgc-app-shell__section-scroll">';

		foreach ( $tabs as $tab ) {
			$classes = 'rwgc-app-shell__section-link' . ( ! empty( $tab['active'] ) ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( (string) $tab['url'] ) . '">';
			echo esc_html( (string) $tab['label'] );
			echo '</a>';
		}

		echo '</div></nav>';
	}

	/**
	 * Settings section: one top tab per satellite (Geo Core, Elementor, …).
	 *
	 * @param array<string, string> $ctx Current context.
	 * @return void
	 */
	private static function render_settings_provider_nav( array $ctx ) {
		$current_slug = isset( $ctx['menu_slug'] ) ? sanitize_key( (string) $ctx['menu_slug'] ) : '';
		$tabs         = array(
			array(
				'url'    => admin_url( 'admin.php?page=rwgc-settings-hub' ),
				'label'  => __( 'Settings home', 'reactwoo-geocore' ),
				'active' => ( 'rwgc-settings-hub' === $current_slug ),
			),
		);

		foreach ( RWGC_Admin_Settings_Nav::get_active_providers() as $pid => $row ) {
			$slug = isset( $row['default_slug'] ) ? (string) $row['default_slug'] : '';
			if ( '' === $slug ) {
				continue;
			}
			$active = ( RWGC_Admin_Settings_Nav::get_current_provider( $ctx ) === $pid );
			$tabs[] = array(
				'url'    => admin_url( 'admin.php?page=' . rawurlencode( $slug ) ),
				'label'  => (string) ( $row['label'] ?? $pid ),
				'active' => $active,
			);
		}

		if ( count( $tabs ) < 2 ) {
			return;
		}

		echo '<nav class="rwgc-app-shell__section-nav" aria-label="' . esc_attr__( 'Settings providers', 'reactwoo-geocore' ) . '">';
		echo '<div class="rwgc-app-shell__section-scroll">';
		foreach ( $tabs as $tab ) {
			$classes = 'rwgc-app-shell__section-link' . ( ! empty( $tab['active'] ) ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( (string) $tab['url'] ) . '">';
			echo esc_html( (string) $tab['label'] );
			echo '</a>';
		}
		echo '</div></nav>';
	}

	/**
	 * Secondary tabs within the active settings provider (license first).
	 *
	 * @param array<string, string> $ctx Current context.
	 * @return void
	 */
	private static function render_settings_subnav( array $ctx ) {
		if ( 'settings' !== ( $ctx['section'] ?? '' ) || ! class_exists( 'RWGC_Admin_Settings_Nav', false ) ) {
			return;
		}

		$current_slug = isset( $ctx['menu_slug'] ) ? sanitize_key( (string) $ctx['menu_slug'] ) : '';
		if ( '' === $current_slug || 'rwgc-settings-hub' === $current_slug ) {
			return;
		}

		$provider_id = RWGC_Admin_Settings_Nav::get_current_provider( $ctx );
		if ( '' === $provider_id ) {
			return;
		}

		$routes = RWGC_Admin_Settings_Nav::get_provider_routes( $provider_id );
		if ( count( $routes ) < 2 ) {
			return;
		}

		echo '<nav class="rwgc-app-shell__settings-subnav" aria-label="' . esc_attr__( 'Provider settings', 'reactwoo-geocore' ) . '">';
		echo '<div class="rwgc-app-shell__settings-subnav-scroll">';
		foreach ( $routes as $slug => $route ) {
			$classes = 'rwgc-app-shell__settings-subnav-link' . ( $current_slug === $slug ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . rawurlencode( (string) $slug ) ) ) . '">';
			echo esc_html( (string) ( $route['label'] ?? $slug ) );
			echo '</a>';
		}
		echo '</div></nav>';
	}
}
