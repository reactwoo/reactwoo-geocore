<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API endpoints for Geo Core.
 */
class RWGC_REST {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! RWGC_Settings::get( 'rest_enabled', 1 ) ) {
			return;
		}

		register_rest_route(
			'reactwoo-geocore/v1',
			'/location',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_location' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/capabilities',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_capabilities' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/ai/variant-draft',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_ai_variant_draft' ),
				'permission_callback' => array( __CLASS__, 'permissions_ai_draft' ),
				'args'                => array(
					'page_id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'instructions' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'country_iso2' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permissions check for location endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function permissions_check( $request ) {
		// This endpoint is intentionally public; site owners can disable it in settings.
		return true;
	}

	/**
	 * AI draft endpoints require an editor who can change pages (no automatic publish).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function permissions_ai_draft( $request ) {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * Get visitor location payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_location( $request ) {
		$data = RWGC_API::get_visitor_data();
		return rest_ensure_response( $data );
	}

	/**
	 * Non-sensitive discovery payload for satellite plugins (Geo Optimise, etc.).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_capabilities( $request ) {
		$payload = array(
			'plugin_slug'    => defined( 'RWGC_PLUGIN_SLUG' ) ? RWGC_PLUGIN_SLUG : 'reactwoo-geocore',
			'text_domain'    => defined( 'RWGC_TEXT_DOMAIN' ) ? RWGC_TEXT_DOMAIN : 'reactwoo-geocore',
			'plugin_version' => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
			'geo_ready'      => function_exists( 'rwgc_is_ready' ) ? rwgc_is_ready() : false,
			'woocommerce_active' => function_exists( 'rwgc_is_woocommerce_active' ) ? rwgc_is_woocommerce_active() : false,
			'event_types'    => class_exists( 'RWGC_Event', false ) ? RWGC_Event::known_event_types() : array(),
			'hooks'          => array(
				'geo_event_action' => 'rwgc_geo_event',
				'geo_event_filter' => 'rwgc_geo_event',
				'route_resolved'   => 'rwgc_route_variant_resolved',
			),
			// ReactWoo Geo AI / Optimise / Commerce: ready only after each plugin’s load hook fired (deps satisfied).
			'satellites'     => self::get_satellites_status(),
			// Primary extension points for satellite plugins (not exhaustive); see get_capabilities_integration_contract().
			'integration'    => self::get_capabilities_integration_contract(),
		);
		/**
		 * Filter REST capabilities discovery payload (`GET /capabilities`).
		 *
		 * @param array<string, mixed> $payload Discovery data. Includes `satellites` (per-plugin ready + version).
		 */
		return rest_ensure_response( apply_filters( 'rwgc_rest_capabilities', $payload ) );
	}

	/**
	 * Whether each official satellite finished boot (load hooks), plus semver when known.
	 *
	 * @return array<string, array{ready: bool, version: string}>
	 */
	private static function get_satellites_status() {
		$ai_ready = did_action( 'rwga_loaded' );
		$opt_ready = did_action( 'rwgo_loaded' );
		$woo_ready = did_action( 'rwgcm_loaded' );
		return array(
			'geo_ai' => array(
				'ready'   => $ai_ready > 0,
				'version' => ( $ai_ready && defined( 'RWGA_VERSION' ) ) ? (string) RWGA_VERSION : '',
			),
			'geo_optimise' => array(
				'ready'   => $opt_ready > 0,
				'version' => ( $opt_ready && defined( 'RWGO_VERSION' ) ) ? (string) RWGO_VERSION : '',
			),
			'geo_commerce' => array(
				'ready'   => $woo_ready > 0,
				'version' => ( $woo_ready && defined( 'RWGCM_VERSION' ) ) ? (string) RWGCM_VERSION : '',
			),
		);
	}

	/**
	 * Curated filter/action names for integrations (documentation contract; extend via rwgc_rest_capabilities).
	 *
	 * @return array<string, mixed> Keys: filters, actions, ai_filters (string lists).
	 */
	private static function get_capabilities_integration_contract() {
		return array(
			'filters' => array(
				'rwgc_geo_data',
				'rwgc_page_route_bundle',
				'rwgc_route_variant_decision',
				'rwgc_geo_event',
				'rwgc_geo_event_known_types',
				'rwgc_emit_route_redirect_event',
				'rwgc_rest_capabilities',
				'rwgc_rest_v1_url',
			),
			'actions' => array(
				'rwgc_loaded',
				'rwgc_geo_resolved',
				'rwgc_geo_event',
				'rwgc_route_variant_resolved',
			),
			'ai_filters' => array(
				'rwgc_ai_variant_draft_payload',
				'rwgc_ai_variant_draft_response',
			),
			'satellite_actions' => array(
				'rwga_loaded',
				'rwgo_loaded',
				'rwgcm_loaded',
				'rwgo_geo_event',
				'rwgo_route_variant_resolved',
				'rwgcm_before_cart_totals',
				'rwgo_variant_assigned',
				'rwgcm_order_attributed',
			),
			'satellite_filters' => array(
				'rwgcm_geo_data',
				'rwgo_stats_snapshot',
				'rwgcm_adjusted_unit_price',
				'rwgcm_apply_catalog_price',
				'rwga_stats_snapshot',
				'rwga_usage_display_rows',
				'rwgcm_order_visitor_geo',
				'rwgcm_checkout_order_meta',
				'rwgcm_cart_fees',
				'rwgcm_fee_rule_rows',
				'rwgcm_skip_pricing_for_cart_item',
				'rwgcm_package_rates',
				'rwgcm_coupon_allowed_for_visitor',
				'rwgcm_coupon_valid_when_country_unknown',
				'rwgcm_store_utm_on_orders',
				'rwgcm_attribution_query_keys',
				'rwgo_emit_assignment_geo_event',
				'rwgo_export_csv_filename',
			),
		);
	}

	/**
	 * POST draft geo variant suggestion (returns data only; does not save).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function post_ai_variant_draft( $request ) {
		$page_id = (int) $request->get_param( 'page_id' );
		$instructions = (string) $request->get_param( 'instructions' );
		$country      = (string) $request->get_param( 'country_iso2' );
		$context      = array();
		if ( '' !== $country ) {
			$context['country_iso2'] = $country;
		} elseif ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$context['country_iso2'] = rwgc_get_visitor_country();
		}

		$result = RWGC_AI_Orchestrator::request_variant_draft( $page_id, $context, $instructions );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}

