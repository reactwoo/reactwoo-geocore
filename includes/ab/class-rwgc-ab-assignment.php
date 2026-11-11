<?php
/**
 * Sticky visitor assignment for free A/B tests.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 50/50 assignment stored in a signed first-party cookie.
 *
 * The cookie is written on `send_headers`, before output buffering, on public
 * front-end GET requests. Bots, logged-in editor previews, REST, and AJAX
 * never receive a Set-Cookie. The fragment render route is the exception: a
 * cached page does not run PHP, so that one GET may assign and set the cookie.
 * A consent check can veto the same way and the visitor is then shown variant A.
 */
class RWGC_AB_Assignment {

	const COOKIE = 'rwgc_ab';

	/**
	 * Ninety days.
	 */
	const TTL = 7776000;

	const CONTROL = 'a';

	/**
	 * @var array{visitor_id:string, assignments:array<int, array{variant:string, viewed:bool, converted:bool}>}|null
	 */
	private static $memo = null;

	/**
	 * @var bool
	 */
	private static $dirty = false;

	/**
	 * @var bool|null
	 */
	private static $can_track = null;

	/**
	 * @var array<string, mixed>|null
	 */
	private static $last_emit = null;

	/**
	 * True while the fragment render route is allowed to assign during REST.
	 *
	 * @var bool
	 */
	private static $fragment_write = false;

