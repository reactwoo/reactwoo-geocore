<?php
/**
 * Targeting section navigation — Assistant, Variants, Rules, Advanced.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Curates Targeting IA: variants live here (not top-level or under Experiences).
 */
class RWGC_Admin_Targeting_Nav {

	/**
	 * Slugs shown as Targeting section tabs.
	 *
	 * @return array<int, string>
	 */
	public static function get_section_tab_slugs() {
		$slugs = array(
			'rwgc-targeting-hub',
			'rwgc-suite-variants',
			'rwgc-visibility-rules',
			'rwgc-targeting-advanced',
		);

		/**
		 * @param array<int, string> $slugs Targeting tab menu slugs.
		 */
		return apply_filters( 'rwgc_targeting_section_tab_slugs', $slugs );
	}

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_app_sections', array( __CLASS__, 'filter_primary_sections' ), 20 );
		add_filter( 'rwgc_app_routes', array( __CLASS__, 'normalize_targeting_routes' ), 56 );
		add_filter( 'rwgc_section_hub_cards', array( __CLASS__, 'filter_targeting_hub_cards' ), 10, 2 );
	}

	/**
	 * Six-item primary nav: hide Commerce from sidebar (routes remain for deep links).
	 *
	 * @param array<string, array<string, mixed>> $sections Sections.
	 * @return array<string, array<string, mixed>>
	 */
	public static function filter_primary_sections( $sections ) {
		if ( ! is_array( $sections ) ) {
			return array();
		}
		if ( isset( $sections['commerce'] ) ) {
			$sections['commerce']['is_active_callback'] = '__return_false';
		}
		return $sections;
	}

	/**
	 * @param array<string, array<string, mixed>> $routes Routes.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize_targeting_routes( $routes ) {
		if ( ! is_array( $routes ) ) {
			return array();
		}

		$tab_slugs = array_flip( self::get_section_tab_slugs() );
		$labels    = array(
			'rwgc-targeting-hub'       => __( 'Assistant', 'reactwoo-geocore' ),
			'rwgc-suite-variants'      => __( 'Variants', 'reactwoo-geocore' ),
			'rwgc-visibility-rules'    => __( 'Rules', 'reactwoo-geocore' ),
			'rwgc-targeting-advanced'  => __( 'Advanced', 'reactwoo-geocore' ),
		);
		$orders    = array(
			'rwgc-targeting-hub'      => 5,
			'rwgc-suite-variants'     => 15,
			'rwgc-visibility-rules'   => 20,
			'rwgc-targeting-advanced' => 35,
		);

		if ( isset( $routes['rwgc-suite-variants'] ) ) {
			$routes['rwgc-suite-variants']['section'] = 'targeting';
		}

		foreach ( $routes as $slug => $route ) {
			if ( ( $route['section'] ?? '' ) !== 'targeting' ) {
				continue;
			}
			if ( isset( $tab_slugs[ $slug ] ) ) {
				$routes[ $slug ]['is_section_nav'] = true;
				if ( isset( $labels[ $slug ] ) ) {
					$routes[ $slug ]['label'] = $labels[ $slug ];
				}
				if ( isset( $orders[ $slug ] ) ) {
					$routes[ $slug ]['order'] = $orders[ $slug ];
				}
			} else {
				$routes[ $slug ]['is_section_nav'] = false;
			}
		}

		return $routes;
	}

	/**
	 * Assistant is the default Targeting view — no hub cards on landing.
	 *
	 * @param array<int, array<string, mixed>> $cards      Cards.
	 * @param string                           $section_id Section id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_targeting_hub_cards( $cards, $section_id ) {
		if ( 'targeting' === $section_id ) {
			return array();
		}
		return is_array( $cards ) ? $cards : array();
	}

	/**
	 * @param string $current Current menu slug.
	 * @return void
	 */
	public static function render_tabs( $current ) {
		if ( function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell() ) {
			return;
		}
		if ( ! class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return;
		}
		$routes = RWGC_Admin_Route_Registry::get_routes_for_section( 'targeting' );
		if ( empty( $routes ) ) {
			return;
		}
		$items = array();
		foreach ( $routes as $slug => $route ) {
			$url = function_exists( 'rw_geo_app_url' )
				? rw_geo_app_url( 'targeting', $slug )
				: admin_url( 'admin.php?page=' . $slug );
			$items[ $slug ] = array(
				'label' => (string) ( $route['label'] ?? $slug ),
				'url'   => $url,
			);
		}
		echo '<nav class="rwgc-inner-nav rwgc-targeting-nav" aria-label="' . esc_attr__( 'Targeting navigation', 'reactwoo-geocore' ) . '">';
		foreach ( $items as $slug => $entry ) {
			$class = 'rwgc-inner-nav__link' . ( (string) $slug === (string) $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $entry['url'] ) . '">' . esc_html( $entry['label'] ) . '</a>';
		}
		echo '</nav>';
	}
}
