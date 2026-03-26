<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page-level variant routing for Geo Core free tier.
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

		$target_page_id = 0;
		$reason         = 'none';

		if ( ! empty( $config['country_iso2'] ) && ! empty( $config['country_page_id'] ) && $country === $config['country_iso2'] ) {
			$target_page_id = (int) $config['country_page_id'];
			$reason         = 'country_match';
		} elseif ( ! empty( $config['default_page_id'] ) ) {
			$target_page_id = (int) $config['default_page_id'];
			$reason         = 'default_fallback';
		}

		$decision = array(
			'target_page_id' => $target_page_id,
			'reason'         => $reason,
			'page_id'        => (int) $page_id,
			'country'        => $country,
		);

		/**
		 * Allow add-ons (for example GeoElementor Pro) to extend base decisions.
		 *
		 * @param array $decision Routing decision.
		 * @param array $config   Base free-tier page config.
		 */
		$decision = apply_filters( 'rwgc_route_variant_decision', $decision, $config );

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
		);

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

		if ( ! preg_match( '/^[A-Z]{2}$/', $out['country_iso2'] ) ) {
			$out['country_iso2']    = '';
			$out['country_page_id'] = 0;
		}

		// Enforce free limit: one additional mapping only.
		if ( '' === $out['country_iso2'] ) {
			$out['country_page_id'] = 0;
		}

		if ( $out['default_page_id'] > 0 && ( ! get_post( $out['default_page_id'] ) || 'page' !== get_post_type( $out['default_page_id'] ) ) ) {
			$out['default_page_id'] = 0;
		}

		if ( $out['country_page_id'] > 0 && ( ! get_post( $out['country_page_id'] ) || 'page' !== get_post_type( $out['country_page_id'] ) ) ) {
			$out['country_page_id'] = 0;
		}

		if ( $page_id > 0 ) {
			if ( $out['default_page_id'] === $page_id ) {
				$out['default_page_id'] = 0;
			}
			if ( $out['country_page_id'] === $page_id ) {
				$out['country_page_id'] = 0;
			}
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

