<?php
/**
 * Consolidated section hub screens (Insights, Settings).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders goal-section landing pages that link to registered app routes.
 */
class RWGC_Admin_Section_Hubs {

	/**
	 * @return void
	 */
	public static function init() {
		// Reserved for future hooks.
	}

	/**
	 * Commerce section home — WooCommerce pricing, fees, overlays.
	 *
	 * @return void
	 */
	public static function render_commerce_hub() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}
		$cards = self::get_hub_cards( 'commerce', 'rwgc-commerce-hub' );
		include RWGC_PATH . 'admin/views/commerce-hub-page.php';
	}

	/**
	 * Targeting section home — rules, Elementor, variants, experiments.
	 *
	 * @return void
	 */
	public static function render_targeting_hub() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}
		$cards = self::get_hub_cards( 'targeting', 'rwgc-targeting-hub' );
		include RWGC_PATH . 'admin/views/targeting-hub-page.php';
	}

	/**
	 * Insights section home — links to Geo reports, AI, Optimise, etc.
	 *
	 * @return void
	 */
	public static function render_insights_hub() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}
		$cards = self::get_hub_cards( 'insights', 'rwgc-insights-hub' );
		include RWGC_PATH . 'admin/views/insights-hub-page.php';
	}

	/**
	 * Settings section home — grouped links to Core and satellite settings.
	 *
	 * @return void
	 */
	public static function render_settings_hub() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}
		$cards = class_exists( 'RWGC_Admin_Settings_Nav', false )
			? RWGC_Admin_Settings_Nav::get_provider_hub_cards()
			: self::get_hub_cards( 'settings', 'rwgc-settings-hub' );
		include RWGC_PATH . 'admin/views/settings-hub-page.php';
	}

	/**
	 * Build hub cards from the route registry.
	 *
	 * @param string $section_id  Goal section id.
	 * @param string $exclude_slug Current hub page slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_hub_cards( $section_id, $exclude_slug = '' ) {
		if ( ! class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return array();
		}

		$section_id  = sanitize_key( (string) $section_id );
		$exclude_slug = sanitize_key( (string) $exclude_slug );
		$cards       = array();

		$skip_dashboard_slugs = array(
			'rwgcm-dashboard',
			'rwgo-dashboard',
		);

		foreach ( RWGC_Admin_Route_Registry::get_routes_for_section( $section_id ) as $slug => $route ) {
			if ( $slug === $exclude_slug ) {
				continue;
			}
			if ( isset( $route['is_section_nav'] ) && empty( $route['is_section_nav'] ) ) {
				continue;
			}
			if ( in_array( $slug, $skip_dashboard_slugs, true ) && in_array( $section_id, array( 'insights', 'commerce' ), true ) ) {
				continue;
			}
			$route_id = isset( $route['route'] ) ? sanitize_key( (string) $route['route'] ) : '';
			if ( 'insights' === $section_id && 'overview' === $route_id && 'rwga-dashboard' === $slug ) {
				continue;
			}
			$label = isset( $route['label'] ) ? (string) $route['label'] : $slug;
			if ( '' === $label ) {
				continue;
			}
			$cards[] = array(
				'menu_slug' => $slug,
				'label'     => $label,
				'url'       => admin_url( 'admin.php?page=' . rawurlencode( $slug ) ),
				'provider'  => isset( $route['provider'] ) ? (string) $route['provider'] : '',
				'module'    => isset( $route['module'] ) ? (string) $route['module'] : '',
				'route'     => isset( $route['route'] ) ? (string) $route['route'] : '',
				'description' => self::default_card_description( $slug, $route ),
			);
		}

		/**
		 * Filter hub cards for a goal section landing page.
		 *
		 * @param array<int, array<string, mixed>> $cards      Card rows.
		 * @param string                           $section_id Section id.
		 */
		return apply_filters( 'rwgc_section_hub_cards', $cards, $section_id );
	}

	/**
	 * @param string               $slug  Menu slug.
	 * @param array<string, mixed> $route Route row.
	 * @return string
	 */
	private static function default_card_description( $slug, array $route ) {
		$map = array(
			'rwgc-targeting-hub'      => '',
			'rwgc-target-types'       => __( 'Visual rule builder playground and condition reference.', 'reactwoo-geocore' ),
			'rwgc-suite-variants'     => __( 'Master and secondary page versions by country.', 'reactwoo-geocore' ),
			'geo-elementor'           => __( 'Elementor rules, geo content, and variant groups.', 'reactwoo-geocore' ),
			'geo-elementor-rules'     => __( 'Geo rules list and assignments for Elementor experiences.', 'reactwoo-geocore' ),
			'rwgo-dashboard'          => __( 'A/B tests, variants, and experiment workflows.', 'reactwoo-geocore' ),
			'rwgo-create-test'        => __( 'Wizard to launch a new page or popup experiment.', 'reactwoo-geocore' ),
			'rwgo-tests'              => __( 'Manage active tests, variants, and goals.', 'reactwoo-geocore' ),
			'rwgo-reports'            => __( 'Experiment outcomes, winners, and conversion reporting.', 'reactwoo-geocore' ),
			'rwgc-usage'              => __( 'REST usage, rule match counts, and geo API reference.', 'reactwoo-geocore' ),
			'rwgc-insights-hub'       => '',
			'rwga-analyses'           => __( 'AI analysis runs and page intelligence reports.', 'reactwoo-geocore' ),
			'rwga-recommendations'    => __( 'Actionable recommendations from Geo AI workflows.', 'reactwoo-geocore' ),
			'rwgc-settings'           => __( 'Detection, MaxMind, and core platform options.', 'reactwoo-geocore' ),
			'rwgc-tools'              => __( 'Database upload, visitor preview, and diagnostics.', 'reactwoo-geocore' ),
			'rwgc-addons'             => __( 'Install and manage ReactWoo geo add-ons.', 'reactwoo-geocore' ),
			'rwgc-commerce-hub'       => '',
			'rwgcm-dashboard'         => __( 'Commerce overview, stats, and quick links.', 'reactwoo-geocore' ),
			'rwgcm-pricing'           => __( 'Regional pricing rules with Geo Core visitor conditions.', 'reactwoo-geocore' ),
			'rwgcm-fees'              => __( 'Country-based cart fees and surcharges.', 'reactwoo-geocore' ),
			'rwgcm-product-overlays'  => __( 'Per-product geo messaging and overlays.', 'reactwoo-geocore' ),
			'rwgcm-attribution'       => __( 'UTM and campaign attribution for commerce reporting.', 'reactwoo-geocore' ),
			'rwgcm-settings'          => __( 'WooCommerce geo commerce behaviour and defaults.', 'reactwoo-geocore' ),
			'rwgcp-geocore-pro'       => __( 'Licence, cloud, and Google integrations (in-app tabs).', 'reactwoo-geocore' ),
		);

		if ( isset( $map[ $slug ] ) && '' !== $map[ $slug ] ) {
			return $map[ $slug ];
		}

		$provider = isset( $route['provider'] ) ? (string) $route['provider'] : '';
		if ( 'geo_ai' === $provider ) {
			return __( 'Geo AI reporting and recommendations.', 'reactwoo-geocore' );
		}
		if ( 'geo_optimise' === $provider ) {
			return __( 'Geo Optimise experiment analytics.', 'reactwoo-geocore' );
		}
		if ( 'geo_commerce' === $provider ) {
			return __( 'Geo Commerce configuration.', 'reactwoo-geocore' );
		}
		if ( 'geocore_pro' === $provider ) {
			return __( 'GeoCore Pro licence and integration settings.', 'reactwoo-geocore' );
		}

		return __( 'Open this screen in ReactWoo Geo.', 'reactwoo-geocore' );
	}
}
