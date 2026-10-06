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
 *
 * LiteSpeed vary groups are derived from server-side GeoIP / Page Version context — never from
 * client-supplied cookie values — so a forged `rwgc_cc` / `rwgc_pv` cookie cannot poison another
 * country's (or version's) cache bucket.
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

		// Set cookies early so $_COOKIE is populated before late cache vary hooks when possible.
		add_action( 'init', array( __CLASS__, 'maybe_set_country_cookie' ), 1 );
		add_action( 'init', array( __CLASS__, 'maybe_set_page_version_cookie' ), 2 );
		add_action( 'send_headers', array( __CLASS__, 'maybe_set_country_cookie' ), 0 );
		add_action( 'send_headers', array( __CLASS__, 'maybe_set_page_version_cookie' ), 5 );
		add_action( 'litespeed_init', array( __CLASS__, 'litespeed_vary_groups' ) );
	}

	/**
	 * Resolve visitor country for cache vary (GeoIP / preview — not the client cookie).
	 *
	 * @return string Uppercase ISO2 or empty.
	 */
	public static function resolve_country_vary_key() {
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return '';
		}

		$country = strtoupper( substr( sanitize_text_field( (string) rwgc_get_visitor_country() ), 0, 2 ) );
		return ( 2 === strlen( $country ) && preg_match( '/^[A-Z]{2}$/', $country ) ) ? $country : '';
	}

	/**
	 * Resolve Page Version slug for cache vary (request context — not the client cookie).
	 *
	 * @return string Sanitized version slug or {@see VERSION_COOKIE_BASE} for base URLs.
	 */
	public static function resolve_page_version_vary_key() {
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
		return $version_key;
	}

	/**
	 * LiteSpeed vary group name for a country code.
	 *
	 * @param string $country ISO2.
	 * @return string Empty when invalid.
	 */
	public static function country_vary_group( $country ) {
		$country = strtoupper( substr( sanitize_text_field( (string) $country ), 0, 2 ) );
		if ( 2 !== strlen( $country ) || ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			return '';
		}
		return 'rwgc_cc_' . $country;
	}

	/**
	 * LiteSpeed vary group name for a page version key.
	 *
	 * @param string $version_key Version slug or base marker.
	 * @return string
	 */
	public static function page_version_vary_group( $version_key ) {
		$version_key = sanitize_key( (string) $version_key );
		if ( '' === $version_key ) {
			$version_key = self::VERSION_COOKIE_BASE;
		}
		return 'rwgc_pv_' . $version_key;
	}

	/**
	 * Sync country cookie to the server-resolved visitor country.
	 *
	 * Overwrites a mismatched client value so forged cookies cannot stick for a day.
	 *
	 * @return void
	 */
	public static function maybe_set_country_cookie() {
		if ( headers_sent() ) {
			return;
		}

		$country = self::resolve_country_vary_key();
		if ( '' === $country ) {
			return;
		}

		$existing = isset( $_COOKIE[ self::COUNTRY_COOKIE ] )
			? strtoupper( substr( sanitize_text_field( (string) wp_unslash( $_COOKIE[ self::COUNTRY_COOKIE ] ) ), 0, 2 ) )
			: '';
		if ( $existing === $country ) {
			return;
		}

		self::set_cookie( self::COUNTRY_COOKIE, $country );
	}

	/**
	 * Sync page-version cookie to the active `/_gc/{version}` request (or base marker).
	 *
	 * @return void
	 */
	public static function maybe_set_page_version_cookie() {
		if ( headers_sent() ) {
			return;
		}

		$version_key = self::resolve_page_version_vary_key();

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
	 * Register LiteSpeed vary groups from server-side geo / page-version context.
	 *
	 * Intentionally does not register `rwgc_cc` / `rwgc_pv` as `litespeed_vary_cookies`
	 * (client-controlled request cookies). LiteSpeed persists these groups via its own vary cookie.
	 *
	 * @return void
	 */
	public static function litespeed_vary_groups() {
		$country_group = self::country_vary_group( self::resolve_country_vary_key() );
		if ( '' !== $country_group ) {
			do_action( 'litespeed_vary_add', $country_group );
		}

		do_action( 'litespeed_vary_add', self::page_version_vary_group( self::resolve_page_version_vary_key() ) );
	}
}
