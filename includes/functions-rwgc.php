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

if ( ! function_exists( 'rwgc_is_pro_enabled' ) ) {
	/**
	 * Whether a Pro runtime extension is active for this site.
	 *
	 * Geo Core defaults to false; GeoCore Pro can return true through the filter.
	 *
	 * @return bool
	 */
	function rwgc_is_pro_enabled() {
		return (bool) apply_filters( 'rwgc_pro_enabled', false );
	}
}

if ( ! function_exists( 'rwgc_advanced_targeting_enabled' ) ) {
	/**
	 * Whether multi-condition portable targeting UI is available (GeoCore Pro).
	 *
	 * GeoCore Free surfaces country-only in builders; GeoCore Pro enables the full selector set in Elementor, Gutenberg, and admin.
	 *
	 * @return bool
	 */
	function rwgc_advanced_targeting_enabled() {
		return (bool) apply_filters( 'rwgc_advanced_targeting_enabled', false );
	}
}

if ( ! function_exists( 'rwgc_normalize_visibility_mode' ) ) {
	/**
	 * Normalize visibility mode to canonical values.
	 *
	 * Backward compatible aliases:
	 * - show => show_if
	 * - hide => hide_if
	 *
	 * @param mixed $mode Raw mode.
	 * @return string
	 */
	function rwgc_normalize_visibility_mode( $mode ) {
		$raw = sanitize_key( (string) $mode );
		if ( in_array( $raw, array( 'hide_if', 'hide', 'restrict', 'suppress' ), true ) ) {
			return 'hide_if';
		}
		return 'show_if';
	}
}

if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
	/**
	 * Determine whether content should render for a mode + match result.
	 *
	 * @param mixed $mode    Raw visibility mode.
	 * @param bool  $matched Whether rules matched.
	 * @return bool
	 */
	function rwgc_visibility_mode_allows_render( $mode, $matched ) {
		$normalized = rwgc_normalize_visibility_mode( $mode );
		if ( 'hide_if' === $normalized ) {
			return ! $matched;
		}
		return (bool) $matched;
	}
}

if ( ! function_exists( 'rwgc_get_portable_targeting_editor_context' ) ) {
	/**
	 * Portable JSON authoring context (audiences, campaigns, Pro flag) for admin and editors.
	 *
	 * @return array<string, mixed>
	 */
	function rwgc_get_portable_targeting_editor_context() {
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return array(
				'pro'       => (bool) apply_filters( 'rwgc_pro_enabled', false ),
				'audiences' => array(),
				'campaigns' => array(),
			);
		}
		return RWGC_Targeting_Rule_Set_Schema::get_editor_context();
	}
}

if ( ! function_exists( 'rwgc_get_visibility_rule_set' ) ) {
	/**
	 * Sanitized portable rule set from the Core visibility rules library CPT.
	 *
	 * @param int $post_id Library post ID ({@see RWGC_Visibility_Rule_CPT::POST_TYPE}).
	 * @return array<string, mixed>|null
	 */
	function rwgc_get_visibility_rule_set( $post_id ) {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return null;
		}
		return RWGC_Visibility_Rule_Repository::get_rule_set( $post_id );
	}
}

if ( ! function_exists( 'rwgc_get_settings_providers' ) ) {
	/**
	 * Active settings providers and their routes (platform shell).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function rwgc_get_settings_providers() {
		if ( ! class_exists( 'RWGC_Admin_Settings_Nav', false ) ) {
			return array();
		}
		return RWGC_Admin_Settings_Nav::get_active_providers();
	}
}

if ( ! function_exists( 'rwgc_get_platform_sync_status' ) ) {
	/**
	 * Shell sync pill snapshot (label, hint, variant, url).
	 *
	 * @return array<string, string>
	 */
	function rwgc_get_platform_sync_status() {
		if ( ! class_exists( 'RWGC_Platform_Sync_Status', false ) ) {
			return array();
		}
		return RWGC_Platform_Sync_Status::get_snapshot();
	}
}

