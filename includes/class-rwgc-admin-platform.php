<?php
/**
 * Geo Core admin platform shell (free hub) — menu parent, ordering, branding.
 *
 * The wp-admin top-level product name stays **Geo Core** (free plugin).
 * Commercial satellites register submenus under {@see RWGC_Admin_Platform::menu_parent()}.
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
	const MENU_LABEL = 'Geo Core';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'reorder_submenu' ), 999 );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
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
	 * Top-level sidebar label (Geo Core).
	 *
	 * @return string
	 */
	public static function menu_label() {
		/**
		 * Filter the Geo Core hub sidebar menu title.
		 *
		 * @param string $label Default "Geo Core".
		 */
		return (string) apply_filters( 'rwgc_admin_menu_label', self::MENU_LABEL );
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
		$prefixes = array( 'rwgcm-', 'rwga-', 'rwgo-', 'geo-elementor', 'elementor-geo-popup', 'geo-content', 'egp-', 'geo-templates' );
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
	 * Reorder Geo Core submenu: core screens first, separator, extension hub entries, then detail pages.
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
			'rwgc-suite-variants',
			'rwgc-getting-started',
			'rwgc-suite-home',
			'rwgc-settings',
			'rwgc-tools',
			'rwgc-usage',
			'rwgc-target-types',
			'rwgc-target-playground',
			'rwgc-addons',
		);

		/**
		 * Core submenu slugs ordered under the Geo Core hub (before extensions).
		 *
		 * @param array<int, string> $core_slugs Slugs.
		 */
		$core_slugs = apply_filters( 'rwgc_admin_core_submenu_order', $core_slugs );

		$hub_slugs = array(
			'geo-elementor',
			'rwgcm-dashboard',
			'rwgo-dashboard',
			'rwga-dashboard',
		);

		/**
		 * Extension “home” submenu slugs (first item per satellite product).
		 *
		 * @param array<int, string> $hub_slugs Slugs.
		 */
		$hub_slugs = apply_filters( 'rwgc_admin_extension_hub_submenu_order', $hub_slugs );

		$ordered   = array();
		$used      = array();

		foreach ( $core_slugs as $slug ) {
			if ( isset( $by_slug[ $slug ] ) ) {
				$ordered[]    = $by_slug[ $slug ];
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
				$ordered[]    = $by_slug[ $slug ];
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

		/**
		 * Filter the final ordered submenu under Geo Core.
		 *
		 * @param array<int, array>  $ordered Submenu rows.
		 * @param array<string, array> $by_slug All entries keyed by slug.
		 */
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
		if ( ! is_admin() || ! self::is_hub_screen() ) {
			return $classes;
		}
		return $classes . ' rwgc-geo-core-hub ';
	}
}
