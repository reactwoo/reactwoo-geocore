<?php
/**
 * Attribution context resolver for runtime targeting.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized attribution source (request + cookie + extension filter).
 */
class RWGC_Context_Attribution {

	/**
	 * Per-request memo so multiple consumers see the same new/returning classification.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $resolved = null;

	/**
	 * Reset per-request memo (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$resolved = null;
	}

	/**
	 * Resolve normalized attribution payload for current request.
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve() {
		if ( is_array( self::$resolved ) ) {
			return self::$resolved;
		}

		$prior_first_touch = self::read_cookie_snapshot( 'rwgc_ft' );
		$session_touch     = self::read_cookie_snapshot( 'rwgc_st' );
		$request_touch     = self::read_touch_from_request();

		$merged_touch = self::merge_touch( $session_touch, $request_touch );
		$first_touch  = self::merge_touch( $prior_first_touch, $request_touch );

		/**
		 * Whether attribution / returning-visitor cookies may be written for this request.
		 *
		 * @param bool $should_persist Default true.
		 */
		$should_persist = (bool) apply_filters( 'rwgc_context_attribution_should_persist', true );

		if ( $should_persist && self::has_attribution_data( $request_touch ) ) {
			self::write_cookie_snapshot( 'rwgc_ft', $first_touch );
			self::write_cookie_snapshot( 'rwgc_st', $merged_touch );
		}

		$returning = self::is_returning_visitor( $prior_first_touch );
		if ( $should_persist ) {
			self::persist_returning_cookie();
		}

		$audiences = apply_filters( 'rwgc_analytics_audiences', array(), array() );
		$audiences = is_array( $audiences ) ? array_values( array_filter( array_map( 'sanitize_key', $audiences ) ) ) : array();

		$out = array(
			'source'              => (string) ( $merged_touch['source'] ?? '' ),
			'medium'              => (string) ( $merged_touch['medium'] ?? '' ),
			'campaign'            => (string) ( $merged_touch['campaign'] ?? '' ),
			'campaign_id'         => (string) ( $merged_touch['campaign_id'] ?? '' ),
			'content'             => (string) ( $merged_touch['content'] ?? '' ),
			'term'                => (string) ( $merged_touch['term'] ?? '' ),
			'gclid'               => (string) ( $merged_touch['gclid'] ?? '' ),
			'fbclid'              => (string) ( $merged_touch['fbclid'] ?? '' ),
			'li_fat_id'           => (string) ( $merged_touch['li_fat_id'] ?? '' ),
			'msclkid'             => (string) ( $merged_touch['msclkid'] ?? '' ),
			'returning_visitor'   => $returning,
			'analytics_audiences' => $audiences,
			'first_touch'         => $first_touch,
			'session_touch'       => $merged_touch,
		);

