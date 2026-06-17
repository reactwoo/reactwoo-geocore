<?php
/**
 * Experiences section — curated hub cards and nav labels.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevents the experiences hub from dumping every registered satellite route.
 */
class RWGC_Admin_Experiences_Nav {

	/**
	 * Menu slugs allowed on the experiences hub (curated product surface).
	 *
	 * @return array<int, string>
	 */
	public static function get_hub_slugs() {
		$slugs = array(
			'geo-elementor-rules',
			'geo-content',
			'rwgo-dashboard',
			'rwgo-reports',
		);

		/**
		 * @param array<int, string> $slugs Menu slugs shown on Experiences hub.
		 */
		return apply_filters( 'rwgc_experiences_hub_slugs', $slugs );
	}

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_section_hub_cards', array( __CLASS__, 'filter_experiences_hub_cards' ), 10, 2 );
		add_filter( 'rwgc_app_routes', array( __CLASS__, 'normalize_experience_route_labels' ), 55 );
	}

	/**
	 * @param array<int, array<string, mixed>> $cards      Hub cards.
	 * @param string                           $section_id Section id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_experiences_hub_cards( $cards, $section_id ) {
		if ( 'experiences' !== $section_id || ! is_array( $cards ) ) {
			return $cards;
		}

		$allowed = array_flip( self::get_hub_slugs() );
		$out     = array();

		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$slug = isset( $card['menu_slug'] ) ? sanitize_key( (string) $card['menu_slug'] ) : '';
			if ( '' === $slug || ! isset( $allowed[ $slug ] ) ) {
				continue;
			}
			if ( 'geo-elementor-rules' === $slug ) {
				$card['label'] = __( 'Dynamic content', 'reactwoo-geocore' );
			}
			if ( 'rwgo-dashboard' === $slug ) {
				$card['label'] = __( 'Experiments', 'reactwoo-geocore' );
			}
			$out[] = $card;
		}

		return $out;
	}

	/**
	 * Human labels for experiences section tabs.
	 *
	 * @param array<string, array<string, mixed>> $routes Routes.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize_experience_route_labels( $routes ) {
		if ( ! is_array( $routes ) ) {
			return array();
		}

		$labels = array(
			'geo-elementor-rules' => __( 'Dynamic content', 'reactwoo-geocore' ),
			'geo-content'         => __( 'Geo content', 'reactwoo-geocore' ),
			'rwgo-dashboard'      => __( 'Experiments', 'reactwoo-geocore' ),
			'rwgo-reports'        => __( 'Reports', 'reactwoo-geocore' ),
		);

		foreach ( $routes as $slug => $route ) {
			if ( ( $route['section'] ?? '' ) !== 'experiences' ) {
				continue;
			}
			if ( 'rwgc-experiences-hub' === $slug ) {
				$routes[ $slug ]['is_section_nav'] = false;
			}
			if ( isset( $labels[ $slug ] ) ) {
				$routes[ $slug ]['label'] = $labels[ $slug ];
			}
		}

		return $routes;
	}
}
