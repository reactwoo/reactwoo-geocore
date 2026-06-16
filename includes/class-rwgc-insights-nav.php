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
 * Horizontal Insights sub-navigation — works with and without platform shell.
 */
class RWGC_Insights_Nav {

	/**
	 * @return void
	 */
	public static function init() {
		// Reserved for filters.
	}

	/**
	 * Insights tab items (slug => label).
	 *
	 * @return array<string, string>
	 */
	public static function get_items() {
		$items = array(
			'rwgc-insights-hub'          => __( 'Capability map', 'reactwoo-geocore' ),
			'rwgc-insights-readiness'    => __( 'Setup & readiness', 'reactwoo-geocore' ),
			'rwgc-insights-ai'           => __( 'AI opportunities', 'reactwoo-geocore' ),
			'rwgc-usage-audience'        => __( 'Audience insights', 'reactwoo-geocore' ),
			'rwgc-insights-experiments'  => __( 'Experience performance', 'reactwoo-geocore' ),
			'rwgc-usage-campaign'        => __( 'Campaign insights', 'reactwoo-geocore' ),
		);

		if ( class_exists( 'WooCommerce', false ) && self::commerce_attribution_available() ) {
			$items['rwgcm-attribution'] = __( 'Commerce performance', 'reactwoo-geocore' );
		}

		/**
		 * Filter Insights section tab navigation.
		 *
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
				'rwgc-insights-readiness' => array( 'insights', 'rwgc-insights-readiness' ),
				'rwgc-insights-ai'        => array( 'insights', 'rwgc-insights-ai' ),
				'rwgc-usage-audience'     => array( 'insights', 'rwgc-usage-audience' ),
				'rwgc-insights-experiments' => array( 'insights', 'rwgc-insights-experiments' ),
				'rwgc-usage-campaign'     => array( 'insights', 'rwgc-usage-campaign' ),
				'rwgcm-attribution'       => array( 'insights', 'rwgcm-attribution' ),
			);
			if ( isset( $map[ $slug ] ) ) {
				return rw_geo_app_url( $map[ $slug ][0], $map[ $slug ][1] );
			}
		}
		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * Render Insights tabs (always visible on Insights screens).
	 *
	 * @param string $current Active menu slug.
	 * @return void
	 */
	public static function render( $current ) {
		$items = self::get_items();
		if ( empty( $items ) ) {
			return;
		}

		$nav_items = array();
		foreach ( $items as $slug => $label ) {
			$nav_items[ $slug ] = array(
				'label' => $label,
				'url'   => self::get_url( $slug ),
			);
		}

		echo '<nav class="rwgc-inner-nav rwgc-insights-nav" aria-label="' . esc_attr__( 'Insights navigation', 'reactwoo-geocore' ) . '">';
		foreach ( $nav_items as $slug => $entry ) {
			$class = 'rwgc-inner-nav__link' . ( (string) $slug === (string) $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $entry['url'] ) . '">' . esc_html( $entry['label'] ) . '</a>';
		}
		echo '</nav>';
	}
}
