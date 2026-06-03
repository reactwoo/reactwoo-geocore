<?php
/**
 * In-app admin routes for the ReactWoo Geo shell (goal sections + contextual tabs).
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
	 * Goal-based primary nav sections (UX platform model).
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $sections = array();

	/**
	 * Legacy module ids (satellite grouping); aliased for backward compatibility.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $modules = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgc_admin_submenu_registered', array( __CLASS__, 'on_submenu_registered' ), 10, 3 );
		add_action( 'rwgc_loaded', array( __CLASS__, 'register_default_sections' ), 5 );
		add_action( 'rwgc_loaded', array( __CLASS__, 'register_default_modules' ), 5 );
		add_action( 'rwgc_loaded', array( __CLASS__, 'register_core_routes' ), 8 );
	}

	/**
	 * @return void
	 */
	public static function register_default_sections() {
		$defaults = array(
			'overview'       => array(
				'label' => __( 'Overview', 'reactwoo-geocore' ),
				'order' => 10,
				'icon'  => 'dashicons-dashboard',
			),
			'targeting'      => array(
				'label' => __( 'Targeting', 'reactwoo-geocore' ),
				'order' => 20,
				'icon'  => 'dashicons-admin-site-alt3',
			),
			'experiences'    => array(
				'label' => __( 'Experiences', 'reactwoo-geocore' ),
				'order' => 30,
				'icon'  => 'dashicons-format-gallery',
			),
			'commerce'       => array(
				'label' => __( 'Commerce', 'reactwoo-geocore' ),
				'order' => 40,
				'icon'  => 'dashicons-cart',
			),
			'insights'       => array(
				'label' => __( 'Insights', 'reactwoo-geocore' ),
				'order' => 50,
				'icon'  => 'dashicons-chart-area',
			),
			'integrations'   => array(
				'label' => __( 'Integrations', 'reactwoo-geocore' ),
				'order' => 60,
				'icon'  => 'dashicons-admin-links',
			),
			'settings'       => array(
				'label' => __( 'Settings', 'reactwoo-geocore' ),
				'order' => 70,
				'icon'  => 'dashicons-admin-settings',
			),
		);

		foreach ( $defaults as $id => $row ) {
			self::register_section( $id, $row );
		}
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
	 * @param string               $section_id Section id.
	 * @param array<string, mixed> $section    Section row.
	 * @return void
	 */
	public static function register_section( $section_id, array $section ) {
		$section_id = sanitize_key( (string) $section_id );
		if ( '' === $section_id ) {
			return;
		}
		self::$sections[ $section_id ] = array_merge(
			array(
				'id'    => $section_id,
				'label' => $section_id,
				'order' => 100,
				'icon'  => 'dashicons-admin-generic',
			),
			$section
		);
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

		$section = '';
		if ( isset( $args['section'] ) ) {
			$section = sanitize_key( (string) $args['section'] );
		}
		if ( '' === $section && isset( $args['module'] ) ) {
			$section = self::module_to_section( sanitize_key( (string) $args['module'] ) );
		}
		if ( '' === $section ) {
			$section = self::infer_section_from_slug( $slug );
		}

		$integration_category = '';
		if ( isset( $args['integration_category'] ) ) {
			$integration_category = sanitize_key( (string) $args['integration_category'] );
		} elseif ( isset( $args['category'] ) ) {
			$integration_category = sanitize_key( (string) $args['category'] );
		}

		$source_plugin = isset( $args['source_plugin'] ) ? sanitize_key( (string) $args['source_plugin'] ) : '';

		self::$routes[ $slug ] = array(
			'section'                => $section,
			'module'                 => $module,
			'route'                  => $route,
			'menu_slug'              => $slug,
			'label'                  => isset( $args['label'] ) ? (string) $args['label'] : $slug,
			'order'                  => isset( $args['order'] ) ? (int) $args['order'] : 100,
			'provider'               => isset( $args['provider'] ) ? sanitize_key( (string) $args['provider'] ) : '',
			'integration_category'   => $integration_category,
			'source_plugin'          => $source_plugin,
			'capability_required'    => isset( $args['capability_required'] ) ? sanitize_key( (string) $args['capability_required'] ) : '',
			'show_in_wp_sidebar'     => ! empty( $args['show_in_wp_sidebar'] ),
			'register_wp_submenu'    => self::resolve_register_wp_submenu( $args ),
			'is_section_nav'         => ! isset( $args['is_section_nav'] ) || ! empty( $args['is_section_nav'] ),
		);

		return true;
	}

	/**
	 * Whether a route should register a visible wp-admin submenu row under the hub.
	 *
	 * @param array<string, mixed> $args Route registration args.
	 * @return bool
	 */
	public static function resolve_register_wp_submenu( array $args ) {
		if ( isset( $args['register_wp_submenu'] ) ) {
			return ! empty( $args['register_wp_submenu'] );
		}
		if ( ! empty( $args['show_in_wp_sidebar'] ) ) {
			return true;
		}
		/**
		 * @param bool                 $register Default false — shell-only routes use virtual hub pages.
		 * @param array<string, mixed> $args     Route args.
		 */
		return (bool) apply_filters( 'rwgc_app_route_register_wp_submenu', false, $args );
	}

	/**
	 * @param string $slug Menu slug.
	 * @return bool
	 */
	public static function route_registers_wp_submenu( $slug ) {
		$slug = sanitize_key( (string) $slug );
		return isset( self::$routes[ $slug ]['register_wp_submenu'] ) && ! empty( self::$routes[ $slug ]['register_wp_submenu'] );
	}

	/**
	 * @return void
	 */
	public static function register_core_routes() {
		$core = array(
			array(
				'menu_slug' => 'rwgc-dashboard',
				'section'   => 'overview',
				'route'     => 'dashboard',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 10,
			),
			array(
				'menu_slug' => 'rwgc-getting-started',
				'section'   => 'overview',
				'route'     => 'setup',
				'label'     => __( 'Setup wizard', 'reactwoo-geocore' ),
				'order'     => 15,
			),
			array(
				'menu_slug' => 'rwgc-suite-home',
				'section'   => 'overview',
				'route'     => 'suite-home',
				'label'     => __( 'Suite home', 'reactwoo-geocore' ),
				'order'     => 20,
			),
			array(
				'menu_slug' => 'rwgc-commerce-hub',
				'section'   => 'commerce',
				'route'     => 'commerce-home',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
			),
			array(
				'menu_slug' => 'rwgc-targeting-hub',
				'section'   => 'targeting',
				'route'     => 'targeting-home',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
			),
			array(
				'menu_slug' => 'rwgc-visibility-rules',
				'section'   => 'targeting',
				'route'     => 'rules',
				'label'     => __( 'Rules', 'reactwoo-geocore' ),
				'order'     => 10,
			),
			array(
				'menu_slug' => 'rwgc-target-types',
				'section'   => 'targeting',
				'route'     => 'geo-conditions',
				'label'     => __( 'Geo conditions', 'reactwoo-geocore' ),
				'order'     => 30,
			),
			array(
				'menu_slug' => 'rwgc-targeting-audiences',
				'section'   => 'targeting',
				'route'     => 'audiences',
				'label'     => __( 'Audiences', 'reactwoo-geocore' ),
				'order'     => 20,
			),
			array(
				'menu_slug' => 'rwgc-targeting-campaigns',
				'section'   => 'targeting',
				'route'     => 'campaigns',
				'label'     => __( 'Campaigns', 'reactwoo-geocore' ),
				'order'     => 25,
			),
			array(
				'menu_slug'      => 'rwgc-suite-variants',
				'section'        => 'experiences',
				'route'          => 'variants',
				'label'          => __( 'Variants', 'reactwoo-geocore' ),
				'order'          => 10,
			),
			array(
				'menu_slug' => 'rwgc-experiences-hub',
				'section'   => 'experiences',
				'route'     => 'experiences-home',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
			),
			array(
				'menu_slug' => 'rwgc-insights-hub',
				'section'   => 'insights',
				'route'     => 'insights-home',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
			),
			array(
				'menu_slug' => 'rwgc-usage',
				'section'   => 'insights',
				'route'     => 'geo-reports',
				'label'     => __( 'Geo insights', 'reactwoo-geocore' ),
				'order'     => 15,
			),
			array(
				'menu_slug' => 'rwgc-usage-audience',
				'section'   => 'insights',
				'route'     => 'audience-insights',
				'label'     => __( 'Audience insights', 'reactwoo-geocore' ),
				'order'     => 20,
			),
			array(
				'menu_slug' => 'rwgc-usage-campaign',
				'section'   => 'insights',
				'route'     => 'campaign-insights',
				'label'     => __( 'Campaign insights', 'reactwoo-geocore' ),
				'order'     => 25,
			),
			array(
				'menu_slug' => 'rwgc-insights-experiments',
				'section'   => 'insights',
				'route'     => 'experience-performance',
				'label'     => __( 'Experience performance', 'reactwoo-geocore' ),
				'order'     => 30,
			),
			array(
				'menu_slug' => 'rwgc-integrations-hub',
				'section'   => 'integrations',
				'route'     => 'integrations-home',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
			),
			array(
				'menu_slug'            => 'rwgc-integrations-gutenberg',
				'section'              => 'integrations',
				'integration_category' => 'content_builders',
				'route'                => 'gutenberg',
				'label'                => __( 'Gutenberg', 'reactwoo-geocore' ),
				'order'                => 20,
			),
			array(
				'menu_slug'            => 'rwgc-integrations-woocommerce',
				'section'              => 'integrations',
				'integration_category' => 'ecommerce',
				'route'                => 'woocommerce',
				'label'                => __( 'WooCommerce', 'reactwoo-geocore' ),
				'order'                => 10,
			),
			array(
				'menu_slug'            => 'rwgc-integrations-maxmind',
				'section'              => 'integrations',
				'integration_category' => 'system_services',
				'route'                => 'maxmind',
				'label'                => __( 'MaxMind (GeoLite2)', 'reactwoo-geocore' ),
				'order'                => 5,
			),
			array(
				'menu_slug' => 'rwgc-settings-hub',
				'section'   => 'settings',
				'route'     => 'settings-home',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
			),
			array(
				'menu_slug' => 'rwgc-settings',
				'section'   => 'settings',
				'route'     => 'settings',
				'label'     => __( 'General', 'reactwoo-geocore' ),
				'order'     => 10,
			),
			array(
				'menu_slug' => 'rwgc-tools',
				'section'   => 'settings',
				'route'     => 'tools',
				'label'     => __( 'Tools', 'reactwoo-geocore' ),
				'order'     => 20,
			),
			array(
				'menu_slug' => 'rwgc-addons',
				'section'   => 'settings',
				'route'     => 'addons',
				'label'     => __( 'Add-ons', 'reactwoo-geocore' ),
				'order'     => 30,
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

		$row = array(
			'module'              => self::infer_module_from_slug( $slug ),
			'section'             => self::infer_section_from_slug( $slug ),
			'route'               => $slug,
			'menu_slug'           => $slug,
			'label'               => $label,
			'order'               => isset( $args['position'] ) ? (int) $args['position'] : 100,
			'show_in_wp_sidebar'  => ! empty( $args['show_in_wp_sidebar'] ),
			'register_wp_submenu' => true,
		);
		if ( ! empty( $args['section'] ) ) {
			$row['section'] = sanitize_key( (string) $args['section'] );
		}
		if ( ! empty( $args['provider'] ) ) {
			$row['provider'] = sanitize_key( (string) $args['provider'] );
		}
		if ( ! empty( $args['integration_category'] ) ) {
			$row['integration_category'] = sanitize_key( (string) $args['integration_category'] );
		} elseif ( ! empty( $args['category'] ) ) {
			$row['integration_category'] = sanitize_key( (string) $args['category'] );
		}

		self::register_route( $row );
	}

	/**
	 * Map legacy module id to default goal section (fallback only).
	 *
	 * @param string $module_id Module id.
	 * @return string
	 */
	public static function module_to_section( $module_id ) {
		$map = array(
			'core'      => 'overview',
			'pro'       => 'integrations',
			'ai'        => 'insights',
			'commerce'  => 'commerce',
			'optimise'  => 'experiences',
			'elementor' => 'integrations',
		);
		$module_id = sanitize_key( (string) $module_id );
		return isset( $map[ $module_id ] ) ? $map[ $module_id ] : 'overview';
	}

	/**
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function infer_section_from_slug( $slug ) {
		$slug = sanitize_key( (string) $slug );

		$settings_slugs = array(
			'rwgc-settings',
			'rwgc-tools',
			'rwgc-addons',
			'rwgcm-license',
			'rwgcm-settings',
			'rwgcm-help',
			'rwga-license',
			'rwga-advanced',
			'rwgo-license',
			'rwgo-settings',
			'rwgo-help',
			'rwgo-developer',
			'rwgo-tracking-tools',
			'rwgcp-geocore-pro',
			'elementor-geo-popup',
			'egp-city-settings',
			'egp-time-settings',
			'rwgcp-settings',
		);
		if ( in_array( $slug, $settings_slugs, true ) ) {
			return 'settings';
		}

		if ( 0 === strpos( $slug, 'rwgcm-' ) ) {
			return 'commerce';
		}
		if ( 0 === strpos( $slug, 'rwga-' ) ) {
			return 'insights';
		}
		if ( 0 === strpos( $slug, 'rwgo-' ) ) {
			return 'experiences';
		}
		if ( 0 === strpos( $slug, 'rwgcp-' ) ) {
			return 'integrations';
		}
		if ( in_array( $slug, array( 'rwgc-usage', 'rwgc-usage-audience', 'rwgc-usage-campaign', 'rwgc-insights-experiments' ), true ) ) {
			return 'insights';
		}
		if ( in_array( $slug, array( 'rwgc-commerce-hub' ), true ) ) {
			return 'commerce';
		}
		if ( in_array( $slug, array( 'rwgc-integrations-hub', 'rwgc-integrations-gutenberg', 'rwgc-integrations-woocommerce' ), true ) ) {
			return 'integrations';
		}
		if ( in_array( $slug, array( 'rwgc-target-types', 'rwgc-visibility-rules', 'rwgc-targeting-hub', 'rwgc-targeting-audiences', 'rwgc-targeting-campaigns' ), true ) ) {
			return 'targeting';
		}
		if ( in_array( $slug, array( 'rwgc-experiences-hub', 'rwgc-suite-variants' ), true ) ) {
			return 'experiences';
		}
		if ( in_array( $slug, array( 'rwgc-getting-started', 'rwgc-suite-home' ), true ) ) {
			return 'overview';
		}
		if ( 0 === strpos( $slug, 'geo-content' ) ) {
			return 'experiences';
		}
		if ( 0 === strpos( $slug, 'geo-elementor' ) || 0 === strpos( $slug, 'geo-templates' ) || 0 === strpos( $slug, 'elementor-geo-popup' ) || 0 === strpos( $slug, 'egp-' ) ) {
			return 'integrations';
		}
		if ( 0 === strpos( $slug, 'rwgc-' ) ) {
			return 'overview';
		}
		return 'overview';
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
	public static function get_sections() {
		$sections = self::$sections;

		/**
		 * @param array<string, array<string, mixed>> $sections
		 */
		$sections = apply_filters( 'rwgc_app_sections', $sections );

		uasort(
			$sections,
			static function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 100 ) ) <=> ( (int) ( $b['order'] ?? 100 ) );
			}
		);

		return $sections;
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
	 * Routes for contextual tabs within a goal section.
	 *
	 * @param string $section_id Section id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_routes_for_section( $section_id ) {
		$section_id = sanitize_key( (string) $section_id );
		$out        = array();

		foreach ( self::get_routes() as $slug => $route ) {
			if ( ( $route['section'] ?? '' ) !== $section_id ) {
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
	 * @deprecated 0.5+ Use get_routes_for_section().
	 * @param string $module_id Module id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_routes_for_module( $module_id ) {
		return self::get_routes_for_section( self::module_to_section( $module_id ) );
	}

	/**
	 * @return array{section:string,module:string,route:string,menu_slug:string,label:string,provider:string}
	 */
	public static function get_current_context() {
		$page = '';
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$routes = self::get_routes();
		if ( isset( $routes[ $page ] ) ) {
			$row = $routes[ $page ];
			$integration_category = (string) ( $row['integration_category'] ?? '' );
			if ( '' === $integration_category && class_exists( 'RWGC_Admin_Integrations_Nav', false ) ) {
				$integration_category = RWGC_Admin_Integrations_Nav::resolve_category( $page, $row );
			}
			return array(
				'section'              => (string) ( $row['section'] ?? 'overview' ),
				'module'               => (string) ( $row['module'] ?? 'core' ),
				'route'                => (string) ( $row['route'] ?? $page ),
				'menu_slug'            => $page,
				'label'                => (string) ( $row['label'] ?? $page ),
				'provider'             => (string) ( $row['provider'] ?? '' ),
				'integration_category' => $integration_category,
				'source_plugin'        => (string) ( $row['source_plugin'] ?? '' ),
			);
		}

		return array(
			'section'              => self::infer_section_from_slug( $page ),
			'module'               => self::infer_module_from_slug( $page ),
			'route'                => $page,
			'menu_slug'            => $page,
			'label'                => $page,
			'provider'             => '',
			'integration_category' => '',
			'source_plugin'        => '',
		);
	}

	/**
	 * @param string $section_id Section id.
	 * @param string $menu_slug  Menu slug.
	 * @return string
	 */
	public static function get_url( $section_id, $menu_slug = '' ) {
		$section_id = sanitize_key( (string) $section_id );
		$menu_slug  = sanitize_key( (string) $menu_slug );
		$routes     = self::get_routes();

		if ( '' !== $menu_slug && ! isset( $routes[ $menu_slug ] ) ) {
			foreach ( $routes as $slug => $route ) {
				if ( ( $route['section'] ?? '' ) !== $section_id ) {
					continue;
				}
				if ( ( $route['route'] ?? '' ) === $menu_slug ) {
					$menu_slug = $slug;
					break;
				}
			}
		}

		if ( '' === $menu_slug ) {
			foreach ( self::get_routes_for_section( $section_id ) as $slug => $route ) {
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
	 * @param string               $section_id Section id.
	 * @param array<string, mixed> $section    Section row.
	 * @return bool
	 */
	public static function is_section_visible( $section_id, array $section ) {
		if ( ! empty( $section['is_active_callback'] ) && is_callable( $section['is_active_callback'] ) ) {
			return (bool) call_user_func( $section['is_active_callback'] );
		}

		$routes = self::get_routes_for_section( $section_id );
		return ! empty( $routes );
	}

	/**
	 * @deprecated 0.5+ Use is_section_visible().
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

		$routes = self::get_routes_for_section( self::module_to_section( $module_id ) );
		if ( empty( $routes ) ) {
			return false;
		}

		if ( 'pro' === $module_id ) {
			return class_exists( 'RWGCP_Bootstrap', false );
		}

		return true;
	}
}
