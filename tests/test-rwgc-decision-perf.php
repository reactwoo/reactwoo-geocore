<?php
/**
 * Decision Runtime performance (WP19).
 *
 * Usage: php tests/test-rwgc-decision-perf.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_options'] = array();
$GLOBALS['rwgc_test_filters'] = array();

if ( ! function_exists( 'do_action' ) ) {
	function do_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['rwgc_test_filters'][ $hook ][] = array(
			'cb'   => $cb,
			'args' => $args,
		);
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['rwgc_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['rwgc_test_filters'][ $hook ] as $entry ) {
			$cb    = $entry['cb'];
			$n     = (int) $entry['args'];
			$call  = array_slice( array_merge( array( $value ), $args ), 0, max( 1, $n ) );
			$value = call_user_func_array( $cb, $call );
		}
		return $value;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ? $GLOBALS['rwgc_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['rwgc_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['rwgc_test_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-platform-capability-registry.php';
require_once dirname( __DIR__ ) . '/includes/platform/functions-reactwoo-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-config.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-http.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-manifest-store.php';

$failed = 0;
function rwgc_perf_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK   $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Platform_Capability_Registry::reset();
reactwoo_register_condition( 'geo.country', array( 'label' => 'Country', 'provider' => 'reactwoo-geocore' ) );
reactwoo_register_condition( 'weather.facet', array( 'label' => 'Weather', 'provider' => 'reactwoo-geocore' ) );

/**
 * @param int $n Audiences.
 * @return RWGC_Contract_Manifest
 */
function rwgc_perf_manifest( $n ) {
	$audiences   = array();
	$experiences = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$audiences[] = array(
			'id'         => 'aud_' . $i,
			'name'       => 'A' . $i,
			'conditions' => array(
				'all' => array(
					array(
						'capability' => 'geo.country',
						'operator'   => 'equals',
						'value'      => 0 === $i ? 'GB' : 'US',
					),
					array(
						'capability' => 'weather.facet',
						'operator'   => 'equals',
						'value'      => 'hot',
					),
				),
			),
		);
	}
	$slots = array( 'slot_a', 'slot_b', 'slot_c', 'slot_d' );
	foreach ( $slots as $s ) {
		$experiences[] = array(
			'id'          => 'exp_' . $s,
			'name'        => $s,
			'audience_id' => 'aud_0',
			'slot_id'     => $s,
			'variant_id'  => 'var_x',
			'status'      => 'active',
			'priority'    => 10,
		);
	}
	return RWGC_Contract_Manifest::from_array(
		array(
			'schema'      => '1.0',
			'revision'    => $n,
			'site'        => 'site_perf',
			'audiences'   => $audiences,
			'experiences' => $experiences,
			'variants'    => array( array( 'id' => 'var_x', 'type' => 'content' ) ),
			'experiments' => array(),
			'goals'       => array(),
			'slots'       => array(),
		)
	);
}

$sizes   = array( 1, 10, 50, 100 );
$timings = array();
foreach ( $sizes as $n ) {
	$weather_calls = 0;
	RWGC_Decision_Runtime::reset_request_cache();
	$loop_ctx = RWGC_Contract_Context::from_array( array( 'geo.country' => 'GB' ) )->with_resolvers(
		array(
			'weather.facet' => static function () use ( &$weather_calls ) {
				++$weather_calls;
				return 'hot';
			},
		)
	);
	$manifest = rwgc_perf_manifest( $n );
	$t0       = microtime( true );
	$result   = RWGC_Decision_Runtime::evaluate( $manifest, $loop_ctx, array( 'debug' => true ) );
	$ms       = ( microtime( true ) - $t0 ) * 1000;
	$timings[ $n ] = array(
		'ms'         => $ms,
		'evaluated'  => (int) $result->debug()['audiences_evaluated'],
		'remote'     => (int) $result->debug()['remote_calls'],
		'weather'    => $weather_calls,
		'experiences'=> count( $result->selected_experiences() ),
	);
}

echo "\nBenchmark (1 experience-audience, 4 slots, N unused audiences)\n";
echo "N\tms\tevaluated\tweather_resolves\tremote\n";
foreach ( $timings as $n => $row ) {
	printf( "%d\t%.3f\t%d\t%d\t%d\n", $n, $row['ms'], $row['evaluated'], $row['weather'], $row['remote'] );
}