	/**
	 * @return void
	 */
	public static function init() {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'send_headers', array( __CLASS__, 'maybe_set_cookie' ), 0 );
	}

	/**
	 * Assign running tests and send the cookie when this request may do so.
	 *
	 * Hooked to send_headers so the Set-Cookie lands before render.
	 *
	 * @return void
	 */
	public static function maybe_set_cookie() {
		if ( ! self::can_write_cookie() ) {
			return;
		}
		foreach ( self::running_test_ids() as $test_id ) {
			self::assign_test( $test_id );
		}
		// A tampered cookie is replaced even when nothing new is assigned.
		self::load_state( true );
		self::flush_cookie();
	}

	/**
	 * Variant to render for one test.
	 *
	 * Public GET requests assign and persist. Other requests only read a
	 * valid cookie. Bots, logged-in editor previews, and a consent veto serve A.
	 *
	 * @param int|string $test_id Test post ID.
	 * @return string
	 */
	public static function variant( $test_id ) {
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || self::serves_control_only() ) {
			return self::CONTROL;
		}
		if ( self::can_write_cookie() ) {
			self::assign_test( $test_id );
			self::flush_cookie();
		} else {
			self::load_state( false );
		}
		$row = self::stored_row( $test_id );
		if ( null === $row ) {
			return self::CONTROL;
		}
		return (string) $row['variant'];
	}

	/**
	 * Assign during the fragment render request.
	 *
	 * Other REST requests still only read a valid cookie. This route may write
	 * because a cached fragment page never reaches `send_headers`.
	 *
	 * @param int|string $test_id Test post ID.
	 * @return string
	 */
	public static function variant_for_fragment( $test_id ) {
		self::$fragment_write = true;
		try {
			return self::variant( $test_id );
		} finally {
			self::$fragment_write = false;
		}
	}

	/**
	 * Deterministic 50/50 arm for a visitor. Bucket 0–4999 is A.
	 *
	 * @param int|string $test_id    Test id. Passed to the shared assigner.
	 * @param string     $visitor_id Anonymous id from the cookie.
	 * @return string `a` or `b`.
	 */
	public static function variant_for_visitor( $test_id, $visitor_id ) {
		$visitor_id = trim( (string) $visitor_id );
		if ( '' === $visitor_id || ! class_exists( 'RWGC_Decision_Experiment_Assigner', false ) ) {
			return self::CONTROL;
		}
		$bucket = RWGC_Decision_Experiment_Assigner::bucket( (string) $test_id, $visitor_id );
		return $bucket < 5000 ? self::CONTROL : 'b';
	}

	/**
	 * Known crawler token in the user agent.
	 *
	 * Same token list Geo Optimise uses to keep bots out of assignment
	 * (`RWGO_Sitewide::is_known_bot`). An empty user agent is not treated as a bot.
	 *
	 * @param string $ua User agent.
	 * @return bool
	 */
	public static function is_known_bot( $ua ) {
		$ua = strtolower( (string) $ua );
		if ( '' === $ua ) {
			return false;
		}
		$needles = array(
			'googlebot',
			'bingbot',
			'slurp',
			'duckduckbot',
			'baiduspider',
			'yandex',
			'facebookexternalhit',
			'twitterbot',
			'linkedinbot',
			'whatsapp',
			'ahrefsbot',
			'semrushbot',
			'petalbot',
			'applebot',
			'bytespider',
			'gptbot',
			'claudebot',
			'amazonbot',
			'pingdom',
			'uptimerobot',
			'lighthouse',
			'chrome-lighthouse',
			'headlesschrome',
		);
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $ua, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * True when this context must not assign or write the cookie.
	 *
	 * @param array<string, mixed> $flags method, bot, admin, ajax, rest, cron, cli, feed, json, preview, editor.
	 * @return bool
	 */
	public static function request_skips_assignment( array $flags ) {
		if ( ! empty( $flags['bot'] ) ) {
			return true;
		}
		if ( ! empty( $flags['preview'] ) && ! empty( $flags['editor'] ) ) {
			return true;
		}
		foreach ( array( 'admin', 'ajax', 'rest', 'cron', 'cli', 'feed', 'json' ) as $key ) {
			if ( ! empty( $flags[ $key ] ) ) {
				return true;
			}
		}
		$method = isset( $flags['method'] ) ? strtoupper( (string) $flags['method'] ) : 'GET';
		return 'GET' !== $method;
	}

	/**
	 * Sign a cookie value. Entries are `testId:variant` with optional `.v` and `.c`.
	 *
	 * @param string                                                                  $visitor_id   32 hex characters.
	 * @param array<int, array{variant:string, viewed?:bool, converted?:bool}>       $assignments  Test id => arm and flags.
	 * @return string Empty when the visitor id or salt cannot be signed.
	 */
	public static function encode_cookie( $visitor_id, array $assignments ) {
		$visitor_id = strtolower( trim( (string) $visitor_id ) );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $visitor_id ) ) {
			return '';
		}
		$salt = self::salt();
		if ( '' === $salt ) {
			return '';
		}
		ksort( $assignments, SORT_NUMERIC );
		$parts = array();
		foreach ( $assignments as $id => $row ) {
			$test_id = absint( $id );
			if ( $test_id <= 0 || ! is_array( $row ) ) {
				continue;
			}
			$variant = isset( $row['variant'] ) ? (string) $row['variant'] : '';
			if ( ! preg_match( '/^[a-z0-9_\-]{1,16}$/', $variant ) ) {
				continue;
			}
			$entry = $test_id . ':' . $variant;
			if ( ! empty( $row['viewed'] ) ) {
				$entry .= '.v';
			}
			if ( ! empty( $row['converted'] ) ) {
				$entry .= '.c';
			}
			$parts[] = $entry;
		}
		$body = '1|' . $visitor_id . '|' . implode( ',', $parts );
		return $body . '|' . hash_hmac( 'sha256', $body, $salt );
	}

	/**
	 * Read a cookie. False means absent. Null means tampered or malformed.
	 *
	 * @param mixed $raw Cookie value.
	 * @return array{visitor_id:string, assignments:array<int, array{variant:string, viewed:bool, converted:bool}>}|false|null
	 */
	public static function decode_cookie( $raw ) {
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return false;
		}
		$pieces = explode( '|', $raw );
		if ( 4 !== count( $pieces ) ) {
			return null;
		}
		list( $version, $visitor_id, $entries, $sig ) = $pieces;
		if ( '1' !== $version || ! preg_match( '/^[a-f0-9]{32}$/', $visitor_id ) || ! preg_match( '/^[a-f0-9]{64}$/', $sig ) ) {
			return null;
		}
		$salt = self::salt();
		if ( '' === $salt ) {
			return null;
		}
		$body     = '1|' . $visitor_id . '|' . $entries;
		$expected = hash_hmac( 'sha256', $body, $salt );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}
		$assignments = array();
		if ( '' !== $entries ) {
			foreach ( explode( ',', $entries ) as $entry ) {
				if ( ! preg_match( '/^([1-9][0-9]{0,18}):([a-z0-9_\-]{1,16})((?:\.v)?(?:\.c)?)$/', $entry, $match ) ) {
					return null;
				}
				$test_id = (int) $match[1];
				if ( isset( $assignments[ $test_id ] ) ) {
					return null;
				}
				$assignments[ $test_id ] = array(
					'variant'   => $match[2],
					'viewed'    => false !== strpos( $match[3], '.v' ),
					'converted' => false !== strpos( $match[3], '.c' ),
				);
			}
		}
		return array(
			'visitor_id'  => $visitor_id,
			'assignments' => $assignments,
		);
	}

	/**
	 * Header arguments from the last cookie this request decided to send.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function last_cookie_header() {
		return self::$last_emit;
	}

	/**
	 * Drop the in-request memo so the next call reads the cookie again.
	 *
	 * @return void
	 */
	public static function reset_request_state() {
		self::$memo           = null;
		self::$dirty          = false;
		self::$can_track      = null;
		self::$last_emit      = null;
		self::$fragment_write = false;
	}

	/**
	 * @return bool
	 */
	private static function can_write_cookie() {
		if ( ! self::tracking_allowed() ) {
			return false;
		}
		$flags = self::current_request_flags();
		if ( self::$fragment_write || self::is_fragment_render_request() ) {
			$flags['rest'] = false;
		}
		return ! self::request_skips_assignment( $flags );
	}

	/**
	 * The no-store fragment route. A normal REST request does not match.
	 *
	 * @return bool
	 */
	private static function is_fragment_render_request() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only searched for a fixed route.
		$uri = is_string( $uri ) ? $uri : '';
		if ( false !== strpos( $uri, 'reactwoo-geocore/v1/ab/render' ) ) {
			return true;
		}
		if ( ! isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route detection, not a form.
			return false;
		}
		$route = wp_unslash( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return is_string( $route ) && false !== strpos( $route, 'reactwoo-geocore/v1/ab/render' );
	}

	/**
	 * Bots, editor previews, and a consent veto render A and never write.
	 *
	 * @return bool
	 */
	private static function serves_control_only() {
		if ( ! self::tracking_allowed() ) {
			return true;
		}
		$flags = self::current_request_flags();
		if ( ! empty( $flags['bot'] ) ) {
			return true;
		}
		return ! empty( $flags['preview'] ) && ! empty( $flags['editor'] );
	}

	/**
	 * Consent hook for a later consent API. False serves A and writes nothing.
	 *
	 * @return bool
	 */
	private static function tracking_allowed() {
		if ( null !== self::$can_track ) {
			return self::$can_track;
		}
		$allowed = '' !== self::salt();
		if ( $allowed ) {
			/**
			 * Whether this request may assign a visitor and store the A/B cookie.
			 *
			 * Consent integrations return false to serve variant A and skip the cookie.
			 *
			 * @param bool $allowed True when the auth salt is available.
			 */
			$allowed = (bool) apply_filters( 'rwgc_ab_can_track', true );
		}
		self::$can_track = $allowed;
		return self::$can_track;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function current_request_flags() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return array(
			'method'  => $method,
			'bot'     => self::is_known_bot( self::user_agent() ),
			'admin'   => function_exists( 'is_admin' ) && is_admin(),
			'ajax'    => ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ),
			'rest'    => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'cron'    => ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ),
			'cli'     => defined( 'WP_CLI' ) && WP_CLI,
			'feed'    => function_exists( 'is_feed' ) && is_feed(),
			'json'    => function_exists( 'wp_is_json_request' ) && wp_is_json_request(),
			'preview' => self::is_preview_request(),
			'editor'  => self::is_logged_in_editor(),
		);
	}

	/**
	 * @return bool
	 */
	private static function is_preview_request() {
		if ( function_exists( 'is_preview' ) && is_preview() ) {
			return true;
		}
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}
		return self::query_present( 'preview' ) || self::query_present( 'elementor-preview' );
	}

	/**
	 * @return bool
	 */
	private static function is_logged_in_editor() {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return false;
		}
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * @param string $key Query key.
	 * @return bool
	 */
	private static function query_present( $key ) {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		$value = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $value ) ) {
			return false;
		}
		return '' !== trim( (string) $value );
	}

	/**
	 * @return string
	 */
	private static function user_agent() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}
		$ua = wp_unslash( $_SERVER['HTTP_USER_AGENT'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return is_string( $ua ) ? $ua : '';
	}

	/**
	 * @return int[]
	 */
	private static function running_test_ids() {
		$ids = array();
		if ( class_exists( 'RWGC_AB_Tests', false ) ) {
			$rows = RWGC_AB_Tests::query();
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) || 'publish' !== (string) ( isset( $row['status'] ) ? $row['status'] : '' ) ) {
						continue;
					}
					$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
			}
		}
		/**
		 * Published test ids assigned on this request.
		 *
		 * @param int[] $ids Test post IDs.
		 */
		$filtered = apply_filters( 'rwgc_ab_running_test_ids', $ids );
		if ( ! is_array( $filtered ) ) {
			$filtered = $ids;
		}
		$out = array();
		foreach ( $filtered as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$out[ $id ] = $id;
			}
		}
		return array_values( $out );
	}

	/**
	 * @param int $test_id Test id.
	 * @return void
	 */
	private static function assign_test( $test_id ) {
		self::load_state( true );
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || null === self::$memo || isset( self::$memo['assignments'][ $test_id ] ) ) {
			return;
		}
		if ( '' === self::$memo['visitor_id'] ) {
			self::$memo['visitor_id'] = self::new_visitor_id();
		}
		self::$memo['assignments'][ $test_id ] = array(
			'variant'   => self::variant_for_visitor( (string) $test_id, self::$memo['visitor_id'] ),
			'viewed'    => false,
			'converted' => false,
		);
		self::$dirty                            = true;
	}

	/**
	 * @param bool $reset_tampered Mint a new visitor when the cookie does not verify.
	 * @return void
	 */
	private static function load_state( $reset_tampered ) {
		if ( null !== self::$memo ) {
			return;
		}
		$raw     = self::raw_cookie();
		$decoded = null === $raw ? null : self::decode_cookie( $raw );
		if ( is_array( $decoded ) ) {
			self::$memo  = $decoded;
			self::$dirty = false;
			return;
		}
		if ( false === $decoded || ! $reset_tampered ) {
			self::$memo  = array(
				'visitor_id'  => '',
				'assignments' => array(),
			);
			self::$dirty = false;
			return;
		}
		self::$memo  = array(
			'visitor_id'  => self::new_visitor_id(),
			'assignments' => array(),
		);
		self::$dirty = true;
	}

	/**
	 * @param int $test_id Test id.
	 * @return array{variant:string, viewed:bool, converted:bool}|null
	 */
	private static function stored_row( $test_id ) {
		if ( null === self::$memo || ! isset( self::$memo['assignments'][ $test_id ] ) || ! is_array( self::$memo['assignments'][ $test_id ] ) ) {
			return null;
		}
		return self::$memo['assignments'][ $test_id ];
	}

	/**
	 * @return void
	 */
	private static function flush_cookie() {
		if ( ! self::$dirty || null === self::$memo || '' === self::$memo['visitor_id'] || ! self::can_write_cookie() ) {
			return;
		}
		$value = self::encode_cookie( self::$memo['visitor_id'], self::$memo['assignments'] );
		if ( '' === $value ) {
			return;
		}
		$path    = ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? (string) COOKIEPATH : '/';
		$domain  = ( defined( 'COOKIE_DOMAIN' ) && is_string( COOKIE_DOMAIN ) ) ? COOKIE_DOMAIN : '';
		$expires = time() + self::TTL;
		$secure  = function_exists( 'is_ssl' ) && is_ssl();
		self::$last_emit = array(
			'name'     => self::COOKIE,
			'value'    => $value,
			'expires'  => $expires,
			'path'     => $path,
			'domain'   => $domain,
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		);
		$_COOKIE[ self::COOKIE ] = $value;
		self::$dirty             = false;
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Cookie string, or null when the value is present but not a string.
	 *
	 * @return string|null
	 */
	private static function raw_cookie() {
		if ( ! isset( $_COOKIE[ self::COOKIE ] ) ) {
			return '';
		}
		$raw = wp_unslash( $_COOKIE[ self::COOKIE ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return is_string( $raw ) ? $raw : null;
	}

	/**
	 * @return string
	 */
	private static function salt() {
		if ( ! function_exists( 'wp_salt' ) ) {
			return '';
		}
		return (string) wp_salt( 'auth' );
	}

	/**
	 * @return string
	 */
	private static function new_visitor_id() {
		try {
			$bytes = random_bytes( 16 );
		} catch ( \Throwable $e ) {
			unset( $e );
			$bytes = '';
		}
		$id = is_string( $bytes ) && 16 === strlen( $bytes ) ? bin2hex( $bytes ) : '';
		if ( preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			return $id;
		}
		return md5( uniqid( 'rwgc_ab', true ) );
	}
}
