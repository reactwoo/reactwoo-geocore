<?php
/**
 * Free A/B view and goal counts: one view, one conversion, no stored address.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * In-memory daily aggregate.
 */
class RWGC_AB_Tracker_Wpdb {

	/** @var string */
	public $prefix = 'wp_';

	/** @var bool */
	public $missing_table = false;

	/** @var string[] */
	public $queries = array();

	/**
	 * @var array<string, array{test_id:int,variant:string,day:string,views:int,conversions:int}>
	 */
	public $rows = array();

	/**
	 * @param string $query SQL.
	 * @param mixed  ...$args Values.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$prepared = (string) $query;
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || ( is_string( $arg ) && preg_match( '/^-?\d+$/', $arg ) ) ) {
				$prepared = preg_replace( '/%d/', (string) (int) $arg, $prepared, 1 );
				continue;
			}
			$safe     = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $arg );
			$prepared = preg_replace( '/%s/', "'" . $safe . "'", $prepared, 1 );
		}
		return $prepared;
	}

	/**
	 * @param string $sql SQL.
	 * @return int
	 */
	public function query( $sql ) {
		$sql             = (string) $sql;
		$this->queries[] = $sql;
		if ( preg_match( "/INSERT INTO `[^`]+` \\(test_id, variant, day, views, conversions\\) VALUES \\((\\d+), '([^']*)', '([^']*)', (\\d+), (\\d+)\\) ON DUPLICATE KEY UPDATE views = views \\+ (\\d+), conversions = conversions \\+ (\\d+)/", $sql, $match ) ) {
			$key = $match[1] . '|' . $match[2] . '|' . $match[3];
			if ( ! isset( $this->rows[ $key ] ) ) {
				$this->rows[ $key ] = array(
					'test_id'     => (int) $match[1],
					'variant'     => $match[2],
					'day'         => $match[3],
					'views'       => (int) $match[4],
					'conversions' => (int) $match[5],
				);
			} else {
				$this->rows[ $key ]['views']       += (int) $match[6];
				$this->rows[ $key ]['conversions'] += (int) $match[7];
			}
		}
		return 1;
	}

	/**
	 * @param string $sql SQL.
	 * @return string
	 */
	public function get_var( $sql ) {
		unset( $sql );
		if ( $this->missing_table ) {
			return '';
		}
		return 'wp_rwgc_ab_daily';
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * @return string
	 */
	public function get_charset_collate() {
		return 'DEFAULT CHARSET=utf8mb4';
	}
}

/**
 * @covers RWGC_AB_Tracker
 * @covers RWGC_AB_DB
 */
final class RWGC_AB_TrackerTest extends TestCase {

	/** @var array<string, mixed> */
	private $server = array();

	/** @var array<string, mixed> */
	private $get = array();

	/** @var array<string, mixed> */
	private $cookie = array();

	/** @var bool */
	private $had_wpdb = false;

	/** @var mixed */
	private $previous_wpdb;

	/** @var array<string, mixed>|null */
	private $previous_options;

	/** @var mixed */
	private $previous_posts;

	/** @var mixed */
	private $previous_meta;

	/** @var mixed */
	private $previous_seq;

	/** @var mixed */
	private $previous_transients;

	/** @var mixed */
	private $previous_dbdelta;

	/** @var bool */
	private $had_filters = false;

