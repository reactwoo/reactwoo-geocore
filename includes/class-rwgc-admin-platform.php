<?php
/**
 * Geo Core admin platform shell (free hub) — menu parent, ordering, branding.
 *
 * Unified wp-admin entry **ReactWoo Geo**; satellites register hidden hub pages.
 * In-app navigation is handled by {@see RWGC_Admin_App_Shell}.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central admin platform helpers for the Geo Core hub menu.
 */
class RWGC_Admin_Platform {

	/**
	 * Top-level / parent menu slug (unchanged for bookmarks and satellite parent).
	 */
	const MENU_PARENT = 'rwgc-dashboard';

	/**
	 * Sidebar menu label for the free hub (not "ReactWoo Geo").
	 */
	const MENU_LABEL = 'ReactWoo Geo';

	/**
	 * Hub pages removed from the wp-admin flyout (capability + hook for direct URL access).
	 *
	 * @var array<string, array{menu_title:string,capability:string,hook:string}>
	 */
	private static $collapsed_page_registry = array();

	/**
	 * Shell-only hub pages (no wp-admin submenu row; bound via {@see bind_shell_only_hub_pages()}).
	 *
	 * @var array<string, array{menu_title:string,capability:string,hook:string,callback:callable}>
	 */
	private static $shell_only_page_registry = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'bind_shell_only_hub_pages' ), 99 );
		add_action( 'admin_menu', array( __CLASS__, 'finalize_hub_submenu' ), 9999 );
		add_action( 'admin_menu', array( __CLASS__, 'ensure_collapsed_hub_page_access' ), 10000 );
		add_filter( 'rwgc_app_route_register_wp_submenu', array( __CLASS__, 'filter_app_route_register_wp_submenu' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_collapsed_menu_styles' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_collapsed_submenu_fallback_css' ), 99 );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_filter( 'rwgc_admin_visible_submenu_slugs', array( __CLASS__, 'filter_visible_setup_wizard_slug' ), 10, 2 );
	}

	/**
	 * Default shell-only registration; Setup wizard may stay in the wp-admin flyout.
	 *
	 * @param bool                 $register_wp_submenu Whether to call add_submenu_page.
	 * @param array<string, mixed> $args                Route args.
	 * @return bool
	 */
	public static function filter_app_route_register_wp_submenu( $register_wp_submenu, $args ) {
		unset( $register_wp_submenu );
		$slug = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( 'rwgc-getting-started' === $slug ) {
			return true;
		}
		return false;
	}

	/**
	 * Register a hub admin screen without adding a wp-admin submenu row (app shell only).
	 *
	 * @param array<string, mixed> $args page_title, menu_title, capability, menu_slug, callback.
	 * @return string|false Hook suffix when bound; true when queued for bind.
	 */
	public static function register_shell_only_page( array $args ) {
		$slug = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug || empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}

		// Top-level hub screen is registered via add_menu_page(); route metadata only.
		if ( $slug === self::menu_parent() ) {
			return false;
		}

		$default_cap = 'manage_options';
		if ( class_exists( 'RWGC_Admin', false ) ) {
			$default_cap = RWGC_Admin::required_capability();
		}

		$parent = self::menu_parent();
		$cap    = isset( $args['capability'] ) ? (string) $args['capability'] : $default_cap;
		$hook   = function_exists( 'get_plugin_page_hookname' )
			? (string) get_plugin_page_hookname( $slug, $parent )
			: $parent . '_page_' . $slug;

		self::$shell_only_page_registry[ $slug ] = array(
			'menu_title' => isset( $args['menu_title'] ) ? (string) $args['menu_title'] : ( isset( $args['label'] ) ? (string) $args['label'] : $slug ),
			'capability' => $cap,
			'hook'       => $hook,
			'callback'   => $args['callback'],
		);

		if ( did_action( 'admin_menu' ) ) {
			return self::bind_shell_only_page( $slug );
		}

		return $hook;
	}

	/**
	 * Bind queued shell-only pages to WordPress admin hooks (before flyout collapse).
	 *
	 * @return void
	 */
	public static function bind_shell_only_hub_pages() {
		foreach ( array_keys( self::$shell_only_page_registry ) as $slug ) {
			self::bind_shell_only_page( $slug );
		}
	}

	/**
	 * @param string $slug Menu slug.
	 * @return string|false Hook suffix.
	 */
	private static function bind_shell_only_page( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( '' === $slug || ! isset( self::$shell_only_page_registry[ $slug ] ) ) {
			return false;
		}

		$meta     = self::$shell_only_page_registry[ $slug ];
		$callback = $meta['callback'];
		if ( ! is_callable( $callback ) ) {
			return false;
		}

		$parent = self::menu_parent();
		$hook   = isset( $meta['hook'] ) ? (string) $meta['hook'] : '';
		if ( '' === $hook && function_exists( 'get_plugin_page_hookname' ) ) {
			$hook = (string) get_plugin_page_hookname( $slug, $parent );
		}
		if ( '' === $hook ) {
			return false;
		}

		self::register_hub_page_globals( $slug, $parent, $hook );

		if ( ! has_action( $hook, $callback ) ) {
			add_action( $hook, $callback );
		}

		self::$shell_only_page_registry[ $slug ]['hook'] = $hook;

		/**
		 * Fires after a shell-only hub page is bound (no wp-admin submenu row).
		 *
		 * @param string               $hook     Admin page hook suffix.
		 * @param string               $slug     Page slug.
		 * @param array<string, mixed> $meta     Registration meta.
		 */
		do_action( 'rwgc_admin_shell_page_bound', $hook, $slug, self::$shell_only_page_registry[ $slug ] );

		return $hook;
	}

	/**
	 * @param string $slug   Page slug.
	 * @param string $parent Parent menu slug.
	 * @param string $hook   Primary hook suffix.
	 * @return void
	 */
	private static function register_hub_page_globals( $slug, $parent, $hook ) {
		global $_parent_pages, $_registered_pages;

		$_parent_pages[ $slug ] = $parent;

		$hooks = array( (string) $hook );
		if ( function_exists( 'get_plugin_page_hookname' ) ) {
			$hooks[] = (string) get_plugin_page_hookname( $slug, $parent );
			$hooks[] = (string) get_plugin_page_hookname( $slug, '' );
			$hooks[] = (string) get_plugin_page_hookname( $slug, 'admin.php' );
		}
		foreach ( array_unique( array_filter( $hooks ) ) as $hookname ) {
			$_registered_pages[ $hookname ] = true;
		}
	}

	/**
	 * Keep Setup wizard reachable from wp-admin when the hub flyout is collapsed.
	 *
	 * @param array<int, string> $keep_slugs Menu slugs to keep visible.
	 * @param string             $parent   Parent slug.
	 * @return array<int, string>
	 */
	public static function filter_visible_setup_wizard_slug( $keep_slugs, $parent ) {
		unset( $parent );
		$keep_slugs   = is_array( $keep_slugs ) ? $keep_slugs : array();
		$keep_slugs[] = 'rwgc-getting-started';
		return array_values( array_unique( array_map( 'strval', $keep_slugs ) ) );
	}

	/**
	 * Parent slug for satellite add_submenu_page() calls.
	 *
	 * @return string
	 */
	public static function menu_parent() {
		/**
		 * Filter the Geo Core hub parent menu slug.
		 *
		 * @param string $parent Default rwgc-dashboard.
		 */
		return (string) apply_filters( 'rwgc_admin_menu_parent', self::MENU_PARENT );
	}

	/**
	 * Top-level sidebar label (ReactWoo Geo).
	 *
	 * @return string
	 */
	public static function menu_label() {
		/**
		 * Filter the Geo suite hub sidebar menu title.
		 *
		 * @param string $label Default "ReactWoo Geo".
		 */
		return (string) apply_filters( 'rwgc_admin_menu_label', self::MENU_LABEL );
	}

	/**
	 * Whether wp-admin should show only the top-level ReactWoo Geo item (no flyout).
	 *
	 * @return bool
	 */
	public static function is_sidebar_collapsed() {
		/**
		 * @param bool $collapsed Default true — wp-admin shows ReactWoo Geo entry only.
		 */
		return (bool) apply_filters( 'rwgc_admin_sidebar_collapsed', true );
	}

	/**
	 * Collapse flyout or restore ordered submenu depending on {@see is_sidebar_collapsed()}.
	 *
	 * @return void
	 */
	public static function finalize_hub_submenu() {
		if ( self::is_sidebar_collapsed() ) {
			self::collapse_hub_submenu();
			return;
		}
		self::reorder_submenu();
	}

	/**
	 * Whether the current request is under the Geo Core hub menu.
	 *
	 * @param string|null $hook_suffix Optional admin_enqueue_scripts hook.
	 * @return bool
	 */
	public static function is_hub_screen( $hook_suffix = null ) {
		if ( is_string( $hook_suffix ) && '' !== $hook_suffix ) {
			if ( false !== strpos( $hook_suffix, 'rwgc-' ) ) {
				return true;
			}
			if ( false !== strpos( $hook_suffix, self::MENU_PARENT ) ) {
				return true;
			}
		}

		$page = '';
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' !== $page && self::is_hub_page_slug( $page ) ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && is_string( $screen->id ) ) {
			if ( false !== strpos( $screen->id, 'rwgc-' ) || false !== strpos( $screen->id, self::MENU_PARENT ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Known page slugs that belong to the Geo Core hub (core + satellites).
	 *
	 * @param string $page Sanitized page slug.
	 * @return bool
	 */
	public static function is_hub_page_slug( $page ) {
		$page = sanitize_key( (string) $page );
		if ( '' === $page ) {
			return false;
		}
		if ( 0 === strpos( $page, 'rwgc-' ) ) {
			return true;
		}
		$prefixes = array( 'rwgcm-', 'rwga-', 'rwgo-', 'rwgcp-', 'geo-elementor', 'elementor-geo-popup', 'geo-content', 'egp-', 'geo-templates' );
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $page, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Register a submenu under the Geo Core hub (wrapper for add_submenu_page).
	 *
	 * @param array<string, mixed> $args page_title, menu_title, capability, menu_slug, callback, optional position, group (core|extension).
	 * @return string|false
	 */
	public static function register_submenu( array $args ) {
		$parent = self::menu_parent();
		$page_title = isset( $args['page_title'] ) ? (string) $args['page_title'] : '';
		$menu_title = isset( $args['menu_title'] ) ? (string) $args['menu_title'] : '';
		$cap        = isset( $args['capability'] ) ? (string) $args['capability'] : 'manage_options';
		$slug       = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		$callback   = isset( $args['callback'] ) ? $args['callback'] : '';
		$position   = isset( $args['position'] ) ? $args['position'] : null;

		if ( '' === $slug || ! is_callable( $callback ) ) {
			return false;
		}

		$hook = add_submenu_page( $parent, $page_title, $menu_title, $cap, $slug, $callback, $position );

		/**
		 * Fires after a submenu is registered under the Geo Core hub.
		 *
		 * @param string               $hook     Submenu hook suffix.
		 * @param string               $slug     Page slug.
		 * @param array<string, mixed> $args     Registration args.
		 */
		do_action( 'rwgc_admin_submenu_registered', $hook, $slug, $args );

		return $hook;
	}

	/**
	 * Slugs that may remain in the wp-admin flyout when the hub is collapsed.
	 *
	 * @return array<int, string>
	 */
	public static function get_visible_submenu_slugs() {
		$parent = self::menu_parent();
		/**
		 * Slugs to keep in the wp-admin flyout when collapsed (default: hide all detail screens).
		 *
		 * @param array<int, string> $keep_slugs Menu slugs to keep visible.
		 * @param string             $parent   Parent slug.
		 */
		$keep_slugs = apply_filters( 'rwgc_admin_visible_submenu_slugs', array(), $parent );
		$keep_map   = array();
		foreach ( (array) $keep_slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug ) {
				$keep_map[ $slug ] = true;
			}
		}
		return array_keys( $keep_map );
	}

	/**
	 * Collapsed hub flyout: remove detail submenu rows; keep allowlisted slugs only.
	 *
	 * Direct ?page= URLs stay accessible via restored submenu rows (hidden by CSS)
	 * and {@see ensure_collapsed_hub_page_access()}.
	 *
	 * @return void
	 */
	public static function collapse_hub_submenu() {
		global $submenu;

		$parent = self::menu_parent();
		if ( ! isset( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$keep_map = array_fill_keys( self::get_visible_submenu_slugs(), true );

		foreach ( $submenu[ $parent ] as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry[2] ) ) {
				continue;
			}
			$slug = sanitize_key( (string) $entry[2] );
			if ( '' === $slug || isset( $keep_map[ $slug ] ) ) {
				continue;
			}

			$cap  = isset( $entry[1] ) ? (string) $entry[1] : 'manage_options';
			$hook = function_exists( 'get_plugin_page_hookname' )
				? (string) get_plugin_page_hookname( $slug, $parent )
				: $parent . '_page_' . $slug;

			self::$collapsed_page_registry[ $slug ] = array(
				'menu_title' => isset( $entry[0] ) ? (string) $entry[0] : '',
				'capability' => $cap,
				'hook'       => $hook,
			);

			remove_submenu_page( $parent, $slug );
		}
	}

	/**
	 * Restore hub submenu rows removed from the flyout so WordPress grants ?page= access.
	 *
	 * Rows stay hidden via {@see enqueue_collapsed_menu_styles()} (entire flyout is display:none).
	 *
	 * @return void
	 */
	public static function ensure_collapsed_hub_page_access() {
		if ( ! is_admin() ) {
			return;
		}

		$access_registry = self::$shell_only_page_registry;
		if ( self::is_sidebar_collapsed() ) {
			$access_registry = array_merge( $access_registry, self::$collapsed_page_registry );
		}
		if ( empty( $access_registry ) ) {
			return;
		}

		global $submenu, $parent_file;

		$parent = self::menu_parent();
		if ( ! isset( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			$submenu[ $parent ] = array();
		}

		$existing = array();
		foreach ( $submenu[ $parent ] as $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) ) {
				$existing[ sanitize_key( (string) $entry[2] ) ] = true;
			}
		}

		foreach ( $access_registry as $slug => $meta ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}

			$hook = isset( $meta['hook'] ) ? (string) $meta['hook'] : '';
			if ( '' === $hook && function_exists( 'get_plugin_page_hookname' ) ) {
				$hook = (string) get_plugin_page_hookname( $slug, $parent );
			}
			if ( '' !== $hook ) {
				self::register_hub_page_globals( $slug, $parent, $hook );
			}

			if ( isset( $existing[ $slug ] ) ) {
				continue;
			}

			$title = isset( $meta['menu_title'] ) ? (string) $meta['menu_title'] : '';
			$cap   = isset( $meta['capability'] ) ? (string) $meta['capability'] : 'manage_options';

			$submenu[ $parent ][] = array(
				$title,
				$cap,
				$slug,
				$title,
				'rwgc-hub-access-screen',
			);
			$existing[ $slug ] = true;
		}

		$page = '';
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' !== $page && isset( $access_registry[ $page ] ) ) {
			$parent_file = $parent;
		}
	}

	/**
	 * Load collapsed-menu CSS on every wp-admin screen (not only hub screens).
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_collapsed_menu_styles( $hook ) {
		unset( $hook );
		if ( ! is_admin() || ! self::is_sidebar_collapsed() ) {
			return;
		}

		wp_enqueue_style(
			'rwgc-admin-menu',
			RWGC_URL . 'admin/css/rwgc-admin-menu.css',
			array(),
			defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '1.0.0'
		);
	}

	/**
	 * Extra CSS hide for satellite detail slugs (backup if a plugin re-adds submenu rows).
	 *
	 * @return void
	 */
	public static function print_collapsed_submenu_fallback_css() {
		if ( ! is_admin() || ! self::is_sidebar_collapsed() ) {
			return;
		}

		$hide_slugs = self::get_collapsed_submenu_hide_slugs();
		if ( empty( $hide_slugs ) ) {
			return;
		}

		echo '<style id="rwgc-collapsed-hub-submenu-fallback">';
		foreach ( $hide_slugs as $slug ) {
			printf(
				'#toplevel_page_rwgc-dashboard .wp-submenu li a[href*="page=%1$s"]{display:none!important;}',
				esc_attr( $slug )
			);
		}
		echo '</style>';
	}

	/**
	 * Detail hub slugs to hide via CSS when they still appear in the flyout.
	 *
	 * @return array<int, string>
	 */
	private static function get_collapsed_submenu_hide_slugs() {
		$slugs = array_keys( self::$collapsed_page_registry );

		$defaults = array(
			'elementor-geo-popup',
			'geo-elementor-rules',
			'geo-content',
			'geo-elementor-variants',
			'egp-addons',
			'geo-elementor-license',
			'geo-templates',
			'egp-city-settings',
			'egp-time-settings',
			'rwgo-edit-test',
			'rwgo-promote-winner',
		);

		/**
		 * @param array<int, string> $slugs Slugs to force-hide in the wp-admin flyout.
		 */
		$slugs = array_merge( $slugs, apply_filters( 'rwgc_collapsed_submenu_hide_slugs', $defaults ) );

		$keep = array_fill_keys( self::get_visible_submenu_slugs(), true );
		$out  = array();
		foreach ( array_unique( array_map( 'sanitize_key', $slugs ) ) as $slug ) {
			if ( '' === $slug || isset( $keep[ $slug ] ) ) {
				continue;
			}
			$out[] = $slug;
		}
		return $out;
	}

	/**
	 * Order hub submenu: core screens, extension heading, extension homes, then the rest.
	 *
	 * @return void
	 */
	public static function reorder_submenu() {
		global $submenu;

		$parent = self::menu_parent();
		if ( ! isset( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}

		$entries = $submenu[ $parent ];
		$by_slug = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry[2] ) ) {
				continue;
			}
			$by_slug[ (string) $entry[2] ] = $entry;
		}

		$core_slugs = array(
			'rwgc-dashboard',
			'rwgc-getting-started',
			'rwgc-suite-home',
			'rwgc-suite-variants',
			'rwgc-target-types',
			'rwgc-usage',
			'rwgc-settings',
			'rwgc-tools',
			'rwgc-addons',
		);
		$core_slugs = apply_filters( 'rwgc_admin_core_submenu_order', $core_slugs );

		$hub_slugs = array(
			'geo-elementor',
			'rwgcm-dashboard',
			'rwgo-dashboard',
			'rwga-dashboard',
		);
		$hub_slugs = apply_filters( 'rwgc_admin_extension_hub_submenu_order', $hub_slugs );

		$ordered = array();
		$used    = array();

		foreach ( $core_slugs as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$ordered[]     = $by_slug[ $slug ];
				$used[ $slug ] = true;
			}
		}

		$has_extensions = false;
		foreach ( array_keys( $by_slug ) as $slug ) {
			if ( isset( $used[ $slug ] ) || self::is_core_submenu_slug( $slug ) ) {
				continue;
			}
			$has_extensions = true;
			break;
		}

		if ( $has_extensions ) {
			$ordered[] = array(
				'<span class="rwgc-wp-submenu-heading">' . esc_html__( 'Geo extensions', 'reactwoo-geocore' ) . '</span>',
				'read',
				'rwgc-menu-heading-extensions',
				'',
				'rwgc-menu-heading',
			);
		}

		foreach ( $hub_slugs as $slug ) {
			if ( isset( $by_slug[ $slug ] ) && ! isset( $used[ $slug ] ) ) {
				$ordered[]     = $by_slug[ $slug ];
				$used[ $slug ] = true;
			}
		}

		$remaining = array();
		foreach ( $by_slug as $slug => $entry ) {
			if ( isset( $used[ $slug ] ) || 'rwgc-menu-heading-extensions' === $slug ) {
				continue;
			}
			$remaining[ $slug ] = $entry;
		}

		uasort(
			$remaining,
			static function ( $a, $b ) {
				$ta = isset( $a[0] ) ? wp_strip_all_tags( (string) $a[0] ) : '';
				$tb = isset( $b[0] ) ? wp_strip_all_tags( (string) $b[0] ) : '';
				return strcasecmp( $ta, $tb );
			}
		);

		foreach ( $remaining as $entry ) {
			$ordered[] = $entry;
		}

		$submenu[ $parent ] = apply_filters( 'rwgc_admin_submenu_ordered', $ordered, $by_slug );
	}

	/**
	 * @param string $slug Menu slug.
	 * @return bool
	 */
	private static function is_core_submenu_slug( $slug ) {
		return 0 === strpos( (string) $slug, 'rwgc-' ) && 'rwgc-menu-heading-extensions' !== $slug;
	}

	/**
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		if ( ! is_admin() ) {
			return $classes;
		}
		if ( self::is_sidebar_collapsed() ) {
			$classes .= ' rwgc-admin-sidebar-collapsed ';
		}
		if ( ! self::is_hub_screen() ) {
			return $classes;
		}
		$classes .= ' rwgc-geo-core-hub ';
		if ( class_exists( 'RWGC_Admin_App_Shell', false ) && class_exists( 'RWGC_Admin_Route_Registry', false ) && RWGC_Admin_App_Shell::should_render() ) {
			$classes .= ' rwgc-app-shell-active ';
		}
		return $classes;
	}
}
