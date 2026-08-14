<?php
/**
 * Cloud migration (WP16) — detect/preview/import/switch. Mock HTTP, no visitor path.
 *
 * Usage: php tests/test-rwgc-cloud-migration.php
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
	 * @param mixed  $autoload Autoload.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) {
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
function rwgc_mig_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

/**
 * @return array<string, mixed>
 */
function rwgc_mig_inventory() {
	return array(
		'visibility_rules' => array(
			array(
				'id'    => '12',
				'label' => 'UK visitors',
				'rules' => array(
					'schema_version' => 1,
					'enabled'        => true,
					'mode'           => 'show_if',
					'match'          => 'any',
					'rules'          => array(
						array(
							'id'         => 'r1',
							'label'      => 'UK',
							'match'      => 'all',
							'conditions' => array(
								array(
									'type'     => 'country',
									'operator' => 'in',
									'value'    => array( 'GB' ),
								),
							),
						),
					),
				),
			),
			array(
				'id'    => '13',
				'label' => 'Hide FR',
				'rules' => array(
					'mode'  => 'hide_if',
					'match' => 'any',
					'rules' => array(
						array(
							'match'      => 'all',
							'conditions' => array(
								array(
									'type'     => 'country',
									'operator' => 'in',
									'value'    => array( 'FR' ),
								),
							),
						),
					),
				),
			),
		),
		'slots'          => array(
			array(
				'id'   => 'slot_hero',
				'name' => 'Hero',
			),
		),
		'variants'       => array(
			array(
				'id'      => 'var_uk',
				'type'    => 'content',
				'name'    => 'UK copy',
				'payload' => array( 'html' => '<p>UK</p>' ),
			),
		),
		'experiments'    => array(
			array(
				'id'   => '99',
				'name' => 'Homepage AB',
			),
		),
		'commerce_rules' => array(
			array(
				'id'   => 'c1',
				'name' => 'NG fee',
			),
		),
	);
}