if ( ! function_exists( 'rwgc_get_platform_integrations' ) ) {
	/**
	 * Integration connection rows for the Integrations hub.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function rwgc_get_platform_integrations() {
		if ( ! class_exists( 'RWGC_Platform_Integrations', false ) ) {
			return array();
		}
		return RWGC_Platform_Integrations::get_items();
	}
}

if ( ! function_exists( 'rwgc_get_setup_progress' ) ) {
	/**
	 * Platform onboarding checklist for Overview (steps, completed, total, percent).
	 *
	 * @return array{steps: array<int, array<string, mixed>>, completed: int, total: int, percent: int}
	 */
	function rwgc_get_setup_progress() {
		if ( ! class_exists( 'RWGC_Onboarding', false ) ) {
			return array(
				'steps'     => array(),
				'completed' => 0,
				'total'     => 0,
				'percent'   => 0,
			);
		}
		return RWGC_Onboarding::get_setup_progress();
	}
}

if ( ! function_exists( 'rwgc_get_maxmind_admin_url' ) ) {
	/**
	 * Admin URL for MaxMind credentials and GeoLite2 country database management.
	 *
	 * @return string
	 */
	function rwgc_get_maxmind_admin_url() {
		$slug = 'rwgc-integrations-maxmind';
		if ( function_exists( 'rw_geo_app_url' ) && function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell() ) {
			return rw_geo_app_url( 'integrations', $slug );
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
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

if ( ! function_exists( 'rwgc_build_ai_snapshot' ) ) {
	/**
	 * Build a compact site intelligence snapshot for Geo AI cloud workflows.
	 *
	 * Does not include page content, Elementor JSON, or personal data.
	 *
	 * @param array<string, mixed> $context Optional builder context.
	 * @return array<string, mixed>
	 */
	function rwgc_build_ai_snapshot( array $context = array() ) {
		if ( ! class_exists( 'RWGC_AI_Snapshot_Builder', false ) ) {
			return array();
		}
		return RWGC_AI_Snapshot_Builder::build( $context );
	}
}

if ( ! function_exists( 'rwgc_get_ai_snapshot_hash' ) ) {
	/**
	 * SHA-256 hash of the current site intelligence snapshot.
	 *
	 * @return string
	 */
	function rwgc_get_ai_snapshot_hash() {
		if ( ! class_exists( 'RWGC_AI_Snapshot_Builder', false ) ) {
			return '';
		}
		$payload = RWGC_AI_Snapshot_Builder::build();
		return isset( $payload['snapshot_hash'] ) ? (string) $payload['snapshot_hash'] : '';
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

if ( ! function_exists( 'rwgc_get_suite_handoff_request_context' ) ) {
	/**
	 * Parse Geo Suite workflow handoff query args (`rwgc_handoff`, `rwgc_from`, `rwgc_launcher`, `rwgc_variant_page_id`).
	 * Satellites use this on their admin screens to show context after deep links from Suite Home / Getting Started / next steps.
	 *
	 * @return array{active: bool, from: string, launcher: string, variant_page_id: int, master_page_id: int, geo_target: string}
	 */
	function rwgc_get_suite_handoff_request_context() {
		$out = array(
			'active'            => false,
			'from'              => '',
			'launcher'          => '',
			'variant_page_id'   => 0,
			'master_page_id'    => 0,
			'geo_target'        => '',
		);
		if ( ! is_admin() ) {
			return $out;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request context for UX.
		if ( ! isset( $_GET['rwgc_handoff'] ) || '1' !== (string) wp_unslash( $_GET['rwgc_handoff'] ) ) {
			return $out;
		}
		$out['active'] = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_from'] ) ) {
			$out['from'] = sanitize_key( wp_unslash( (string) $_GET['rwgc_from'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_launcher'] ) ) {
			$out['launcher'] = sanitize_key( wp_unslash( (string) $_GET['rwgc_launcher'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_variant_page_id'] ) ) {
			$out['variant_page_id'] = absint( wp_unslash( $_GET['rwgc_variant_page_id'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_master_page_id'] ) ) {
			$out['master_page_id'] = absint( wp_unslash( $_GET['rwgc_master_page_id'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwga_geo_target'] ) ) {
			$out['geo_target'] = strtoupper( substr( sanitize_text_field( wp_unslash( (string) $_GET['rwga_geo_target'] ) ), 0, 2 ) );
		}
		/**
		 * Filter parsed suite handoff request context (tests or custom entry points).
		 *
		 * @param array{active: bool, from: string, launcher: string, variant_page_id: int, master_page_id: int, geo_target: string} $out Parsed values.
		 */
		$filtered = apply_filters( 'rwgc_suite_handoff_request_context', $out );
		return is_array( $filtered ) ? array_merge( $out, $filtered ) : $out;
	}
}

if ( ! function_exists( 'rwgc_is_builder_edit_request' ) ) {
	/**
	 * Whether the current request is an Elementor (or future builder) edit/preview surface that should skip geo routing.
	 *
	 * Delegates to {@see RWGC_Routing::is_builder_edit_request()}.
	 *
	 * @param int|null $post_id Optional document ID for capability checks.
	 * @return bool
	 */
	function rwgc_is_builder_edit_request( $post_id = null ) {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return false;
		}
		return RWGC_Routing::is_builder_edit_request( $post_id );
	}
}

if ( ! function_exists( 'rwgc_is_builder_context' ) ) {
	/**
	 * Alias for builder/editor detection used by Geo Elementor and other integrations.
	 * Debug mode does not change the return value.
	 *
	 * @param int|null $post_id Optional document ID for capability checks.
	 * @return bool
	 */
	function rwgc_is_builder_context( $post_id = null ) {
		return rwgc_is_builder_edit_request( $post_id );
	}
}

if ( ! function_exists( 'rwgc_get_target_types' ) ) {
	/**
	 * All registered target type definitions (suite targeting vocabulary).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function rwgc_get_target_types() {
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			return array();
		}
		RWGC_Target_Registry::init();
		return RWGC_Target_Registry::instance()->get_target_types();
	}
}

if ( ! function_exists( 'rwgc_get_available_target_types' ) ) {
	/**
	 * Target types that pass availability checks for this site/request.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function rwgc_get_available_target_types() {
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			return array();
		}
		RWGC_Target_Registry::init();
		return RWGC_Target_Registry::instance()->get_available_target_types();
	}
}

if ( ! function_exists( 'rwgc_get_target_definition' ) ) {
	/**
	 * Single target definition by key.
	 *
	 * @param string $key Target key.
	 * @return array<string, mixed>|null
	 */
	function rwgc_get_target_definition( $key ) {
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			return null;
		}
		RWGC_Target_Registry::init();
		return RWGC_Target_Registry::instance()->get_target_type( (string) $key );
	}
}

if ( ! function_exists( 'rwgc_get_context_snapshot' ) ) {
	/**
	 * Normalised context for the current visitor/request (array shape).
	 *
	 * @return array<string, mixed>
	 */
	function rwgc_get_context_snapshot() {
		if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return array();
		}
		$flat = RWGC_Context_Resolver::resolve_current()->to_array();
		if ( class_exists( 'RWGC_Context_Snapshot_Formatter', false ) ) {
			return RWGC_Context_Snapshot_Formatter::enrich( $flat );
		}
		return $flat;
	}
}

if ( ! function_exists( 'rwgc_get_context_value' ) ) {
	/**
	 * One resolved value from the current context snapshot.
	 *
	 * @param string $key Target key.
	 * @return mixed|null
	 */
	function rwgc_get_context_value( $key ) {
		if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return null;
		}
		return RWGC_Context_Resolver::resolve_target_value( (string) $key );
	}
}

if ( ! function_exists( 'rwgc_resolve_preview_context' ) ) {
	/**
	 * Simulated context for admin previews (overrides merged when supported).
	 *
	 * @param array<string, mixed> $overrides Keyed overrides.
	 * @return array<string, mixed>
	 */
	function rwgc_resolve_preview_context( $overrides = array() ) {
		if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return is_array( $overrides ) ? $overrides : array();
		}
		return RWGC_Context_Resolver::resolve_for_preview( is_array( $overrides ) ? $overrides : array() )->to_array();
	}
}

if ( ! function_exists( 'rwgc_is_target_available' ) ) {
	/**
	 * Whether a target key is available for rule building on this site.
	 *
	 * @param string $key Target key.
	 * @return bool
	 */
	function rwgc_is_target_available( $key ) {
		$def = rwgc_get_target_definition( $key );
		if ( null === $def || ! class_exists( 'RWGC_Target_Availability', false ) ) {
			return false;
		}
		return RWGC_Target_Availability::is_available( $def );
	}
}

if ( ! function_exists( 'rw_geo_register_module' ) ) {
	/**
	 * Register or replace a Geo Suite module row (alias for rwgc_register_modules).
	 *
	 * @param array<string, mixed> $module Module row: id, label, description, optional active, admin_url, install_url, is_active_callback, plugin_files.
	 * @return void
	 */
	function rw_geo_register_module( array $module ) {
		if ( empty( $module['id'] ) || ! is_string( $module['id'] ) ) {
			return;
		}
		$id = $module['id'];
		add_filter(
			'rwgc_register_modules',
			static function ( $modules ) use ( $module, $id ) {
				if ( ! is_array( $modules ) ) {
					$modules = array();
				}
				$replaced = false;
				foreach ( $modules as $i => $row ) {
					if ( is_array( $row ) && isset( $row['id'] ) && (string) $row['id'] === (string) $id ) {
						$modules[ $i ] = array_merge( $row, $module );
						$replaced      = true;
						break;
					}
				}
				if ( ! $replaced ) {
					$modules[] = $module;
				}
				return $modules;
			}
		);
	}
}

if ( ! function_exists( 'rw_geo_register_dashboard_card' ) ) {
	/**
	 * Register a Geo Core dashboard summary card (fires on rwgc_dashboard_satellite_panels).
	 *
	 * @param callable $callback Echoes card markup (same pattern as satellite summary cards).
	 * @return void
	 */
	function rw_geo_register_dashboard_card( $callback ) {
		if ( is_callable( $callback ) ) {
			add_action( 'rwgc_dashboard_satellite_panels', $callback, 10, 0 );
		}
	}
}

if ( ! function_exists( 'rw_geo_register_app_route' ) ) {
	/**
	 * Register an in-app route (app shell section nav; wp-admin submenu optional).
	 *
	 * @param array<string, mixed> $args section, route, menu_slug, label, callback; optional register_wp_submenu (default false), show_in_wp_sidebar (legacy alias).
	 * @return string|false Hook suffix.
	 */
	function rw_geo_register_app_route( array $args ) {
		if ( empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}
		$slug = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug ) {
			return false;
		}

		if ( ! isset( $args['show_in_wp_sidebar'] ) ) {
			$args['show_in_wp_sidebar'] = false;
		}

		if ( class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			RWGC_Admin_Route_Registry::register_route( $args );
		}

		$page_title = isset( $args['page_title'] ) ? (string) $args['page_title'] : ( isset( $args['label'] ) ? (string) $args['label'] : $slug );
		$menu_title = isset( $args['menu_title'] ) ? (string) $args['menu_title'] : ( isset( $args['label'] ) ? (string) $args['label'] : $slug );

		$register_wp_submenu = class_exists( 'RWGC_Admin_Route_Registry', false )
			? RWGC_Admin_Route_Registry::resolve_register_wp_submenu( $args )
			: ! empty( $args['register_wp_submenu'] ) || ! empty( $args['show_in_wp_sidebar'] );

		$merged = array_merge(
			$args,
			array(
				'page_title' => $page_title,
				'menu_title' => $menu_title,
				'menu_slug'  => $slug,
			)
		);

		if ( $register_wp_submenu ) {
			return rw_geo_register_admin_submenu( $merged );
		}

		$parent_slug = function_exists( 'rwgc_admin_menu_parent' ) ? rwgc_admin_menu_parent() : 'rwgc-dashboard';
		if ( $slug === $parent_slug ) {
			return false;
		}

		if ( class_exists( 'RWGC_Admin_Platform', false ) ) {
			return RWGC_Admin_Platform::register_shell_only_page( $merged );
		}

		return rw_geo_register_admin_submenu( $merged );
	}
}

if ( ! function_exists( 'rw_geo_app_url' ) ) {
	/**
	 * Admin URL for a goal section route in the ReactWoo Geo app shell.
	 *
	 * @param string $section_id Goal section id (overview, targeting, commerce, …).
	 * @param string $menu_slug  Optional menu slug.
	 * @return string
	 */
	function rw_geo_app_url( $section_id, $menu_slug = '' ) {
		if ( class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return RWGC_Admin_Route_Registry::get_url( $section_id, $menu_slug );
		}
		$menu_slug = sanitize_key( (string) $menu_slug );
		if ( '' === $menu_slug ) {
			$menu_slug = 'rwgc-dashboard';
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $menu_slug ) );
	}
}

if ( ! function_exists( 'rw_geo_register_admin_submenu' ) ) {
	/**
	 * Register a wp-admin submenu under the ReactWoo Geo hub (hidden from sidebar by default).
	 *
	 * @param array<string, mixed> $args page_title, menu_title, capability, menu_slug, callback; optional position.
	 * @return string|false Hook suffix from add_submenu_page.
	 */
	function rw_geo_register_admin_submenu( array $args ) {
		$default_cap = 'manage_options';
		if ( class_exists( 'RWGC_Admin', false ) ) {
			$default_cap = RWGC_Admin::required_capability();
		}
		if ( empty( $args['capability'] ) ) {
			$args['capability'] = $default_cap;
		}
		if ( class_exists( 'RWGC_Admin_Platform', false ) ) {
			return RWGC_Admin_Platform::register_submenu( $args );
		}
		$parent = 'rwgc-dashboard';
		$slug   = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug || empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}
		return add_submenu_page(
			$parent,
			isset( $args['page_title'] ) ? (string) $args['page_title'] : '',
			isset( $args['menu_title'] ) ? (string) $args['menu_title'] : '',
			(string) $args['capability'],
			$slug,
			$args['callback'],
			isset( $args['position'] ) ? $args['position'] : null
		);
	}
}

if ( ! function_exists( 'rwgc_get_setup_progress' ) ) {
	/**
	 * Platform onboarding checklist progress for Overview.
	 *
	 * @return array{steps: array<int, array<string, mixed>>, completed: int, total: int, percent: int}
	 */
	function rwgc_get_setup_progress() {
		return class_exists( 'RWGC_Onboarding', false )
			? RWGC_Onboarding::get_setup_progress()
			: array(
				'steps'     => array(),
				'completed' => 0,
				'total'     => 0,
				'percent'   => 0,
			);
	}
}

if ( ! function_exists( 'rwgc_uses_platform_shell' ) ) {
	/**
	 * Whether the ReactWoo Geo unified admin app shell is active on this request.
	 *
	 * @return bool
	 */
	function rwgc_uses_platform_shell() {
		return class_exists( 'RWGC_Admin_App_Shell', false ) && RWGC_Admin_App_Shell::should_render();
	}
}

if ( ! function_exists( 'rwgc_admin_menu_parent' ) ) {
	/**
	 * Geo Core hub parent menu slug for satellite submenus.
	 *
	 * @return string
	 */
	function rwgc_admin_menu_parent() {
		return class_exists( 'RWGC_Admin_Platform', false )
			? RWGC_Admin_Platform::menu_parent()
			: 'rwgc-dashboard';
	}
}

if ( ! function_exists( 'rw_geo_render_inner_nav' ) ) {
	/**
	 * Render horizontal section nav (shared Geo Core / satellite shell).
	 *
	 * @param array<string, string|array{label:string,url?:string}> $items   Slug => label or array with label + optional url.
	 * @param string                                                  $current Active admin page slug.
	 * @param array<string, mixed>                                      $args    Optional: filter, aria_label, show_hub_breadcrumb, hub_extension_label.
	 * @return void
	 */
	function rw_geo_render_inner_nav( array $items, $current, $args = array() ) {
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_inner_nav( $items, (string) $current, $args );
			return;
		}
		$filter = isset( $args['filter'] ) ? (string) $args['filter'] : '';
		if ( '' !== $filter ) {
			$items = apply_filters( $filter, $items, $current );
		}
		$aria = isset( $args['aria_label'] ) ? (string) $args['aria_label'] : __( 'Section navigation', 'reactwoo-geocore' );
		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr( $aria ) . '">';
		foreach ( $items as $slug => $label ) {
			if ( is_array( $label ) ) {
				$label = isset( $label['label'] ) ? (string) $label['label'] : '';
			}
			if ( '' === (string) $label ) {
				continue;
			}
			$class = 'rwgc-inner-nav__link' . ( (string) $slug === (string) $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( (string) $label ) . '</a>';
		}
		echo '</nav>';
	}
}

