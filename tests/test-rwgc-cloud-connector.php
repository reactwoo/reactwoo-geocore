<?php
/**
 * Cloud Connector smoke tests (WP10) — mock HTTP, no visitor path.
 *
 * Usage: php tests/test-rwgc-cloud-connector.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGC_VERSION', '0.0-test' );

$GLOBALS['rwgc_test_options'] = array();
$GLOBALS['rwgc_test_filters'] = array();
$GLOBALS['rwgc_cloud_mock']   = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ? $GLOBALS['rwgc_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		$GLOBALS['rwgc_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_option( $key ) {
		unset( $GLOBALS['rwgc_test_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @return void
	 */
	function do_action() {}
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @return true
	 */
	function add_action() {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook Hook.
	 * @param callable $cb Callback.
	 * @param int      $prio Priority.
	 * @param int      $args Args.
	 * @return true
	 */
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['rwgc_test_filters'][ $hook ][] = array(
			'cb'   => $cb,
			'args' => $args,
		);
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['rwgc_test_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['rwgc_test_filters'][ $hook ] as $entry ) {
			$cb    = $entry['cb'];
			$n     = (int) $entry['args'];
			$call  = array_merge( array( $value ), $args );
			$call  = array_slice( $call, 0, max( 1, $n ) );
			$value = call_user_func_array( $cb, $call );
		}
		return $value;
	}
}
if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * @param string $scheme Scheme.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		return 'test-salt-' . $scheme;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	/**
	 * @param string $show Show.
	 * @return string
	 */
	function get_bloginfo( $show = '' ) {
		return 'Example';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * @param string $s String.
	 * @return string
	 */
	function untrailingslashit( $s ) {
		return rtrim( (string) $s, '/\\' );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return false;
	}
}
if ( ! function_exists( 'gmdate' ) ) {
	// native
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/variants/class-rwgc-variants.php';
RWGC_Variants::load();
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud.php';
RWGC_Cloud::load();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_cloud_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

// Mock transport.
add_filter(
	'rwgc_cloud_http_transport',
	static function ( $response, $payload ) {
		$url    = $payload['url'];
		$method = $payload['method'];
		$mock   = $GLOBALS['rwgc_cloud_mock'];

		if ( false !== strpos( $url, '/sites/pair' ) && 'POST' === $method ) {
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array(
					'site_id'     => 'site_abc',
					'site_secret' => 'secret_xyz',
					'api_base'    => 'https://cloud.test/api/v1',
				),
				'raw'    => '',
				'error'  => '',
			);
		}
		if ( false !== strpos( $url, '/sites/confirm' ) && 'POST' === $method ) {
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array( 'confirmed' => true ),
				'raw'    => '',
				'error'  => '',
			);
		}
		if ( false !== strpos( $url, '/manifest' ) && 'GET' === $method ) {
			if ( ! empty( $mock['manifest_304'] ) ) {
				return array(
					'ok'     => false,
					'status' => 304,
					'body'   => null,
					'raw'    => '',
					'error'  => 'http_304',
				);
			}
			if ( ! empty( $mock['manifest_fail'] ) ) {
				return array(
					'ok'     => false,
					'status' => 503,
					'body'   => null,
					'raw'    => '',
					'error'  => 'http_503',
				);
			}
			$site = ! empty( $mock['wrong_site'] ) ? 'other_site' : 'site_abc';
			$rev  = isset( $mock['revision'] ) ? (int) $mock['revision'] : 2;
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array(
					'schema'   => '1.0',
					'revision' => $rev,
					'site'     => $site,
					'audiences'=> array(),
					'experiences' => array(),
					'variants' => array(
						array(
							'id'      => 'var_cloud',
							'type'    => 'content',
							'payload' => array( 'html' => '<p>CLOUD</p>' ),
						),
					),
					'experiments' => array(),
					'goals'       => array(),
					'slots'       => array(),
				),
				'raw'    => '',
				'error'  => '',
			);
		}
		if ( false !== strpos( $url, '/heartbeat' ) || false !== strpos( $url, '/capabilities' ) ) {
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array( 'ok' => true ),
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

add_filter( 'rwgc_cloud_api_base', static function () {
	return 'https://cloud.test/api/v1';
} );

// Pair.
$pair = reactwoo_cloud_pair( 'token-123' );
rwgc_cloud_assert( 'pair ok', $pair['ok'] && 'site_abc' === $pair['site_id'] );
rwgc_cloud_assert( 'connected', reactwoo_cloud_is_connected() );
$creds = RWGC_Cloud_Credentials::get();
rwgc_cloud_assert( 'secret stored encrypted', $creds && 'secret_xyz' === $creds['site_secret'] );
$opt = get_option( RWGC_Cloud_Credentials::OPTION );
rwgc_cloud_assert( 'secret not plaintext in option', is_array( $opt ) && false === strpos( wp_json_encode( $opt ), 'secret_xyz' ) );

// Sync install.
$GLOBALS['rwgc_cloud_mock']['revision'] = 2;
$sync = reactwoo_cloud_sync_manifest();
rwgc_cloud_assert( 'manifest synced', $sync['ok'] && 'updated' === $sync['status'] && 2 === $sync['revision'] );
$manifest = reactwoo_cloud_get_manifest();
rwgc_cloud_assert( 'manifest readable locally', $manifest && 2 === $manifest->revision() );
rwgc_cloud_assert( 'variant hydrated', null !== reactwoo_get_variant( 'var_cloud' ) );

// 304 not modified.
$GLOBALS['rwgc_cloud_mock']['manifest_304'] = true;
$sync304 = reactwoo_cloud_sync_manifest();
rwgc_cloud_assert( '304 keeps revision', $sync304['ok'] && 'not_modified' === $sync304['status'] );
unset( $GLOBALS['rwgc_cloud_mock']['manifest_304'] );

// Wrong site rejected; previous retained.
$prev_rev = reactwoo_cloud_get_manifest()->revision();
$GLOBALS['rwgc_cloud_mock']['wrong_site'] = true;
$GLOBALS['rwgc_cloud_mock']['revision']   = 9;
$bad = reactwoo_cloud_sync_manifest();
rwgc_cloud_assert( 'wrong site rejected', ! $bad['ok'] && 'wrong_site' === $bad['error'] );
rwgc_cloud_assert( 'previous retained after reject', $prev_rev === reactwoo_cloud_get_manifest()->revision() );
unset( $GLOBALS['rwgc_cloud_mock']['wrong_site'] );

// Outage retains cache.
$GLOBALS['rwgc_cloud_mock']['manifest_fail'] = true;
$fail = reactwoo_cloud_sync_manifest();
rwgc_cloud_assert( 'outage soft-fails', ! $fail['ok'] );
rwgc_cloud_assert( 'outage keeps cache', null !== reactwoo_cloud_get_manifest() );
unset( $GLOBALS['rwgc_cloud_mock']['manifest_fail'] );

// Atomic previous on successful update.
$GLOBALS['rwgc_cloud_mock']['revision'] = 3;
reactwoo_cloud_sync_manifest();
$previous = RWGC_Cloud_Manifest_Store::previous_raw();
rwgc_cloud_assert( 'previous known-good saved', is_array( $previous ) && 2 === (int) $previous['revision'] );

// Disconnect keeps manifests, clears credentials.
RWGC_Cloud_Connection::disconnect();
rwgc_cloud_assert( 'disconnected', ! reactwoo_cloud_is_connected() );
rwgc_cloud_assert( 'manifest survives disconnect', null !== reactwoo_cloud_get_manifest() );

// Reconnect path: pair again.
$pair2 = reactwoo_cloud_pair( 'token-456' );
rwgc_cloud_assert( 'reconnect pair ok', $pair2['ok'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Cloud Connector smoke tests passed.\n";
exit( 0 );