add_filter(
	'rwgc_cloud_migration_inventory',
	static function () {
		return rwgc_mig_inventory();
	}
);

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
		if ( false !== strpos( $url, '/heartbeat' ) || false !== strpos( $url, '/capabilities' ) ) {
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array( 'ok' => true ),
				'raw'    => '',
				'error'  => '',
			);
		}
		if ( false !== strpos( $url, '/migration/import' ) && 'POST' === $method ) {
			$GLOBALS['rwgc_cloud_mock']['last_import'] = $payload['body'];
			if ( ! empty( $mock['import_fail'] ) ) {
				return array(
					'ok'     => false,
					'status' => 500,
					'body'   => null,
					'raw'    => '',
					'error'  => 'http_500',
				);
			}
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array(
					'dry_run'          => false,
					'imported'         => array( 'audiences' => 1 ),
					'management_mode'  => 'local',
				),
				'raw'    => '',
				'error'  => '',
			);
		}
		if ( false !== strpos( $url, '/management-mode' ) && 'POST' === $method ) {
			$GLOBALS['rwgc_cloud_mock']['last_mode'] = $payload['body'];
			return array(
				'ok'     => true,
				'status' => 200,
				'body'   => array(
					'ok'              => true,
					'management_mode' => isset( $payload['body']['mode'] ) ? $payload['body']['mode'] : 'local',
				),
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

add_filter(
	'rwgc_cloud_api_base',
	static function () {
		return 'https://cloud.test/api/v1';
	}
);

$options_before = $GLOBALS['rwgc_test_options'];
$preview        = reactwoo_cloud_migration_preview();
rwgc_mig_assert( 'preview is local (no HTTP import)', empty( $GLOBALS['rwgc_cloud_mock']['last_import'] ) );
rwgc_mig_assert( 'preview does not write options', $options_before === $GLOBALS['rwgc_test_options'] );
rwgc_mig_assert( 'detected UK + hide_if', 2 === (int) $preview['detected']['visibility_rules'] );
rwgc_mig_assert( 'UK rule supported', 1 === count( array_filter( $preview['supported'], static function ( $row ) {
	return '12' === (string) $row['id'];
} ) ) );
rwgc_mig_assert( 'hide_if unsupported', 1 === count( array_filter( $preview['unsupported'], static function ( $row ) {
	return 'hide_if_not_imported' === (string) $row['reason'];
} ) ) );
rwgc_mig_assert( 'experiment needs review', 1 === count( array_filter( $preview['unsupported'], static function ( $row ) {
	return 'experiment_needs_review' === (string) $row['reason'];
} ) ) );
rwgc_mig_assert( 'commerce stays local', 1 === count( array_filter( $preview['unsupported'], static function ( $row ) {
	return 'commerce_outcomes_stay_local' === (string) $row['reason'];
} ) ) );

$audience = $preview['resources']['audiences'][0];
rwgc_mig_assert( 'country aliased to geo.country', 'geo.country' === (string) $audience['conditions']['all'][0]['capability'] );
rwgc_mig_assert( 'operator in preserved', 'in' === (string) $audience['conditions']['all'][0]['operator'] );
rwgc_mig_assert( 'GB value preserved', array( 'GB' ) === $audience['conditions']['all'][0]['value'] );
rwgc_mig_assert( 'preview mode is local', 'local' === $preview['management_mode'] );

$pair = reactwoo_cloud_pair( 'token-123' );
rwgc_mig_assert( 'pair ok', $pair['ok'] );
rwgc_mig_assert( 'pair leaves management_mode local', 'local' === RWGC_Cloud_Connection::get()['management_mode'] );

$too_soon = reactwoo_cloud_switch_management_mode( 'cloud' );
rwgc_mig_assert( 'switch before import blocked', ! $too_soon['ok'] && 'import_required' === $too_soon['error'] );
rwgc_mig_assert( 'still local after blocked switch', 'local' === RWGC_Cloud_Connection::get()['management_mode'] );

$imported = reactwoo_cloud_import();
rwgc_mig_assert( 'import ok', $imported['ok'] );
rwgc_mig_assert( 'import does not flip mode', 'local' === RWGC_Cloud_Connection::get()['management_mode'] );
rwgc_mig_assert( 'backup stored', is_array( RWGC_Cloud_Migration::backup() ) );
rwgc_mig_assert( 'import posted audiences', ! empty( $GLOBALS['rwgc_cloud_mock']['last_import']['resources']['audiences'] ) );
rwgc_mig_assert( 'import not dry_run', false === (bool) $GLOBALS['rwgc_cloud_mock']['last_import']['dry_run'] );

$switched = reactwoo_cloud_switch_management_mode( 'cloud' );
rwgc_mig_assert( 'switch to cloud ok', $switched['ok'] && 'cloud' === $switched['management_mode'] );
rwgc_mig_assert( 'local connection is cloud', 'cloud' === RWGC_Cloud_Connection::get()['management_mode'] );
rwgc_mig_assert( 'cloud received mode', 'cloud' === $GLOBALS['rwgc_cloud_mock']['last_mode']['mode'] );

$backup = RWGC_Cloud_Migration::backup();
RWGC_Cloud_Connection::disconnect();
rwgc_mig_assert( 'disconnected', ! reactwoo_cloud_is_connected() );
rwgc_mig_assert( 'disconnect resets mode to local', 'local' === RWGC_Cloud_Connection::get()['management_mode'] );
rwgc_mig_assert( 'backup survives disconnect', is_array( RWGC_Cloud_Migration::backup() ) && $backup === RWGC_Cloud_Migration::backup() );
rwgc_mig_assert( 'inventory still in backup', '12' === (string) RWGC_Cloud_Migration::backup()['inventory']['visibility_rules'][0]['id'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Cloud migration tests passed.\n";
exit( 0 );
