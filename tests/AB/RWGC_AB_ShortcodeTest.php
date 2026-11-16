<?php
/**
 * [rwgc_ab] shortcode: same render contract as the Gutenberg A/B block.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * @param array<string, mixed> $pairs     Defaults.
	 * @param mixed                $atts      Attributes.
	 * @param string               $shortcode Shortcode name.
	 * @return array<string, mixed>
	 */
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		unset( $shortcode );
		$atts = is_array( $atts ) ? $atts : array();
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	/**
	 * Expand the nested tag the shortcode tests register. Other text is unchanged.
	 *
	 * @param mixed $content Content.
	 * @return string
	 */
	function do_shortcode( $content ) {
		$content = (string) $content;
		if ( isset( $GLOBALS['rwgc_test_shortcode_replacements'] ) && is_array( $GLOBALS['rwgc_test_shortcode_replacements'] ) ) {
			$content = strtr( $content, $GLOBALS['rwgc_test_shortcode_replacements'] );
		}
		return $content;
	}
}

/**
 * @covers RWGC_Shortcodes
 */
final class RWGC_AB_ShortcodeTest extends TestCase {

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
		if ( ! defined( 'RWGC_PATH' ) ) {
			define( 'RWGC_PATH', dirname( __DIR__, 2 ) . '/' );
		}
		if ( ! defined( 'RWGC_URL' ) ) {
			define( 'RWGC_URL', 'https://example.test/wp-content/plugins/reactwoo-geocore/' );
		}
		if ( ! defined( 'RWGC_VERSION' ) ) {
			define( 'RWGC_VERSION', '1.9.27' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tests.php';
		require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-cache-compat.php';
		require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-assignment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-shortcodes.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-admin.php';
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
		$GLOBALS['rwgc_test_is_admin']    = false;
		$GLOBALS['rwgc_test_is_revision'] = false;
		$GLOBALS['rwgc_test_is_autosave'] = false;
		$GLOBALS['rwgc_test_filters']     = array(
			'rwgc_ab_emit_http_headers' => static function () {
				return false;
			},
		);
		$GLOBALS['rwgc_ab_test_posts']                 = array();
		$GLOBALS['rwgc_ab_test_meta']                  = array();
		$GLOBALS['rwgc_ab_test_seq']                   = 0;
		$GLOBALS['rwgc_test_scripts']                  = array();
		$GLOBALS['rwgc_test_registered_scripts']       = array();
		$GLOBALS['rwgc_test_shortcode_replacements']   = array(
			'[rwgc_city]' => 'Paris',
		);
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
		$GLOBALS['rwgc_test_is_admin']               = false;
		$GLOBALS['rwgc_test_is_revision']            = false;
		$GLOBALS['rwgc_test_is_autosave']            = false;
		$GLOBALS['rwgc_test_shortcode_replacements'] = array();
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		parent::tearDown();
	}

	public function test_variants_parse_in_order_and_nested_shortcodes_run(): void {
		$arms = RWGC_Shortcodes::variant_arms(
			"[rwgc_ab_variant]\nAlpha [rwgc_city]\n[/rwgc_ab_variant]\n[rwgc_ab_variant variant=\"b\"]Beta[/rwgc_ab_variant][rwgc_ab_variant]Gamma[/rwgc_ab_variant]"
		);
		$this->assertSame( "\nAlpha [rwgc_city]\n", $arms['a'] );
		$this->assertSame( 'Beta', $arms['b'] );
		$this->assertSame( "\nAlpha [rwgc_city]\n", $arms['first'] );
		$this->assertStringNotContainsString( 'Gamma', $arms['a'] . $arms['b'] );

		$reversed = RWGC_Shortcodes::variant_arms(
			'[rwgc_ab_variant variant="b"]Beta[/rwgc_ab_variant][rwgc_ab_variant variant=\'a\']Alpha[/rwgc_ab_variant]'
		);
		$this->assertSame( 'Alpha', $reversed['a'] );
		$this->assertSame( 'Beta', $reversed['b'] );
		$this->assertSame( 'Beta', $reversed['first'] );

		$html = RWGC_Shortcodes::render_ab(
			array( 'id' => '0' ),
			'[rwgc_ab_variant]<p>City [rwgc_city]</p>[/rwgc_ab_variant][rwgc_ab_variant]<p>Other</p>[/rwgc_ab_variant]'
		);
		$this->assertStringContainsString( 'City Paris', $html );
		$this->assertStringNotContainsString( '[rwgc_city]', $html );
		$this->assertStringNotContainsString( 'Other', $html );
	}

	public function test_a_running_test_prints_only_the_assigned_arm_and_bypasses_a_shared_cache(): void {
		$test_id = $this->running_test();
		$this->seed( $test_id, 'b' );
		$html = $this->render( $test_id, '<p onclick="alert(1)">ARM-A-ONLY</p>', '<p onclick="alert(1)">ARM-B-ONLY</p><script>alert(1)</script>' );

		$this->assertStringContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringNotContainsString( '<script', strtolower( $html ) );
		$this->assertStringNotContainsString( 'onclick', strtolower( $html ) );
		$this->assertStringContainsString( 'data-rwgc-ab="' . $test_id . '"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="server"', $html );
		$this->assertSame( 'private, no-store', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertTrue( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );

		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-shortcodes.php' );
		$this->assertStringContainsString( 'rwgc_ab_render_test(', $src );
		$this->assertStringContainsString( 'do_shortcode(', $src );
		$this->assertStringNotContainsString( 'DONOTCACHEPAGE', $src );
		$this->assertStringNotContainsString( 'litespeed_control_set_nocache', $src );
	}

	public function test_an_unknown_or_inactive_id_renders_the_first_variant(): void {
		$unknown = RWGC_Shortcodes::render_ab(
			array( 'id' => '999' ),
			'[rwgc_ab_variant]<p onclick="alert(1)">ARM-A-ONLY</p><script>alert(1)</script>[/rwgc_ab_variant][rwgc_ab_variant]<p>ARM-B-ONLY</p>[/rwgc_ab_variant]'
		);
		$this->assertStringContainsString( 'ARM-A-ONLY', $unknown );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $unknown );
		$this->assertStringNotContainsString( '<script', strtolower( $unknown ) );
		$this->assertStringNotContainsString( 'onclick', strtolower( $unknown ) );
		$this->assertStringContainsString( 'data-rwgc-ab="999"', $unknown );
		$this->assertStringContainsString( 'data-rwgc-arm="a"', $unknown );
		$this->assertStringNotContainsString( 'data-rwgc-ab-mode=', $unknown );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );

		$reversed = RWGC_Shortcodes::render_ab(
			array( 'id' => 'not-a-test' ),
			'[rwgc_ab_variant variant="b"]ARM-B-ONLY[/rwgc_ab_variant][rwgc_ab_variant variant="a"]ARM-A-ONLY[/rwgc_ab_variant]'
		);
		$this->assertStringContainsString( 'ARM-B-ONLY', $reversed );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $reversed );
		$this->assertStringContainsString( 'data-rwgc-ab="0"', $reversed );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );

		$draft = RWGC_AB_Tests::create(
			array(
				'title'  => 'Draft',
				'status' => 'draft',
				'config' => array( 'adapter' => 'shortcode' ),
			)
		);
		$draft_html = $this->render( $draft, '<p>ARM-A-ONLY</p>', '<p>ARM-B-ONLY</p>' );
		$this->assertStringContainsString( 'ARM-A-ONLY', $draft_html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $draft_html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );

	}

	public function test_an_ended_shortcode_serves_the_kept_arm_in_both_cache_modes(): void {
		$ended = $this->running_test();
		$this->assertTrue( RWGC_AB_Tests::end( $ended, 'b' ) );
		$this->seed( $ended, 'a' );
		$ended_html = $this->render( $ended, '<p>ARM-A-ONLY</p>', '<p>ARM-B-ONLY</p>' );
		$this->assertStringContainsString( 'ARM-B-ONLY', $ended_html );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $ended_html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="ended"', $ended_html );
		$this->assertStringContainsString( 'data-rwgc-arm="b"', $ended_html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['vary'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );

		RWGC_AB_Cache::reset_request_state();
		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		$fragment = $this->render( $ended, '<p>ARM-A-ONLY</p>', '<p>ARM-B-ONLY</p>' );
		$this->assertStringContainsString( 'ARM-B-ONLY', $fragment );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $fragment );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="ended"', $fragment );
		$this->assertStringNotContainsString( 'data-rwgc-ab-mode="fragment"', $fragment );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['vary'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
	}

	public function test_fragment_mode_prints_a_and_the_render_route_returns_the_shortcode_arm(): void {
		$test_id = $this->running_test();
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Classic',
				'post_content' => '[rwgc_ab id=\'' . $test_id . '\'][rwgc_ab_variant]<p>ARM-A-ONLY [rwgc_city]</p>[/rwgc_ab_variant][rwgc_ab_variant]<p onclick="alert(1)">ARM-B-ONLY</p><script>alert(1)</script>[/rwgc_ab_variant][/rwgc_ab]',
			)
		);
		RWGC_Shortcodes::sync_on_save( $post_id );
		$this->assertSame( $post_id, (int) get_post_meta( $test_id, RWGC_Shortcodes::AB_SOURCE_META, true ) );

		$this->seed( $test_id, 'b' );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		$html = $this->render( $test_id, '<p>ARM-A-ONLY [rwgc_city]</p>', '<p>ARM-B-ONLY</p>' );

		$this->assertStringContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringContainsString( 'Paris', $html );
		$this->assertStringNotContainsString( '[rwgc_city]', $html );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="a"', $html );
		$this->assertStringNotContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertTrue( RWGC_AB_Cache::fragment_script_queued() );

		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-shortcodes.php' );
		$this->assertStringContainsString( "add_filter( 'rwgc_ab_variant_html'", $src );
		$GLOBALS['rwgc_test_filters']['rwgc_ab_variant_html'] = array( 'RWGC_Shortcodes', 'filter_variant_html' );

		RWGC_AB_Cache::reset_request_state();
		$this->seed( $test_id, 'b' );
		$arm_b = RWGC_AB_Cache::build_render_response( $test_id, '203.0.113.80' );
		$this->assertSame( 200, $arm_b['status'] );
		$this->assertSame( 'b', $arm_b['body']['arm'] );
		$this->assertSame( $test_id, $arm_b['body']['test'] );
		$this->assertStringContainsString( 'ARM-B-ONLY', $arm_b['body']['html'] );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $arm_b['body']['html'] );
		$this->assertStringNotContainsString( '<script', strtolower( $arm_b['body']['html'] ) );
		$this->assertStringNotContainsString( 'onclick', strtolower( $arm_b['body']['html'] ) );
		$this->assertStringNotContainsString( 'data-rwgc-ab=', $arm_b['body']['html'] );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );

		RWGC_AB_Cache::reset_request_state();
		$this->seed( $test_id, 'a' );
		$arm_a = RWGC_AB_Cache::build_render_response( $test_id, '203.0.113.81' );
		$this->assertSame( 200, $arm_a['status'] );
		$this->assertSame( 'a', $arm_a['body']['arm'] );
		$this->assertStringContainsString( 'ARM-A-ONLY', $arm_a['body']['html'] );
		$this->assertStringContainsString( 'Paris', $arm_a['body']['html'] );
		$this->assertStringNotContainsString( 'ARM-B-ONLY', $arm_a['body']['html'] );
		$this->assertStringNotContainsString( '[rwgc_city]', $arm_a['body']['html'] );

		$missing = RWGC_AB_Cache::build_render_response( 99, '203.0.113.82' );
		$this->assertSame( 404, $missing['status'] );
		$this->assertSame( 'rwgc_ab_not_running', $missing['body']['code'] );
	}

	public function test_revisions_do_not_replace_the_shortcode_source(): void {
		$test_id = $this->running_test();
		$page    = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Kept',
				'post_content' => '[rwgc_ab id="' . $test_id . '"][rwgc_ab_variant]Keep[/rwgc_ab_variant][/rwgc_ab]',
			)
		);
		RWGC_Shortcodes::sync_on_save( $page );
		$this->assertSame( $page, (int) get_post_meta( $test_id, RWGC_Shortcodes::AB_SOURCE_META, true ) );

		$revision = wp_insert_post(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_title'   => 'Revision',
				'post_content' => '[rwgc_ab id="' . $test_id . '"][rwgc_ab_variant]Other[/rwgc_ab_variant][/rwgc_ab]',
			)
		);
		$GLOBALS['rwgc_test_is_revision'] = true;
		RWGC_Shortcodes::sync_on_save( $revision );
		$this->assertSame( $page, (int) get_post_meta( $test_id, RWGC_Shortcodes::AB_SOURCE_META, true ) );
	}

	public function test_the_tests_list_can_copy_the_shortcode(): void {
		$this->assertSame(
			'[rwgc_ab id="123"][rwgc_ab_variant][/rwgc_ab_variant][rwgc_ab_variant][/rwgc_ab_variant][/rwgc_ab]',
			RWGC_Shortcodes::shortcode_snippet( '123' )
		);
		$this->assertSame(
			'[rwgc_ab id="0"][rwgc_ab_variant][/rwgc_ab_variant][rwgc_ab_variant][/rwgc_ab_variant][/rwgc_ab]',
			RWGC_Shortcodes::shortcode_snippet( 'nope' )
		);

		$root = dirname( __DIR__, 2 );
		$view = (string) file_get_contents( $root . '/admin/views/ab-tests-list.php' );
		$this->assertStringContainsString( 'Copy shortcode', $view );
		$this->assertStringContainsString( 'rwgc-ab-copy-shortcode', $view );
		$this->assertStringContainsString( 'data-rwgc-ab-shortcode', $view );
		$this->assertStringContainsString( 'shortcode_snippet', $view );

		$admin = (string) file_get_contents( $root . '/includes/ab/class-rwgc-ab-admin.php' );
		$this->assertStringContainsString( 'enqueue_copy_script', $admin );
		$this->assertStringContainsString( 'assets/js/rwgc-ab-admin.js', $admin );
		$js = (string) file_get_contents( $root . '/assets/js/rwgc-ab-admin.js' );
		$this->assertStringContainsString( 'rwgc-ab-copy-shortcode', $js );
		$this->assertStringContainsString( 'data-rwgc-ab-shortcode', $js );

		RWGC_AB_Admin::enqueue_copy_script();
		$this->assertArrayHasKey( 'rwgc-ab-admin', $GLOBALS['rwgc_test_scripts'] );
		$this->assertStringContainsString( 'rwgc-ab-admin.js', (string) $GLOBALS['rwgc_test_registered_scripts']['rwgc-ab-admin']['src'] );
	}

	/**
	 * @return int
	 */
	private function running_test() {
		return (int) RWGC_AB_Tests::create(
			array(
				'title'  => 'Shortcode',
				'status' => 'publish',
				'config' => array(
					'adapter' => 'shortcode',
				),
			)
		);
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $arm_a   Variant A.
	 * @param string $arm_b   Variant B.
	 * @return string
	 */
	private function render( $test_id, $arm_a, $arm_b ) {
		return RWGC_Shortcodes::render_ab(
			array( 'id' => (string) $test_id ),
			'[rwgc_ab_variant]' . $arm_a . '[/rwgc_ab_variant][rwgc_ab_variant]' . $arm_b . '[/rwgc_ab_variant]'
		);
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $arm     Arm.
	 * @return void
	 */
	private function seed( $test_id, $arm ) {
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie(
			str_repeat( 'ab', 16 ),
			array(
				(int) $test_id => array(
					'variant'   => $arm,
					'viewed'    => false,
					'converted' => false,
				),
			)
		);
		RWGC_AB_Assignment::reset_request_state();
	}
}
