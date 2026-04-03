<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rwgc_is_geo_core_active' ) ) {
	/**
	 * Whether ReactWoo Geo Core is loaded (for satellite plugin guards).
	 *
	 * @return bool
	 */
	function rwgc_is_geo_core_active() {
		$active = class_exists( 'RWGC_Plugin', false );
		/**
		 * Filter Geo Core active detection (e.g. tests).
		 *
		 * @param bool $active Whether the main plugin class is present.
		 */
		return (bool) apply_filters( 'rwgc_is_geo_core_active', $active );
	}
}

if ( ! function_exists( 'rwgc_is_ready' ) ) {
	/**
	 * Whether Geo Core is ready for use.
	 *
	 * @return bool
	 */
	function rwgc_is_ready() {
		return RWGC_API::is_ready();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_data' ) ) {
	/**
	 * Get full visitor geo payload.
	 *
	 * @return array
	 */
	function rwgc_get_visitor_data() {
		return RWGC_API::get_visitor_data();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * Get visitor country code.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return RWGC_API::get_visitor_country();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country_name' ) ) {
	/**
	 * Get visitor country name.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_country_name() {
		return RWGC_API::get_visitor_country_name();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_region' ) ) {
	/**
	 * Get visitor region (informational payload).
	 *
	 * Geo Core does not use region for built-in page routing; routing is country-based.
	 * Region may still appear in REST/shortcodes/admin preview.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_region() {
		return RWGC_API::get_visitor_region();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_city' ) ) {
	/**
	 * Get visitor city string from the resolved geo payload (e.g. local MaxMind City DB or filtered API data).
	 *
	 * Geo Core does **not** use city for `RWGC_Routing` or other core routing decisions — those are **country**-based.
	 * **City-based matching and Elementor routing** are implemented in **Geo Elementor** (City Targeting add-on), including
	 * stacks where the authoritative city database is on the ReactWoo API rather than on-disk.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_city() {
		return RWGC_API::get_visitor_city();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_currency' ) ) {
	/**
	 * Get visitor currency.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_currency() {
		return RWGC_API::get_visitor_currency();
	}
}

if ( ! function_exists( 'rwgc_get_currency_for_country' ) ) {
	/**
	 * Get suggested currency for a country.
	 *
	 * @param string $country_code ISO2.
	 * @return string
	 */
	function rwgc_get_currency_for_country( $country_code ) {
		return RWGC_API::get_currency_for_country( $country_code );
	}
}

if ( ! function_exists( 'rwgc_has_country' ) ) {
	/**
	 * Whether a country is present in the mapping.
	 *
	 * @param string $country_code ISO2.
	 * @return bool
	 */
	function rwgc_has_country( $country_code ) {
		return RWGC_API::has_country( $country_code );
	}
}

if ( ! function_exists( 'rwgc_get_page_route_bundle' ) ) {
	/**
	 * Legacy page meta as a canonical default + variants bundle (after filters).
	 *
	 * Requires `rwgc_loaded` (plugins_loaded priority 5). Returns null if the engine is not booted.
	 *
	 * @param int        $page_id Page ID.
	 * @param array|null $config  Optional preloaded config array.
	 * @return RWGC_Page_Route_Bundle|null
	 */
	function rwgc_get_page_route_bundle( $page_id, $config = null ) {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return null;
		}
		return RWGC_Routing::get_page_route_bundle( $page_id, $config );
	}
}

if ( ! function_exists( 'rwgc_get_page_route_decision' ) ) {
	/**
	 * Resolved redirect decision for a page (same filters as front-end routing).
	 *
	 * @param int               $page_id Page ID.
	 * @param RWGC_Context|null $context Context or null for visitor.
	 * @param array|null        $config  Optional preloaded config.
	 * @return array|null Keys: target_page_id, reason, page_id, country, variant_id.
	 */
	function rwgc_get_page_route_decision( $page_id, $context = null, $config = null ) {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return null;
		}
		return RWGC_Routing::get_route_decision_for_page( $page_id, $context, $config );
	}
}

if ( ! function_exists( 'rwgc_ai_request_variant_draft' ) ) {
	/**
	 * Request a draft geo variant from api.reactwoo.com (does not save or publish).
	 *
	 * @param int                  $page_id     Page ID.
	 * @param array<string, mixed> $context     Optional context (e.g. country_iso2).
	 * @param string               $instructions Optional instructions.
	 * @return array<string, mixed>|\WP_Error
	 */
	function rwgc_ai_request_variant_draft( $page_id, $context = array(), $instructions = '' ) {
		if ( ! class_exists( 'RWGC_AI_Orchestrator', false ) ) {
			return new WP_Error( 'rwgc_not_loaded', __( 'Geo Core is not loaded.', 'reactwoo-geocore' ) );
		}
		return RWGC_AI_Orchestrator::request_variant_draft( $page_id, $context, $instructions );
	}
}

