<?php
/**
 * A/B results: two-proportion significance, the results route, and ending a test.
 *
 * The reference z-test is RWGO_Winner_Policy from ReactWoo Geo Optimise, copied
 * here so the check does not call that plugin.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * @param string $type Type.
	 * @param int    $gmt  GMT flag.
	 * @return string
	 */
	function current_time( $type, $gmt = 0 ) {
		unset( $type, $gmt );
		return '2026-11-09 10:00:00';
	}
}

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

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param mixed $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return void
	 */
	function esc_html_e( $text, $domain = 'default' ) {
		unset( $domain );
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * @param string $action Action.
	 * @return string
	 */
	function wp_create_nonce( $action = '' ) {
		return 'nonce-' . (string) $action;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * @param string $nonce  Nonce.
	 * @param string $action Action.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action = '' ) {
		return hash_equals( wp_create_nonce( $action ), (string) $nonce ) ? 1 : false;
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	/**
	 * @param string $actionurl URL.
	 * @param string $action    Action.
	 * @return string
	 */
	function wp_nonce_url( $actionurl, $action = '' ) {
		$join = ( false === strpos( (string) $actionurl, '?' ) ) ? '?' : '&';
		return (string) $actionurl . $join . '_wpnonce=' . wp_create_nonce( $action );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * @param string $message Message.
	 * @param string $title   Title.
	 * @param array  $args    Args.
	 * @return void
	 */
	function wp_die( $message = '', $title = '', $args = array() ) {
		unset( $title );
		$code = is_array( $args ) && isset( $args['response'] ) ? (int) $args['response'] : 500;
		throw new RuntimeException( (string) $message, $code );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * @param string $action    Action.
	 * @param string $query_arg Query arg.
	 * @return int
	 */
	function check_admin_referer( $action = '', $query_arg = '_wpnonce' ) {
		$nonce = isset( $_REQUEST[ $query_arg ] ) ? (string) wp_unslash( $_REQUEST[ $query_arg ] ) : '';
		$ok    = wp_verify_nonce( $nonce, $action );
		if ( ! $ok ) {
			wp_die( 'The link you followed has expired.', '', array( 'response' => 403 ) );
		}
		return (int) $ok;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		$caps = isset( $GLOBALS['rwgc_test_caps'] ) && is_array( $GLOBALS['rwgc_test_caps'] ) ? $GLOBALS['rwgc_test_caps'] : array();
		return ! empty( $caps[ (string) $cap ] );
	}
}

/**
 * In-memory daily table for results queries.
 */
class RWGC_AB_Results_Wpdb {

	/** @var string */
	public $prefix = 'wp_';

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
	 * @param string $sql    SQL.
	 * @param string $output Output. Unused.
	 * @return array<int, array{test_id:int,variant:string,day:string,views:int,conversions:int}>
	 */
	public function get_results( $sql, $output = 'ARRAY_A' ) {
		unset( $output );
		$this->queries[] = (string) $sql;
		if ( ! preg_match( '/WHERE test_id = (\d+)/', (string) $sql, $match ) ) {
			return array();
		}
		$id  = (int) $match[1];
		$out = array();
		foreach ( $this->rows as $row ) {
			if ( (int) $row['test_id'] === $id ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param string $sql SQL.
	 * @return string
	 */
	public function get_var( $sql ) {
		unset( $sql );
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
 * Request for the results route.
 */
class RWGC_AB_Results_Request {

	/** @var array<string, mixed> */
	public $params = array();

	/** @var string */
	public $route = '/reactwoo-geocore/v1/ab/results';

	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}

	/**
	 * @return string
	 */
	public function get_route() {
		return $this->route;
	}
}

/**
 * Verbatim two-proportion test from RWGO_Winner_Policy.
 *
 * Coefficients are Abramowitz & Stegun 7.1.26, same as Geo Optimise.
 */
class RWGC_AB_Optimise_Reference {

	const DEFAULT_ALPHA = 0.05;

	/**
	 * @param int   $s1    Successes A.
	 * @param int   $n1    Trials A.
	 * @param int   $s2    Successes B.
	 * @param int   $n2    Trials B.
	 * @param float $alpha Alpha.
	 * @return array{z:float,p_value:float,significant:bool,alpha:float}
	 */
	public static function two_proportion_test( $s1, $n1, $s2, $n2, $alpha = 0.05 ) {
		$s1 = max( 0, (int) $s1 );
		$n1 = max( 1, (int) $n1 );
		$s2 = max( 0, (int) $s2 );
		$n2 = max( 1, (int) $n2 );
		$alpha = (float) $alpha;
		if ( $alpha <= 0 || $alpha >= 1 ) {
			$alpha = self::DEFAULT_ALPHA;
		}

		$p1 = $s1 / $n1;
		$p2 = $s2 / $n2;
		$p  = ( $s1 + $s2 ) / ( $n1 + $n2 );
		if ( $p <= 0.0 || $p >= 1.0 ) {
			return array(
				'z'           => 0.0,
				'p_value'     => 1.0,
				'significant' => false,
				'alpha'       => $alpha,
			);
		}
		$se = sqrt( $p * ( 1.0 - $p ) * ( ( 1.0 / $n1 ) + ( 1.0 / $n2 ) ) );
		if ( $se <= 0.0 ) {
			return array(
				'z'           => 0.0,
				'p_value'     => 1.0,
				'significant' => false,
				'alpha'       => $alpha,
			);
		}
		$z       = ( $p2 - $p1 ) / $se;
		$p_value = 2.0 * ( 1.0 - self::standard_normal_cdf( abs( $z ) ) );
		if ( $p_value < 0.0 ) {
			$p_value = 0.0;
		}
		if ( $p_value > 1.0 ) {
			$p_value = 1.0;
		}

		return array(
			'z'           => $z,
			'p_value'     => $p_value,
			'significant' => ( $p_value < $alpha ),
			'alpha'       => $alpha,
		);
	}

	/**
	 * @param float $x Value.
	 * @return float
	 */
	public static function standard_normal_cdf( $x ) {
		$x = (float) $x;
		return 0.5 * ( 1.0 + self::erf( $x / sqrt( 2.0 ) ) );
	}

	/**
	 * @param float $x Value.
	 * @return float
	 */
	public static function erf( $x ) {
		$x = (float) $x;
		$sign = $x < 0 ? -1.0 : 1.0;
		$x    = abs( $x );
		$a1 = 0.254829592;
		$a2 = -0.284496736;
		$a3 = 1.421413741;
		$a4 = -1.453152027;
		$a5 = 1.061405429;
		$p  = 0.3275911;
		$t  = 1.0 / ( 1.0 + $p * $x );
		$y  = 1.0 - ( ( ( ( ( $a5 * $t + $a4 ) * $t ) + $a3 ) * $t + $a2 ) * $t + $a1 ) * $t * exp( -$x * $x );
		return $sign * $y;
	}
}

/**
 * @covers RWGC_AB_Stats
 * @covers RWGC_AB_Admin
 */
final class RWGC_AB_ResultsTest extends TestCase {

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
	private $request = array();

	/**
	 * @var array<string, mixed>
	 */
	private $cookie = array();

	/**
	 * @var bool
	 */
	private $had_wpdb = false;

	/**
	 * @var object|null
	 */
	private $previous_wpdb = null;

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
		$root = dirname( __DIR__, 2 );
		require_once $root . '/includes/functions-rwgc.php';
		require_once $root . '/includes/ab/class-rwgc-ab-db.php';
		require_once $root . '/includes/ab/class-rwgc-ab-tests.php';
		require_once $root . '/includes/ab/class-rwgc-ab-stats.php';
		require_once $root . '/includes/ab/class-rwgc-ab-admin.php';
		require_once $root . '/includes/integrations/class-rwgc-cache-compat.php';
		require_once $root . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once $root . '/includes/ab/class-rwgc-ab-assignment.php';
		require_once $root . '/includes/ab/class-rwgc-ab-cache.php';
		require_once $root . '/includes/ab/class-rwgc-ab-tracker.php';
		require_once $root . '/includes/class-rwgc-rest.php';
		require_once $root . '/includes/class-rwgc-shortcodes.php';
		require_once $root . '/includes/integrations/gutenberg/class-rwgc-gutenberg-ab.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->server      = $_SERVER;
		$this->get         = $_GET;
		$this->request     = $_REQUEST;
		$this->cookie      = $_COOKIE;
		$this->had_wpdb    = array_key_exists( 'wpdb', $GLOBALS );
		$this->previous_wpdb = $this->had_wpdb ? $GLOBALS['wpdb'] : null;
		$this->had_filters = isset( $GLOBALS['rwgc_test_filters'] );
		$this->filters     = $this->had_filters && is_array( $GLOBALS['rwgc_test_filters'] ) ? $GLOBALS['rwgc_test_filters'] : null;

		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		$_SERVER['REMOTE_ADDR']     = '203.0.113.50';
		$_SERVER['HTTP_HOST']       = 'example.test';
		$_SERVER['REQUEST_URI']     = '/';
		$_GET                       = array();
		$_REQUEST                   = array();
		unset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );

		$GLOBALS['rwgc_test_options']    = array( RWGC_AB_DB::OPTION => RWGC_AB_DB::VERSION );
		$GLOBALS['rwgc_ab_test_posts']   = array();
		$GLOBALS['rwgc_ab_test_meta']    = array();
		$GLOBALS['rwgc_ab_test_seq']     = 0;
		$GLOBALS['rwgc_test_caps']       = array();
		$GLOBALS['rwgc_test_transients'] = array();
		$GLOBALS['wpdb']                 = new RWGC_AB_Results_Wpdb();
		$GLOBALS['rwgc_test_filters']    = array(
			'rwgc_ab_emit_http_headers' => static function () {
				return false;
			},
			'rwgc_ab_results_today'     => static function () {
				return '2026-11-09';
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
		$_SERVER  = $this->server;
		$_GET     = $this->get;
		$_REQUEST = $this->request;
		$_COOKIE  = $this->cookie;
		if ( $this->had_wpdb ) {
			$GLOBALS['wpdb'] = $this->previous_wpdb;
		} else {
			unset( $GLOBALS['wpdb'] );
		}
		if ( $this->had_filters ) {
			$GLOBALS['rwgc_test_filters'] = $this->filters;
		} else {
			unset( $GLOBALS['rwgc_test_filters'] );
		}
		$GLOBALS['rwgc_test_caps'] = array();
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
		parent::tearDown();
	}

	public function test_z_and_p_match_known_values_and_optimise(): void {
		$inputs = array(
			array( 10, 100, 20, 100, 0.05 ),
			array( 20, 100, 10, 100, 0.05 ),
			array( 0, 100, 0, 100, 0.05 ),
			array( 100, 100, 100, 100, 0.05 ),
			array( 50, 200, 80, 200, 0.05 ),
			array( 15, 150, 30, 300, 0.05 ),
			array( 100, 1000, 200, 1000, 0.05 ),
			array( 200, 1000, 100, 1000, 0.05 ),
			array( 0, 0, 5, 10, 0.05 ),
			array( 3, 10, 1, 10, 0.0 ),
			array( 3, 10, 1, 10, 2.0 ),
			array( 1, 2, 1, 2, 0.01 ),
		);
		foreach ( $inputs as $input ) {
			$core = RWGC_AB_Stats::two_proportion_test( $input[0], $input[1], $input[2], $input[3], $input[4] );
			$opt  = RWGC_AB_Optimise_Reference::two_proportion_test( $input[0], $input[1], $input[2], $input[3], $input[4] );
			$this->assertSame( $opt, $core, wp_json_encode( $input ) );
			$this->assertSame( RWGC_AB_Optimise_Reference::erf( 1.25 ), RWGC_AB_Stats::erf( 1.25 ) );
			$this->assertSame( RWGC_AB_Optimise_Reference::standard_normal_cdf( -0.4 ), RWGC_AB_Stats::standard_normal_cdf( -0.4 ) );
		}

		$better = RWGC_AB_Stats::two_proportion_test( 10, 100, 20, 100 );
		$this->assertEqualsWithDelta( 1.980295085953, $better['z'], 1.0e-9 );
		$this->assertEqualsWithDelta( 0.047670242150, $better['p_value'], 1.0e-9 );
		$this->assertTrue( $better['significant'] );
		$this->assertSame( 0.05, $better['alpha'] );

		$worse = RWGC_AB_Stats::two_proportion_test( 20, 100, 10, 100 );
		$this->assertEqualsWithDelta( -1.980295085953, $worse['z'], 1.0e-9 );
		$this->assertTrue( $worse['significant'] );

		$none = RWGC_AB_Stats::two_proportion_test( 0, 100, 0, 100 );
		$this->assertSame( 0.0, $none['z'] );
		$this->assertSame( 1.0, $none['p_value'] );
		$this->assertFalse( $none['significant'] );

		$replaced = RWGC_AB_Stats::two_proportion_test( 3, 10, 1, 10, 0 );
		$this->assertSame( 0.05, $replaced['alpha'] );
	}

	public function test_verdict_uplift_and_the_30_day_chart_use_all_time_totals(): void {
		$insufficient = RWGC_AB_Stats::report(
			array(
				array(
					'variant'     => 'a',
					'day'         => '2026-11-09',
					'views'       => 99,
					'conversions' => 40,
				),
				array(
					'variant'     => 'b',
					'day'         => '2026-11-09',
					'views'       => 500,
					'conversions' => 200,
				),
			),
			'2026-11-09'
		);
		$this->assertSame( 'Not enough data (<100 per arm)', $insufficient['verdict'] );
		$this->assertSame( 'insufficient', $insufficient['verdict_code'] );

		$quiet = RWGC_AB_Stats::report(
			array(
				array(
					'variant'     => 'a',
					'day'         => '2026-11-09',
					'views'       => 100,
					'conversions' => 10,
				),
				array(
					'variant'     => 'b',
					'day'         => '2026-11-09',
					'views'       => 100,
					'conversions' => 10,
				),
			),
			'2026-11-09'
		);
		$this->assertSame( 'No clear winner yet', $quiet['verdict'] );
		$this->assertSame( 0.0, $quiet['uplift'] );

		$better = RWGC_AB_Stats::report(
			array(
				array(
					'variant'     => 'a',
					'day'         => '2026-11-09',
					'views'       => 1000,
					'conversions' => 100,
				),
				array(
					'variant'     => 'b',
					'day'         => '2026-11-09',
					'views'       => 1000,
					'conversions' => 200,
				),
				array(
					'variant'     => 'a',
					'day'         => '2026-09-01',
					'views'       => 40,
					'conversions' => 4,
				),
				array(
					'variant'     => 'c',
					'day'         => '2026-11-09',
					'views'       => 9999,
					'conversions' => 9999,
				),
			),
			'2026-11-09'
		);
		$this->assertSame( 'B is better (95%)', $better['verdict'] );
		$this->assertSame( 1040, $better['arms']['a']['views'] );
		$this->assertSame( 1000, $better['arms']['b']['views'] );
		$this->assertEqualsWithDelta( 1.0, ( ( 200 / 1000 ) - ( 104 / 1040 ) ) / ( 104 / 1040 ), 1.0e-12 );
		$this->assertEqualsWithDelta( $better['uplift'], ( ( 200 / 1000 ) - ( 104 / 1040 ) ) / ( 104 / 1040 ), 1.0e-12 );
		$this->assertCount( 30, $better['daily'] );
		$this->assertSame( '2026-10-11', $better['daily'][0]['day'] );
		$this->assertSame( '2026-11-09', $better['daily'][29]['day'] );
		$this->assertSame( 1000, $better['daily'][29]['a']['views'] );
		$this->assertSame( 0, $better['daily'][0]['a']['views'] );
		$this->assertTrue( $better['significance']['significant'] );

		$worse = RWGC_AB_Stats::report(
			array(
				array(
					'variant'     => 'a',
					'day'         => '2026-11-01',
					'views'       => 1000,
					'conversions' => 200,
				),
				array(
					'variant'     => 'b',
					'day'         => '2026-11-01',
					'views'       => 1000,
					'conversions' => 100,
				),
			),
			'2026-11-09'
		);
		$this->assertSame( 'B is worse (95%)', $worse['verdict'] );
		$this->assertEqualsWithDelta( -0.5, $worse['uplift'], 1.0e-12 );
		$this->assertSame( 1000, $worse['daily'][21]['a']['views'] );

		$zero = RWGC_AB_Stats::report( array(), '2026-11-09' );
		$this->assertSame( 'Not enough data (<100 per arm)', $zero['verdict'] );
		$this->assertNull( $zero['uplift'] );
		$this->assertNull( $zero['significance'] );
	}

	public function test_results_route_is_admin_only_and_no_store(): void {
		$plugin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-plugin.php' );
		$this->assertStringContainsString( 'includes/ab/class-rwgc-ab-stats.php', $plugin );

		$GLOBALS['rwgc_test_routes'] = array();
		RWGC_REST::register_ab_routes();
		$route = null;
		foreach ( $GLOBALS['rwgc_test_routes'] as $candidate ) {
			if ( is_array( $candidate ) && isset( $candidate['route'] ) && '/ab/results' === $candidate['route'] ) {
				$route = $candidate;
			}
		}
		$this->assertIsArray( $route );
		$this->assertSame( 'GET', $route['args']['methods'] );
		$this->assertFalse( call_user_func( $route['args']['permission_callback'], new RWGC_AB_Results_Request() ) );

		$denied = RWGC_REST::get_ab_results( new RWGC_AB_Results_Request() );
		$this->assertSame( 403, $denied['status'] );
		$this->assertSame( 'rest_forbidden', $denied['body']['code'] );
		$this->assertStringContainsString( 'no-store', $denied['headers']['Cache-Control'] );
		$this->assertStringContainsString( 'private', $denied['headers']['Cache-Control'] );
		$this->assertSame( 'no-cache', $denied['headers']['X-LiteSpeed-Cache-Control'] );

		$wrapped = RWGC_REST::nocache_ab_results_result(
			array(
				'status'  => 401,
				'headers' => array(),
				'body'    => array( 'code' => 'rest_forbidden' ),
			),
			null,
			new RWGC_AB_Results_Request()
		);
		$this->assertStringContainsString( 'no-store', $wrapped['headers']['Cache-Control'] );
		$other = RWGC_REST::nocache_ab_results_result(
			array(
				'headers' => array( 'Cache-Control' => 'max-age=60' ),
			),
			null,
			new RWGC_AB_Results_Request()
		);
		$untouched = new RWGC_AB_Results_Request();
		$untouched->route = '/reactwoo-geocore/v1/ab/render';
		$left = RWGC_REST::nocache_ab_results_result(
			array( 'headers' => array( 'Cache-Control' => 'max-age=60' ) ),
			null,
			$untouched
		);
		$this->assertSame( 'max-age=60', $left['headers']['Cache-Control'] );

		$id = $this->seed_test();
		$GLOBALS['wpdb']->rows[ $id . '|a|2026-11-09' ] = array(
			'test_id'     => $id,
			'variant'     => 'a',
			'day'         => '2026-11-09',
			'views'       => 120,
			'conversions' => 12,
		);
		$GLOBALS['wpdb']->rows[ $id . '|b|2026-10-01' ] = array(
			'test_id'     => $id,
			'variant'     => 'b',
			'day'         => '2026-10-01',
			'views'       => 80,
			'conversions' => 8,
		);
		$GLOBALS['rwgc_test_caps']['manage_options'] = true;
		$this->assertTrue( RWGC_AB_Admin::user_can_manage() );
		$this->assertTrue( call_user_func( $route['args']['permission_callback'], new RWGC_AB_Results_Request() ) );

		$list = RWGC_REST::get_ab_results( new RWGC_AB_Results_Request() );
		$this->assertSame( 200, $list['status'] );
		$this->assertStringContainsString( 'no-store', $list['headers']['Cache-Control'] );
		$this->assertCount( 1, $list['body']['tests'] );
		$this->assertSame( 120, $list['body']['tests'][0]['arms']['a']['views'] );
		$this->assertSame( 80, $list['body']['tests'][0]['arms']['b']['views'] );
		$this->assertSame( 'Not enough data (<100 per arm)', $list['body']['tests'][0]['verdict'] );
		$this->assertCount( 30, $list['body']['tests'][0]['daily'] );

		$one_request         = new RWGC_AB_Results_Request();
		$one_request->params = array( 'test' => (string) $id );
		$one                 = RWGC_REST::get_ab_results( $one_request );
		$this->assertSame( 200, $one['status'] );
		$this->assertSame( $id, $one['body']['id'] );
		$this->assertSame( 12, $one['body']['arms']['a']['conversions'] );
		$this->assertStringContainsString( 'no-store', $one['headers']['Cache-Control'] );

		$bad         = new RWGC_AB_Results_Request();
		$bad->params = array( 'test' => 'nope' );
		$invalid     = RWGC_REST::get_ab_results( $bad );
		$this->assertSame( 400, $invalid['status'] );
		$this->assertStringContainsString( 'no-store', $invalid['headers']['Cache-Control'] );

		$missing_request         = new RWGC_AB_Results_Request();
		$missing_request->params = array( 'test' => '999' );
		$missing                 = RWGC_REST::get_ab_results( $missing_request );
		$this->assertSame( 404, $missing['status'] );
		$this->assertSame( 'rwgc_ab_not_found', $missing['body']['code'] );
		$this->assertStringContainsString( 'no-store', $missing['headers']['Cache-Control'] );

		unset( $other );
	}

	public function test_end_and_keep_requires_a_nonce_and_can_switch_the_arm(): void {
		$id = $this->seed_test();
		$_GET['test_id'] = (string) $id;
		$_GET['kept']    = 'b';
		$_REQUEST        = $_GET;

		try {
			RWGC_AB_Admin::end_from_request();
			$this->fail( 'A subscriber must not end a test.' );
		} catch ( RuntimeException $denied ) {
			$this->assertSame( 403, $denied->getCode() );
		}
		$this->assertSame( 'publish', RWGC_AB_Tests::get( $id )['status'] );

		$GLOBALS['rwgc_test_caps']['manage_options'] = true;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'rwgc_ab_end_test_' . $id . '_a' );
		try {
			RWGC_AB_Admin::end_from_request();
			$this->fail( 'A nonce for arm A must not keep arm B.' );
		} catch ( RuntimeException $mismatch ) {
			$this->assertSame( 403, $mismatch->getCode() );
		}
		$this->assertSame( 'publish', RWGC_AB_Tests::get( $id )['status'] );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'rwgc_ab_end_test_' . $id . '_b' );
		$this->assertSame( 'kept-b', RWGC_AB_Admin::end_from_request() );
		$ended = RWGC_AB_Tests::get( $id );
		$this->assertSame( RWGC_AB_Tests::STATUS_ENDED, $ended['status'] );
		$this->assertSame( 'b', $ended['config']['kept'] );
		$ended_at = $ended['config']['ended_at'];

		$_GET['kept']         = 'a';
		$_REQUEST             = $_GET;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'rwgc_ab_end_test_' . $id . '_a' );
		$this->assertSame( 'kept-a', RWGC_AB_Admin::end_from_request() );
		$switched = RWGC_AB_Tests::get( $id );
		$this->assertSame( RWGC_AB_Tests::STATUS_ENDED, $switched['status'] );
		$this->assertSame( 'a', $switched['config']['kept'] );
		$this->assertSame( $ended_at, $switched['config']['ended_at'] );

		$admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-admin.php' );
		$this->assertStringContainsString( "check_admin_referer( 'rwgc_ab_end_test_' . \$test_id )", $admin );
		$this->assertStringContainsString( "check_admin_referer( 'rwgc_ab_end_test_' . \$test_id . '_' . \$kept )", $admin );
		$this->assertStringContainsString( "current_user_can( 'manage_options' )", $admin );

		$url = RWGC_AB_Admin::keep_url( $id, 'b' );
		$this->assertStringContainsString( 'action=rwgc_ab_end_test', $url );
		$this->assertStringContainsString( 'test_id=' . $id, $url );
		$this->assertStringContainsString( 'kept=b', $url );
		$this->assertStringContainsString( 'nonce-rwgc_ab_end_test_' . $id . '_b', $url );
	}

	public function test_results_screen_escapes_output_and_confirms_the_keep_buttons(): void {
		$id = $this->seed_test( 'Hero <script>alert(1)</script>' );
		$GLOBALS['wpdb']->rows[ $id . '|a|2026-11-09' ] = array(
			'test_id'     => $id,
			'variant'     => 'a',
			'day'         => '2026-11-09',
			'views'       => 10,
			'conversions' => 1,
		);
		$rwgc_ab_result = RWGC_AB_Stats::for_test( $id );
		$rwgc_ab_notice = '';
		ob_start();
		include dirname( __DIR__, 2 ) . '/admin/views/ab-test-results.php';
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'Hero &lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringContainsString( 'Not enough data (&lt;100 per arm)', $html );
		$this->assertStringContainsString( 'End and keep A', $html );
		$this->assertStringContainsString( 'End and keep B', $html );
		$this->assertStringContainsString( 'confirm(', $html );
		$this->assertStringContainsString( 'kept=a', $html );
		$this->assertStringContainsString( 'kept=b', $html );
		$this->assertStringContainsString( '2026-10-11', $html );
		$this->assertStringContainsString( '2026-11-09', $html );
		$this->assertSame( 30, substr_count( $html, '<th scope="row">2026-' ) );

		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/ab-test-results.php' );
		$this->assertStringContainsString( 'esc_html(', $view );
		$this->assertStringContainsString( 'esc_attr(', $view );
		$this->assertStringContainsString( 'esc_url(', $view );
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/rwgc-ab.js' );
		$this->assertStringContainsString( "mode === 'ended'", $js );
	}

	public function test_ending_a_test_serves_the_kept_arm_and_stops_counting(): void {
		$id = RWGC_AB_Tests::create(
			array(
				'title'  => 'Goal',
				'status' => 'publish',
				'config' => array(
					'adapter' => 'gutenberg',
					'goal'    => array(
						'type' => 'url',
						'url'  => 'https://example.test/thanks/',
					),
				),
			)
		);
		$this->assign( $id, 'a', true );
		$_SERVER['REQUEST_URI'] = '/thanks';
		$_SERVER['HTTP_HOST']   = 'example.test';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->cell( $id, 'a', 'conversions' ) );

		$this->assign( $id, 'b', false );
		$counted = RWGC_AB_Tracker::build_event_response( $id, 'view', '203.0.113.60' );
		$this->assertTrue( $counted['body']['counted'] );
		$this->assertSame( 1, $this->cell( $id, 'b', 'views' ) );

		$this->assertTrue( RWGC_AB_Tests::end( $id, 'b' ) );
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
		RWGC_AB_Cache::reset_request_state();

		$this->assign( $id, 'a', true );
		$_SERVER['REQUEST_URI'] = '/thanks';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->cell( $id, 'a', 'conversions' ) );
		$this->assertFalse( RWGC_AB_Tracker::cache_bypass_marked() );

		$ignored = RWGC_AB_Tracker::build_event_response( $id, 'view', '203.0.113.61' );
		$this->assertSame( 404, $ignored['status'] );
		$this->assertSame( 'rwgc_ab_not_running', $ignored['body']['code'] );
		$this->assertStringContainsString( 'no-store', $ignored['headers']['Cache-Control'] );
		$this->assertSame( 1, $this->cell( $id, 'b', 'views' ) );
		$click = RWGC_AB_Tracker::build_event_response( $id, 'conversion', '203.0.113.62' );
		$this->assertSame( 404, $click['status'] );
		$this->assertSame( 0, $this->cell( $id, 'b', 'conversions' ) );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_litespeed_active'] = static function () {
			return true;
		};
		$GLOBALS['rwgc_test_filters']['rwgc_ab_litespeed_can_vary'] = static function () {
			return true;
		};
		$this->assign( $id, 'a', false );
		$html = rwgc_ab_render_test(
			$id,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-B-ONLY', $html );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="ended"', $html );
		$this->assertStringContainsString( 'data-rwgc-arm="b"', $html );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['vary'] );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['donotcachepage'] );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertSame( array(), RWGC_AB_Cache::vary_groups_added() );
		$this->assertSame( array(), RWGC_Cache_Compat::ab_vary_groups_for_request() );
		$this->assertArrayNotHasKey( 'rwgc_ab_' . $id, RWGC_Cache_Compat::filter_litespeed_ab( array() ) );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );

		RWGC_AB_Cache::reset_request_state();
		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		$fragment = rwgc_ab_render_test(
			$id,
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-B-ONLY', $fragment );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $fragment );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="ended"', $fragment );
		$this->assertStringNotContainsString( 'data-rwgc-ab-mode="fragment"', $fragment );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertSame( array(), RWGC_AB_Cache::vary_groups_added() );
		$this->assertFalse( RWGC_AB_Cache::last_directives()['vary'] );

		$block = RWGC_Gutenberg_AB::render_from_arms(
			array( 'testId' => $id ),
			array(
				'a' => '<p>ARM-A-ONLY</p>',
				'b' => '<p>ARM-B-ONLY</p>',
			)
		);
		$this->assertStringContainsString( 'ARM-B-ONLY', $block );
		$this->assertStringNotContainsString( 'ARM-A-ONLY', $block );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="ended"', $block );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_variant_html'] = static function ( $html, $test_id, $arm ) use ( $id ) {
			unset( $html );
			if ( (int) $test_id !== (int) $id ) {
				return '';
			}
			return '<p>ROUTE-' . $arm . '</p>';
		};
		$route = RWGC_AB_Cache::build_render_response( $id, '203.0.113.70' );
		$this->assertSame( 200, $route['status'] );
		$this->assertSame( 'b', $route['body']['arm'] );
		$this->assertStringContainsString( 'ROUTE-b', $route['body']['html'] );
		$this->assertStringContainsString( 'no-store', $route['headers']['Cache-Control'] );
		$this->assertNull( RWGC_AB_Assignment::last_cookie_header() );
	}

	/**
	 * @param string $title Title.
	 * @return int
	 */
	private function seed_test( $title = 'Hero' ) {
		return (int) RWGC_AB_Tests::create(
			array(
				'title'  => $title,
				'status' => 'publish',
				'config' => array(
					'adapter' => 'shortcode',
					'goal'    => array(
						'type'     => 'click',
						'selector' => '.buy',
					),
				),
			)
		);
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @param bool   $viewed  Viewed flag.
	 * @return void
	 */
	private function assign( $test_id, $variant, $viewed ) {
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie(
			'0123456789abcdef0123456789abcdef',
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
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @param string $metric  views|conversions.
	 * @return int
	 */
	private function cell( $test_id, $variant, $metric ) {
		$key = (int) $test_id . '|' . $variant . '|2026-11-09';
		if ( ! isset( $GLOBALS['wpdb']->rows[ $key ][ $metric ] ) ) {
			return 0;
		}
		return (int) $GLOBALS['wpdb']->rows[ $key ][ $metric ];
	}
}
