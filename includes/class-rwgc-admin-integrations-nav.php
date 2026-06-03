<?php
/**
 * Integrations section navigation — category groups + scoped provider tabs.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Groups integration routes by curated category (not raw plugin registration).
 */
class RWGC_Admin_Integrations_Nav {

	/**
	 * Query arg for integrations category when no route is active yet.
	 */
	const CATEGORY_QUERY_ARG = 'rwgc_integration_category';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_app_routes', array( __CLASS__, 'normalize_integration_routes' ), 50 );
		add_filter( 'rwgc_section_hub_cards', array( __CLASS__, 'filter_integrations_hub_cards' ), 10, 2 );
	}

	/**
	 * Curated integration categories (product IA).
	 *
	 * @return array<string, array{label:string,order:int,description:string}>
	 */
	public static function get_category_definitions() {
		$defs = array(
			'analytics'        => array(
				'label'       => __( 'Analytics', 'reactwoo-geocore' ),
				'order'       => 10,
				'description' => __( 'Measurement, GA4 audiences, and analytics sync.', 'reactwoo-geocore' ),
			),
			'advertising'      => array(
				'label'       => __( 'Advertising', 'reactwoo-geocore' ),
				'order'       => 20,
				'description' => __( 'Google Ads, Meta Ads, and campaign sync.', 'reactwoo-geocore' ),
			),
			'apis'             => array(
				'label'       => __( 'APIs', 'reactwoo-geocore' ),
				'order'       => 30,
				'description' => __( 'Weather, Unkey, and other external API providers.', 'reactwoo-geocore' ),
			),
			'ecommerce'        => array(
				'label'       => __( 'Ecommerce', 'reactwoo-geocore' ),
				'order'       => 40,
				'description' => __( 'WooCommerce and store-level integrations.', 'reactwoo-geocore' ),
			),
			'content_builders' => array(
				'label'       => __( 'Content builders', 'reactwoo-geocore' ),
				'order'       => 50,
				'description' => __( 'Elementor, Gutenberg, and page builders.', 'reactwoo-geocore' ),
			),
			'system_services'  => array(
				'label'       => __( 'System services', 'reactwoo-geocore' ),
				'order'       => 60,
				'description' => __( 'Licence service, updates, queues, and platform health.', 'reactwoo-geocore' ),
			),
		);

		/**
		 * @param array<string, array{label:string,order:int,description:string}> $defs Category definitions.
		 */
		return apply_filters( 'rwgc_integration_categories', $defs );
	}

	/**
	 * Hide integration routes from the global section tab bar; show per category instead.
	 *
	 * @param array<string, array<string, mixed>> $routes Routes.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize_integration_routes( $routes ) {
		if ( ! is_array( $routes ) ) {
			return array();
		}

		foreach ( $routes as $slug => $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}
			if ( ( $route['section'] ?? '' ) !== 'integrations' ) {
				continue;
			}
			if ( 'rwgc-integrations-hub' === $slug ) {
				continue;
			}

			$routes[ $slug ]['integration_category'] = self::resolve_category( $slug, $route );
			$routes[ $slug ]['is_section_nav']       = false;
		}

		return $routes;
	}

	/**
	 * @param string               $slug  Menu slug.
	 * @param array<string, mixed> $route Route row.
	 * @return string
	 */
	public static function resolve_category( $slug, array $route ) {
		if ( ! empty( $route['integration_category'] ) ) {
			return sanitize_key( (string) $route['integration_category'] );
		}
		if ( ! empty( $route['category'] ) ) {
			return sanitize_key( (string) $route['category'] );
		}

		$slug   = sanitize_key( (string) $slug );
		$route_id = isset( $route['route'] ) ? sanitize_key( (string) $route['route'] ) : '';

		$map = array(
			'rwgcp-google-analytics' => 'analytics',
			'rwgcp-google-ads'       => 'advertising',
			'rwgcp-google'           => 'analytics',
			'rwgcp-weather'          => 'apis',
			'rwgcp-api-keys'         => 'apis',
			'rwgcp-meta'             => 'advertising',
			'rwgcp-geocore-pro'      => 'system_services',
			'rwgc-integrations-maxmind'     => 'system_services',
			'rwgc-integrations-gutenberg'   => 'content_builders',
			'rwgc-integrations-woocommerce' => 'ecommerce',
			'geo-elementor'          => 'content_builders',
			'elementor-geo-popup'    => 'content_builders',
			'egp-city-settings'      => 'content_builders',
			'egp-time-settings'      => 'content_builders',
		);

		if ( isset( $map[ $slug ] ) ) {
			return $map[ $slug ];
		}

		if ( in_array( $route_id, array( 'google-analytics', 'ga4' ), true ) ) {
			return 'analytics';
		}
		if ( in_array( $route_id, array( 'google-ads', 'google', 'meta' ), true ) ) {
			return 'advertising';
		}
		if ( in_array( $route_id, array( 'weather', 'api-keys', 'api-providers', 'unkey' ), true ) ) {
			return 'apis';
		}
		if ( 'woocommerce' === $route_id ) {
			return 'ecommerce';
		}
		if ( in_array( $route_id, array( 'gutenberg', 'elementor' ), true ) ) {
			return 'content_builders';
		}
		if ( in_array( $route_id, array( 'maxmind', 'geolite2', 'geoip' ), true ) || 'rwgc-integrations-maxmind' === $slug ) {
			return 'system_services';
		}

		$provider = isset( $route['provider'] ) ? sanitize_key( (string) $route['provider'] ) : '';
		if ( 'geo_commerce' === $provider || 0 === strpos( $slug, 'rwgcm-' ) ) {
			return 'ecommerce';
		}
		if ( 'geo_elementor' === $provider || 'elementor' === ( $route['module'] ?? '' ) ) {
			return 'content_builders';
		}
		if ( 'geocore_pro' === $provider || 0 === strpos( $slug, 'rwgcp-' ) ) {
			if ( in_array( $slug, array( 'rwgcp-weather', 'rwgcp-api-keys' ), true ) ) {
				return 'apis';
			}
			if ( in_array( $slug, array( 'rwgcp-meta' ), true ) ) {
				return 'advertising';
			}
			if ( in_array( $slug, array( 'rwgcp-google', 'rwgcp-google-analytics' ), true ) ) {
				return 'analytics';
			}
			if ( 'rwgcp-google-ads' === $slug ) {
				return 'advertising';
			}
			return 'system_services';
		}

		return '';
	}

	/**
	 * Active categories that have at least one registered route.
	 *
	 * @return array<string, array{label:string,order:int,description:string,default_slug:string,routes:array<string, array<string, mixed>>}>
	 */
	public static function get_active_categories() {
		if ( ! class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return array();
		}

		$defs       = self::get_category_definitions();
		$categories = array();

		foreach ( RWGC_Admin_Route_Registry::get_routes() as $slug => $route ) {
			if ( ( $route['section'] ?? '' ) !== 'integrations' || 'rwgc-integrations-hub' === $slug ) {
				continue;
			}
			$cat = self::resolve_category( $slug, $route );
			if ( '' === $cat ) {
				continue;
			}
			if ( ! isset( $categories[ $cat ] ) ) {
				$categories[ $cat ] = array(
					'label'        => isset( $defs[ $cat ]['label'] ) ? (string) $defs[ $cat ]['label'] : $cat,
					'order'        => isset( $defs[ $cat ]['order'] ) ? (int) $defs[ $cat ]['order'] : 100,
					'description'  => isset( $defs[ $cat ]['description'] ) ? (string) $defs[ $cat ]['description'] : '',
					'default_slug' => '',
					'routes'       => array(),
				);
			}
			$categories[ $cat ]['routes'][ $slug ] = $route;
		}

		foreach ( $categories as $cat => $row ) {
			$categories[ $cat ]['default_slug'] = self::pick_default_slug( $row['routes'] );
		}

		uasort(
			$categories,
			static function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 100 ) ) <=> ( (int) ( $b['order'] ?? 100 ) );
			}
		);

		return $categories;
	}

	/**
	 * @param array<string, array<string, mixed>> $routes Routes in category.
	 * @return string
	 */
	public static function pick_default_slug( array $routes ) {
		if ( empty( $routes ) ) {
			return '';
		}

		$best_slug  = '';
		$best_order = PHP_INT_MAX;

		foreach ( $routes as $slug => $route ) {
			$order = (int) ( $route['order'] ?? 100 );
			if ( $order < $best_order ) {
				$best_order = $order;
				$best_slug  = $slug;
			}
		}

		return '' !== $best_slug ? $best_slug : (string) array_key_first( $routes );
	}

	/**
	 * Routes for a category, sorted for sub-navigation.
	 *
	 * @param string $category_id Category id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_category_routes( $category_id ) {
		$category_id = sanitize_key( (string) $category_id );
		$categories  = self::get_active_categories();
		if ( ! isset( $categories[ $category_id ]['routes'] ) ) {
			return array();
		}

		$routes = $categories[ $category_id ]['routes'];

		uasort(
			$routes,
			static function ( $a, $b ) {
				$oa = (int) ( $a['order'] ?? 100 );
				$ob = (int) ( $b['order'] ?? 100 );
				if ( $oa === $ob ) {
					return strcasecmp( (string) ( $a['label'] ?? '' ), (string) ( $b['label'] ?? '' ) );
				}
				return $oa <=> $ob;
			}
		);

		return $routes;
	}

	/**
	 * Resolve active integrations category from route context or query arg.
	 *
	 * @param array<string, string> $ctx Current app context.
	 * @return string
	 */
	public static function get_current_category( array $ctx ) {
		$menu_slug = isset( $ctx['menu_slug'] ) ? sanitize_key( (string) $ctx['menu_slug'] ) : '';
		if ( 'rwgc-integrations-hub' === $menu_slug ) {
			return '';
		}

		if ( isset( $_GET[ self::CATEGORY_QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$from_query = sanitize_key( wp_unslash( (string) $_GET[ self::CATEGORY_QUERY_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( '' !== $from_query && isset( self::get_category_definitions()[ $from_query ] ) ) {
				return $from_query;
			}
		}

		if ( '' !== $menu_slug && class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			$routes = RWGC_Admin_Route_Registry::get_routes();
			if ( isset( $routes[ $menu_slug ] ) ) {
				return self::resolve_category( $menu_slug, $routes[ $menu_slug ] );
			}
		}

		if ( ! empty( $ctx['integration_category'] ) ) {
			return sanitize_key( (string) $ctx['integration_category'] );
		}

		return '';
	}

	/**
	 * URL for an integrations category landing (first route in category).
	 *
	 * @param string $category_id Category id.
	 * @return string
	 */
	public static function get_category_url( $category_id ) {
		$category_id = sanitize_key( (string) $category_id );
		$categories  = self::get_active_categories();
		if ( isset( $categories[ $category_id ]['default_slug'] ) && '' !== $categories[ $category_id ]['default_slug'] ) {
			return admin_url( 'admin.php?page=' . rawurlencode( (string) $categories[ $category_id ]['default_slug'] ) );
		}
		return add_query_arg(
			self::CATEGORY_QUERY_ARG,
			$category_id,
			admin_url( 'admin.php?page=rwgc-integrations-hub' )
		);
	}

	/**
	 * Replace flat route dump on integrations hub with category cards.
	 *
	 * @param array<int, array<string, mixed>> $cards      Hub cards.
	 * @param string                           $section_id Section id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_integrations_hub_cards( $cards, $section_id ) {
		if ( 'integrations' !== $section_id ) {
			return $cards;
		}

		$out = array();
		foreach ( self::get_active_categories() as $cat_id => $row ) {
			$out[] = array(
				'menu_slug'   => '',
				'label'       => (string) ( $row['label'] ?? $cat_id ),
				'description' => (string) ( $row['description'] ?? '' ),
				'url'         => self::get_category_url( $cat_id ),
				'category'    => $cat_id,
			);
		}

		return $out;
	}
}
