<?php
/**
 * Cache-safe rendering for free A/B tests.
 *
 * Server mode (default) prints only the assigned arm. A full-page cache that
 * cannot vary is bypassed with Cache-Control: private, no-store and
 * DONOTCACHEPAGE. LiteSpeed, which can vary, gets a server-derived rwgc_ab
 * group for a visitor whose assignment cookie was already set, so A and B are
 * stored separately. A response that is setting that cookie stays private, so
 * one visitor's Set-Cookie is not replayed for someone else.
 *
 * Fragment mode prints variant A inside a placeholder. The page can be cached.
 * assets/js/rwgc-ab.js loads the assigned arm from the no-store render route
 * and swaps it in only when the arm is B, so an A visitor never sees B.
 *
 * Renderers in later releases call rwgc_ab_render_test() and print the result.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server and fragment rendering, plus the render-route rate limit.
 */
class RWGC_AB_Cache {

	const MODE_SERVER = 'server';

	const MODE_FRAGMENT = 'fragment';

	const RATE_LIMIT = 40;

	const REST_NAMESPACE = 'reactwoo-geocore/v1';

	const REST_ROUTE = '/ab/render';

	/**
	 * Test id => arm for tests varied on this request.
	 *
	 * @var array<int, string>
	 */
	private static $rendered = array();

	/**
	 * Vary groups passed to LiteSpeed on this request.
	 *
	 * @var string[]
	 */
	private static $vary_added = array();

	/**
	 * Directives from the latest rendered test.
	 *
	 * @var array{cache_control:string,donotcachepage:bool,vary:bool}
	 */
	private static $last_directives = array(
		'cache_control'  => '',
		'donotcachepage' => false,
		'vary'           => false,
	);

	/**
	 * @var bool
	 */
	private static $fragment_script_queued = false;

