<?php
/**
 * Cache-safe A/B rendering: server bypass, LiteSpeed vary, fragment placeholder, rate limit.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * @param string $scheme Scheme.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		unset( $scheme );
		return 'rwgc-ab-test-salt';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['rwgc_test_is_admin'] );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	/**
	 * @param string $handle Handle.
	 * @param string $src    Source.
	 * @param array  $deps   Dependencies.
	 * @param mixed  $ver    Version.
	 * @param bool   $footer Footer.
	 * @return void
	 */
	function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['rwgc_test_registered_scripts'][ $handle ] = array(
			'src'    => $src,
			'deps'   => $deps,
			'ver'    => $ver,
			'footer' => $footer,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * @param string $handle Handle.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['rwgc_test_scripts'][ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	/**
	 * @param string               $handle Handle.
	 * @param string               $object Object name.
	 * @param array<string, mixed> $data   Data.
	 * @return void
	 */
	function wp_localize_script( $handle, $object, $data ) {
		$GLOBALS['rwgc_test_localized'][ $handle ] = array(
			'object' => $object,
			'data'   => $data,
		);
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	/**
	 * @param string $handle Handle.
	 * @param string $status Status.
	 * @return bool
	 */
	function wp_script_is( $handle, $status = 'enqueued' ) {
		unset( $status );
		return ! empty( $GLOBALS['rwgc_test_scripts'][ $handle ] );
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function rest_url( $path = '' ) {
		return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return trim( (string) $url );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return isset( $GLOBALS['rwgc_test_transients'][ $key ] ) ? $GLOBALS['rwgc_test_transients'][ $key ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		$GLOBALS['rwgc_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	/**
	 * @param string               $namespace Namespace.
	 * @param string               $route     Route.
	 * @param array<string, mixed> $args      Args.
	 * @return void
	 */
	function register_rest_route( $namespace, $route, $args ) {
		$GLOBALS['rwgc_test_routes'][] = array(
			'namespace' => $namespace,
			'route'     => $route,
			'args'      => $args,
		);
	}
}

/**
 * Minimal REST request.
 */
class RWGC_AB_Cache_Request {

	/**
	 * @var array<string, mixed>
	 */
	private $params;

	/**
	 * @param array<string, mixed> $params Params.
	 */
	public function __construct( array $params ) {
		$this->params = $params;
	}

	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_param( $key ) {
		return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
	}
}

/**
 * @covers RWGC_AB_Cache
 */
final class RWGC_AB_CacheTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private $server = array();

	/**
	 * @var array<string, mixed>
	 */
	private $cookie = array();

	/**
	 * @var bool
	 */
	private $had_filters = false;

	/**
	 * @var array<string, mixed>|null
	 */
	private $filters = null;

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'RWGC_URL' ) ) {
			define( 'RWGC_URL', 'https://example.test/wp-content/plugins/reactwoo-geocore/' );
		}
		if ( ! defined( 'RWGC_VERSION' ) ) {
			define( 'RWGC_VERSION', '1.9.26' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
		require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-cache-compat.php';
		require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-assignment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-rest.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->server      = $_SERVER;
		$this->cookie      = $_COOKIE;
		$this->had_filters = isset( $GLOBALS['rwgc_test_filters'] );
		$this->filters     = $this->had_filters && is_array( $GLOBALS['rwgc_test_filters'] ) ? $GLOBALS['rwgc_test_filters'] : null;
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		unset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$GLOBALS['rwgc_test_is_admin']  = false;
		$GLOBALS['rwgc_test_filters']   = array(
			'rwgc_ab_emit_http_headers' => static function () {
				return false;
			},
			'rwgc_ab_test_is_running'   => static function ( $active, $test_id ) {
				unset( $active );
				return in_array( (int) $test_id, array( 42, 43 ), true );
			},
		);
		$GLOBALS['rwgc_test_transients'] = array();
		$GLOBALS['rwgc_ab_test_litespeed'] = false;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER = $this->server;
		$_COOKIE = $this->cookie;
		if ( $this->had_filters ) {
			$GLOBALS['rwgc_test_filters'] = $this->filters;
		} else {
			unset( $GLOBALS['rwgc_test_filters'] );
		}
		$GLOBALS['rwgc_test_is_admin']     = false;
		$GLOBALS['rwgc_ab_test_litespeed'] = false;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		parent::tearDown();
	}

	/**
	 * @return array<string, array{0:bool,1:bool,2:bool,3:bool,4:array{cache_control:string,donotcachepage:bool,vary:bool}}>
	 */
	public function directive_provider(): array {
		$bypass = array(
			'cache_control'  => 'private, no-store',
			'donotcachepage' => true,
			'vary'           => false,
		);
		$share  = array(
			'cache_control'  => '',
			'donotcachepage' => false,
			'vary'           => false,
		);
		$vary   = array(
			'cache_control'  => '',
			'donotcachepage' => false,
			'vary'           => true,
		);
		return array(
			'fragment page can be cached'          => array( true, true, true, false, $share ),
			'fragment page can be cached off ls'   => array( false, true, false, false, $share ),
			'fragment response that sets cookie'   => array( true, true, true, true, $bypass ),
			'litespeed established assignment'     => array( true, false, true, false, $vary ),
			'litespeed first cookie stays private' => array( true, false, true, true, $bypass ),
			'litespeed without assignment'         => array( false, false, true, false, $bypass ),
			'super cache with assignment'          => array( true, false, false, false, $bypass ),
			'super cache without assignment'       => array( false, false, false, false, $bypass ),
		);
	}

	/**
	 * @dataProvider directive_provider
	 * @param bool                                                                        $has_assignment  Assignment.
	 * @param bool                                                                        $fragment        Fragment.
	 * @param bool                                                                        $litespeed       LiteSpeed.
	 * @param bool                                                                        $cookie_just_set New cookie.
	 * @param array{cache_control:string,donotcachepage:bool,vary:bool}                   $expected        Directives.
	 * @return void
	 */
	public function test_cache_directives( $has_assignment, $fragment, $litespeed, $cookie_just_set, array $expected ): void {
		$this->assertSame( $expected, RWGC_AB_Cache::cache_directives( $has_assignment, $fragment, $litespeed, $cookie_just_set ) );
	}

	public function test_server_mode_prints_only_the_assigned_arm_and_bypasses_a_shared_cache(): void {
		$this->seed( 42, 'b' );
		$html = rwgc_ab_render_test(
			42,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
				'c' => '<p>ARM-C-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-C-ONLY', $html );
		$this->assertStringContainsString( 'data-rwgc-ab="42"', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="server"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertSame(
			array(
				'cache_control'  => 'private, no-store',
				'donotcachepage' => true,
				'vary'           => false,
			),
			RWGC_AB_Cache::last_directives()
		);
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertSame( array(), RWGC_AB_Cache::vary_groups_added() );
	}

	public function test_litespeed_varies_on_the_verified_arm_not_the_raw_cookie(): void {
		$this->seed( 42, 'a' );
		$this->seed_extra( 43, 'b' );
		$GLOBALS['rwgc_ab_test_litespeed'] = true;
		$GLOBALS['rwgc_test_filters']['rwgc_ab_litespeed_active'] = static function () {
			return true;
		};

		$first = rwgc_ab_render_test(
			42,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$second = rwgc_ab_render_test(
			43,
			array(
				'a' => '<p>OTHER-A</p>',
				'b' => '<p>OTHER-B</p>',
			)
		);

		$this->assertStringContainsString( 'ARM-A-ONLY', $first );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $first );
		$this->assertStringContainsString( 'OTHER-B', $second );
		$this->assertStringNotContainsString( 'OTHER-A', $second );
		$this->assertSame(
			array(
				'cache_control'  => '',
				'donotcachepage' => false,
				'vary'           => true,
			),
			RWGC_AB_Cache::last_directives()
		);
		$this->assertSame( array( 'rwgc_ab_42_a', 'rwgc_ab_43_b' ), RWGC_AB_Cache::vary_groups_added() );
		$this->assertSame( array( 'rwgc_ab_42_a', 'rwgc_ab_43_b' ), RWGC_Cache_Compat::ab_vary_groups_for_request() );
		$this->assertSame(
			array(
				'rwgc_ab_42' => 'a',
				'rwgc_ab_43' => 'b',
			),
			RWGC_Cache_Compat::filter_litespeed_ab( array() )
		);
		$forged = '1|ffffffffffffffffffffffffffffffff|42:b|aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$this->assertStringNotContainsString( $forged, implode( ' ', RWGC_AB_Cache::vary_groups_added() ) );
	}

	public function test_litespeed_guest_mode_does_not_store_a_shared_copy(): void {
		$this->seed( 42, 'b' );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_litespeed_active']  = static function () {
			return true;
		};
		$GLOBALS['rwgc_test_filters']['rwgc_ab_litespeed_can_vary'] = static function () {
			return false;
		};
		$html = rwgc_ab_render_test(
			42,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $html );
		$this->assertSame( 'private, no-store', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertTrue( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['vary'] );
		$this->assertSame( array(), RWGC_AB_Cache::vary_groups_added() );
	}

	public function test_a_forged_cookie_does_not_choose_the_vary_group(): void {
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = '1|ffffffffffffffffffffffffffffffff|42:b|aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$GLOBALS['rwgc_test_filters']['rwgc_ab_litespeed_active'] = static function () {
			return true;
		};
		$html = rwgc_ab_render_test(
			42,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringNotContainsString( '42:b', implode( ' ', RWGC_AB_Cache::vary_groups_added() ) );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['vary'] );
		$this->assertTrue( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertSame( 'private, no-store', RWGC_AB_Cache::last_directives()['cache_control'] );
		$shows_a = false !== strpos( $html, 'ARM-A-ONLY' );
		$shows_b = false !== strpos( $html, 'ARM-B-ONLY' );
		$this->assertTrue( $shows_a xor $shows_b );
		$this->assertStringNotContainsString( '42:b', $html );
	}

	public function test_fragment_mode_shows_a_and_hides_b_until_javascript_runs(): void {
		$this->seed( 42, 'b' );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		$html = rwgc_ab_render_test(
			42,
			array(
				'a' => '<p>ARM-A-ONLY</p><script>alert(1)</script>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( '<script', strtolower( $html ) );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="a"', $html );
		$this->assertStringNotContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['vary'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertTrue( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertArrayHasKey( 'rwgc-ab', $GLOBALS['rwgc_test_registered_scripts'] );
		$this->assertStringContainsString( 'assets/js/rwgc-ab.js', $GLOBALS['rwgc_test_registered_scripts']['rwgc-ab']['src'] );
		$this->assertSame( 'rwgcAb', $GLOBALS['rwgc_test_localized']['rwgc-ab']['object'] );
		$this->assertSame( 'https://example.test/wp-json/reactwoo-geocore/v1/ab/render', $GLOBALS['rwgc_test_localized']['rwgc-ab']['data']['endpoint'] );
	}

	public function test_fragment_mode_without_an_existing_cookie_is_not_cached(): void {
		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		$html = rwgc_ab_render_test(
			42,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $html );
		$this->assertSame( 'private, no-store', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertTrue( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertNotNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_a_stopped_test_does_not_change_the_cache_and_returns_a(): void {
		$this->seed( 7, 'b' );
		$html = rwgc_ab_render_test(
			7,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( 'data-rwgc-ab', $html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertFalse( rwgc_ab_mark_rendered( 7 ) );
	}

	public function test_render_route_is_public_no_store_and_rate_limited(): void {
		$GLOBALS['rwgc_test_routes'] = array();
		RWGC_REST::register_ab_routes();
		$this->assertNotEmpty( $GLOBALS['rwgc_test_routes'] );
		$route = $GLOBALS['rwgc_test_routes'][ count( $GLOBALS['rwgc_test_routes'] ) - 1 ];
		$this->assertSame( 'reactwoo-geocore/v1', $route['namespace'] );
		$this->assertSame( '/ab/render', $route['route'] );
		$this->assertSame( 'GET', $route['args']['methods'] );
		$this->assertTrue( call_user_func( $route['args']['permission_callback'], null ) );

		$this->seed( 42, 'b' );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_variant_html'] = static function ( $html, $test_id, $arm ) {
			unset( $html );
			return '<p>ARM-' . $test_id . '-' . $arm . '</p><script>alert(1)</script>';
		};
		$built = RWGC_REST::get_ab_render( new RWGC_AB_Cache_Request( array( 'test' => 42 ) ) );
		$this->assertSame( 200, $built['status'] );
		$this->assertStringContainsString( 'no-store', $built['headers']['Cache-Control'] );
		$this->assertStringContainsString( 'private', $built['headers']['Cache-Control'] );
		$this->assertSame( 'no-cache', $built['headers']['X-LiteSpeed-Cache-Control'] );
		$this->assertSame( 'b', $built['body']['arm'] );
		$this->assertSame( 42, $built['body']['test'] );
		$this->assertStringContainsString( 'ARM-42-b', $built['body']['html'] );
		$this->assertStringNotContainsString( '<script', strtolower( $built['body']['html'] ) );

		$missing = RWGC_AB_Cache::build_render_response( 99, '203.0.113.9' );
		$this->assertSame( 404, $missing['status'] );
		$this->assertStringContainsString( 'no-store', $missing['headers']['Cache-Control'] );
	}

	public function test_render_route_allows_forty_requests_per_ip_each_minute(): void {
		$ip = '203.0.113.10';
		for ( $i = 0; $i < 40; $i++ ) {
			$step = RWGC_AB_Cache::consume_rate_limit( $ip );
			$this->assertTrue( $step['allowed'], 'Request ' . ( $i + 1 ) . ' should be allowed' );
		}
		$blocked = RWGC_AB_Cache::consume_rate_limit( $ip );
		$this->assertFalse( $blocked['allowed'] );
		$this->assertSame( 0, $blocked['remaining'] );
		$this->assertGreaterThan( 0, $blocked['retry_after'] );

		$other = RWGC_AB_Cache::consume_rate_limit( '203.0.113.11' );
		$this->assertTrue( $other['allowed'] );
		$this->assertSame( 39, $other['remaining'] );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_render_rate_now'] = static function () {
			return time() + 61;
		};
		$again = RWGC_AB_Cache::build_render_response( 42, $ip );
		$this->assertSame( 200, $again['status'] );
		$this->assertArrayNotHasKey( 'Retry-After', $again['headers'] );

		$GLOBALS['rwgc_test_transients'] = array();
		unset( $GLOBALS['rwgc_test_filters']['rwgc_ab_render_rate_now'] );
		for ( $i = 0; $i < 40; $i++ ) {
			RWGC_AB_Cache::consume_rate_limit( '203.0.113.12' );
		}
		$limited = RWGC_AB_Cache::build_render_response( 42, '203.0.113.12' );
		$this->assertSame( 429, $limited['status'] );
		$this->assertSame( 'rwgc_ab_rate_limited', $limited['body']['code'] );
		$this->assertArrayHasKey( 'Retry-After', $limited['headers'] );
		$this->assertStringContainsString( 'no-store', $limited['headers']['Cache-Control'] );
	}

	public function test_invalid_ip_does_not_open_its_own_bucket(): void {
		RWGC_AB_Cache::consume_rate_limit( 'not-an-ip' );
		$second = RWGC_AB_Cache::consume_rate_limit( 'also-bad' );
		$this->assertSame( 38, $second['remaining'] );
	}

	public function test_default_mode_is_server_and_fragment_is_the_only_other_mode(): void {
		$this->assertSame( 'server', RWGC_AB_Cache::sanitize_mode( '' ) );
		$this->assertSame( 'server', RWGC_AB_Cache::sanitize_mode( 'ajax' ) );
		$this->assertSame( 'fragment', RWGC_AB_Cache::sanitize_mode( 'Fragment' ) );
		$this->assertSame( 'server', RWGC_AB_Cache::mode() );
	}

	public function test_ab_classes_do_not_check_a_licence(): void {
		foreach ( array( 'class-rwgc-ab-cache.php', 'class-rwgc-ab-assignment.php' ) as $file ) {
			$lower = strtolower( (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/ab/' . $file ) );
			$this->assertStringNotContainsString( 'license', $lower );
			$this->assertStringNotContainsString( 'licence', $lower );
			$this->assertStringNotContainsString( 'entitle', $lower );
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_fragment_route_assigns_during_rest(): void {
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		unset( $_SERVER['REQUEST_URI'], $_GET['rest_route'], $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$GLOBALS['rwgc_test_filters'] = array(
			'rwgc_ab_test_is_running' => static function () {
				return true;
			},
		);
		$GLOBALS['rwgc_test_transients'] = array();
		define( 'REST_REQUEST', true );
		RWGC_AB_Assignment::reset_request_state();

		$this->assertSame( 'a', RWGC_AB_Assignment::variant( 99 ) );
		$this->assertArrayNotHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );

		RWGC_AB_Assignment::reset_request_state();
		$built = RWGC_AB_Cache::build_render_response( 42, '203.0.113.20' );
		$this->assertSame( 200, $built['status'] );
		$this->assertContains( $built['body']['arm'], array( 'a', 'b' ) );
		$this->assertArrayHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );
		$this->assertNotNull( RWGC_AB_Assignment::last_cookie_header() );

		RWGC_AB_Assignment::reset_request_state();
		$again = RWGC_AB_Cache::build_render_response( 42, '203.0.113.21' );
		$this->assertSame( $built['body']['arm'], $again['body']['arm'] );
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $arm     Arm.
	 * @return void
	 */
	private function seed( $test_id, $arm ): void {
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie(
			str_repeat( 'ab', 16 ),
			array(
				$test_id => array(
					'variant'   => $arm,
					'viewed'    => false,
					'converted' => false,
				),
			)
		);
		RWGC_AB_Assignment::reset_request_state();
	}

	/**
	 * Add another assignment without dropping the one already seeded.
	 *
	 * @param int    $test_id Test id.
	 * @param string $arm     Arm.
	 * @return void
	 */
	private function seed_extra( $test_id, $arm ): void {
		$state = RWGC_AB_Assignment::decode_cookie( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$this->assertIsArray( $state );
		$state['assignments'][ $test_id ] = array(
			'variant'   => $arm,
			'viewed'    => false,
			'converted' => false,
		);
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie( $state['visitor_id'], $state['assignments'] );
		RWGC_AB_Assignment::reset_request_state();
	}
}
