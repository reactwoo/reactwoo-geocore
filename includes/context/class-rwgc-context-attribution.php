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
	 * Resolve normalized attribution payload for current request.
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve() {
		$first_touch   = self::read_cookie_snapshot( 'rwgc_ft' );
		$session_touch = self::read_cookie_snapshot( 'rwgc_st' );
		$request_touch = self::read_touch_from_request();

		$merged_touch = self::merge_touch( $session_touch, $request_touch );
		$first_touch  = self::merge_touch( $first_touch, $request_touch );

		if ( self::has_attribution_data( $request_touch ) ) {
			self::write_cookie_snapshot( 'rwgc_ft', $first_touch );
			self::write_cookie_snapshot( 'rwgc_st', $merged_touch );
		}

		$returning = self::is_returning_visitor( $first_touch );
		$audiences = apply_filters( 'rwgc_analytics_audiences', array(), array() );
		$audiences = is_array( $audiences ) ? array_values( array_filter( array_map( 'sanitize_key', $audiences ) ) ) : array();

		$out = array(
			'source'              => (string) ( $merged_touch['source'] ?? '' ),
			'medium'              => (string) ( $merged_touch['medium'] ?? '' ),
			'campaign'            => (string) ( $merged_touch['campaign'] ?? '' ),
			'content'             => (string) ( $merged_touch['content'] ?? '' ),
			'term'                => (string) ( $merged_touch['term'] ?? '' ),
			'gclid'               => (string) ( $merged_touch['gclid'] ?? '' ),
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
		return is_array( $out ) ? $out : array();
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
		);
		$out = array(
			'source'   => '',
			'medium'   => '',
			'campaign' => '',
			'content'  => '',
			'term'     => '',
			'gclid'    => '',
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only context enrichment.
		foreach ( $map as $key => $request_key ) {
			if ( isset( $_GET[ $request_key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( (string) $_GET[ $request_key ] ) );
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
			'source'   => sanitize_text_field( (string) ( $val['source'] ?? '' ) ),
			'medium'   => sanitize_text_field( (string) ( $val['medium'] ?? '' ) ),
			'campaign' => sanitize_text_field( (string) ( $val['campaign'] ?? '' ) ),
			'content'  => sanitize_text_field( (string) ( $val['content'] ?? '' ) ),
			'term'     => sanitize_text_field( (string) ( $val['term'] ?? '' ) ),
			'gclid'    => sanitize_text_field( (string) ( $val['gclid'] ?? '' ) ),
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
				'source'   => (string) ( $snapshot['source'] ?? '' ),
				'medium'   => (string) ( $snapshot['medium'] ?? '' ),
				'campaign' => (string) ( $snapshot['campaign'] ?? '' ),
				'content'  => (string) ( $snapshot['content'] ?? '' ),
				'term'     => (string) ( $snapshot['term'] ?? '' ),
				'gclid'    => (string) ( $snapshot['gclid'] ?? '' ),
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
	 * @param array<string, string> $first_touch First touch.
	 * @return bool
	 */
	private static function is_returning_visitor( array $first_touch ) {
		foreach ( $first_touch as $value ) {
			if ( '' !== (string) $value ) {
				return true;
			}
		}
		$legacy = isset( $_COOKIE['rwgc_returning'] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE['rwgc_returning'] ) ) : '';
		return '' !== $legacy;
	}

	/**
	 * @return array<string, string>
	 */
	private static function empty_touch() {
		return array(
			'source'   => '',
			'medium'   => '',
			'campaign' => '',
			'content'  => '',
			'term'     => '',
			'gclid'    => '',
		);
	}
}