	/**
	 * @return void
	 */
	public static function init() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		}
	}

	/**
	 * @param mixed $mode Raw mode.
	 * @return string server|fragment
	 */
	public static function sanitize_mode( $mode ) {
		$mode = strtolower( trim( is_scalar( $mode ) ? (string) $mode : '' ) );
		return self::MODE_FRAGMENT === $mode ? self::MODE_FRAGMENT : self::MODE_SERVER;
	}

	/**
	 * @return string server|fragment
	 */
	public static function mode() {
		$mode = self::MODE_SERVER;
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$mode = (string) RWGC_Settings::get( 'ab_cache_mode', self::MODE_SERVER );
		}
		$filtered = apply_filters( 'rwgc_ab_cache_mode', $mode );
		return self::sanitize_mode( is_scalar( $filtered ) ? (string) $filtered : $mode );
	}

	/**
	 * @return bool
	 */
	public static function is_fragment() {
		return self::MODE_FRAGMENT === self::mode();
	}

	/**
	 * @return bool
	 */
	public static function litespeed_active() {
		$active = defined( 'LSCWP_V' );
		return (bool) apply_filters( 'rwgc_ab_litespeed_active', $active );
	}

	/**
	 * LiteSpeed can store separate arm entries on this request.
	 *
	 * Guest mode finalizes its vary map before `litespeed_vary` runs, so a guest
	 * response is not varied and must not be stored.
	 *
	 * @return bool
	 */
	public static function litespeed_can_vary() {
		$can = self::litespeed_active();
		if ( $can && defined( 'LITESPEED_GUEST' ) && LITESPEED_GUEST ) {
			$can = false;
		}
		return (bool) apply_filters( 'rwgc_ab_litespeed_can_vary', $can );
	}

	/**
	 * Cache policy for one active test on this request.
	 *
	 * @param bool $has_assignment  Verified assignment for the test.
	 * @param bool $fragment        Fragment mode.
	 * @param bool $litespeed       LiteSpeed Cache is active.
	 * @param bool $cookie_just_set This response is sending the rwgc_ab cookie.
	 * @return array{cache_control:string,donotcachepage:bool,vary:bool}
	 */
	public static function cache_directives( $has_assignment, $fragment, $litespeed, $cookie_just_set ) {
		$has_assignment  = (bool) $has_assignment;
		$fragment        = (bool) $fragment;
		$litespeed       = (bool) $litespeed;
		$cookie_just_set = (bool) $cookie_just_set;

		// The placeholder is the same for every visitor. Do not cache the response that sets the cookie.
		if ( $fragment && ! $cookie_just_set ) {
			return array(
				'cache_control'  => '',
				'donotcachepage' => false,
				'vary'           => false,
			);
		}

		// Established assignment: LiteSpeed can keep one entry per arm. no-store would discard those entries.
		if ( ! $fragment && $litespeed && $has_assignment && ! $cookie_just_set ) {
			return array(
				'cache_control'  => '',
				'donotcachepage' => false,
				'vary'           => true,
			);
		}

		return array(
			'cache_control'  => 'private, no-store',
			'donotcachepage' => true,
			'vary'           => false,
		);
	}

	/**
	 * HTML for one test. Renderers print the return value and do not wrap it again.
	 *
	 * @param int|string           $test_id Test post ID.
	 * @param array<string,string> $arms    Arm id => HTML. `a` is the no-JS fallback.
	 * @return string
	 */
	public static function render( $test_id, array $arms ) {
		$test_id = absint( $test_id );
		$arms    = self::arm_html_map( $arms );
		if ( $test_id <= 0 || ! self::test_is_running( $test_id ) ) {
			return isset( $arms['a'] ) ? $arms['a'] : '';
		}

		$arm = self::arm_for_page( $test_id );
		if ( ! isset( $arms[ $arm ] ) ) {
			$arm = 'a';
		}
		$has    = self::visitor_has_assignment( $test_id );
		$cookie = class_exists( 'RWGC_AB_Assignment', false ) && null !== RWGC_AB_Assignment::last_cookie_header();
		self::apply_cache_policy( $test_id, $arm, $has, $cookie );

		if ( self::is_fragment() ) {
			self::enqueue_fragment_script();
			$html = self::placeholder( $test_id, isset( $arms['a'] ) ? $arms['a'] : '' );
			$shown = 'a';
		} else {
			$html  = self::server_wrapper( $test_id, $arm, isset( $arms[ $arm ] ) ? $arms[ $arm ] : '' );
			$shown = $arm;
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'rwgc_ab_rendered_test', $test_id, $shown, self::mode() );
		}
		$filtered = apply_filters( 'rwgc_ab_render_html', $html, $test_id, $shown, self::mode() );
		return is_string( $filtered ) ? $filtered : $html;
	}

	/**
	 * Mark a running test and apply the cache policy without returning markup.
	 *
	 * Prefer rwgc_ab_render_test() so fragment mode can print the placeholder.
	 *
	 * @param int|string $test_id Test post ID.
	 * @return bool False when the test is not running.
	 */
	public static function mark_rendered( $test_id ) {
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! self::test_is_running( $test_id ) ) {
			return false;
		}
		$arm    = self::arm_for_page( $test_id );
		$has    = self::visitor_has_assignment( $test_id );
		$cookie = class_exists( 'RWGC_AB_Assignment', false ) && null !== RWGC_AB_Assignment::last_cookie_header();
		self::apply_cache_policy( $test_id, $arm, $has, $cookie );
		if ( self::is_fragment() ) {
			self::enqueue_fragment_script();
		}
		return true;
	}

	/**
	 * Verified assignment for one test. A tampered cookie does not count.
	 *
	 * @param int|string $test_id Test post ID.
	 * @return bool
	 */
	public static function visitor_has_assignment( $test_id ) {
		$state   = self::verified_cookie();
		$test_id = absint( $test_id );
		return is_array( $state ) && isset( $state['assignments'][ $test_id ] ) && is_array( $state['assignments'][ $test_id ] );
	}

	/**
	 * Vary groups for tests rendered with the LiteSpeed policy.
	 *
	 * @return string[]
	 */
	public static function rendered_vary_groups() {
		if ( ! self::litespeed_can_vary() || self::is_fragment() ) {
			return array();
		}
		$groups = array();
		foreach ( self::$rendered as $test_id => $arm ) {
			$group = class_exists( 'RWGC_Cache_Compat', false ) ? RWGC_Cache_Compat::ab_vary_group( $test_id, $arm ) : '';
			if ( '' !== $group ) {
				$groups[] = $group;
			}
		}
		return $groups;
	}

	/**
	 * Groups passed to litespeed_vary_add while rendering.
	 *
	 * @return string[]
	 */
	public static function vary_groups_added() {
		return self::$vary_added;
	}

	/**
	 * @return array{cache_control:string,donotcachepage:bool,vary:bool}
	 */
	public static function last_directives() {
		return self::$last_directives;
	}

	/**
	 * @return bool
	 */
	public static function fragment_script_queued() {
		return self::$fragment_script_queued;
	}

	/**
	 * Add rendered arms to LiteSpeed's vary map. Keys are server-derived.
	 *
	 * @param mixed $vary Vary map.
	 * @return mixed
	 */
	public static function filter_litespeed_vary( $vary ) {
		if ( ! is_array( $vary ) || ! self::litespeed_can_vary() || self::is_fragment() ) {
			return $vary;
		}
		foreach ( self::$rendered as $test_id => $arm ) {
			$test_id = absint( $test_id );
			$arm     = sanitize_key( (string) $arm );
			if ( $test_id <= 0 || ! preg_match( '/^[a-z0-9_\-]{1,16}$/', $arm ) ) {
				continue;
			}
			$vary[ 'rwgc_ab_' . $test_id ] = $arm;
		}
		return $vary;
	}

	/**
	 * @return void
	 */
	public static function register_assets() {
		if ( ! function_exists( 'wp_register_script' ) || ! defined( 'RWGC_URL' ) ) {
			return;
		}
		wp_register_script(
			'rwgc-ab',
			RWGC_URL . 'assets/js/rwgc-ab.js',
			array(),
			defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '1.9.26',
			true
		);
	}

	/**
	 * Public read of one arm. Rate-limited, and never cacheable.
	 *
	 * @param int|string $test_id Test post ID.
	 * @param string     $ip      Client IP. Empty uses the resolved visitor IP.
	 * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
	 */
	public static function build_render_response( $test_id, $ip = '' ) {
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

		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! self::test_is_running( $test_id ) ) {
			return array(
				'status'  => 404,
				'headers' => $headers,
				'body'    => array(
					'code'    => 'rwgc_ab_not_running',
					'message' => __( 'That A/B test is not running.', 'reactwoo-geocore' ),
				),
			);
		}

		$arm  = self::arm_for_fragment_request( $test_id );
		$html = apply_filters( 'rwgc_ab_variant_html', '', $test_id, $arm );
		if ( ! is_string( $html ) ) {
			$html = '';
		}
		return array(
			'status'  => 200,
			'headers' => $headers,
			'body'    => array(
				'test' => $test_id,
				'arm'  => $arm,
				'html' => self::escape_variant_html( $html ),
			),
		);
	}

	/**
	 * Forty requests a minute per IP, same window as Geo Commerce prices.
	 *
	 * @param string $ip Client IP.
	 * @return array{allowed:bool,limit:int,remaining:int,retry_after:int}
	 */
	public static function consume_rate_limit( $ip ) {
		$limit = (int) apply_filters( 'rwgc_ab_render_rate_limit', self::RATE_LIMIT );
		if ( $limit < 1 ) {
			$limit = self::RATE_LIMIT;
		}
		$now    = (int) apply_filters( 'rwgc_ab_render_rate_now', time() );
		$key    = 'rwgc_ab_rl_' . md5( self::normal_ip( $ip ) );
		$bucket = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( ! is_array( $bucket ) || ! isset( $bucket['start'], $bucket['count'] ) || ( $now - (int) $bucket['start'] ) >= 60 ) {
			$bucket = array(
				'start' => $now,
				'count' => 0,
			);
		}
		$bucket['count'] = (int) $bucket['count'] + 1;
		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, $bucket, 60 );
		}
		$allowed = (int) $bucket['count'] <= $limit;
		return array(
			'allowed'     => $allowed,
			'limit'       => $limit,
			'remaining'   => max( 0, $limit - (int) $bucket['count'] ),
			'retry_after' => $allowed ? 0 : max( 1, 60 - ( $now - (int) $bucket['start'] ) ),
		);
	}

	/**
	 * @return void
	 */
	public static function reset_request_state() {
		self::$rendered               = array();
		self::$vary_added             = array();
		self::$fragment_script_queued = false;
		self::$last_directives        = array(
			'cache_control'  => '',
			'donotcachepage' => false,
			'vary'           => false,
		);
	}

	/**
	 * @param int                  $test_id Test id.
	 * @param string               $arm     Arm being output in server mode.
	 * @param bool                 $has     Verified assignment.
	 * @param bool                 $cookie  Set-Cookie on this response.
	 * @return void
	 */
	private static function apply_cache_policy( $test_id, $arm, $has, $cookie ) {
		$directives            = self::cache_directives( $has, self::is_fragment(), self::litespeed_can_vary(), $cookie );
		self::$last_directives = $directives;
		if ( empty( $directives['vary'] ) ) {
			self::emit_directives( $directives );
			return;
		}

		$test_id                     = absint( $test_id );
		self::$rendered[ $test_id ] = $arm;
		$group                    = class_exists( 'RWGC_Cache_Compat', false ) ? RWGC_Cache_Compat::ab_vary_group( $test_id, $arm ) : '';
		if ( '' !== $group ) {
			self::$vary_added[] = $group;
			if ( function_exists( 'do_action' ) ) {
				do_action( 'litespeed_vary_add', $group ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache public vary API.
			}
		}
		self::emit_directives( $directives );
	}

	/**
	 * @param array{cache_control:string,donotcachepage:bool,vary:bool} $directives Directives.
	 * @return void
	 */
	private static function emit_directives( array $directives ) {
		if ( ! self::emit_http_headers() ) {
			return;
		}
		if ( ! empty( $directives['donotcachepage'] ) && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! empty( $directives['donotcachepage'] ) && self::litespeed_active() && function_exists( 'do_action' ) ) {
			do_action( 'litespeed_control_set_nocache', 'reactwoo-geocore A/B test' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache public nocache API.
		}
		if ( empty( $directives['cache_control'] ) ) {
			return;
		}
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return;
		}
		header( 'Cache-Control: ' . $directives['cache_control'] );
		header( 'Pragma: no-cache' );
	}

	/**
	 * @return bool
	 */
	private static function emit_http_headers() {
		return (bool) apply_filters( 'rwgc_ab_emit_http_headers', true );
	}

	/**
	 * @param int $test_id Test id.
	 * @return string
	 */
	private static function arm_for_page( $test_id ) {
		if ( ! class_exists( 'RWGC_AB_Assignment', false ) ) {
			return 'a';
		}
		return self::normal_arm( RWGC_AB_Assignment::variant( $test_id ) );
	}

	/**
	 * @param int $test_id Test id.
	 * @return string
	 */
	private static function arm_for_fragment_request( $test_id ) {
		if ( ! class_exists( 'RWGC_AB_Assignment', false ) ) {
			return 'a';
		}
		return self::normal_arm( RWGC_AB_Assignment::variant_for_fragment( $test_id ) );
	}

	/**
	 * @param mixed $arm Arm id.
	 * @return string
	 */
	private static function normal_arm( $arm ) {
		$arm = sanitize_key( (string) $arm );
		return 'b' === $arm ? 'b' : 'a';
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
	 * @param array<mixed> $arms Proposed arms.
	 * @return array<string, string>
	 */
	private static function arm_html_map( array $arms ) {
		$out = array();
		foreach ( $arms as $key => $html ) {
			$key = sanitize_key( (string) $key );
			if ( ! in_array( $key, array( 'a', 'b' ), true ) || ! is_string( $html ) ) {
				continue;
			}
			$out[ $key ] = self::escape_variant_html( $html );
		}
		return $out;
	}

	/**
	 * @param string $html HTML.
	 * @return string
	 */
	private static function escape_variant_html( $html ) {
		$html = (string) $html;
		if ( function_exists( 'rwgc_kses_geo_content_html' ) ) {
			return rwgc_kses_geo_content_html( $html );
		}
		if ( function_exists( 'wp_kses_post' ) ) {
			return wp_kses_post( $html );
		}
		return $html;
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $arm     Arm.
	 * @param string $html    Escaped HTML.
	 * @return string
	 */
	private static function server_wrapper( $test_id, $arm, $html ) {
		return '<div class="rwgc-ab" data-rwgc-ab="' . esc_attr( (string) $test_id ) . '" data-rwgc-ab-mode="server" data-rwgc-arm="' . esc_attr( $arm ) . '">' . $html . '</div>';
	}

	/**
	 * Visible fallback is always A. B is not in the markup.
	 *
	 * @param int    $test_id Test id.
	 * @param string $control Escaped control HTML.
	 * @return string
	 */
	private static function placeholder( $test_id, $control ) {
		return '<div class="rwgc-ab" data-rwgc-ab="' . esc_attr( (string) $test_id ) . '" data-rwgc-ab-mode="fragment" data-rwgc-arm="a">' . $control . '</div>';
	}

	/**
	 * @return void
	 */
	private static function enqueue_fragment_script() {
		self::$fragment_script_queued = true;
		self::register_assets();
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		$already = function_exists( 'wp_script_is' ) && wp_script_is( 'rwgc-ab', 'enqueued' );
		if ( function_exists( 'wp_localize_script' ) && ! $already ) {
			$url = function_exists( 'rest_url' ) ? rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) : '';
			$url = apply_filters( 'rwgc_ab_render_endpoint', $url );
			if ( ! is_string( $url ) ) {
				$url = '';
			}
			if ( function_exists( 'esc_url_raw' ) ) {
				$url = esc_url_raw( $url );
			}
			wp_localize_script(
				'rwgc-ab',
				'rwgcAb',
				array(
					'endpoint' => $url,
				)
			);
		}
		wp_enqueue_script( 'rwgc-ab' );
	}

	/**
	 * @return array{visitor_id:string,assignments:array<int,array<string,mixed>>}|null
	 */
	private static function verified_cookie() {
		if ( ! class_exists( 'RWGC_AB_Assignment', false ) || ! isset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] ) ) {
			return null;
		}
		$raw = wp_unslash( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HMAC payload. decode_cookie() verifies it.
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$decoded = RWGC_AB_Assignment::decode_cookie( $raw );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * @param array{allowed:bool,limit:int,remaining:int,retry_after:int} $limit Limit state.
	 * @return array<string, string>
	 */
	private static function no_store_headers( array $limit ) {
		$headers = array(
			'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0, private',
			'Pragma'                    => 'no-cache',
			'Expires'                   => 'Wed, 11 Jan 1984 05:00:00 GMT',
			'X-LiteSpeed-Cache-Control' => 'no-cache',
			'X-Robots-Tag'              => 'noindex',
		);
		if ( empty( $limit['allowed'] ) ) {
			$headers['Retry-After'] = (string) (int) $limit['retry_after'];
		}
		return $headers;
	}

	/**
	 * Visitor IP. Forwarding headers count only when Geo Core already trusts that proxy.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = '';
		if ( class_exists( 'RWGC_GeoIP', false ) ) {
			$ip = (string) RWGC_GeoIP::get_current_ip();
		}
		if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$raw = wp_unslash( $_SERVER['REMOTE_ADDR'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by normal_ip().
			$ip  = is_string( $raw ) ? sanitize_text_field( $raw ) : '';
		}
		$filtered = apply_filters( 'rwgc_ab_render_client_ip', $ip );
		return self::normal_ip( is_string( $filtered ) ? $filtered : $ip );
	}

	/**
	 * @param string $ip Raw IP.
	 * @return string
	 */
	private static function normal_ip( $ip ) {
		$ip = trim( (string) $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}

if ( ! function_exists( 'rwgc_ab_render_test' ) ) {
	/**
	 * Render one active test and apply the cache policy.
	 *
	 * Gutenberg, Elementor, and the shortcode call this when they output a test.
	 * Print the returned HTML. In fragment mode it is the variant A placeholder.
	 *
	 * @param int|string           $test_id Test post ID.
	 * @param array<string,string> $arms    Arm id => HTML. Key `a` is shown when JavaScript does not run.
	 * @return string
	 */
	function rwgc_ab_render_test( $test_id, array $arms ) {
		return RWGC_AB_Cache::render( $test_id, $arms );
	}
}

if ( ! function_exists( 'rwgc_ab_mark_rendered' ) ) {
	/**
	 * Tell the cache layer that this request is outputting an active test.
	 *
	 * Use rwgc_ab_render_test() when the renderer wants the placeholder or the
	 * single-arm wrapper. This only applies the cache headers.
	 *
	 * @param int|string $test_id Test post ID.
	 * @return bool
	 */
	function rwgc_ab_mark_rendered( $test_id ) {
		return RWGC_AB_Cache::mark_rendered( $test_id );
	}
}
