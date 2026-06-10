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
 * Persists lightweight cookies so cache plugins can vary HTML per visitor geo and page version.
 */
class RWGC_Cache_Compat {

	const COUNTRY_COOKIE = 'rwgc_cc';

	const VERSION_COOKIE = 'rwgc_pv';

	const VERSION_COOKIE_BASE = '-';

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'send_headers', array( __CLASS__, 'maybe_set_country_cookie' ), 0 );
		add_action( 'send_headers', array( __CLASS__, 'maybe_set_page_version_cookie' ), 5 );
		add_filter( 'litespeed_vary_cookies', array( __CLASS__, 'litespeed_vary_cookies' ) );
		add_action( 'litespeed_init', array( __CLASS__, 'litespeed_vary_groups' ) );
	}

	/**
	 * Set a stable country cookie for cache vary groups (first visit only).
	 *
	 * @return void
	 */
	public static function maybe_set_country_cookie() {
		if ( headers_sent() || isset( $_COOKIE[ self::COUNTRY_COOKIE ] ) ) {
			return;
		}
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return;
		}

		$country = strtoupper( substr( sanitize_text_field( (string) rwgc_get_visitor_country() ), 0, 2 ) );
		if ( strlen( $country ) !== 2 ) {
			return;
		}

		self::set_cookie( self::COUNTRY_COOKIE, $country );
	}

	/**
	 * Vary full-page cache between base URLs and active `/_gc/{version}` requests.
	 *
	 * @return void
	 */
	public static function maybe_set_page_version_cookie() {
		if ( headers_sent() ) {
			return;
		}

		$version_key = self::VERSION_COOKIE_BASE;
		if ( class_exists( 'RWGC_Page_Version_Routing', false ) ) {
			$ctx = RWGC_Page_Version_Routing::get_request_page_version_context();
			if ( ! empty( $ctx['page_version_active'] ) && ! empty( $ctx['page_version'] ) ) {
				$version_key = sanitize_key( (string) $ctx['page_version'] );
			}
		}
		if ( '' === $version_key ) {
			$version_key = self::VERSION_COOKIE_BASE;
		}

		$existing = isset( $_COOKIE[ self::VERSION_COOKIE ] )
			? sanitize_key( (string) wp_unslash( $_COOKIE[ self::VERSION_COOKIE ] ) )
			: '';
		if ( $existing === $version_key ) {
			return;
		}

		self::set_cookie( self::VERSION_COOKIE, $version_key );
	}

	/**
	 * @param string $name  Cookie name.
	 * @param string $value Cookie value.
	 * @return void
	 */
	private static function set_cookie( $name, $value ) {
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();

		setcookie( $name, $value, time() + DAY_IN_SECONDS, $path, $domain, $secure, true );
		$_COOKIE[ $name ] = $value;
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
		$cookies[] = self::VERSION_COOKIE;
		return array_values( array_unique( $cookies ) );
	}

	/**
	 * @return void
	 */
	public static function litespeed_vary_groups() {
		if ( ! empty( $_COOKIE[ self::COUNTRY_COOKIE ] ) ) {
			$country = strtoupper( substr( sanitize_key( (string) wp_unslash( $_COOKIE[ self::COUNTRY_COOKIE ] ) ), 0, 2 ) );
			if ( 2 === strlen( $country ) ) {
				do_action( 'litespeed_vary_add', 'rwgc_cc_' . $country );
			}
		}

		$version = isset( $_COOKIE[ self::VERSION_COOKIE ] )
			? sanitize_key( (string) wp_unslash( $_COOKIE[ self::VERSION_COOKIE ] ) )
			: self::VERSION_COOKIE_BASE;
		if ( '' === $version ) {
			$version = self::VERSION_COOKIE_BASE;
		}
		do_action( 'litespeed_vary_add', 'rwgc_pv_' . $version );
	}
}
