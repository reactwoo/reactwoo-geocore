<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP client for api.reactwoo.com and JWT caching (license key supplied by commercial satellite plugins via filters).
 */
class RWGC_Platform_Client {

	const TOKEN_TRANSIENT = 'rwgc_rw_jwt_cache';

	/**
	 * Absolute expiry for a failed /auth/login. Seconds, not minutes.
	 */
	const LOGIN_FAIL_TTL = 900;

	const LOGIN_FAIL_TRANSIENT = 'rwgc_login_fail';

	const LOGIN_PATH = '/api/v5/auth/login';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'update_option_' . RWGC_Settings::OPTION_KEY, array( __CLASS__, 'maybe_clear_token_on_settings_change' ), 10, 2 );
	}

	/**
	 * When the license key changes, drop cached JWT.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public static function maybe_clear_token_on_settings_change( $old_value, $value ) {
		$old = is_array( $old_value ) ? $old_value : array();
		$val = is_array( $value ) ? $value : array();
		$old_key = isset( $old['reactwoo_license_key'] ) ? (string) $old['reactwoo_license_key'] : '';
		$new_key = isset( $val['reactwoo_license_key'] ) ? (string) $val['reactwoo_license_key'] : '';
		$old_base = isset( $old['reactwoo_api_base'] ) ? (string) $old['reactwoo_api_base'] : '';
		$new_base = isset( $val['reactwoo_api_base'] ) ? (string) $val['reactwoo_api_base'] : '';
		if ( $old_key !== $new_key || $old_base !== $new_base ) {
			self::clear_token_cache();
		}
	}

	/**
	 * Public API base (no trailing slash).
	 *
	 * @return string
	 */
	public static function get_api_base() {
		$default = 'https://api.reactwoo.com';
		$base    = (string) apply_filters( 'rwgc_reactwoo_api_base', $default );
		$base    = is_string( $base ) ? trim( $base ) : $default;
		if ( '' === $base ) {
			$base = $default;
		}
		return untrailingslashit( $base );
	}

	/**
	 * Effective product license key (satellite plugins add filters; Geo Core does not store one).
	 *
	 * @return string
	 */
	public static function get_effective_license_key() {
		$key = apply_filters( 'rwgc_reactwoo_license_key', '' );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Site host for license login (api expects `domain`).
	 *
	 * @return string
	 */
	public static function get_site_domain() {
		$home = home_url( '/' );
		$host = wp_parse_url( $home, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			return $host;
		}
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}
		return '';
	}

	/**
	 * Drop cached bearer token.
	 *
	 * @return void
	 */
	public static function clear_token_cache() {
		delete_transient( self::TOKEN_TRANSIENT );
		self::clear_login_failure_cache();
	}

	/**
	 * Drop the 15-minute failed-login flag so the next update check can try again.
	 *
	 * @return void
	 */
	public static function clear_login_failure_cache() {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::LOGIN_FAIL_TRANSIENT );
		}
	}

	/**
	 * Bearer JWT for Authorization header, refreshed via login when needed.
	 *
	 * Remote login is only for the plugin update path. Other callers use a cached token or a cached
	 * failure. A failed login stays cached for 900 seconds.
	 *
	 * @param int  $timeout Login HTTP timeout in seconds. The update path passes 10; other callers keep 30.
	 * @param bool $allow_remote_login Whether this call may POST /api/v5/auth/login.
	 * @return string|\WP_Error
	 */
	public static function get_access_token( $timeout = 30, $allow_remote_login = false ) {
		$timeout = (int) $timeout;
		if ( $timeout < 1 ) {
			$timeout = 30;
		}
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && isset( $cached['expires'] ) && (int) $cached['expires'] > time() + 120 ) {
			return (string) $cached['token'];
		}

		$failed = self::cached_login_failure();
		if ( is_wp_error( $failed ) ) {
			return $failed;
		}

		if ( ! $allow_remote_login ) {
			return new WP_Error(
				'rwgc_login_not_cached',
				__( 'ReactWoo license login runs from the plugin update check. No cached license token is available yet.', 'reactwoo-geocore' )
			);
		}

		$license = self::get_effective_license_key();
		if ( '' === $license ) {
			return new WP_Error(
				'rwgc_no_license',
				__( 'Add your ReactWoo product license in the commercial Geo satellite plugin (e.g. Geo AI → License) to use AI features. Geo Core does not use a ReactWoo product license.', 'reactwoo-geocore' )
			);
		}

		$domain = self::get_site_domain();
		if ( '' === $domain ) {
			return new WP_Error(
				'rwgc_no_domain',
				__( 'Could not determine this site domain for license login.', 'reactwoo-geocore' )
			);
		}

		$url  = self::get_api_base() . self::LOGIN_PATH;
		$body = array(
			'license_key' => $license,
			'domain'      => $domain,
		);
		/**
		 * Add optional fields for multi-product JWTs (e.g. product_slug, plan_code). Satellites should
		 * only augment when the license_key matches their stored key.
		 *
		 * @param array<string, string> $body    JSON body for POST /api/v5/auth/login.
		 * @param string                  $license Effective license key.
		 * @param string                  $domain  Site host.
		 */
		$body = apply_filters( 'rwgc_auth_login_body', $body, $license, $domain );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => self::base_headers(),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			self::remember_login_failure( $response );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg   = isset( $data['message'] ) ? (string) $data['message'] : __( 'License login failed.', 'reactwoo-geocore' );
			$error = new WP_Error( 'rwgc_login_failed', $msg, array( 'status' => $code ) );
			self::remember_login_failure( $error );
			return $error;
		}

		$token = isset( $data['access_token'] ) ? (string) $data['access_token'] : '';
		if ( '' === $token ) {
			$error = new WP_Error( 'rwgc_login_no_token', __( 'License login response did not include a token.', 'reactwoo-geocore' ) );
			self::remember_login_failure( $error );
			return $error;
		}

		self::clear_login_failure_cache();

		$ttl = self::parse_expires_in( isset( $data['expires_in'] ) ? $data['expires_in'] : null );
		$ttl = min( $ttl, 23 * HOUR_IN_SECONDS );

		set_transient(
			self::TOKEN_TRANSIENT,
			array(
				'token'   => $token,
				'expires' => time() + $ttl,
			),
			$ttl
		);

		return $token;
	}

	/**
	 * @return \WP_Error|null Active failure, if the 900-second window has not elapsed.
	 */
	private static function cached_login_failure() {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$cached = get_transient( self::LOGIN_FAIL_TRANSIENT );
		if ( ! is_array( $cached ) || empty( $cached['expires_at'] ) || (int) $cached['expires_at'] <= time() ) {
			return null;
		}
		$code    = isset( $cached['code'] ) ? (string) $cached['code'] : 'rwgc_login_failed';
		$message = isset( $cached['message'] ) ? (string) $cached['message'] : __( 'License login failed.', 'reactwoo-geocore' );
		return new WP_Error( $code, $message );
	}

	/**
	 * @param \WP_Error $error Login error.
	 * @return void
	 */
	private static function remember_login_failure( $error ) {
		if ( ! function_exists( 'set_transient' ) || ! is_wp_error( $error ) ) {
			return;
		}
		$ttl = self::LOGIN_FAIL_TTL;
		set_transient(
			self::LOGIN_FAIL_TRANSIENT,
			array(
				'expires_at' => time() + $ttl,
				'code'       => $error->get_error_code(),
				'message'    => $error->get_error_message(),
			),
			$ttl
		);
	}

	/**
	 * @param mixed $raw expires_in from API.
	 * @return int Seconds.
	 */
	private static function parse_expires_in( $raw ) {
		if ( is_numeric( $raw ) ) {
			return max( 300, (int) $raw );
		}
		if ( is_string( $raw ) && preg_match( '/^(\d+)h$/i', $raw, $m ) ) {
			return max( 300, (int) $m[1] * HOUR_IN_SECONDS );
		}
		return DAY_IN_SECONDS;
	}

	/**
	 * @return array<string, string>
	 */
	private static function base_headers() {
		return array(
			'Content-Type'     => 'application/json',
			'X-Requested-With' => 'XMLHttpRequest',
		);
	}

	/**
	 * JSON request to api.reactwoo.com.
	 *
	 * @param string               $method GET|POST|PUT|PATCH|DELETE.
	 * @param string               $path   Absolute path starting with / (e.g. /api/v5/ai/...).
	 * @param array<string, mixed>|null $body Request JSON body or null.
	 * @param bool                 $auth   Whether to send Bearer token.
	 * @return array<string, mixed>|\WP_Error Keys: code (int), data (array|null), body_raw (string).
	 */
	public static function request( $method, $path, $body = null, $auth = true ) {
		$path = is_string( $path ) ? $path : '';
		if ( '' === $path || '/' !== $path[0] ) {
			return new WP_Error( 'rwgc_bad_path', __( 'Invalid API path.', 'reactwoo-geocore' ) );
		}

		$url = self::get_api_base() . $path;

		$headers = self::base_headers();
		if ( $auth ) {
			$token = self::get_access_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 45,
			'headers' => $headers,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( 401 === $code && $auth ) {
			self::clear_token_cache();
		}

		return array(
			'code'     => $code,
			'data'     => is_array( $data ) ? $data : null,
			'body_raw' => $raw,
		);
	}
}