		/**
		 * Filter normalized attribution payload before consumers read it.
		 *
		 * @param array<string, mixed> $out Attribution payload.
		 */
		$out = apply_filters( 'rwgc_context_attribution', $out );
		self::$resolved = is_array( $out ) ? $out : array();
		return self::$resolved;
	}

	/**
	 * @return array<string, string>
	 */
	private static function read_touch_from_request() {
		$map = array(
			'source'   => 'utm_source',
			'medium'   => 'utm_medium',
			'campaign' => 'utm_campaign',
			'content'  => 'utm_content',
			'term'     => 'utm_term',
			'gclid'    => 'gclid',
			'fbclid'   => 'fbclid',
			'li_fat_id'=> 'li_fat_id',
			'msclkid'  => 'msclkid',
		);
		$out = self::empty_touch();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only context enrichment.
		foreach ( $map as $key => $request_key ) {
			if ( isset( $_GET[ $request_key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( (string) $_GET[ $request_key ] ) );
			}
		}

		$campaign_id_keys = array( 'campaignid', 'gad_campaignid', 'utm_campaign_id' );
		/**
		 * Google Ads auto-tagging and custom trackers may pass a numeric campaign id separately from utm_campaign.
		 *
		 * @param string[] $campaign_id_keys Request parameter names (first non-empty wins).
		 */
		$campaign_id_keys = apply_filters( 'rwgc_attribution_campaign_id_keys', $campaign_id_keys );
		if ( is_array( $campaign_id_keys ) ) {
			foreach ( $campaign_id_keys as $request_key ) {
				$request_key = sanitize_key( (string) $request_key );
				if ( '' === $request_key || ! isset( $_GET[ $request_key ] ) ) {
					continue;
				}
				$out['campaign_id'] = sanitize_text_field( wp_unslash( (string) $_GET[ $request_key ] ) );
				if ( '' !== $out['campaign_id'] ) {
					break;
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $out;
	}

	/**
	 * @param string $cookie_key Cookie name.
	 * @return array<string, string>
	 */
	private static function read_cookie_snapshot( $cookie_key ) {
		if ( ! isset( $_COOKIE[ $cookie_key ] ) ) {
			return self::empty_touch();
		}

		$raw = wp_unslash( (string) $_COOKIE[ $cookie_key ] );
		$raw = rawurldecode( $raw );
		$val = json_decode( $raw, true );
		if ( ! is_array( $val ) ) {
			return self::empty_touch();
		}

		return array(
			'source'      => sanitize_text_field( (string) ( $val['source'] ?? '' ) ),
			'medium'      => sanitize_text_field( (string) ( $val['medium'] ?? '' ) ),
			'campaign'    => sanitize_text_field( (string) ( $val['campaign'] ?? '' ) ),
			'campaign_id' => sanitize_text_field( (string) ( $val['campaign_id'] ?? '' ) ),
			'content'     => sanitize_text_field( (string) ( $val['content'] ?? '' ) ),
			'term'        => sanitize_text_field( (string) ( $val['term'] ?? '' ) ),
			'gclid'       => sanitize_text_field( (string) ( $val['gclid'] ?? '' ) ),
			'fbclid'      => sanitize_text_field( (string) ( $val['fbclid'] ?? '' ) ),
			'li_fat_id'   => sanitize_text_field( (string) ( $val['li_fat_id'] ?? '' ) ),
			'msclkid'     => sanitize_text_field( (string) ( $val['msclkid'] ?? '' ) ),
		);
	}

	/**
	 * @param string               $cookie_key Cookie name.
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return void
	 */
	private static function write_cookie_snapshot( $cookie_key, array $snapshot ) {
		$json = wp_json_encode(
			array(
				'source'      => (string) ( $snapshot['source'] ?? '' ),
				'medium'      => (string) ( $snapshot['medium'] ?? '' ),
				'campaign'    => (string) ( $snapshot['campaign'] ?? '' ),
				'campaign_id' => (string) ( $snapshot['campaign_id'] ?? '' ),
				'content'     => (string) ( $snapshot['content'] ?? '' ),
				'term'        => (string) ( $snapshot['term'] ?? '' ),
				'gclid'       => (string) ( $snapshot['gclid'] ?? '' ),
				'fbclid'      => (string) ( $snapshot['fbclid'] ?? '' ),
				'li_fat_id'   => (string) ( $snapshot['li_fat_id'] ?? '' ),
				'msclkid'     => (string) ( $snapshot['msclkid'] ?? '' ),
			)
		);
		if ( ! is_string( $json ) || '' === $json ) {
			return;
		}

		$ttl           = defined( 'MONTH_IN_SECONDS' ) ? (int) MONTH_IN_SECONDS : 2592000;
		$expire       = time() + $ttl;
		$cookie_path  = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure       = function_exists( 'is_ssl' ) ? (bool) is_ssl() : false;
		setcookie( $cookie_key, rawurlencode( $json ), $expire, $cookie_path, $cookie_domain, $secure, true );
		$_COOKIE[ $cookie_key ] = rawurlencode( $json );
	}

	/**
	 * @param array<string, string> $base Base touch.
	 * @param array<string, string> $incoming Incoming touch.
	 * @return array<string, string>
	 */
	private static function merge_touch( array $base, array $incoming ) {
		$out = self::empty_touch();
		foreach ( $out as $key => $unused ) {
			$base_val     = isset( $base[ $key ] ) ? (string) $base[ $key ] : '';
			$incoming_val = isset( $incoming[ $key ] ) ? (string) $incoming[ $key ] : '';
			$out[ $key ]  = '' !== $incoming_val ? $incoming_val : $base_val;
		}
		return $out;
	}

	/**
	 * @param array<string, string> $touch Touch payload.
	 * @return bool
	 */
	private static function has_attribution_data( array $touch ) {
		foreach ( $touch as $value ) {
			if ( '' !== (string) $value ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returning is true only when a previous visit already left a cookie.
	 * Current-request UTM / click IDs do not count as returning.
	 *
	 * @param array<string, string> $prior_first_touch First-touch cookie from before this request.
	 * @return bool
	 */
	private static function is_returning_visitor( array $prior_first_touch ) {
		if ( self::has_returning_cookie() ) {
			return true;
		}
		return self::has_attribution_data( $prior_first_touch );
	}

	/**
	 * @return bool
	 */
	private static function has_returning_cookie() {
		if ( ! isset( $_COOKIE['rwgc_returning'] ) ) {
			return false;
		}
		$legacy = sanitize_text_field( wp_unslash( (string) $_COOKIE['rwgc_returning'] ) );
		return '' !== $legacy;
	}

	/**
	 * Persist a first-seen cookie so the next visit classifies as returning.
	 * Does not change this request's classification.
	 *
	 * @return void
	 */
	private static function persist_returning_cookie() {
		$existing = self::has_returning_cookie()
			? sanitize_text_field( wp_unslash( (string) $_COOKIE['rwgc_returning'] ) )
			: '';
		$value = '' !== $existing ? $existing : (string) time();
		$ttl   = defined( 'YEAR_IN_SECONDS' ) ? (int) YEAR_IN_SECONDS : 31536000;
		/**
		 * Returning-visitor cookie lifetime in seconds.
		 *
		 * @param int $ttl Default one year.
		 */
		$ttl           = (int) apply_filters( 'rwgc_returning_visitor_cookie_ttl', $ttl );
		$expire        = time() + max( 86400, $ttl );
		$cookie_path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure        = function_exists( 'is_ssl' ) ? (bool) is_ssl() : false;
		setcookie( 'rwgc_returning', $value, $expire, $cookie_path, $cookie_domain, $secure, true );
		// Do not populate $_COOKIE on first visit — this request stays "new".
	}

	/**
	 * @return array<string, string>
	 */
	private static function empty_touch() {
		return array(
			'source'      => '',
			'medium'      => '',
			'campaign'    => '',
			'campaign_id' => '',
			'content'     => '',
			'term'        => '',
			'gclid'       => '',
			'fbclid'      => '',
			'li_fat_id'   => '',
			'msclkid'     => '',
		);
	}
}
