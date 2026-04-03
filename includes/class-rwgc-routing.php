<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page-level variant routing for Geo Core free tier.
 *
 * Routing decisions use **visitor country only** (not city/region). City-level rules and Elementor city routing
 * belong to Geo Elementor, not this class.
 *
 * Free scope:
 * - One default fallback page.
 * - One additional country mapping.
 */
class RWGC_Routing {

	const META_ENABLED          = '_rwgc_route_enabled';
	const META_DEFAULT_PAGE_ID  = '_rwgc_route_default_page_id';
	const META_COUNTRY_ISO2     = '_rwgc_route_country_iso2';
	const META_COUNTRY_PAGE_ID  = '_rwgc_route_country_page_id';
	const META_ROLE             = '_rwgc_route_role';
	const META_MASTER_PAGE_ID   = '_rwgc_route_master_page_id';
	const TRANSIENT_LOOP_PREFIX = 'rwgc_route_loop_';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_route_request' ), 0 );
	}

	/**
	 * Route current page request when mapping exists.
	 *
	 * @return void
	 */
	public static function maybe_route_request() {
		if ( self::should_bypass_request() ) {
			return;
		}

		$page_id = get_queried_object_id();
		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			return;
		}

		$config = self::get_page_route_config( $page_id );
		if ( empty( $config['enabled'] ) ) {
			return;
		}

		$country = strtoupper( (string) rwgc_get_visitor_country() );
		if ( '' === $country ) {
			return;
		}

		$context  = RWGC_Context::from_visitor();
		$decision = self::get_route_decision_for_page( $page_id, $context, $config );
		if ( ! is_array( $decision ) ) {
			return;
		}

		$final_target = isset( $decision['target_page_id'] ) ? absint( $decision['target_page_id'] ) : 0;
		if ( $final_target <= 0 || $final_target === (int) $page_id ) {
			self::maybe_debug_log( 'No redirect', $decision );
			return;
		}

		$target_url = get_permalink( $final_target );
		if ( ! $target_url ) {
			self::maybe_debug_log( 'Missing permalink for target', $decision );
			return;
		}

		if ( self::is_loop_blocked( $page_id, $final_target ) ) {
			self::maybe_debug_log( 'Redirect blocked due to loop guard', $decision );
			return;
		}

		self::mark_redirect( $page_id, $final_target );
		self::maybe_debug_log( 'Redirecting to page', $decision );

		/**
		 * Whether Geo Core should emit a `route_redirect` geo event before a server-side variant redirect.
		 *
		 * @param bool               $emit     Default true.
		 * @param array<string, mixed> $decision Route decision.
		 * @param RWGC_Context       $context  Visitor context.
		 * @param int                $page_id  Current page ID.
		 * @param int                $final_target Target page ID.
		 */
		$emit_event = (bool) apply_filters( 'rwgc_emit_route_redirect_event', true, $decision, $context, $page_id, $final_target );
		if ( $emit_event && function_exists( 'rwgc_emit_geo_event' ) && class_exists( 'RWGC_Event', false ) ) {
			$event = RWGC_Event::from_route_decision( $decision, $context, RWGC_Event::TYPE_ROUTE_REDIRECT );
			$event->meta['target_url'] = $target_url;
			$event->meta['http_status'] = 302;
			rwgc_emit_geo_event( $event );
		}

		wp_safe_redirect( $target_url, 302 );
		exit;
	}

	/**
	 * Return sanitized page-level route config.
	 *
	 * @param int $page_id Page ID.
	 * @return array
	 */
	public static function get_page_route_config( $page_id ) {
		$page_id = absint( $page_id );
		if ( $page_id <= 0 ) {
			return self::empty_config();
		}

		$enabled = (int) get_post_meta( $page_id, self::META_ENABLED, true );
		$config  = array(
			'enabled'         => ( 1 === $enabled ),
			'default_page_id' => absint( get_post_meta( $page_id, self::META_DEFAULT_PAGE_ID, true ) ),
			'country_iso2'    => strtoupper( sanitize_text_field( (string) get_post_meta( $page_id, self::META_COUNTRY_ISO2, true ) ) ),
			'country_page_id' => absint( get_post_meta( $page_id, self::META_COUNTRY_PAGE_ID, true ) ),
			'role'            => sanitize_key( (string) get_post_meta( $page_id, self::META_ROLE, true ) ),
			'master_page_id'  => absint( get_post_meta( $page_id, self::META_MASTER_PAGE_ID, true ) ),
		);

		// Also support Elementor-only editing flow (without Gutenberg sidebar save).
		$elementor_settings = get_post_meta( $page_id, '_elementor_page_settings', true );
		if ( is_array( $elementor_settings ) && ! empty( $elementor_settings['rwgc_route_enabled'] ) && 'yes' === (string) $elementor_settings['rwgc_route_enabled'] ) {
			$config['enabled'] = true;
			if ( isset( $elementor_settings['rwgc_route_role'] ) ) {
				$config['role'] = sanitize_key( (string) $elementor_settings['rwgc_route_role'] );
			}
			if ( isset( $elementor_settings['rwgc_route_master_page_id'] ) ) {
				$config['master_page_id'] = absint( $elementor_settings['rwgc_route_master_page_id'] );
			}
			if ( isset( $elementor_settings['rwgc_route_country_iso2'] ) ) {
				$config['country_iso2'] = strtoupper( sanitize_text_field( (string) $elementor_settings['rwgc_route_country_iso2'] ) );
			}
		} elseif ( is_array( $elementor_settings ) && isset( $elementor_settings['rwgc_route_enabled'] ) && '' === (string) $elementor_settings['rwgc_route_enabled'] ) {
			$config['enabled'] = false;
		}

		return self::sanitize_config( $config, $page_id );
	}

	/**
	 * Validate and sanitize route config.
	 *
	 * @param array $config Raw config.
	 * @param int   $page_id Context page ID.
	 * @return array
	 */
	public static function sanitize_config( $config, $page_id = 0 ) {
		$page_id = absint( $page_id );
		$out     = self::empty_config();

		$out['enabled']         = ! empty( $config['enabled'] );
		$out['default_page_id'] = isset( $config['default_page_id'] ) ? absint( $config['default_page_id'] ) : 0;
		$out['country_iso2']    = isset( $config['country_iso2'] ) ? strtoupper( substr( sanitize_text_field( (string) $config['country_iso2'] ), 0, 2 ) ) : '';
		$out['country_page_id'] = isset( $config['country_page_id'] ) ? absint( $config['country_page_id'] ) : 0;
		$out['role']            = isset( $config['role'] ) ? sanitize_key( (string) $config['role'] ) : 'master';
		$out['master_page_id']  = isset( $config['master_page_id'] ) ? absint( $config['master_page_id'] ) : 0;

		if ( ! in_array( $out['role'], array( 'master', 'variant' ), true ) ) {
			$out['role'] = 'master';
		}

		if ( ! preg_match( '/^[A-Z]{2}$/', $out['country_iso2'] ) ) {
			$out['country_iso2']    = '';
			$out['country_page_id'] = 0;
		}

		// Enforce free limit: one additional mapping only.
		if ( '' === $out['country_iso2'] ) {
			$out['country_page_id'] = 0;
		}

		// New model: variant pages point to one master, and one country code.
		if ( 'master' === $out['role'] ) {
			$out['master_page_id'] = 0;
		}

		if ( $out['default_page_id'] > 0 && ( ! get_post( $out['default_page_id'] ) || 'page' !== get_post_type( $out['default_page_id'] ) ) ) {
			$out['default_page_id'] = 0;
		}

		if ( $out['country_page_id'] > 0 && ( ! get_post( $out['country_page_id'] ) || 'page' !== get_post_type( $out['country_page_id'] ) ) ) {
			$out['country_page_id'] = 0;
		}

		if ( $out['master_page_id'] > 0 && ( ! get_post( $out['master_page_id'] ) || 'page' !== get_post_type( $out['master_page_id'] ) ) ) {
			$out['master_page_id'] = 0;
		}

		if ( $page_id > 0 ) {
			if ( $out['default_page_id'] === $page_id ) {
				$out['default_page_id'] = 0;
			}
			if ( $out['country_page_id'] === $page_id ) {
				$out['country_page_id'] = 0;
			}
			if ( $out['master_page_id'] === $page_id ) {
				$out['master_page_id'] = 0;
			}
		}

		if ( 'variant' === $out['role'] ) {
			$out['default_page_id'] = 0;
			$out['country_page_id'] = 0;

			if ( $out['enabled'] && ( $out['master_page_id'] <= 0 || '' === $out['country_iso2'] ) ) {
				$out['enabled'] = false;
			} elseif ( $out['enabled'] && self::master_has_variant( $out['master_page_id'], $page_id ) ) {
				$out['enabled'] = false;
			} elseif ( $out['enabled'] && self::is_variant_country_taken( $out['master_page_id'], $out['country_iso2'], $page_id ) ) {
				$out['enabled'] = false;
			}
		} else {
			$out['master_page_id'] = 0;
		}

		return $out;
	}

	/**
	 * Persist sanitized route config to page meta.
	 *
	 * @param int   $page_id Page ID.
	 * @param array $config Route config.
	 * @return void
	 */
	public static function save_page_route_config( $page_id, $config ) {
		$page_id = absint( $page_id );
		if ( $page_id <= 0 || 'page' !== get_post_type( $page_id ) ) {
			return;
		}

		$config = self::sanitize_config( is_array( $config ) ? $config : array(), $page_id );

		update_post_meta( $page_id, self::META_ENABLED, $config['enabled'] ? '1' : '0' );
		update_post_meta( $page_id, self::META_DEFAULT_PAGE_ID, (string) absint( $config['default_page_id'] ) );
		update_post_meta( $page_id, self::META_COUNTRY_ISO2, (string) $config['country_iso2'] );
		update_post_meta( $page_id, self::META_COUNTRY_PAGE_ID, (string) absint( $config['country_page_id'] ) );
		update_post_meta( $page_id, self::META_ROLE, (string) $config['role'] );
		update_post_meta( $page_id, self::META_MASTER_PAGE_ID, (string) absint( $config['master_page_id'] ) );
	}

	/**
	 * Check whether a variant country is already used for this master.
	 *
	 * @param int    $master_page_id Master page ID.
	 * @param string $country_iso2 Country code.
	 * @param int    $exclude_page_id Excluded page ID.
	 * @return bool
	 */
	public static function is_variant_country_taken( $master_page_id, $country_iso2, $exclude_page_id = 0 ) {
		$master_page_id = absint( $master_page_id );
		$exclude_page_id = absint( $exclude_page_id );
		$country_iso2 = strtoupper( sanitize_text_field( (string) $country_iso2 ) );
		if ( $master_page_id <= 0 || '' === $country_iso2 ) {
			return false;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'exclude'        => $exclude_page_id > 0 ? array( $exclude_page_id ) : array(),
				'meta_query'     => array(
					array(
						'key'   => self::META_ENABLED,
						'value' => '1',
					),
					array(
						'key'   => self::META_ROLE,
						'value' => 'variant',
					),
					array(
						'key'   => self::META_MASTER_PAGE_ID,
						'value' => (string) $master_page_id,
					),
					array(
						'key'   => self::META_COUNTRY_ISO2,
						'value' => $country_iso2,
					),
				),
			)
		);

		return ! empty( $ids );
	}

	/**
	 * Free-tier guard: allow only one variant page per master.
	 *
	 * @param int $master_page_id Master page ID.
	 * @param int $exclude_page_id Excluded page ID.
	 * @return bool
	 */
	public static function master_has_variant( $master_page_id, $exclude_page_id = 0 ) {
		$master_page_id  = absint( $master_page_id );
		$exclude_page_id = absint( $exclude_page_id );
		if ( $master_page_id <= 0 ) {
			return false;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'exclude'        => $exclude_page_id > 0 ? array( $exclude_page_id ) : array(),
				'meta_query'     => array(
					array(
						'key'   => self::META_ENABLED,
						'value' => '1',
					),
					array(
						'key'   => self::META_ROLE,
						'value' => 'variant',
					),
					array(
						'key'   => self::META_MASTER_PAGE_ID,
						'value' => (string) $master_page_id,
					),
				),
			)
		);

		return ! empty( $ids );
	}

	/**
	 * Find a matching variant page for master + visitor country.
	 *
	 * @param int    $master_page_id Master page ID.
	 * @param string $country_iso2 Country code.
	 * @return int
	 */
	public static function find_variant_for_master_country( $master_page_id, $country_iso2 ) {
		$master_page_id = absint( $master_page_id );
		$country_iso2   = strtoupper( sanitize_text_field( (string) $country_iso2 ) );
		if ( $master_page_id <= 0 || '' === $country_iso2 ) {
			return 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => self::META_ENABLED,
						'value' => '1',
					),
					array(
						'key'   => self::META_ROLE,
						'value' => 'variant',
					),
					array(
						'key'   => self::META_MASTER_PAGE_ID,
						'value' => (string) $master_page_id,
					),
					array(
						'key'   => self::META_COUNTRY_ISO2,
						'value' => $country_iso2,
					),
				),
			)
		);

		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	/**
	 * Canonical page route bundle (legacy meta → default + variants) after `rwgc_page_route_bundle` filter.
	 *
	 * @param int        $page_id Page ID.
	 * @param array|null $config  Optional preloaded config from get_page_route_config().
	 * @return RWGC_Page_Route_Bundle|null
	 */
	public static function get_page_route_bundle( $page_id, $config = null ) {
		$page_id = absint( $page_id );
		if ( $page_id <= 0 || ! class_exists( 'RWGC_Legacy_Route_Mapper', false ) ) {
			return null;
		}
		if ( ! is_array( $config ) ) {
			$config = self::get_page_route_config( $page_id );
		}
		$bundle = RWGC_Legacy_Route_Mapper::bundle_from_legacy_config( $page_id, $config );
		return apply_filters( 'rwgc_page_route_bundle', $bundle, $config, $page_id );
	}

	/**
	 * Resolve redirect decision for a page (same pipeline as template routing).
	 *
	 * @param int               $page_id Page ID.
	 * @param RWGC_Context|null $context Context or null for current visitor.
	 * @param array|null        $config  Optional preloaded config from get_page_route_config().
	 * @return array|null Keys: target_page_id, reason, page_id, country, variant_id.
	 */
	public static function get_route_decision_for_page( $page_id, $context = null, $config = null ) {
		$page_id = absint( $page_id );
		if ( $page_id <= 0 || ! class_exists( 'RWGC_Legacy_Route_Mapper', false ) ) {
			return null;
		}
		if ( ! is_array( $config ) ) {
			$config = self::get_page_route_config( $page_id );
		}
		$bundle = RWGC_Legacy_Route_Mapper::bundle_from_legacy_config( $page_id, $config );
		$bundle = apply_filters( 'rwgc_page_route_bundle', $bundle, $config, $page_id );
		if ( ! $context instanceof RWGC_Context ) {
			$context = RWGC_Context::from_visitor();
		}
		$decision = RWGC_Page_Route_Resolver::resolve( $bundle, $context );
		/**
		 * Filter the resolved route decision for a page.
		 *
		 * @param array            $decision Decision: target_page_id, reason, page_id, country, variant_id.
		 * @param array            $config   Sanitized {@see RWGC_Routing::get_page_route_config()} output.
		 * @param int              $page_id  Page ID passed to {@see RWGC_Routing::get_route_decision_for_page()}.
		 * @param RWGC_Context     $context  Context used for resolution.
		 */
		$decision = apply_filters( 'rwgc_route_variant_decision', $decision, $config, $page_id, $context );
		/**
		 * Fires after a route variant decision is computed (reporting, experiments).
		 *
		 * @param array<string, mixed> $decision Includes variant_id when resolved.
		 * @param RWGC_Context         $context  Context used for resolution.
		 * @param array<string, mixed> $config   Page route config.
		 * @param int                    $page_id  Page id passed to the resolver.
		 */
		do_action( 'rwgc_route_variant_resolved', $decision, $context, $config, $page_id );
		return $decision;
	}

	/**
	 * Decide when to bypass routing.
	 *
	 * @return bool
	 */
	private static function should_bypass_request() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		if ( is_feed() || is_trackback() || is_robots() || is_preview() ) {
			return true;
		}

		if ( ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['action'] ) && 'elementor' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * Basic redirect loop guard via transient.
	 *
	 * @param int $page_id Current page ID.
	 * @param int $target_page_id Target page ID.
	 * @return bool
	 */
	private static function is_loop_blocked( $page_id, $target_page_id ) {
		$key = self::TRANSIENT_LOOP_PREFIX . md5( (string) $page_id . ':' . (string) $target_page_id . ':' . (string) self::request_fingerprint() );
		return (bool) get_transient( $key );
	}

	/**
	 * Mark redirect to avoid immediate recursive loops.
	 *
	 * @param int $page_id Current page ID.
	 * @param int $target_page_id Target page ID.
	 * @return void
	 */
	private static function mark_redirect( $page_id, $target_page_id ) {
		$key = self::TRANSIENT_LOOP_PREFIX . md5( (string) $page_id . ':' . (string) $target_page_id . ':' . (string) self::request_fingerprint() );
		set_transient( $key, 1, 30 );
	}

	/**
	 * Return lightweight request fingerprint.
	 *
	 * @return string
	 */
	private static function request_fingerprint() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return $ip . '|' . $ua;
	}

	/**
	 * Empty config shape.
	 *
	 * @return array
	 */
	private static function empty_config() {
		return array(
			'enabled'         => false,
			'default_page_id' => 0,
			'country_iso2'    => '',
			'country_page_id' => 0,
			'role'            => 'master',
			'master_page_id'  => 0,
		);
	}

	/**
	 * Write debug logs when Geo Core debug mode is enabled.
	 *
	 * @param string $message Log message.
	 * @param array  $context Context payload.
	 * @return void
	 */
	private static function maybe_debug_log( $message, $context = array() ) {
		if ( ! RWGC_Settings::get( 'debug_mode', 0 ) ) {
			return;
		}

		$payload = is_array( $context ) ? wp_json_encode( $context ) : '';
		error_log( '[RWGC Routing] ' . sanitize_text_field( $message ) . ' ' . $payload ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

