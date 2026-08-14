<?php
/**
 * Cloud site health (WP17) — local evaluate, no visitor HTTP.
 *
 * Usage: php tests/test-rwgc-cloud-health.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGC_VERSION', '0.0-test' );

$GLOBALS['rwgc_test_options'] = array();
$GLOBALS['rwgc_test_filters'] = array();

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
			$call = array_slice( array_merge( array( $value ), $args ), 0, max( 1, $n ) );
			$value = call_user_func_array( $cb, $call );
		}
		return $value;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'version' === $show ? '6.8' : 'Example';
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
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
		return false;
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
function rwgc_health_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

$disconnected = reactwoo_cloud_health();
rwgc_health_assert( 'default is disconnected', 'disconnected' === $disconnected['status'] );
rwgc_health_assert( 'disconnected label', 'Disconnected' === $disconnected['status_label'] );
rwgc_health_assert(
	'disconnected message is actionable',
	false !== strpos( $disconnected['issues'][0]['remediation'], 'pairing token' )
);
rwgc_health_assert( 'environment reports php', '' !== $disconnected['environment']['php'] );
rwgc_health_assert( 'environment reports geocore', '0.0-test' === $disconnected['environment']['geocore'] );

$now     = 1_700_000_000;
$healthy = RWGC_Cloud_Health::evaluate(
	array(
		'connected'         => true,
		'has_credentials'   => true,
		'connection_state'  => 'connected',
		'last_heartbeat_at' => gmdate( 'c', $now - 60 ),
		'last_error'        => '',
		'manifest_revision' => 3,
		'management_mode'   => 'local',
		'queue_pending'     => 0,
		'queue_dropped'     => 0,
		'now'               => $now,
		'environment'       => array(
			'wordpress'   => '6.8',
			'php'         => '8.2',
			'geocore'     => '1.8.153',
			'woocommerce' => '9.0',
			'elementor'   => '3.24',
		),
		'capability_count'  => 4,
	)
);
rwgc_health_assert( 'healthy when connected and fresh', 'healthy' === $healthy['status'] );
rwgc_health_assert( 'healthy has no issues', array() === $healthy['issues'] );
rwgc_health_assert( 'healthy reports Woo + Elementor', '9.0' === $healthy['environment']['woocommerce'] && '3.24' === $healthy['environment']['elementor'] );

$sync = RWGC_Cloud_Health::evaluate(
	array(
		'connected'         => true,
		'has_credentials'   => true,
		'connection_state'  => 'connected',
		'last_heartbeat_at' => gmdate( 'c', $now - 60 ),
		'last_error'        => 'sync_failed',
		'now'               => $now,
	)
);
rwgc_health_assert( 'sync_failed is warning', 'warning' === $sync['status'] );
rwgc_health_assert( 'sync_failed tells operator to Sync now', false !== strpos( $sync['issues'][0]['remediation'], 'Sync now' ) );

$creds = RWGC_Cloud_Health::evaluate(
	array(
		'connected'         => true,
		'has_credentials'   => false,
		'connection_state'  => 'connected',
		'last_heartbeat_at' => gmdate( 'c', $now - 60 ),
		'last_error'        => '',
		'now'               => $now,
	)
);
rwgc_health_assert( 'missing credentials is configuration error', 'configuration_error' === $creds['status'] );
rwgc_health_assert( 'config error label', 'Configuration Error' === $creds['status_label'] );

$stale = RWGC_Cloud_Health::evaluate(
	array(
		'connected'         => true,
		'has_credentials'   => true,
		'connection_state'  => 'connected',
		'last_heartbeat_at' => gmdate( 'c', $now - 8000 ),
		'last_error'        => '',
		'now'               => $now,
	)
);
rwgc_health_assert( 'stale heartbeat is warning', 'warning' === $stale['status'] );
rwgc_health_assert( 'stale code', 'heartbeat_stale' === $stale['issues'][0]['code'] );

$queue = RWGC_Cloud_Health::evaluate(
	array(
		'connected'         => true,
		'has_credentials'   => true,
		'connection_state'  => 'connected',
		'last_heartbeat_at' => gmdate( 'c', $now - 60 ),
		'queue_pending'     => 150,
		'now'               => $now,
	)
);
rwgc_health_assert( 'queue backlog is warning', 'warning' === $queue['status'] );

$managed = RWGC_Cloud_Health::evaluate(
	array(
		'connected'         => true,
		'has_credentials'   => true,
		'connection_state'  => 'connected',
		'last_heartbeat_at' => gmdate( 'c', $now - 60 ),
		'management_mode'   => 'cloud',
		'manifest_revision' => 0,
		'now'               => $now,
	)
);
rwgc_health_assert( 'cloud-managed without manifest is configuration error', 'configuration_error' === $managed['status'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Cloud health tests passed.\n";
exit( 0 );
