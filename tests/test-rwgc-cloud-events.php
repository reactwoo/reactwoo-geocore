<?php
/**
 * Cloud event queue (WP14) — local persist, cron flush, no visitor HTTP.
 *
 * Usage: php tests/test-rwgc-cloud-events.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGC_VERSION', '0.0-test' );

$GLOBALS['rwgc_test_options'] = array();
$GLOBALS['rwgc_test_filters'] = array();
$GLOBALS['rwgc_cloud_mock']   = array();
$GLOBALS['rwgc_is_admin']     = false;

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ? $GLOBALS['rwgc_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
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
if ( ! function_exists( 'do_action' ) ) {
	function do_action() {}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
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
			$cb   = $entry['cb'];
			$n    = (int) $entry['args'];
			$call = array_merge( array( $value ), $args );
			$call = array_slice( $call, 0, max( 1, $n ) );
			$value = call_user_func_array( $cb, $call );
		}
		return $value;
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $s ) {
		return rtrim( (string) $s, '/\\' );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['rwgc_is_admin'] );
	}
}
if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron() {
		return false;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo() {
		return 'Example';
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud.php';
RWGC_Cloud::load();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_events_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Cloud_Event_Queue::reset();

rwgc_events_assert( 'reject unknown type', ! RWGC_Cloud_Event_Queue::enqueue( array( 'type' => 'page_view' ) ) );
rwgc_events_assert(
	'enqueue impression',
	RWGC_Cloud_Event_Queue::enqueue(
		array(
			'type'                 => 'variant.impression',
			'experience'           => 'exp_a',
			'variant'              => 'var_b',
			'anonymous_visitor_id' => 'anon_1',
		)
	)
);
rwgc_events_assert( 'buffer size before persist', RWGC_Cloud_Event_Queue::size() >= 1 );
RWGC_Cloud_Event_Queue::persist_buffer();
rwgc_events_assert( 'persisted', RWGC_Cloud_Event_Queue::size() >= 1 );

RWGC_Cloud_Event_Queue::reset();
for ( $i = 0; $i < 2100; $i++ ) {
	RWGC_Cloud_Event_Queue::enqueue(
		array(
			'type'       => 'variant.impression',
			'experience' => 'exp_a',
			'variant'    => 'var_b',
		)
	);
}
RWGC_Cloud_Event_Queue::persist_buffer();
rwgc_events_assert( 'oversize compacted', RWGC_Cloud_Event_Queue::size() <= RWGC_Cloud_Event_Queue::MAX_ITEMS );
rwgc_events_assert( 'impressions aggregated not just dropped', RWGC_Cloud_Event_Queue::size() < 50 );

$flush_skip = RWGC_Cloud_Event_Queue::flush();
rwgc_events_assert( 'flush skipped off cron/admin', 'skipped' === $flush_skip['status'] || 'not_allowed' === $flush_skip['error'] );

RWGC_Cloud_Credentials::store( 'site_abc', 'secret_xyz', 'https://cloud.test/api/v1' );
RWGC_Cloud_Connection::update( array( 'state' => RWGC_Cloud_Connection::STATE_CONNECTED, 'site_id' => 'site_abc' ) );
add_filter( 'rwgc_cloud_force_event_flush', static function () {
	return true;
} );
add_filter( 'rwgc_cloud_api_base', static function () {
	return 'https://cloud.test/api/v1';
} );

$GLOBALS['rwgc_cloud_mock']['events_ok'] = true;
add_filter(
	'rwgc_cloud_http_transport',
	static function ( $response, $payload ) {
		$url = $payload['url'];
		if ( false !== strpos( $url, '/events/batch' ) ) {
			if ( ! empty( $GLOBALS['rwgc_cloud_mock']['events_fail'] ) ) {
				return array(
					'ok'     => false,
					'status' => 503,
					'body'   => null,
					'raw'    => '',
					'error'  => 'http_503',
				);
			}
			$GLOBALS['rwgc_cloud_mock']['last_batch'] = $payload['body'];
			return array(
				'ok'     => true,
				'status' => 202,
				'body'   => array( 'accepted' => 1 ),
				'raw'    => '',
				'error'  => '',
			);
		}
		return array(
			'ok'     => false,
			'status' => 404,
			'body'   => null,
			'raw'    => '',
			'error'  => 'http_404',
		);
	},
	10,
	2
);

RWGC_Cloud_Event_Queue::reset();
RWGC_Cloud_Event_Queue::enqueue(
	array(
		'type'      => 'commerce.purchase',
		'variant'   => 'var_b',
		'value'     => 12.5,
		'email'     => 'leak@example.com',
	)
);
RWGC_Cloud_Event_Queue::persist_buffer();
$uploaded = RWGC_Cloud_Event_Queue::flush();
rwgc_events_assert( 'flush uploaded', ! empty( $uploaded['ok'] ) && 1 === (int) $uploaded['uploaded'] );
$batch = isset( $GLOBALS['rwgc_cloud_mock']['last_batch']['events'][0] ) ? $GLOBALS['rwgc_cloud_mock']['last_batch']['events'][0] : array();
rwgc_events_assert( 'PII stripped from queue', empty( $batch['email'] ) );
rwgc_events_assert( 'purchase value kept', isset( $batch['value'] ) && 12.5 === (float) $batch['value'] );

RWGC_Cloud_Event_Queue::reset();
RWGC_Cloud_Event_Queue::enqueue( array( 'type' => 'goal.click', 'variant' => 'var_b' ) );
RWGC_Cloud_Event_Queue::persist_buffer();
$GLOBALS['rwgc_cloud_mock']['events_fail'] = true;
$fail = RWGC_Cloud_Event_Queue::flush();
rwgc_events_assert( 'failed flush keeps queue', ! $fail['ok'] && RWGC_Cloud_Event_Queue::size() >= 1 );
$backoff = RWGC_Cloud_Event_Queue::flush();
rwgc_events_assert( 'exponential backoff', 'backoff' === $backoff['status'] );

add_filter( 'rwgc_cloud_telemetry_allowed', static function () {
	return true;
} );
$recorded = reactwoo_cloud_record_event(
	'variant.impression',
	array(
		'experience' => 'exp_a',
		'variant'    => 'var_b',
	)
);
rwgc_events_assert( 'helper records when connected', $recorded );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Cloud event queue smoke tests passed.\n";
exit( 0 );