	/** @var array<string, mixed>|null */
	private $filters;

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
			define( 'RWGC_VERSION', '1.9.26' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
		require_once dirname( __DIR__, 2 ) . '/includes/events/class-rwgc-event.php';
		require_once dirname( __DIR__, 2 ) . '/includes/events/class-rwgc-events.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-db.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tests.php';
		require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-assignment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tracker.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-rest.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-shortcodes.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->server              = $_SERVER;
		$this->get                 = $_GET;
		$this->cookie              = $_COOKIE;
		$this->had_wpdb            = array_key_exists( 'wpdb', $GLOBALS );
		$this->previous_wpdb       = $this->had_wpdb ? $GLOBALS['wpdb'] : null;
		$this->previous_options    = isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] ) ? $GLOBALS['rwgc_test_options'] : null;
		$this->previous_posts      = isset( $GLOBALS['rwgc_ab_test_posts'] ) ? $GLOBALS['rwgc_ab_test_posts'] : null;
		$this->previous_meta       = isset( $GLOBALS['rwgc_ab_test_meta'] ) ? $GLOBALS['rwgc_ab_test_meta'] : null;
		$this->previous_seq        = isset( $GLOBALS['rwgc_ab_test_seq'] ) ? $GLOBALS['rwgc_ab_test_seq'] : null;
		$this->previous_transients = isset( $GLOBALS['rwgc_test_transients'] ) ? $GLOBALS['rwgc_test_transients'] : null;
		$this->previous_dbdelta    = isset( $GLOBALS['rwgc_ab_dbdelta_calls'] ) ? $GLOBALS['rwgc_ab_dbdelta_calls'] : null;
		$this->had_filters         = isset( $GLOBALS['rwgc_test_filters'] );
		$this->filters             = $this->had_filters && is_array( $GLOBALS['rwgc_test_filters'] ) ? $GLOBALS['rwgc_test_filters'] : null;

		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		$_SERVER['REMOTE_ADDR']     = '203.0.113.77';
		$_SERVER['HTTP_HOST']       = 'example.test';
		$_SERVER['REQUEST_URI']     = '/';
		$_GET                       = array( 'user_id' => '918273' );
		unset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );

		$GLOBALS['rwgc_test_options']      = array( RWGC_AB_DB::OPTION => RWGC_AB_DB::VERSION );
		$GLOBALS['rwgc_ab_test_posts']     = array();
		$GLOBALS['rwgc_ab_test_meta']      = array();
		$GLOBALS['rwgc_ab_test_seq']       = 0;
		$GLOBALS['rwgc_test_transients']   = array();
		$GLOBALS['rwgc_ab_dbdelta_calls']  = array();
		$GLOBALS['rwgc_ab_events']         = array();
		$GLOBALS['rwgc_test_is_admin']     = false;
		$GLOBALS['rwgc_test_scripts']      = array();
		$GLOBALS['rwgc_test_localized']    = array();
		$GLOBALS['wpdb']                   = new RWGC_AB_Tracker_Wpdb();
		$GLOBALS['rwgc_test_filters']      = array(
			'rwgc_ab_emit_http_headers' => static function () {
				return false;
			},
			'rwgc_ab_event_client_ip'   => static function () {
				return '203.0.113.77';
			},
			'rwgc_geo_event'            => static function ( $payload ) {
				$GLOBALS['rwgc_ab_events'][] = $payload;
				return $payload;
			},
		);
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER = $this->server;
		$_GET    = $this->get;
		$_COOKIE = $this->cookie;
		if ( $this->had_wpdb ) {
			$GLOBALS['wpdb'] = $this->previous_wpdb;
		} else {
			unset( $GLOBALS['wpdb'] );
		}
		if ( null === $this->previous_options ) {
			unset( $GLOBALS['rwgc_test_options'] );
		} else {
			$GLOBALS['rwgc_test_options'] = $this->previous_options;
		}
		if ( null === $this->previous_posts ) {
			unset( $GLOBALS['rwgc_ab_test_posts'] );
		} else {
			$GLOBALS['rwgc_ab_test_posts'] = $this->previous_posts;
		}
		if ( null === $this->previous_meta ) {
			unset( $GLOBALS['rwgc_ab_test_meta'] );
		} else {
			$GLOBALS['rwgc_ab_test_meta'] = $this->previous_meta;
		}
		if ( null === $this->previous_seq ) {
			unset( $GLOBALS['rwgc_ab_test_seq'] );
		} else {
			$GLOBALS['rwgc_ab_test_seq'] = $this->previous_seq;
		}
		if ( null === $this->previous_transients ) {
			unset( $GLOBALS['rwgc_test_transients'] );
		} else {
			$GLOBALS['rwgc_test_transients'] = $this->previous_transients;
		}
		if ( null === $this->previous_dbdelta ) {
			unset( $GLOBALS['rwgc_ab_dbdelta_calls'] );
		} else {
			$GLOBALS['rwgc_ab_dbdelta_calls'] = $this->previous_dbdelta;
		}
		if ( $this->had_filters ) {
			$GLOBALS['rwgc_test_filters'] = $this->filters;
		} else {
			unset( $GLOBALS['rwgc_test_filters'] );
		}
		$GLOBALS['rwgc_test_is_admin'] = false;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
		parent::tearDown();
	}

	public function test_upsert_creates_the_daily_table_and_adds_one_view_per_arm(): void {
		unset( $GLOBALS['rwgc_test_options'][ RWGC_AB_DB::OPTION ] );
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'b' );

		$first = $this->event( $id, 'view' );
		$this->assertTrue( $first['body']['counted'] );
		$this->assertSame( '1', (string) get_option( RWGC_AB_DB::OPTION ) );
		$this->assertNotEmpty( $GLOBALS['rwgc_ab_dbdelta_calls'] );
		$sql = (string) $GLOBALS['rwgc_ab_dbdelta_calls'][0];
		$this->assertStringContainsString( 'CREATE TABLE wp_rwgc_ab_daily', $sql );
		$this->assertStringContainsString( 'views int(10) unsigned NOT NULL default 0', $sql );
		$this->assertStringContainsString( 'conversions int(10) unsigned NOT NULL default 0', $sql );
		$this->assertStringNotContainsString( '203.0.113.77', $sql );
		$this->assertSame( 1, $this->totals( $id, 'b' )['views'] );
		$this->assertSame( 0, $this->totals( $id, 'b' )['conversions'] );

		$this->assign( $id, 'b', true );
		$again = $this->event( $id, 'view' );
		$this->assertFalse( $again['body']['counted'] );
		$this->assertSame( 1, $this->totals( $id, 'b' )['views'] );

		$this->assign_visitor( $id, 'a', 'abcdefabcdefabcdefabcdefabcdefab' );
		$this->event( $id, 'view' );
		$this->assertSame( 1, $this->totals( $id, 'a' )['views'] );
		$this->assertSame( 1, $this->totals( $id, 'b' )['views'] );

		$GLOBALS['wpdb']->missing_table = true;
		$before                          = count( $GLOBALS['rwgc_ab_dbdelta_calls'] );
		$this->assign_visitor( $id, 'b', 'fedcbafedcbafedcbafedcbafedcbafa' );
		$this->event( $id, 'view' );
		$this->assertGreaterThan( $before, count( $GLOBALS['rwgc_ab_dbdelta_calls'] ) );
		$this->assertSame( RWGC_AB_DB::VERSION, (string) get_option( RWGC_AB_DB::OPTION ) );
	}

	public function test_reloads_do_not_double_count_and_a_conversion_needs_a_view(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'a' );

		$early = $this->event( $id, 'conversion' );
		$this->assertSame( 200, $early['status'] );
		$this->assertFalse( $early['body']['counted'] );
		$this->assertSame( 0, $this->totals( $id, 'a' )['views'] );
		$this->assertSame( 0, $this->totals( $id, 'a' )['conversions'] );
		$this->assertFalse( $this->flags( $id )['converted'] );

		$view = $this->event( $id, 'view' );
		$this->assertTrue( $view['body']['counted'] );
		$this->assertTrue( $this->flags( $id )['viewed'] );
		$this->assertSame( 'ab_exposure', $GLOBALS['rwgc_ab_events'][0]['event_type'] );
		$this->assertSame( 'a', $GLOBALS['rwgc_ab_events'][0]['variant_id'] );
		$this->assertSame( (string) $id, $GLOBALS['rwgc_ab_events'][0]['experiment_id'] );
		$this->assertSame( '', $GLOBALS['rwgc_ab_events'][0]['assignment_id'] );

		RWGC_AB_Assignment::reset_request_state();
		$reload = $this->event( $id, 'view' );
		$this->assertFalse( $reload['body']['counted'] );
		$this->assertSame( 1, $this->totals( $id, 'a' )['views'] );

		$conversion = $this->event( $id, 'conversion' );
		$this->assertTrue( $conversion['body']['counted'] );
		$this->assertTrue( $this->flags( $id )['converted'] );
		$this->assertSame( 'ab_conversion', $GLOBALS['rwgc_ab_events'][1]['event_type'] );
		$this->assertSame( 'beacon', $GLOBALS['rwgc_ab_events'][1]['subject']['source'] );

		RWGC_AB_Assignment::reset_request_state();
		$second = $this->event( $id, 'conversion' );
		$this->assertFalse( $second['body']['counted'] );
		$this->assertSame( 1, $this->totals( $id, 'a' )['conversions'] );
		$this->assertCount( 2, $GLOBALS['rwgc_ab_events'] );
	}

	public function test_url_goal_counts_once_on_template_redirect_after_a_view(): void {
		$id = $this->test_id(
			array(
				'type' => 'url',
				'url'  => 'https://example.test/thanks/',
			)
		);
		$this->assign( $id, 'b', true );
		$_SERVER['REQUEST_URI'] = '/thanks?ref=1';
		$_SERVER['HTTP_HOST']   = 'example.test';

		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );
		$this->assertTrue( $this->flags( $id )['converted'] );
		$this->assertTrue( RWGC_AB_Tracker::cache_bypass_marked() );
		$this->assertSame( 'private, no-store', RWGC_AB_Tracker::nocache_headers()['Cache-Control'] );
		$this->assertSame( 'ab_conversion', $GLOBALS['rwgc_ab_events'][0]['event_type'] );
		$this->assertSame( 'url', $GLOBALS['rwgc_ab_events'][0]['subject']['source'] );

		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );
		$this->assertFalse( RWGC_AB_Tracker::cache_bypass_marked() );

		$unseen = $this->test_id(
			array(
				'type' => 'url',
				'url'  => 'https://example.test/thanks',
			)
		);
		$this->assign( $unseen, 'a' );
		$_SERVER['REQUEST_URI'] = '/thanks';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertArrayNotHasKey( $unseen . '|a|2026-11-09', $GLOBALS['wpdb']->rows );

		$this->assign( $id, 'b', true );
		$_SERVER['REQUEST_URI'] = '/other';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );

		$this->assign( $id, 'b', true );
		$_SERVER['REQUEST_URI'] = '/thanks';
		$_SERVER['HTTP_HOST']   = 'evil.test';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );

		$this->assign( $id, 'b', true );
		$_SERVER['HTTP_HOST']       = 'example.test';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Googlebot/2.1';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );
	}

	public function test_event_route_is_public_no_store_and_rate_limited_without_storing_the_address(): void {
		$GLOBALS['rwgc_test_routes'] = array();
		RWGC_REST::register_ab_routes();
		$event = null;
		foreach ( $GLOBALS['rwgc_test_routes'] as $route ) {
			if ( '/ab/event' === $route['route'] ) {
				$event = $route;
			}
		}
		$this->assertNotNull( $event );
		$this->assertSame( 'POST', $event['args']['methods'] );
		$this->assertTrue( call_user_func( $event['args']['permission_callback'], null ) );

		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'b' );
		$via = RWGC_REST::post_ab_event(
			new RWGC_AB_Cache_Request(
				array(
					'test' => (string) $id,
					'type' => 'view',
				)
			)
		);
		$this->assertSame( 200, $via['status'] );
		$this->assertTrue( $via['body']['counted'] );
		$this->assertStringContainsString( 'no-store', $via['headers']['Cache-Control'] );

		$bad_type = $this->event( $id, 'purchase' );
		$this->assertSame( 400, $bad_type['status'] );
		$this->assertSame( 'rwgc_ab_invalid_type', $bad_type['body']['code'] );
		$this->assertStringContainsString( 'no-store', $bad_type['headers']['Cache-Control'] );

		$bad_id = $this->event( 'abc', 'view' );
		$this->assertSame( 400, $bad_id['status'] );
		$this->assertSame( 'rwgc_ab_invalid_test', $bad_id['body']['code'] );

		$missing = $this->event( 99999, 'view' );
		$this->assertSame( 404, $missing['status'] );
		$this->assertSame( 'rwgc_ab_not_running', $missing['body']['code'] );

		$draft = $this->test_id( array( 'type' => 'click' ), 'draft' );
		$this->assign( $draft, 'a' );
		$idle = $this->event( $draft, 'view' );
		$this->assertSame( 404, $idle['status'] );

		$ip = '203.0.113.77';
		$GLOBALS['rwgc_test_transients'] = array();
		for ( $i = 0; $i < 40; $i++ ) {
			$step = RWGC_AB_Tracker::consume_rate_limit( $ip );
			$this->assertTrue( $step['allowed'], 'Request ' . ( $i + 1 ) . ' should be allowed' );
		}
		$blocked = RWGC_AB_Tracker::build_event_response( $id, 'view' );
		$this->assertSame( 429, $blocked['status'] );
		$this->assertSame( 'rwgc_ab_rate_limited', $blocked['body']['code'] );
		$this->assertArrayHasKey( 'Retry-After', $blocked['headers'] );
		$this->assertStringContainsString( 'no-store', $blocked['headers']['Cache-Control'] );

		$key = RWGC_AB_Tracker::rate_transient_key( $ip );
		$this->assertArrayHasKey( $key, $GLOBALS['rwgc_test_transients'] );
		$this->assertStringNotContainsString( $ip, $key );
		$this->assertSame( array( 'start', 'count' ), array_keys( $GLOBALS['rwgc_test_transients'][ $key ] ) );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_event_rate_now'] = static function () {
			return time() + 120;
		};
		$this->assertTrue( RWGC_AB_Tracker::consume_rate_limit( $ip )['allowed'] );

		$_SERVER['REQUEST_URI'] = '/wp-json/reactwoo-geocore/v1/ab/event';
		$error                  = new WP_Error( 'rest_cookie_invalid_nonce', 'Cookie check failed' );
		$this->assertNull( RWGC_AB_Tracker::allow_public_event_without_nonce( $error ) );
		$this->assertSame( 'other', RWGC_AB_Tracker::allow_public_event_without_nonce( 'other' ) );
		$_SERVER['REQUEST_URI'] = '/';
		$this->assertSame( $error, RWGC_AB_Tracker::allow_public_event_without_nonce( $error ) );

		$root = dirname( __DIR__, 2 );
		$rest = (string) file_get_contents( $root . '/includes/class-rwgc-rest.php' );
		$src  = (string) file_get_contents( $root . '/includes/ab/class-rwgc-ab-tracker.php' );
		$this->assertStringNotContainsString( 'wp_verify_nonce', $rest );
		$this->assertStringNotContainsString( 'wp_verify_nonce', $src );
		$this->assertStringNotContainsString( 'get_current_user_id', $src );
		$this->assertStringContainsString( 'RWGC_AB_Tracker::init()', (string) file_get_contents( $root . '/includes/class-rwgc-plugin.php' ) );
		$this->assertStringContainsString( 'rwgc_ab_daily', (string) file_get_contents( $root . '/uninstall.php' ) );
	}

	public function test_nothing_persists_the_address_or_an_account_id(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$visitor = '0123456789abcdef0123456789abcdef';
		$this->assign_visitor( $id, 'b', $visitor );
		$response = RWGC_AB_Tracker::build_event_response( $id, 'view' );
		$this->assertTrue( $response['body']['counted'] );

		$stored = array(
			'queries'  => $GLOBALS['wpdb']->queries,
			'rows'     => $GLOBALS['wpdb']->rows,
			'options'  => $GLOBALS['rwgc_test_options'],
			'events'   => $GLOBALS['rwgc_ab_events'],
			'response' => $response,
		);
		foreach ( $GLOBALS['rwgc_test_transients'] as $key => $bucket ) {
			$this->assertStringNotContainsString( '203.0.113.77', (string) $key );
			$this->assertStringNotContainsString( $visitor, (string) $key );
			$this->assertIsArray( $bucket );
			$this->assertSame( array( 'start', 'count' ), array_keys( $bucket ) );
			$stored['buckets'][] = array(
				'count' => $bucket['count'],
			);
		}
		$blob = strtolower( (string) wp_json_encode( $stored ) );
		$this->assertStringNotContainsString( '203.0.113.77', $blob );
		$this->assertStringNotContainsString( $visitor, $blob );
		$this->assertStringNotContainsString( '918273', $blob );
		foreach ( $GLOBALS['wpdb']->rows as $row ) {
			$this->assertSame( array( 'test_id', 'variant', 'day', 'views', 'conversions' ), array_keys( $row ) );
		}
	}

	public function test_server_and_fragment_wrappers_expose_the_click_goal_and_the_beacon_script(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'b', true );
		unset( $GLOBALS['rwgc_test_scripts']['rwgc-ab'], $GLOBALS['rwgc_test_localized']['rwgc-ab'] );

		$html = rwgc_ab_render_test(
			$id,
			array(
				'a' => '<p>ARM-A</p><a class="buy" href="/buy">Buy</a>',
				'b' => '<p>ARM-B</p><a class="buy" href="/buy">Buy</a>',
			)
		);
		$this->assertStringContainsString( 'data-rwgc-ab-mode="server"', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-goal=".buy"', $html );
		$this->assertStringContainsString( 'ARM-B', $html );
		$this->assertSame( 'private, no-store', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertArrayHasKey( 'rwgc-ab', $GLOBALS['rwgc_test_scripts'] );
		$this->assertSame( 'https://example.test/wp-json/reactwoo-geocore/v1/ab/event', $GLOBALS['rwgc_test_localized']['rwgc-ab']['data']['eventEndpoint'] );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		unset( $GLOBALS['rwgc_test_scripts']['rwgc-ab'], $GLOBALS['rwgc_test_localized']['rwgc-ab'] );
		RWGC_AB_Cache::reset_request_state();
		$fragment = rwgc_ab_render_test(
			$id,
			array(
				'a' => '<p>ARM-A</p>',
				'b' => '<p>ARM-B</p>',
			)
		);
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $fragment );
		$this->assertStringContainsString( 'data-rwgc-ab-goal=".buy"', $fragment );
		$this->assertStringContainsString( 'ARM-A', $fragment );
		$this->assertStringNotContainsString( 'ARM-B', $fragment );
		$this->assertTrue( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );

		$shortcode = RWGC_Shortcodes::render_ab(
			array( 'id' => (string) $id ),
			'[rwgc_ab_variant]<a class="buy" href="/buy">A</a>[/rwgc_ab_variant][rwgc_ab_variant]<a class="buy" href="/buy">B</a>[/rwgc_ab_variant]'
		);
		$this->assertStringContainsString( 'data-rwgc-ab-goal=".buy"', $shortcode );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $shortcode );

		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/rwgc-ab.js' );
		$this->assertStringContainsString( 'IntersectionObserver', $js );
		$this->assertStringContainsString( 'eventEndpoint', $js );
		$this->assertStringContainsString( "postEvent(testId, 'view')", $js );
		$this->assertStringContainsString( "postEvent(testId, 'conversion')", $js );
		$this->assertStringContainsString( 'data-rwgc-ab-goal', $js );
		$this->assertStringNotContainsString( 'X-WP-Nonce', $js );
	}

	/**
	 * @param array<string, string> $goal   Goal config.
	 * @param string                $status Post status.
	 * @return int
	 */
	private function test_id( array $goal, $status = 'publish' ) {
		return (int) RWGC_AB_Tests::create(
			array(
				'title'  => 'Counted',
				'status' => $status,
				'config' => array(
					'adapter' => 'shortcode',
					'goal'    => $goal,
				),
			)
		);
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @param bool   $viewed  .v flag.
	 * @return void
	 */
	private function assign( $test_id, $variant, $viewed = false ) {
		$this->assign_visitor( $test_id, $variant, '0123456789abcdef0123456789abcdef', $viewed );
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @param string $visitor 32 hex characters.
	 * @param bool   $viewed  .v flag.
	 * @return void
	 */
	private function assign_visitor( $test_id, $variant, $visitor, $viewed = false ) {
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie(
			$visitor,
			array(
				(int) $test_id => array(
					'variant'   => $variant,
					'viewed'    => $viewed,
					'converted' => false,
				),
			)
		);
		RWGC_AB_Assignment::reset_request_state();
	}

	/**
	 * @param mixed  $test_id Test id.
	 * @param string $type    Event type.
	 * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
	 */
	private function event( $test_id, $type ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ACCEPT']    = 'application/json';
		$_SERVER['CONTENT_TYPE']   = 'application/json';
		RWGC_AB_Assignment::reset_request_state();
		return RWGC_AB_Tracker::build_event_response( $test_id, $type, '203.0.113.77' );
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @return array{views:int,conversions:int}
	 */
	private function totals( $test_id, $variant ) {
		$key = (int) $test_id . '|' . $variant . '|2026-11-09';
		if ( ! isset( $GLOBALS['wpdb']->rows[ $key ] ) ) {
			return array(
				'views'       => 0,
				'conversions' => 0,
			);
		}
		return $GLOBALS['wpdb']->rows[ $key ];
	}

	/**
	 * @param int $test_id Test id.
	 * @return array{viewed:bool,converted:bool}
	 */
	private function flags( $test_id ) {
		RWGC_AB_Assignment::reset_request_state();
		$row = RWGC_AB_Assignment::verified_assignment( $test_id );
		return array(
			'viewed'    => is_array( $row ) && ! empty( $row['viewed'] ),
			'converted' => is_array( $row ) && ! empty( $row['converted'] ),
		);
	}
}