if ( ! function_exists( 'rwgc_platform_clear_api_token' ) ) {
	/**
	 * Clear cached JWT for api.reactwoo.com (e.g. after license change).
	 *
	 * @return void
	 */
	function rwgc_platform_clear_api_token() {
		if ( class_exists( 'RWGC_Platform_Client', false ) ) {
			RWGC_Platform_Client::clear_token_cache();
		}
	}
}

if ( ! function_exists( 'rwgc_context_snapshot' ) ) {
	/**
	 * Portable context array for events (see RWGC_Context::to_snapshot()).
	 *
	 * @param RWGC_Context|null $context Context.
	 * @return array<string, mixed>
	 */
	function rwgc_context_snapshot( $context ) {
		if ( $context instanceof RWGC_Context ) {
			return $context->to_snapshot();
		}
		return array();
	}
}

if ( ! function_exists( 'rwgc_emit_geo_event' ) ) {
	/**
	 * Emit a Geo Core event via `rwgc_geo_event` filter and action.
	 *
	 * @param RWGC_Event $event Event envelope.
	 * @return void
	 */
	function rwgc_emit_geo_event( $event ) {
		if ( ! class_exists( 'RWGC_Events', false ) || ! $event instanceof RWGC_Event ) {
			return;
		}
		RWGC_Events::emit( $event );
	}
}

if ( ! function_exists( 'rwgc_get_geo_event_types' ) ) {
	/**
	 * Known `event_type` slugs for Geo Core events (see RWGC_Event::known_event_types()).
	 *
	 * @return string[]
	 */
	function rwgc_get_geo_event_types() {
		if ( ! class_exists( 'RWGC_Event', false ) ) {
			return array();
		}
		return RWGC_Event::known_event_types();
	}
}

if ( ! function_exists( 'rwgc_get_rest_v1_url' ) ) {
	/**
	 * Public URL for a `reactwoo-geocore/v1` REST route, or empty string if REST is disabled or unavailable.
	 *
	 * @param string $endpoint Route after `v1/` (e.g. `location`, `capabilities`, `ai/variant-draft`).
	 * @return string
	 */
	function rwgc_get_rest_v1_url( $endpoint ) {
		if ( ! function_exists( 'rest_url' ) ) {
			return '';
		}
		if ( class_exists( 'RWGC_Settings', false ) && ! RWGC_Settings::get( 'rest_enabled', 1 ) ) {
			return '';
		}
		$endpoint = trim( preg_replace( '#[^a-z0-9/\-_]#i', '', (string) $endpoint ), '/' );
		if ( '' === $endpoint ) {
			return '';
		}
		$url = rest_url( 'reactwoo-geocore/v1/' . $endpoint );
		/**
		 * Filter Geo Core REST v1 URL (proxies, subdirectory installs, tests).
		 *
		 * @param string $url      Full REST URL.
		 * @param string $endpoint Sanitized route segment(s) after `v1/`.
		 */
		return (string) apply_filters( 'rwgc_rest_v1_url', $url, $endpoint );
	}
}

if ( ! function_exists( 'rwgc_get_rest_location_url' ) ) {
	/**
	 * Public URL for `GET …/location` (visitor geo), or empty string when REST is off.
	 *
	 * @return string
	 */
	function rwgc_get_rest_location_url() {
		return rwgc_get_rest_v1_url( 'location' );
	}
}

if ( ! function_exists( 'rwgc_get_rest_capabilities_url' ) ) {
	/**
	 * Public URL for REST discovery (`GET …/capabilities`), or empty string if REST is disabled or unavailable.
	 *
	 * @return string
	 */
	function rwgc_get_rest_capabilities_url() {
		return rwgc_get_rest_v1_url( 'capabilities' );
	}
}

if ( ! function_exists( 'rwgc_is_woocommerce_active' ) ) {
	/**
	 * Whether WooCommerce is loaded (for Geo Commerce and other satellite plugins).
	 *
	 * @return bool
	 */
	function rwgc_is_woocommerce_active() {
		$active = class_exists( 'WooCommerce', false );
		/**
		 * Filter WooCommerce active detection (e.g. tests or custom stacks).
		 *
		 * @param bool $active Whether the WooCommerce class exists.
		 */
		return (bool) apply_filters( 'rwgc_is_woocommerce_active', $active );
	}
}

