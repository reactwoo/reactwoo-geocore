<?php
/**
 * Free A/B visitor cookie: 50/50 bucket, HMAC, and bot / preview / REST skips.
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

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * @return bool
	 */
	function is_user_logged_in() {
		return ! empty( $GLOBALS['rwgc_test_logged_in'] );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap  Capability.
	 * @param mixed  ...$args Extra arguments.
	 * @return bool
	 */
	function current_user_can( $cap, ...$args ) {
		unset( $args );
		$caps = isset( $GLOBALS['rwgc_test_caps'] ) && is_array( $GLOBALS['rwgc_test_caps'] ) ? $GLOBALS['rwgc_test_caps'] : array();
		return ! empty( $caps[ (string) $cap ] );
	}
}

/**
 * @covers RWGC_AB_Assignment
 */
final class RWGC_AB_AssignmentTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private $server = array();

	/**
	 * @var array<string, mixed>
	 */
	private $get = array();

	/**
	 * @var array<string, mixed>
	 */
	private $cookie = array();

	/**
	 * @var array<string, mixed>|null
	 */
	private $filters = null;

	/**
	 * @var bool
	 */
	private $had_filters = false;

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-assignment.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->server  = $_SERVER;
		$this->get     = $_GET;
		$this->cookie  = $_COOKIE;
		$this->had_filters = isset( $GLOBALS['rwgc_test_filters'] );
		$this->filters     = $this->had_filters && is_array( $GLOBALS['rwgc_test_filters'] ) ? $GLOBALS['rwgc_test_filters'] : null;
		$_SERVER['REQUEST_METHOD']   = 'GET';
		$_SERVER['HTTP_USER_AGENT']  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
		unset( $_GET['preview'], $_GET['elementor-preview'], $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$GLOBALS['rwgc_test_is_admin']   = false;
		$GLOBALS['rwgc_test_logged_in']  = false;
		$GLOBALS['rwgc_test_caps']       = array();
		$GLOBALS['rwgc_test_filters']    = array();
		RWGC_AB_Assignment::reset_request_state();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER = $this->server;
		$_GET    = $this->get;
		$_COOKIE = $this->cookie;
		if ( $this->had_filters ) {
			$GLOBALS['rwgc_test_filters'] = $this->filters;
		} else {
			unset( $GLOBALS['rwgc_test_filters'] );
		}
		$GLOBALS['rwgc_test_is_admin']  = false;
		$GLOBALS['rwgc_test_logged_in'] = false;
		$GLOBALS['rwgc_test_caps']      = array();
		RWGC_AB_Assignment::reset_request_state();
		parent::tearDown();
	}

	public function test_ten_thousand_visitors_split_within_two_percent(): void {
		$counts = array(
			'a' => 0,
			'b' => 0,
		);
		for ( $i = 0; $i < 10000; $i++ ) {
			$visitor = sprintf( 'sim-%05d', $i );
			$arm     = RWGC_AB_Assignment::variant_for_visitor( '42', $visitor );
			$bucket  = RWGC_Decision_Experiment_Assigner::bucket( '42', $visitor );
			$this->assertSame( $bucket < 5000 ? 'a' : 'b', $arm );
			$this->assertSame( $arm, RWGC_AB_Assignment::variant_for_visitor( '42', $visitor ) );
			++$counts[ $arm ];
		}
		$this->assertGreaterThanOrEqual( 4800, $counts['a'] );
		$this->assertLessThanOrEqual( 5200, $counts['a'] );
		$this->assertSame( 10000, $counts['a'] + $counts['b'] );
	}

	public function test_same_cookie_keeps_the_same_variant(): void {
		$this->running( array( 42 ) );
		RWGC_AB_Assignment::variant( 42 );
		$first = $this->cookie_value();
		$decoded = RWGC_AB_Assignment::decode_cookie( $first );
		$this->assertIsArray( $decoded );
		$arm = $decoded['assignments'][42]['variant'];
		$this->assertSame( RWGC_AB_Assignment::variant_for_visitor( '42', $decoded['visitor_id'] ), $arm );

		RWGC_AB_Assignment::reset_request_state();
		$this->assertSame( $arm, RWGC_AB_Assignment::variant( 42 ) );
		$second = RWGC_AB_Assignment::decode_cookie( $this->cookie_value() );
		$this->assertIsArray( $second );
		$this->assertSame( $decoded['visitor_id'], $second['visitor_id'] );
		$this->assertSame( $arm, $second['assignments'][42]['variant'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_tampered_cookie_is_reset_and_resigned(): void {
		$this->running( array( 42 ) );
		$visitor = str_repeat( 'ab', 16 );
		$signed  = RWGC_AB_Assignment::encode_cookie(
			$visitor,
			array(
				42 => array(
					'variant'   => 'a',
					'viewed'    => true,
					'converted' => true,
				),
			)
		);
		$forged = str_replace( '42:a.v.c', '42:b.v.c', $signed );
		$this->assertNotSame( $signed, $forged );
		$this->assertNull( RWGC_AB_Assignment::decode_cookie( $forged ) );
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = $forged;

		RWGC_AB_Assignment::variant( 42 );

		$fresh = RWGC_AB_Assignment::decode_cookie( $this->cookie_value() );
		$this->assertIsArray( $fresh );
		$this->assertNotSame( $visitor, $fresh['visitor_id'] );
		$this->assertArrayHasKey( 42, $fresh['assignments'] );
		$this->assertSame(
			RWGC_AB_Assignment::variant_for_visitor( '42', $fresh['visitor_id'] ),
			$fresh['assignments'][42]['variant']
		);
		$this->assertFalse( $fresh['assignments'][42]['viewed'] );
		$this->assertFalse( $fresh['assignments'][42]['converted'] );
		$this->assertSame( 'Lax', RWGC_AB_Assignment::last_cookie_header()['samesite'] );
		$this->assertTrue( RWGC_AB_Assignment::last_cookie_header()['httponly'] );
		$this->assertSame( RWGC_AB_Assignment::COOKIE, RWGC_AB_Assignment::last_cookie_header()['name'] );
		$expires = (int) RWGC_AB_Assignment::last_cookie_header()['expires'];
		$this->assertGreaterThanOrEqual( time() + RWGC_AB_Assignment::TTL - 5, $expires );
		$this->assertLessThanOrEqual( time() + RWGC_AB_Assignment::TTL + 5, $expires );
	}

	public function test_signed_flags_survive_a_new_assignment(): void {
		$visitor = str_repeat( 'cd', 16 );
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie(
			$visitor,
			array(
				7 => array(
					'variant'   => 'b',
					'viewed'    => true,
					'converted' => false,
				),
			)
		);
		$this->running( array( 7, 8 ) );

		RWGC_AB_Assignment::variant( 8 );

		$decoded = RWGC_AB_Assignment::decode_cookie( $this->cookie_value() );
		$this->assertIsArray( $decoded );
		$this->assertSame( $visitor, $decoded['visitor_id'] );
		$this->assertSame( 'b', $decoded['assignments'][7]['variant'] );
		$this->assertTrue( $decoded['assignments'][7]['viewed'] );
		$this->assertFalse( $decoded['assignments'][7]['converted'] );
		$this->assertArrayHasKey( 8, $decoded['assignments'] );
		$this->assertStringContainsString( '7:b.v', $this->cookie_value() );
	}

	public function test_bots_do_not_get_a_cookie_and_are_served_a(): void {
		$this->assertTrue( RWGC_AB_Assignment::is_known_bot( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) );
		$this->assertTrue( RWGC_AB_Assignment::is_known_bot( 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.1)' ) );
		$this->assertFalse( RWGC_AB_Assignment::is_known_bot( 'Mozilla/5.0 Chrome/120.0.0.0' ) );
		$this->assertFalse( RWGC_AB_Assignment::is_known_bot( '' ) );

		$visitor = str_repeat( 'ef', 16 );
		$signed  = RWGC_AB_Assignment::encode_cookie(
			$visitor,
			array(
				42 => array(
					'variant' => 'b',
				),
			)
		);
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = $signed;
		$_SERVER['HTTP_USER_AGENT']            = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
		$this->running( array( 42, 99 ) );

		$this->assertSame( 'a', RWGC_AB_Assignment::variant( 42 ) );
		RWGC_AB_Assignment::maybe_set_cookie();

		$this->assertSame( $signed, $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
		$this->assertArrayNotHasKey( 99, RWGC_AB_Assignment::decode_cookie( $signed )['assignments'] );
	}

	public function test_logged_in_editor_preview_does_not_write_and_serves_a(): void {
		$signed = $this->seed_variant( 42, 'b' );
		$this->as_editor_preview();
		$this->running( array( 42 ) );

		$this->assertSame( 'a', RWGC_AB_Assignment::variant( 42 ) );
		RWGC_AB_Assignment::maybe_set_cookie();

		$this->assertSame( $signed, $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_logged_in_editor_outside_preview_keeps_the_sticky_arm(): void {
		$signed = $this->seed_variant( 42, 'b' );
		$GLOBALS['rwgc_test_logged_in'] = true;
		$GLOBALS['rwgc_test_caps']      = array( 'edit_pages' => true );
		$this->running( array( 42 ) );

		$this->assertSame( 'b', RWGC_AB_Assignment::variant( 42 ) );
		RWGC_AB_Assignment::maybe_set_cookie();
		$this->assertSame( $signed, $this->cookie_value() );
	}

	public function test_consent_veto_serves_a_and_writes_nothing(): void {
		$signed = $this->seed_variant( 42, 'b' );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_can_track'] = static function () {
			return false;
		};
		$this->running( array( 42, 100 ) );
		RWGC_AB_Assignment::reset_request_state();

		$this->assertSame( 'a', RWGC_AB_Assignment::variant( 42 ) );
		RWGC_AB_Assignment::maybe_set_cookie();

		$this->assertSame( $signed, $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_request_gate_skips_non_public_gets(): void {
		$this->assertTrue( RWGC_AB_Assignment::request_skips_assignment( array( 'bot' => true, 'method' => 'GET' ) ) );
		$this->assertTrue( RWGC_AB_Assignment::request_skips_assignment( array( 'preview' => true, 'editor' => true, 'method' => 'GET' ) ) );
		$this->assertFalse( RWGC_AB_Assignment::request_skips_assignment( array( 'preview' => true, 'method' => 'GET' ) ) );
		$this->assertFalse( RWGC_AB_Assignment::request_skips_assignment( array( 'editor' => true, 'method' => 'GET' ) ) );
		foreach ( array( 'admin', 'ajax', 'rest', 'cron', 'cli', 'feed', 'json' ) as $flag ) {
			$this->assertTrue(
				RWGC_AB_Assignment::request_skips_assignment(
					array(
						$flag   => true,
						'method' => 'GET',
					)
				),
				$flag
			);
		}
		$this->assertTrue( RWGC_AB_Assignment::request_skips_assignment( array( 'method' => 'POST' ) ) );
		$this->assertFalse( RWGC_AB_Assignment::request_skips_assignment( array( 'method' => 'GET' ) ) );
	}

	public function test_post_and_admin_skip_but_a_logged_out_preview_can_assign(): void {
		$this->running( array( 42 ) );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		RWGC_AB_Assignment::maybe_set_cookie();
		$this->assertArrayNotHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );

		$_SERVER['REQUEST_METHOD']         = 'GET';
		$GLOBALS['rwgc_test_is_admin']     = true;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Assignment::maybe_set_cookie();
		$this->assertArrayNotHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );

		$GLOBALS['rwgc_test_is_admin'] = false;
		$_GET['preview']               = 'true';
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Assignment::variant( 42 );
		$this->assertArrayHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_rest_request_never_writes_the_cookie(): void {
		$this->boot_isolated_request();
		define( 'REST_REQUEST', true );
		$signed = $this->seed_variant( 15, 'b' );
		$this->running( array( 15, 16 ) );

		$this->assertSame( 'b', RWGC_AB_Assignment::variant( 15 ) );
		$this->assertSame( 'a', RWGC_AB_Assignment::variant( 16 ) );
		RWGC_AB_Assignment::maybe_set_cookie();

		$this->assertSame( $signed, $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_ajax_request_never_writes_the_cookie(): void {
		$this->boot_isolated_request();
		define( 'DOING_AJAX', true );
		$this->running( array( 15 ) );

		RWGC_AB_Assignment::maybe_set_cookie();
		$this->assertSame( 'a', RWGC_AB_Assignment::variant( 15 ) );

		$this->assertArrayNotHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_a_page_without_a_rendered_test_does_not_set_the_cookie(): void {
		$this->running( array( 42, 43 ) );
		RWGC_AB_Assignment::init();
		RWGC_AB_Assignment::maybe_set_cookie();

		$this->assertArrayNotHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	public function test_plugin_assigns_when_a_test_is_rendered_not_on_send_headers(): void {
		$root = dirname( __DIR__, 2 );
		$src  = (string) file_get_contents( $root . '/includes/ab/class-rwgc-ab-assignment.php' );
		$plugin = (string) file_get_contents( $root . '/includes/class-rwgc-plugin.php' );

		$this->assertStringNotContainsString( "add_action( 'send_headers', array( __CLASS__, 'maybe_set_cookie' )", $src );
		$this->assertStringContainsString( 'RWGC_Decision_Experiment_Assigner::bucket(', $src );
		$this->assertStringContainsString( "apply_filters( 'rwgc_ab_can_track'", $src );
		$this->assertStringContainsString( 'includes/ab/class-rwgc-ab-assignment.php', $plugin );
		$this->assertStringContainsString( 'RWGC_AB_Assignment::init()', $plugin );
		$this->assertStringNotContainsString( 'rwgo_ab', $src );

		$lower = strtolower( $src );
		$this->assertStringNotContainsString( 'license', $lower );
		$this->assertStringNotContainsString( 'licence', $lower );
		$this->assertStringNotContainsString( 'entitle', $lower );
	}

	/**
	 * @param int[] $ids Running test ids.
	 * @return void
	 */
	private function running( array $ids ) {
		$GLOBALS['rwgc_test_filters']['rwgc_ab_running_test_ids'] = static function () use ( $ids ) {
			return $ids;
		};
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @return string Signed cookie.
	 */
	private function seed_variant( $test_id, $variant ) {
		$cookie = RWGC_AB_Assignment::encode_cookie(
			str_repeat( 'a1', 16 ),
			array(
				$test_id => array(
					'variant' => $variant,
				),
			)
		);
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = $cookie;
		return $cookie;
	}

	/**
	 * @return void
	 */
	private function as_editor_preview() {
		$GLOBALS['rwgc_test_logged_in'] = true;
		$GLOBALS['rwgc_test_caps']      = array(
			'edit_pages' => true,
		);
		$_GET['elementor-preview'] = '88';
	}

	/**
	 * @return string
	 */
	private function cookie_value() {
		$this->assertArrayHasKey( RWGC_AB_Assignment::COOKIE, $_COOKIE );
		return (string) $_COOKIE[ RWGC_AB_Assignment::COOKIE ];
	}

	/**
	 * Separate-process tests do not inherit the parent setUp superglobals.
	 *
	 * @return void
	 */
	private function boot_isolated_request() {
		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		unset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );
		$GLOBALS['rwgc_test_filters'] = array();
		RWGC_AB_Assignment::reset_request_state();
	}
}
