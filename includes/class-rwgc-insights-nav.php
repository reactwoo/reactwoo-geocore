<?php
/**
 * Insights section tab navigation (Capability Map + report tabs).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Horizontal Insights sub-navigation — hidden when platform shell renders section tabs.
 */
class RWGC_Insights_Nav {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_app_routes', array( __CLASS__, 'normalize_insights_routes' ), 55 );
	}

	/**
	 * @param array<string, array<string, mixed>> $routes Routes.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize_insights_routes( $routes ) {
		if ( ! is_array( $routes ) ) {
			return array();
		}
		$items     = self::get_items();
		$nav_slugs = array_flip( array_keys( $items ) );
		foreach ( $routes as $slug => $route ) {
			if ( ( $route['section'] ?? '' ) !== 'insights' ) {
				continue;
			}
			$routes[ $slug ]['is_section_nav'] = isset( $nav_slugs[ $slug ] );
			if ( isset( $nav_slugs[ $slug ] ) ) {
				$routes[ $slug ]['label'] = $items[ $slug ];
			}
		}
		return $routes;
	}

	/**
	 * Insights tab items (slug => label).
	 *
	 * @return array<string, string>
	 */
	public static function get_items() {
		$items = array(
			'rwgc-insights-hub'         => __( 'Capability map', 'reactwoo-geocore' ),
			'rwgc-insights-readiness'   => __( 'Setup', 'reactwoo-geocore' ),
			'rwgc-insights-ai'          => __( 'AI UX Reviewer', 'reactwoo-geocore' ),
			'rwgc-usage-audience'       => __( 'Audience', 'reactwoo-geocore' ),
			'rwgc-usage-campaign'       => __( 'Campaigns', 'reactwoo-geocore' ),
			'rwgc-insights-experiments' => __( 'Experiences', 'reactwoo-geocore' ),
		);

		if ( class_exists( 'WooCommerce', false ) && self::commerce_attribution_available() ) {
			$items['rwgcm-attribution'] = __( 'Commerce', 'reactwoo-geocore' );
		}

		/**
		 * @param array<string, string> $items Slug => label.
		 */
		return apply_filters( 'rwgc_insights_nav_items', $items );
	}

	/**
	 * @return bool
	 */
	private static function commerce_attribution_available() {
		if ( function_exists( 'rw_geo_app_url' ) ) {
			return true;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'reactwoo-geo-commerce/reactwoo-geo-commerce.php' );
	}

	/**
	 * Resolve URL for an Insights tab slug.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function get_url( $slug ) {
		$slug = sanitize_key( (string) $slug );
		if ( '' === $slug ) {
			return admin_url( 'admin.php?page=rwgc-insights-hub' );
		}
		if ( function_exists( 'rw_geo_app_url' ) ) {
			$map = array(
				'rwgc-insights-hub'         => array( 'insights', 'rwgc-insights-hub' ),
				'rwgc-insights-readiness'   => array( 'insights', 'rwgc-insights-readiness' ),
				'rwgc-insights-ai'          => array( 'insights', 'rwgc-insights-ai' ),
				'rwgc-usage-audience'       => array( 'insights', 'rwgc-usage-audience' ),
				'rwgc-insights-experiments' => array( 'insights', 'rwgc-insights-experiments' ),
				'rwgc-usage-campaign'       => array( 'insights', 'rwgc-usage-campaign' ),
				'rwgcm-attribution'         => array( 'insights', 'rwgcm-attribution' ),
			);
			if ( isset( $map[ $slug ] ) ) {
				return rw_geo_app_url( $map[ $slug ][0], $map[ $slug ][1] );
			}
		}
		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * Render Insights tabs when platform shell is off (avoid duplicate tab rows).
	 *
	 * @param string $current Active menu slug.
	 * @return void
	 */
	public static function render( $current ) {
		if ( function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell() ) {
			return;
		}

		$items = self::get_items();
		if ( empty( $items ) ) {
			return;
		}

		echo '<nav class="rwgc-inner-nav rwgc-insights-nav" aria-label="' . esc_attr__( 'Insights navigation', 'reactwoo-geocore' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( (string) $slug === (string) $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( self::get_url( $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}
}