rwgc_perf_assert( '100 audiences evaluates 1', 1 === $timings[100]['evaluated'] );
rwgc_perf_assert( '100 audiences still 4 slot winners', 4 === $timings[100]['experiences'] );
rwgc_perf_assert( 'weather resolved once (AND after country match on aud_0 only)', 1 === $timings[100]['weather'] );
rwgc_perf_assert( 'zero remote calls at 100', 0 === $timings[100]['remote'] );
rwgc_perf_assert( '100-audience evaluate under 50ms', $timings[100]['ms'] < 50 );

$and_weather = 0;
$and_ctx     = RWGC_Contract_Context::from_array( array( 'geo.country' => 'GB' ) )->with_resolvers(
	array(
		'weather.facet' => static function () use ( &$and_weather ) {
			++$and_weather;
			return 'hot';
		},
	)
);
$and_fail    = RWGC_Contract_Condition_Group::from_array(
	array(
		'all' => array(
			array( 'capability' => 'geo.country', 'operator' => 'equals', 'value' => 'US' ),
			array( 'capability' => 'weather.facet', 'operator' => 'equals', 'value' => 'hot' ),
		),
	)
);
$trace = array();
rwgc_perf_assert( 'AND short-circuit fails closed', false === RWGC_Decision_Condition_Evaluator::matches_group( $and_fail, $and_ctx, $trace ) );
rwgc_perf_assert( 'AND short-circuit skips weather resolver', 0 === $and_weather );

$cache_calls = 0;
add_filter(
	'reactwoo_decision_context_resolvers',
	static function () use ( &$cache_calls ) {
		return array(
			'weather.facet' => static function () use ( &$cache_calls ) {
				++$cache_calls;
				return 'mild';
			},
		);
	}
);
RWGC_Context_Value_Cache::reset();
$factory_ctx = RWGC_Decision_Context_Factory::for_request( array( 'geo.country' => 'GB' ) );
rwgc_perf_assert( 'factory lazy first get', 'mild' === $factory_ctx->get( 'weather.facet' ) );
rwgc_perf_assert( 'factory lazy second get cached on context', 'mild' === $factory_ctx->get( 'weather.facet' ) );
$again = RWGC_Decision_Context_Factory::for_request( array( 'geo.country' => 'GB' ) );
rwgc_perf_assert( 'request cache shared across factory contexts', 'mild' === $again->get( 'weather.facet' ) );
rwgc_perf_assert( 'expensive provider ran once', 1 === $cache_calls );

RWGC_Cloud_Http::reset_attempt_count();
RWGC_Cloud_Http::begin_visitor_render();
$blocked = RWGC_Cloud_Http::request( 'GET', '/sites/x/manifest' );
rwgc_perf_assert( 'Cloud HTTP blocked on visitor path', ! $blocked['ok'] && 'cloud_http_forbidden_on_render' === $blocked['error'] );
rwgc_perf_assert( 'blocked HTTP is not an attempt', 0 === RWGC_Cloud_Http::attempt_count() );
RWGC_Cloud_Http::end_visitor_render();

RWGC_Cloud_Manifest_Store::reset_request_cache();
update_option(
	RWGC_Cloud_Manifest_Store::OPTION_CURRENT,
	rwgc_perf_manifest( 1 )->to_array()
);
$m1 = RWGC_Cloud_Manifest_Store::current();
$m2 = RWGC_Cloud_Manifest_Store::current();
rwgc_perf_assert( 'manifest parsed once per revision', 1 === RWGC_Cloud_Manifest_Store::parse_count() );
rwgc_perf_assert( 'manifest memo returns same object', $m1 === $m2 );

RWGC_Decision_Runtime::reset_request_cache();
$m = rwgc_perf_manifest( 10 );
$c = RWGC_Contract_Context::from_array( array( 'geo.country' => 'GB' ) )->with_resolvers(
	array(
		'weather.facet' => static function () {
			return 'hot';
		},
	)
);
$first  = RWGC_Decision_Runtime::evaluate( $m, $c, array( 'slot_id' => 'slot_a' ) );
$second = RWGC_Decision_Runtime::evaluate( $m, $c, array( 'slot_id' => 'slot_b' ) );
rwgc_perf_assert( 'second slot uses audience cache', 0 === (int) $second->debug()['audiences_evaluated'] );
rwgc_perf_assert( 'first slot evaluated one audience', 1 === (int) $first->debug()['audiences_evaluated'] );
rwgc_perf_assert( 'slot filter returns one experience', 1 === count( $first->selected_experiences() ) && 1 === count( $second->selected_experiences() ) );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll decision performance tests passed.\n";
exit( 0 );
