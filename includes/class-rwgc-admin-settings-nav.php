<?php
/**
 * Settings section navigation — one top tab per satellite, sub-tabs per screen.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Groups settings routes by provider for the app shell.
 */
class RWGC_Admin_Settings_Nav {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_app_routes', array( __CLASS__, 'normalize_settings_routes' ), 50 );
	}

	/**
	 * Provider metadata (order + label).
	 *
	 * @return array<string, array{label:string,order:int}>
	 */
	public static function get_provider_definitions() {
		$defs = array(
			'core'          => array(
				'label' => __( 'Geo Core', 'reactwoo-geocore' ),
				'order' => 10,
			),
			'geocore_pro'   => array(
				'label' => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'order' => 15,
			),
			'geo_elementor' => array(
				'label' => __( 'Elementor integration', 'reactwoo-geocore' ),
				'order' => 25,
			),
			'geo_optimise'  => array(
				'label' => __( 'Geo Optimise', 'reactwoo-geocore' ),
				'order' => 30,
			),
			'geo_commerce'  => array(
				'label' => __( 'Geo Commerce', 'reactwoo-geocore' ),
				'order' => 40,
			),
			'geo_ai'        => array(
				'label' => __( 'Geo AI', 'reactwoo-geocore' ),
				'order' => 50,
			),
		);

		/**
		 * @param array<string, array{label:string,order:int}> $defs Provider definitions.
		 */
		return apply_filters( 'rwgc_settings_providers', $defs );
	}

	/**
	 * Hide per-screen settings routes from the top contextual bar; group by provider instead.
	 *
	 * @param array<string, array<string, mixed>> $routes Routes.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize_settings_routes( $routes ) {
		if ( ! is_array( $routes ) ) {
			return array();
		}

		foreach ( $routes as $slug => $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}
			if ( ( $route['section'] ?? '' ) !== 'settings' ) {
				continue;
			}
			if ( 'rwgc-settings-hub' === $slug ) {
				continue;
			}

			$routes[ $slug ]['is_section_nav']       = false;
			$routes[ $slug ]['settings_provider']    = self::resolve_provider( $slug, $route );
			$routes[ $slug ]['settings_subnav_order'] = self::infer_subnav_order( $slug, $route );
			$routes[ $slug ]['label']                = self::humanize_route_label( $slug, $route );
		}

		return $routes;
	}

	/**
	 * @param string               $slug  Menu slug.
	 * @param array<string, mixed> $route Route row.
	 * @return string
	 */
	public static function resolve_provider( $slug, array $route ) {
		if ( ! empty( $route['settings_provider'] ) ) {
			return sanitize_key( (string) $route['settings_provider'] );
		}

		$provider = isset( $route['provider'] ) ? sanitize_key( (string) $route['provider'] ) : '';
		$map      = array(
			'geo_elementor' => 'geo_elementor',
			'geo_optimise'  => 'geo_optimise',
			'geo_commerce'  => 'geo_commerce',
			'geo_ai'        => 'geo_ai',
			'geocore_pro'   => 'geocore_pro',
		);
		if ( isset( $map[ $provider ] ) ) {
			return $map[ $provider ];
		}

		$module = isset( $route['module'] ) ? sanitize_key( (string) $route['module'] ) : '';
		$mod_map = array(
			'elementor' => 'geo_elementor',
			'optimise'  => 'geo_optimise',
			'commerce'  => 'geo_commerce',
			'ai'        => 'geo_ai',
			'core'      => 'core',
			'pro'       => 'geocore_pro',
		);
		if ( isset( $mod_map[ $module ] ) ) {
			return $mod_map[ $module ];
		}

		$slug = sanitize_key( (string) $slug );
		if ( 0 === strpos( $slug, 'rwgo-' ) ) {
			return 'geo_optimise';
		}
		if ( 0 === strpos( $slug, 'rwgcm-' ) ) {
			return 'geo_commerce';
		}
		if ( 0 === strpos( $slug, 'rwga-' ) ) {
			return 'geo_ai';
		}
		if ( 0 === strpos( $slug, 'rwgcp-' ) ) {
			return 'geocore_pro';
		}
		if ( 0 === strpos( $slug, 'geo-elementor' ) || 0 === strpos( $slug, 'egp-' ) || 0 === strpos( $slug, 'elementor-geo-popup' ) ) {
			return 'geo_elementor';
		}

		return 'core';
	}

	/**
	 * @param string               $slug  Menu slug.
	 * @param array<string, mixed> $route Route row.
	 * @return int
	 */
	public static function infer_subnav_order( $slug, array $route ) {
		if ( isset( $route['settings_subnav_order'] ) ) {
			return (int) $route['settings_subnav_order'];
		}

		$slug   = sanitize_key( (string) $slug );
		$route_id = isset( $route['route'] ) ? sanitize_key( (string) $route['route'] ) : '';

		if ( false !== strpos( $slug, 'license' ) || false !== strpos( $route_id, 'license' ) ) {
			return 10;
		}
		if ( false !== strpos( $route_id, 'settings' ) || 'rwgc-settings' === $slug ) {
			return 20;
		}
		if ( false !== strpos( $route_id, 'tools' ) || 'rwgc-tools' === $slug ) {
			return 25;
		}
		if ( false !== strpos( $route_id, 'addons' ) || 'rwgc-addons' === $slug ) {
			return 28;
		}
		if ( false !== strpos( $route_id, 'help' ) ) {
			return 45;
		}
		if ( false !== strpos( $route_id, 'developer' ) || false !== strpos( $route_id, 'tracking' ) || false !== strpos( $route_id, 'advanced' ) ) {
			return 35;
		}

		return (int) ( $route['order'] ?? 100 );
	}

	/**
	 * Clearer sub-nav labels (avoid duplicate generic "Settings" without context).
	 *
	 * @param string               $slug  Menu slug.
	 * @param array<string, mixed> $route Route row.
	 * @return string
	 */
	public static function humanize_route_label( $slug, array $route ) {
		$label = isset( $route['label'] ) ? trim( (string) $route['label'] ) : '';
		$map   = array(
			'rwgc-settings'           => __( 'General', 'reactwoo-geocore' ),
			'rwgc-tools'              => __( 'Tools', 'reactwoo-geocore' ),
			'rwgc-addons'             => __( 'Add-ons', 'reactwoo-geocore' ),
			'rwgcm-license'           => __( 'License', 'reactwoo-geocore' ),
			'rwgcm-settings'          => __( 'Commerce settings', 'reactwoo-geocore' ),
			'rwgcm-help'              => __( 'Help', 'reactwoo-geocore' ),
			'rwgo-license'            => __( 'License', 'reactwoo-geocore' ),
			'rwgo-settings'             => __( 'Optimise settings', 'reactwoo-geocore' ),
			'rwgo-tracking-tools'     => __( 'Tracking tools', 'reactwoo-geocore' ),
			'rwgo-developer'          => __( 'Developer', 'reactwoo-geocore' ),
			'rwgo-help'               => __( 'Help', 'reactwoo-geocore' ),
			'rwga-license'            => __( 'License', 'reactwoo-geocore' ),
			'rwga-advanced'           => __( 'Advanced', 'reactwoo-geocore' ),
			'rwgcp-geocore-pro'       => __( 'License', 'reactwoo-geocore' ),
			'elementor-geo-popup'     => __( 'Plugin settings', 'reactwoo-geocore' ),
		);

		$slug = sanitize_key( (string) $slug );
		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}

		if ( '' !== $label && __( 'Settings', 'reactwoo-geocore' ) !== $label ) {
			return $label;
		}

		return $label !== '' ? $label : $slug;
	}

	/**
	 * Active providers that have at least one settings route.
	 *
	 * @return array<string, array{label:string,order:int,default_slug:string,routes:array<string, array<string, mixed>>}>
	 */
	public static function get_active_providers() {
		if ( ! class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return array();
		}

		$defs      = self::get_provider_definitions();
		$providers = array();

		foreach ( RWGC_Admin_Route_Registry::get_routes() as $slug => $route ) {
			if ( ( $route['section'] ?? '' ) !== 'settings' || 'rwgc-settings-hub' === $slug ) {
				continue;
			}
			// Legacy Geo Elementor licence screen — Pro licence lives under GeoCore Pro.
			if ( 'geo-elementor-license' === $slug ) {
				continue;
			}
			$pid = self::resolve_provider( $slug, $route );
			if ( ! isset( $providers[ $pid ] ) ) {
				$providers[ $pid ] = array(
					'label'        => isset( $defs[ $pid ]['label'] ) ? (string) $defs[ $pid ]['label'] : $pid,
					'order'        => isset( $defs[ $pid ]['order'] ) ? (int) $defs[ $pid ]['order'] : 100,
					'default_slug' => '',
					'routes'       => array(),
				);
			}
			$providers[ $pid ]['routes'][ $slug ] = $route;
		}

		foreach ( $providers as $pid => $row ) {
			$providers[ $pid ]['default_slug'] = self::pick_default_slug( $row['routes'] );
		}

		uasort(
			$providers,
			static function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 100 ) ) <=> ( (int) ( $b['order'] ?? 100 ) );
			}
		);

		return $providers;
	}

	/**
	 * Prefer license screen, then lowest subnav order.
	 *
	 * @param array<string, array<string, mixed>> $routes Routes for one provider.
	 * @return string
	 */
	public static function pick_default_slug( array $routes ) {
		if ( empty( $routes ) ) {
			return '';
		}

		$best_slug  = '';
		$best_order = PHP_INT_MAX;

		foreach ( $routes as $slug => $route ) {
			$order = self::infer_subnav_order( $slug, $route );
			if ( $order < $best_order ) {
				$best_order = $order;
				$best_slug  = $slug;
			}
		}

		if ( '' !== $best_slug ) {
			return $best_slug;
		}

		foreach ( array_keys( $routes ) as $slug ) {
			return (string) $slug;
		}

		return '';
	}

	/**
	 * Routes for the active provider, sorted for sub-navigation.
	 *
	 * @param string $provider_id Provider id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_provider_routes( $provider_id ) {
		$provider_id = sanitize_key( (string) $provider_id );
		$providers   = self::get_active_providers();
		if ( ! isset( $providers[ $provider_id ]['routes'] ) ) {
			return array();
		}

		$routes = $providers[ $provider_id ]['routes'];

		uasort(
			$routes,
			static function ( $a, $b ) {
				$oa = (int) ( $a['settings_subnav_order'] ?? 100 );
				$ob = (int) ( $b['settings_subnav_order'] ?? 100 );
				if ( $oa === $ob ) {
					return strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
				}
				return $oa <=> $ob;
			}
		);

		return $routes;
	}

	/**
	 * Provider id for the current settings screen.
	 *
	 * @param array<string, string> $ctx Route context.
	 * @return string
	 */
	public static function get_current_provider( array $ctx ) {
		$slug = isset( $ctx['menu_slug'] ) ? sanitize_key( (string) $ctx['menu_slug'] ) : '';
		if ( '' === $slug || 'rwgc-settings-hub' === $slug ) {
			return '';
		}
		if ( ! class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return 'core';
		}
		$routes = RWGC_Admin_Route_Registry::get_routes();
		if ( isset( $routes[ $slug ] ) ) {
			return self::resolve_provider( $slug, $routes[ $slug ] );
		}
		return 'core';
	}

	/**
	 * One hub card per satellite provider (settings home grid).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_provider_hub_cards() {
		$cards = array();
		foreach ( self::get_active_providers() as $pid => $row ) {
			$slug = isset( $row['default_slug'] ) ? (string) $row['default_slug'] : '';
			if ( '' === $slug ) {
				continue;
			}
			$cards[] = array(
				'menu_slug'   => $slug,
				'label'       => (string) $row['label'],
				'url'         => admin_url( 'admin.php?page=' . rawurlencode( $slug ) ),
				'provider'    => $pid,
				'description' => self::provider_hub_description( $pid ),
			);
		}
		return $cards;
	}

	/**
	 * @param string $provider_id Provider id.
	 * @return string
	 */
	/**
	 * Quick links for Geo Core settings routes (shown on Settings hub).
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	public static function get_core_settings_quick_links() {
		$links  = array();
		$routes = self::get_provider_routes( 'core' );
		foreach ( $routes as $slug => $route ) {
			$label = isset( $route['label'] ) ? (string) $route['label'] : $slug;
			if ( '' === $label ) {
				continue;
			}
			$links[] = array(
				'label' => $label,
				'url'   => admin_url( 'admin.php?page=' . rawurlencode( (string) $slug ) ),
			);
		}
		return $links;
	}

	/**
	 * @param string $provider_id Provider id.
	 * @return string
	 */
	private static function provider_hub_description( $provider_id ) {
		$map = array(
			'core'          => __( 'General options, MaxMind tools, and add-ons.', 'reactwoo-geocore' ),
			'geocore_pro'   => __( 'GeoCore Pro licence, React Cloud, and advanced targeting.', 'reactwoo-geocore' ),
			'geo_elementor' => __( 'Elementor adapter settings and add-ons (free integration).', 'reactwoo-geocore' ),
			'geo_optimise'  => __( 'Licence, experiment settings, tracking, and developer tools.', 'reactwoo-geocore' ),
			'geo_commerce'  => __( 'Licence, WooCommerce geo settings, and help.', 'reactwoo-geocore' ),
			'geo_ai'        => __( 'Licence and advanced Geo AI options.', 'reactwoo-geocore' ),
		);
		return isset( $map[ $provider_id ] ) ? $map[ $provider_id ] : '';
	}
}
