<?php
/**
 * In-app admin routes for the ReactWoo Geo shell (module + section navigation).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects routes registered explicitly or inferred from hub submenus.
 */
class RWGC_Admin_Route_Registry {

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static $routes = array();

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static $modules = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgc_admin_submenu_registered', array( __CLASS__, 'on_submenu_registered' ), 10, 3 );
		add_action( 'rwgc_loaded', array( __CLASS__, 'register_default_modules' ), 5 );
		add_action( 'rwgc_loaded', array( __CLASS__, 'register_core_routes' ), 8 );
	}

	/**
	 * @return void
	 */
	public static function register_default_modules() {
		$defaults = array(
			'core'      => array(
				'label' => __( 'Core', 'reactwoo-geocore' ),
				'order' => 10,
				'icon'  => 'dashicons-admin-site-alt3',
			),
			'pro'       => array(
				'label' => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'order' => 20,
				'icon'  => 'dashicons-star-filled',
			),
			'ai'        => array(
				'label' => __( 'AI', 'reactwoo-geocore' ),
				'order' => 30,
				'icon'  => 'dashicons-lightbulb',
			),
			'commerce'  => array(
				'label' => __( 'Commerce', 'reactwoo-geocore' ),
				'order' => 40,
				'icon'  => 'dashicons-cart',
			),
			'optimise'  => array(
				'label' => __( 'Optimise', 'reactwoo-geocore' ),
				'order' => 50,
				'icon'  => 'dashicons-chart-line',
			),
			'elementor' => array(
				'label' => __( 'Elementor', 'reactwoo-geocore' ),
				'order' => 60,
				'icon'  => 'dashicons-layout',
			),
		);

		foreach ( $defaults as $id => $row ) {
			self::register_module( $id, $row );
		}
	}

	/**
	 * @param string               $module_id Module id.
	 * @param array<string, mixed> $module    Module row.
	 * @return void
	 */
	public static function register_module( $module_id, array $module ) {
		$module_id = sanitize_key( (string) $module_id );
		if ( '' === $module_id ) {
			return;
		}
		self::$modules[ $module_id ] = array_merge(
			array(
				'id'    => $module_id,
				'label' => $module_id,
				'order' => 100,
				'icon'  => 'dashicons-admin-generic',
			),
			$module
		);
	}

	/**
	 * @param array<string, mixed> $args Route args.
	 * @return bool
	 */
	public static function register_route( array $args ) {
		$slug = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug ) {
			return false;
		}

		$module = isset( $args['module'] ) ? sanitize_key( (string) $args['module'] ) : self::infer_module_from_slug( $slug );
		$route  = isset( $args['route'] ) ? sanitize_key( (string) $args['route'] ) : $slug;

		self::$routes[ $slug ] = array(
			'module'             => $module,
			'route'              => $route,
			'menu_slug'          => $slug,
			'label'              => isset( $args['label'] ) ? (string) $args['label'] : $slug,
			'order'              => isset( $args['order'] ) ? (int) $args['order'] : 100,
			'show_in_wp_sidebar' => ! empty( $args['show_in_wp_sidebar'] ),
			'is_section_nav'     => ! isset( $args['is_section_nav'] ) || ! empty( $args['is_section_nav'] ),
		);

		return true;
	}

	/**
	 * @return void
	 */
	public static function register_core_routes() {
		$core = array(
			array(
				'menu_slug' => 'rwgc-dashboard',
				'route'     => 'dashboard',
				'label'     => __( 'Dashboard', 'reactwoo-geocore' ),
				'order'     => 10,
			),
			array(
				'menu_slug' => 'rwgc-target-types',
				'route'     => 'targeting',
				'label'     => __( 'Targeting', 'reactwoo-geocore' ),
				'order'     => 20,
			),
			array(
				'menu_slug' => 'rwgc-suite-variants',
				'route'     => 'rules',
				'label'     => __( 'Rules / Page versions', 'reactwoo-geocore' ),
				'order'     => 30,
			),
			array(
				'menu_slug' => 'rwgc-usage',
				'route'     => 'reports',
				'label'     => __( 'Reports', 'reactwoo-geocore' ),
				'order'     => 40,
			),
			array(
				'menu_slug' => 'rwgc-settings',
				'route'     => 'settings',
				'label'     => __( 'Settings', 'reactwoo-geocore' ),
				'order'     => 50,
			),
			array(
				'menu_slug' => 'rwgc-tools',
				'route'     => 'tools',
				'label'     => __( 'Tools', 'reactwoo-geocore' ),
				'order'     => 60,
			),
			array(
				'menu_slug' => 'rwgc-addons',
				'route'     => 'addons',
				'label'     => __( 'Add-ons', 'reactwoo-geocore' ),
				'order'     => 70,
			),
			array(
				'menu_slug' => 'rwgc-getting-started',
				'route'     => 'setup',
				'label'     => __( 'Setup', 'reactwoo-geocore' ),
				'order'     => 80,
			),
			array(
				'menu_slug' => 'rwgc-suite-home',
				'route'     => 'suite-home',
				'label'     => __( 'Suite home', 'reactwoo-geocore' ),
				'order'     => 85,
			),
		);

		foreach ( $core as $row ) {
			$row['module'] = 'core';
			self::register_route( $row );
		}
	}

	/**
	 * @param string               $hook Hook suffix.
	 * @param string               $slug Menu slug.
	 * @param array<string, mixed> $args Registration args.
	 * @return void
	 */
	public static function on_submenu_registered( $hook, $slug, $args ) {
		unset( $hook );
		if ( ! is_string( $slug ) || '' === $slug || 'rwgc-menu-heading-extensions' === $slug ) {
			return;
		}
		if ( isset( self::$routes[ $slug ] ) ) {
			return;
		}

		$label = isset( $args['menu_title'] ) ? wp_strip_all_tags( (string) $args['menu_title'] ) : $slug;
		if ( '' === $label ) {
			$label = $slug;
		}

		self::register_route(
			array(
				'module'             => self::infer_module_from_slug( $slug ),
				'route'              => $slug,
				'menu_slug'          => $slug,
				'label'              => $label,
				'order'              => isset( $args['position'] ) ? (int) $args['position'] : 100,
				'show_in_wp_sidebar' => ! empty( $args['show_in_wp_sidebar'] ),
			)
		);
	}

	/**
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function infer_module_from_slug( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( 0 === strpos( $slug, 'rwga-' ) ) {
			return 'ai';
		}
		if ( 0 === strpos( $slug, 'rwgcm-' ) ) {
			return 'commerce';
		}
		if ( 0 === strpos( $slug, 'rwgo-' ) ) {
			return 'optimise';
		}
		if ( 0 === strpos( $slug, 'rwgcp-' ) ) {
			return 'pro';
		}
		if ( 0 === strpos( $slug, 'rwgc-' ) ) {
			return 'core';
		}
		if ( 0 === strpos( $slug, 'geo-elementor' ) || 0 === strpos( $slug, 'geo-content' ) || 0 === strpos( $slug, 'geo-templates' ) || 0 === strpos( $slug, 'elementor-geo-popup' ) || 0 === strpos( $slug, 'egp-' ) ) {
			return 'elementor';
		}
		return 'core';
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_modules() {
		$modules = self::$modules;

		/**
		 * @param array<string, array<string, mixed>> $modules
		 */
		$modules = apply_filters( 'rwgc_app_modules', $modules );

		uasort(
			$modules,
			static function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 100 ) ) <=> ( (int) ( $b['order'] ?? 100 ) );
			}
		);

		return $modules;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_routes() {
		$routes = self::$routes;

		/**
		 * @param array<string, array<string, mixed>> $routes
		 */
		return apply_filters( 'rwgc_app_routes', $routes );
	}

	/**
	 * @param string $module_id Module id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_routes_for_module( $module_id ) {
		$module_id = sanitize_key( (string) $module_id );
		$out       = array();

		foreach ( self::get_routes() as $slug => $route ) {
			if ( ( $route['module'] ?? '' ) !== $module_id ) {
				continue;
			}
			if ( empty( $route['is_section_nav'] ) ) {
				continue;
			}
			$out[ $slug ] = $route;
		}

		uasort(
			$out,
			static function ( $a, $b ) {
				$oa = (int) ( $a['order'] ?? 100 );
				$ob = (int) ( $b['order'] ?? 100 );
				if ( $oa === $ob ) {
					return strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
				}
				return $oa <=> $ob;
			}
		);

		return $out;
	}

	/**
	 * @return array{module:string,route:string,menu_slug:string,label:string}
	 */
	public static function get_current_context() {
		$page = '';
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$routes = self::get_routes();
		if ( isset( $routes[ $page ] ) ) {
			$row = $routes[ $page ];
			return array(
				'module'    => (string) ( $row['module'] ?? 'core' ),
				'route'     => (string) ( $row['route'] ?? $page ),
				'menu_slug' => $page,
				'label'     => (string) ( $row['label'] ?? $page ),
			);
		}

		return array(
			'module'    => self::infer_module_from_slug( $page ),
			'route'     => $page,
			'menu_slug' => $page,
			'label'     => $page,
		);
	}

	/**
	 * @param string $module_id Module id.
	 * @param string $menu_slug Menu slug.
	 * @return string
	 */
	public static function get_url( $module_id, $menu_slug = '' ) {
		$menu_slug = sanitize_key( (string) $menu_slug );
		if ( '' === $menu_slug ) {
			foreach ( self::get_routes_for_module( $module_id ) as $slug => $route ) {
				unset( $route );
				$menu_slug = $slug;
				break;
			}
		}
		if ( '' === $menu_slug ) {
			$menu_slug = 'rwgc-dashboard';
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $menu_slug ) );
	}

	/**
	 * @param string               $module_id Module id.
	 * @param array<string, mixed> $module    Module row.
	 * @return bool
	 */
	public static function is_module_visible( $module_id, array $module ) {
		if ( ! empty( $module['is_active_callback'] ) && is_callable( $module['is_active_callback'] ) ) {
			return (bool) call_user_func( $module['is_active_callback'] );
		}

		if ( 'core' === $module_id ) {
			return true;
		}

		$routes = self::get_routes_for_module( $module_id );
		if ( empty( $routes ) ) {
			return false;
		}

		if ( 'pro' === $module_id ) {
			return class_exists( 'RWGCP_Bootstrap', false );
		}

		return true;
	}
}
