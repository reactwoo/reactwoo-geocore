<?php
/**
 * Full-page cache compatibility (LiteSpeed and similar vary-by-cookie stacks).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists a lightweight country cookie so cache plugins can vary HTML per visitor geo.
 */
class RWGC_Cache_Compat {

	const COUNTRY_COOKIE = 'rwgc_cc';

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'maybe_set_country_cookie' ), 25 );
		add_filter( 'litespeed_vary_cookies', array( __CLASS__, 'litespeed_vary_cookies' ) );
		add_action( 'litespeed_init', array( __CLASS__, 'litespeed_vary_country' ) );
	}

	/**
	 * Set a stable country cookie for cache vary groups (first visit only).
	 *
	 * @return void
	 */
	public static function maybe_set_country_cookie() {
		if ( isset( $_COOKIE[ self::COUNTRY_COOKIE ] ) ) {
			return;
		}
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return;
		}

		$country = strtoupper( substr( sanitize_text_field( (string) rwgc_get_visitor_country() ), 0, 2 ) );
		if ( strlen( $country ) !== 2 ) {
			return;
		}

		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();

		setcookie( self::COUNTRY_COOKIE, $country, time() + DAY_IN_SECONDS, $path, $domain, $secure, true );
		$_COOKIE[ self::COUNTRY_COOKIE ] = $country;
	}

	/**
	 * @param array<int, string>|mixed $cookies Registered vary cookies.
	 * @return array<int, string>
	 */
	public static function litespeed_vary_cookies( $cookies ) {
		if ( ! is_array( $cookies ) ) {
			$cookies = array();
		}
		$cookies[] = self::COUNTRY_COOKIE;
		return array_values( array_unique( $cookies ) );
	}

	/**
	 * @return void
	 */
	public static function litespeed_vary_country() {
		if ( empty( $_COOKIE[ self::COUNTRY_COOKIE ] ) ) {
			return;
		}

		$country = strtoupper( substr( sanitize_key( (string) wp_unslash( $_COOKIE[ self::COUNTRY_COOKIE ] ) ), 0, 2 ) );
		if ( strlen( $country ) !== 2 ) {
			return;
		}

		do_action( 'litespeed_vary_add', 'rwgc_cc_' . $country );
	}
}
