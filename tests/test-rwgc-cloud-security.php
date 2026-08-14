<?php
/**
 * WP18 security regressions — pairing api_base, credentials MAC, SSRF hosts.
 *
 * Usage: php tests/test-rwgc-cloud-security.php
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
if ( ! function_exists( 'do_action' ) ) {
	function do_action() {}
}
if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $v ) {
		return rtrim( (string) $v, '/\\' );
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
if ( ! function_exists( 'gmdate' ) ) {
	// native
}

require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-config.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-credentials.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-connection.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-http.php';
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-pairing.php';

$failed = 0;
function rwgc_sec_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK   $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

rwgc_sec_assert( 'https public host allowed', RWGC_Cloud_Config::is_secure_base( 'https://cloud.reactwoo.com/api/v1' ) );
rwgc_sec_assert( 'http blocked without filter', ! RWGC_Cloud_Config::is_secure_base( 'http://127.0.0.1:3040/api/v1' ) );
rwgc_sec_assert( 'metadata host blocked', ! RWGC_Cloud_Config::is_secure_base( 'https://169.254.169.254/' ) );
rwgc_sec_assert( 'private ipv4 blocked', ! RWGC_Cloud_Config::is_secure_base( 'https://10.0.0.8/api' ) );
rwgc_sec_assert( 'localhost blocked', RWGC_Cloud_Config::is_blocked_host( 'localhost' ) );

add_filter( 'rwgc_cloud_allow_insecure_api_base', static function () {
	return true;
} );
rwgc_sec_assert( 'local http allowed with filter', RWGC_Cloud_Config::is_secure_base( 'http://127.0.0.1:3040/api/v1' ) );

RWGC_Cloud_Credentials::store( 'site_a', 'super-secret-value', 'https://cloud.test/api/v1' );
$opt = get_option( RWGC_Cloud_Credentials::OPTION );
rwgc_sec_assert( 'cipher uses v2 mac prefix', is_array( $opt ) && 0 === strpos( (string) $opt['cipher'], 'v2.' ) );
$got = RWGC_Cloud_Credentials::get();
rwgc_sec_assert( 'round-trip secret', $got && 'super-secret-value' === $got['site_secret'] );

$raw = base64_decode( substr( $opt['cipher'], 3 ), true );
$raw[20] = 'A' === $raw[20] ? 'B' : 'A';
$tampered           = $opt;
$tampered['cipher'] = 'v2.' . base64_encode( $raw );
update_option( RWGC_Cloud_Credentials::OPTION, $tampered );
rwgc_sec_assert( 'tampered mac rejected', null === RWGC_Cloud_Credentials::get() );

update_option( RWGC_Cloud_Credentials::OPTION, $opt );
add_filter(
	'rwgc_cloud_api_base',
	static function () {
		return 'https://cloud.test/api/v1';
	}
);
add_filter(
	'rwgc_cloud_http_transport',
	static function ( $response, $payload ) {
		if ( false !== strpos( $payload['url'], '/sites/pair' ) ) {
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array(
					'site_id'     => 'site_sec',
					'site_secret' => 'paired-secret',
					'api_base'    => 'https://evil.example/steal',
				),
				'raw'    => '',
				'error'  => '',
			);
		}
		if ( false !== strpos( $payload['url'], '/sites/confirm' ) ) {
			rwgc_sec_assert(
				'confirm uses configured base not cloud-returned host',
				false !== strpos( $payload['url'], 'cloud.test' ) && false === strpos( $payload['url'], 'evil.example' )
			);
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array( 'confirmed' => true ),
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

$pair = RWGC_Cloud_Pairing::pair( 'token-sec' );
rwgc_sec_assert( 'pair ok', ! empty( $pair['ok'] ) );
$creds = RWGC_Cloud_Credentials::get();
rwgc_sec_assert(
	'stored api_base ignores cloud-returned host',
	$creds && 'https://cloud.test/api/v1' === $creds['api_base']
);

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Cloud security tests passed.\n";
exit( 0 );
