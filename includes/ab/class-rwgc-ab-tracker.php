<?php
/**
 * View and goal counts for free A/B tests.
 *
 * A view is the first time an arm enters the viewport. A conversion is one
 * click on the goal, or one hit on the goal URL, after that view. The signed
 * rwgc_ab cookie carries `.v` and `.c`. Daily totals live in rwgc_ab_daily.
 * The visitor address is used only to build a rate-limit key and is not stored.
 * An account id is not read or stored.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records aggregate views and conversions.
 */
class RWGC_AB_Tracker {

	const RATE_LIMIT = 40;

	const RATE_PREFIX = 'rwgc_ab_ev_';

	/**
	 * True after a URL conversion rewrote the cookie on this request.
	 *
	 * @var bool
	 */
	private static $cache_bypass = false;

	/**
	 * @return void
	 */
	public static function init() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_count_url_goal' ), 20 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rest_authentication_errors', array( __CLASS__, 'allow_public_event_without_nonce' ), 101 );
		}
	}

	/**
	 * @return void
	 */
	public static function reset_request_state() {
		self::$cache_bypass = false;
	}

	/**
	 * A URL conversion sends Set-Cookie, so that response must not be stored.
	 *
	 * @return bool
	 */
	public static function cache_bypass_marked() {
		return self::$cache_bypass;
	}

	/**
	 * Headers for a response that rewrote the assignment cookie.
	 *
	 * @return array<string, string>
	 */
	public static function nocache_headers() {
		return array(
			'Cache-Control' => 'private, no-store',
			'Pragma'        => 'no-cache',
		);
	}

	/**
	 * Headers for every /ab/event response, including a rejected parameter.
	 *
	 * @return array<string, string>
	 */
	public static function event_no_store_headers() {
		return array(
			'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0, private',
			'Pragma'                    => 'no-cache',
			'Expires'                   => 'Wed, 11 Jan 1984 05:00:00 GMT',
			'X-LiteSpeed-Cache-Control' => 'no-cache',
			'X-Robots-Tag'              => 'noindex',
		);
	}

	/**
	 * Accept a JSON number or a numeric string. Anything else is rejected.
	 *
	 * @param mixed           $value   Raw test id.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter name.
	 * @return true|WP_Error
	 */
	public static function rest_validate_test_param( $value, $request = null, $param = '' ) {
		unset( $request, $param );
		if ( self::positive_test_id( $value ) > 0 ) {
			return true;
		}
		if ( class_exists( 'WP_Error' ) ) {
			return new WP_Error(
				'rwgc_ab_invalid_test',
				__( 'The test id must be a positive integer.', 'reactwoo-geocore' ),
				array( 'status' => 400 )
			);
		}
		return false;
	}

	/**
	 * Cast a JSON number or a numeric string to a positive int.
	 *
	 * @param mixed           $value   Raw test id.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter name.
	 * @return int|WP_Error
	 */
	public static function rest_sanitize_test_param( $value, $request = null, $param = '' ) {
		unset( $request, $param );
		$id = self::positive_test_id( $value );
		if ( $id > 0 ) {
			return $id;
		}
		if ( class_exists( 'WP_Error' ) ) {
			return new WP_Error(
				'rwgc_ab_invalid_test',
				__( 'The test id must be a positive integer.', 'reactwoo-geocore' ),
				array( 'status' => 400 )
			);
		}
		return 0;
	}

	/**
	 * Click-goal selector attribute for an arm wrapper. Empty when the goal is a URL.
	 *
	 * @param int|string $test_id Test id.
	 * @return string Leading space included when the attribute is present.
	 */
	public static function goal_attribute( $test_id ) {
		$goal = self::goal( absint( $test_id ) );
		if ( 'click' !== $goal['type'] || '' === $goal['selector'] ) {
			return '';
		}
		$selector = function_exists( 'esc_attr' ) ? esc_attr( $goal['selector'] ) : htmlspecialchars( $goal['selector'], ENT_QUOTES, 'UTF-8' );
		return ' data-rwgc-ab-goal="' . $selector . '"';
	}

	/**
	 * Transient key for one visitor address. The address is not part of the key.
	 *
	 * @param string $ip Visitor address. Hashed and discarded.
	 * @return string
	 */
	public static function rate_transient_key( $ip ) {
		$ip   = self::normal_ip( $ip );
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : '';
		if ( '' === $salt ) {
			$salt = 'rwgc-ab-event';
		}
		return self::RATE_PREFIX . substr( hash_hmac( 'sha256', 'event|' . $ip, $salt ), 0, 32 );
	}

	/**
	 * Forty events a minute per address. The transient stores a counter only.
	 *
	 * @param string $ip Visitor address. Hashed into the key and not stored.
	 * @return array{allowed:bool,limit:int,remaining:int,retry_after:int}
	 */
	public static function consume_rate_limit( $ip ) {
		$limit = (int) apply_filters( 'rwgc_ab_event_rate_limit', self::RATE_LIMIT );
		if ( $limit < 1 ) {
			$limit = self::RATE_LIMIT;
		}
		$now    = (int) apply_filters( 'rwgc_ab_event_rate_now', time() );
		$key    = self::rate_transient_key( $ip );
		$bucket = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( ! is_array( $bucket ) || ! isset( $bucket['start'], $bucket['count'] ) || ( $now - (int) $bucket['start'] ) >= 60 ) {
			$bucket = array(
				'start' => $now,
				'count' => 0,
			);
		}
		$bucket = array(
			'start' => (int) $bucket['start'],
			'count' => (int) $bucket['count'] + 1,
		);
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $bucket, 120 );
		}
		$allowed = (int) $bucket['count'] <= $limit;
		$retry   = 60 - ( $now - (int) $bucket['start'] );
		if ( $retry < 1 ) {
			$retry = 1;
		}
		return array(
			'allowed'     => $allowed,
			'limit'       => $limit,
			'remaining'   => max( 0, $limit - (int) $bucket['count'] ),
			'retry_after' => $allowed ? 0 : $retry,
		);
	}

	/**
	 * POST /ab/event. Public, no nonce, and not stored.
	 *
	 * @param mixed  $test Test id from the body.
	 * @param mixed  $type view|conversion.
	 * @param string $ip   Visitor address override. Empty resolves the request.
	 * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
	 */
	public static function build_event_response( $test, $type, $ip = '' ) {
		$limit   = self::consume_rate_limit( '' !== (string) $ip ? (string) $ip : self::client_ip() );
		$headers = self::no_store_headers( $limit );
		if ( empty( $limit['allowed'] ) ) {
			return array(
				'status'  => 429,
				'headers' => $headers,
				'body'    => array(
					'code'    => 'rwgc_ab_rate_limited',
					'message' => __( 'Too many A/B requests. Try again shortly.', 'reactwoo-geocore' ),
				),
			);
		}

		$type = sanitize_key( is_scalar( $type ) ? (string) $type : '' );
		if ( ! in_array( $type, array( 'view', 'conversion' ), true ) ) {
			return array(
				'status'  => 400,
				'headers' => $headers,
				'body'    => array(
					'code'    => 'rwgc_ab_invalid_type',
					'message' => __( 'The event type must be view or conversion.', 'reactwoo-geocore' ),
				),
			);
		}

		$test_id = self::positive_test_id( $test );
		if ( $test_id <= 0 ) {
			return array(
				'status'  => 400,
				'headers' => $headers,
				'body'    => array(
					'code'    => 'rwgc_ab_invalid_test',
					'message' => __( 'The test id must be a positive integer.', 'reactwoo-geocore' ),
				),
			);
		}
		if ( ! self::test_is_running( $test_id ) ) {
			return array(
				'status'  => 404,
				'headers' => $headers,
				'body'    => array(
					'code'    => 'rwgc_ab_not_running',
					'message' => __( 'That A/B test is not running.', 'reactwoo-geocore' ),
				),
			);
		}

		$result = self::record( $test_id, $type, 'beacon' );
		return array(
			'status'  => 200,
			'headers' => $headers,
			'body'    => array(
				'ok'      => true,
				'counted' => ( 'counted' === $result ),
				'test'    => $test_id,
				'type'    => $type,
			),
		);
	}

	/**
	 * Count a goal URL for a visitor who already has `.v` and not `.c`.
	 *
	 * @return void
	 */
	public static function maybe_count_url_goal() {
		if ( self::skips_url_goal() || ! class_exists( 'RWGC_AB_Assignment', false ) ) {
			return;
		}
		if ( RWGC_AB_Assignment::is_known_bot( self::user_agent() ) ) {
			return;
		}
		foreach ( RWGC_AB_Assignment::verified_assignments() as $test_id => $row ) {
			$test_id = absint( $test_id );
			if ( $test_id <= 0 || ! is_array( $row ) || ! self::test_is_running( $test_id ) ) {
				continue;
			}
			$goal = self::goal( $test_id );
			if ( 'url' !== $goal['type'] || ! self::url_matches_request( $goal['url'] ) ) {
				continue;
			}
			self::record( $test_id, 'conversion', 'url' );
		}
	}

	/**
	 * The event route stays public when a logged-in browser has no REST nonce.
	 *
	 * A cached page cannot keep a fresh nonce. The signed assignment cookie is
	 * what this route checks.
	 *
	 * @param mixed $result Authentication result.
	 * @return mixed
	 */
	public static function allow_public_event_without_nonce( $result ) {
		if ( ! self::is_event_request() ) {
			return $result;
		}
		$code = '';
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) && is_object( $result ) && method_exists( $result, 'get_error_code' ) ) {
			$code = (string) $result->get_error_code();
		}
		if ( 'rest_cookie_invalid_nonce' === $code ) {
			return null;
		}
		return $result;
	}

	/**
	 * @param mixed $value Raw test id.
	 * @return int
	 */
	private static function positive_test_id( $value ) {
		if ( class_exists( 'RWGC_AB_Cache', false ) ) {
			return RWGC_AB_Cache::positive_test_id( $value );
		}
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}
		if ( is_string( $value ) && preg_match( '/^[1-9][0-9]{0,18}$/', $value ) ) {
			$id = (int) $value;
			return (string) $id === $value ? $id : 0;
		}
		return 0;
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $type    view|conversion.
	 * @param string $source  beacon|url.
	 * @return string counted|duplicate|missing|no_view|ignored
	 */
	private static function record( $test_id, $type, $source ) {
		$goal = self::goal( $test_id );
		if ( 'conversion' === $type ) {
			if ( 'url' === $source && 'url' !== $goal['type'] ) {
				return 'ignored';
			}
			if ( 'beacon' === $source && 'click' !== $goal['type'] ) {
				return 'ignored';
			}
		}
		if ( ! class_exists( 'RWGC_AB_Assignment', false ) ) {
			return 'missing';
		}
		$flag   = ( 'conversion' === $type ) ? 'converted' : 'viewed';
		$marked = RWGC_AB_Assignment::mark_counted( $test_id, $flag, 'beacon' === $source );
		if ( 'counted' !== $marked ) {
			return $marked;
		}
		$row     = RWGC_AB_Assignment::verified_assignment( $test_id );
		$variant = is_array( $row ) && isset( $row['variant'] ) ? sanitize_key( (string) $row['variant'] ) : '';
		if ( ! preg_match( '/^[a-z0-9_\-]{1,16}$/', $variant ) ) {
			return 'missing';
		}
		if ( class_exists( 'RWGC_AB_DB', false ) ) {
			RWGC_AB_DB::increment( $test_id, $variant, ( 'conversion' === $type ) ? 'conversions' : 'views' );
		}
		self::emit_event( $type, $test_id, $variant, $source );
		if ( 'url' === $source ) {
			self::send_nocache();
		}
		return 'counted';
	}

	/**
	 * @param string $type    view|conversion.
	 * @param int    $test_id Test id.
	 * @param string $variant Arm id.
	 * @param string $source  beacon|url.
	 * @return void
	 */
	private static function emit_event( $type, $test_id, $variant, $source ) {
		if ( ! class_exists( 'RWGC_Events', false ) || ! class_exists( 'RWGC_Event', false ) ) {
			return;
		}
		$event_type = ( 'conversion' === $type ) ? RWGC_Event::TYPE_AB_CONVERSION : RWGC_Event::TYPE_AB_EXPOSURE;
		$event      = new RWGC_Event(
			array(
				'event_type'    => $event_type,
				'variant_id'    => $variant,
				'experiment_id' => (string) absint( $test_id ),
				'subject'       => array(
					'test_id' => absint( $test_id ),
					'source'  => sanitize_key( $source ),
				),
			)
		);
		RWGC_Events::emit( $event );
	}

	/**
	 * @param int $test_id Test id.
	 * @return array{type:string,selector:string,url:string}
	 */
	private static function goal( $test_id ) {
		$goal = array(
			'type'     => 'click',
			'selector' => '',
			'url'      => '',
		);
		if ( $test_id <= 0 || ! class_exists( 'RWGC_AB_Tests', false ) ) {
			return $goal;
		}
		$row = RWGC_AB_Tests::get( $test_id );
		if ( ! is_array( $row ) || ! isset( $row['config']['goal'] ) || ! is_array( $row['config']['goal'] ) ) {
			return $goal;
		}
		$raw  = $row['config']['goal'];
		$type = sanitize_key( isset( $raw['type'] ) ? (string) $raw['type'] : 'click' );
		if ( 'url' !== $type ) {
			$type = 'click';
		}
		$selector = isset( $raw['selector'] ) && is_scalar( $raw['selector'] ) ? sanitize_text_field( (string) $raw['selector'] ) : '';
		if ( strlen( $selector ) > 191 ) {
			$selector = substr( $selector, 0, 191 );
		}
		$url = isset( $raw['url'] ) && is_scalar( $raw['url'] ) ? trim( (string) $raw['url'] ) : '';
		if ( function_exists( 'esc_url_raw' ) ) {
			$url = (string) esc_url_raw( $url );
		}
		$goal['type']     = $type;
		$goal['selector'] = $selector;
		$goal['url']      = $url;
		return $goal;
	}

	/**
	 * @param int $test_id Test id.
	 * @return bool
	 */
	private static function test_is_running( $test_id ) {
		$active = false;
		if ( class_exists( 'RWGC_AB_Tests', false ) ) {
			$row    = RWGC_AB_Tests::get( $test_id );
			$active = is_array( $row ) && 'publish' === (string) ( isset( $row['status'] ) ? $row['status'] : '' );
		}
		return (bool) apply_filters( 'rwgc_ab_test_is_running', $active, absint( $test_id ) );
	}

	/**
	 * @param string $goal_url Stored goal URL or path.
	 * @return bool
	 */
	private static function url_matches_request( $goal_url ) {
		$goal_url = trim( (string) $goal_url );
		if ( '' === $goal_url ) {
			return false;
		}
		$goal_host = '';
		$goal_path = $goal_url;
		if ( false !== strpos( $goal_url, '://' ) ) {
			$parts = wp_parse_url( $goal_url );
			if ( ! is_array( $parts ) ) {
				return false;
			}
			$goal_host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
			$goal_path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
			if ( '' === $goal_path ) {
				return false;
			}
		}
		$goal_path = self::normal_path( $goal_path );
		if ( '' === $goal_path || self::request_path() !== $goal_path ) {
			return false;
		}
		if ( '' === $goal_host ) {
			return true;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared as a host, then discarded.
		$host = is_string( $host ) ? (string) preg_replace( '/:\d+$/', '', $host ) : '';
		$host = (string) preg_replace( '/[^a-z0-9.\-]/', '', $host );
		return '' === $host || $host === $goal_host;
	}

	/**
	 * @return string
	 */
	private static function request_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reduced to a path below.
		if ( ! is_string( $uri ) ) {
			$uri = '';
		}
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = $uri;
		}
		return self::normal_path( $path );
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	private static function normal_path( $path ) {
		$path = (string) $path;
		$query = strpos( $path, '?' );
		if ( false !== $query ) {
			$path = substr( $path, 0, $query );
		}
		$path = '/' . ltrim( $path, '/' );
		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
		}
		return $path;
	}

	/**
	 * @return bool
	 */
	private static function skips_url_goal() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared to GET.
		if ( 'GET' !== $method ) {
			return true;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	private static function is_event_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Searched for a fixed route.
		if ( is_string( $uri ) && false !== strpos( $uri, 'reactwoo-geocore/v1/ab/event' ) ) {
			return true;
		}
		if ( ! isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route detection, not a form.
			return false;
		}
		$route = wp_unslash( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return is_string( $route ) && false !== strpos( $route, 'reactwoo-geocore/v1/ab/event' );
	}

	/**
	 * @return void
	 */
	private static function send_nocache() {
		self::$cache_bypass = true;
		if ( ! apply_filters( 'rwgc_ab_emit_http_headers', true ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- DONOTCACHEPAGE is the shared WordPress cache-plugin no-store flag.
		}
		if ( function_exists( 'do_action' ) ) {
			do_action( 'litespeed_control_set_nocache', 'reactwoo-geocore A/B goal' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache public nocache API.
		}
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return;
		}
		if ( function_exists( 'header' ) ) {
			foreach ( self::nocache_headers() as $name => $value ) {
				header( $name . ': ' . $value );
			}
		}
	}

	/**
	 * @param array{allowed:bool,limit:int,remaining:int,retry_after:int} $limit Limit state.
	 * @return array<string, string>
	 */
	private static function no_store_headers( array $limit ) {
		$headers = self::event_no_store_headers();
		if ( empty( $limit['allowed'] ) ) {
			$headers['Retry-After'] = (string) (int) $limit['retry_after'];
		}
		return $headers;
	}

	/**
	 * @return string
	 */
	private static function client_ip() {
		$override = apply_filters( 'rwgc_ab_event_client_ip', null );
		if ( is_string( $override ) && '' !== $override ) {
			return self::normal_ip( $override );
		}
		$ip = '';
		if ( class_exists( 'RWGC_GeoIP', false ) && method_exists( 'RWGC_GeoIP', 'get_current_ip' ) ) {
			$ip = (string) RWGC_GeoIP::get_current_ip();
		}
		if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw = wp_unslash( $_SERVER['REMOTE_ADDR'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked by normal_ip().
			$ip  = is_string( $raw ) ? $raw : '';
		}
		return self::normal_ip( $ip );
	}

	/**
	 * @param string $ip Raw address.
	 * @return string
	 */
	private static function normal_ip( $ip ) {
		$ip = trim( (string) $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * @return string
	 */
	private static function user_agent() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}
		$ua = wp_unslash( $_SERVER['HTTP_USER_AGENT'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to the bot check.
		return is_string( $ua ) ? $ua : '';
	}
}
