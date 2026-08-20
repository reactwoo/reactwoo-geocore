<?php
/**
 * Entitlement provider smoke tests (WP15).
 *
 * Usage: php tests/test-rwgc-entitlements.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_options'] = array();
$GLOBALS['rwgc_test_filters'] = array();

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
		foreach ( $GLOBALS['rwgc_test_filters'][ $hook ] as $row ) {
			$value = call_user_func_array( $row['cb'], array_merge( array( $value ), $args ) );
		}
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/entitlements/class-rwgc-entitlements.php';
RWGC_Entitlements::load();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_ent_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Entitlements::set_provider( null );

rwgc_ent_assert( 'standalone default denies commerce', ! RWGC_Entitlements::allows( 'cloud.commerce' ) );
rwgc_ent_assert( 'standalone source', 'standalone' === RWGC_Entitlements::source() );
rwgc_ent_assert( 'standalone components on', RWGC_Entitlements::allows( 'cloud.components' ) );
rwgc_ent_assert( 'sites.max is 1 locally', 1 === RWGC_Entitlements::limit( 'sites.max' ) );

add_filter(
	'rwgc_standalone_entitlements',
	static function ( $rows ) {
		$rows['cloud.personalisation']['allowed'] = true;
		$rows['cloud.commerce']['allowed']        = true;
		return $rows;
	}
);
RWGC_Entitlements::set_provider( null );
rwgc_ent_assert( 'standalone filter unlocks commerce', RWGC_Entitlements::allows( 'cloud.commerce' ) );
rwgc_ent_assert( 'helper matches facade', reactwoo_entitlements_allows( 'cloud.personalisation' ) );

$contract = RWGC_Contract_Entitlement::from_array(
	array(
		'key'     => 'cloud.optimise',
		'allowed' => true,
		'limit'   => 90,
		'source'  => 'cloud',
	)
);
rwgc_ent_assert( 'contract getters', 'cloud' === $contract->source() && 90 === $contract->limit() );

// Cloud cache without connection is ignored.
RWGC_Cloud_Entitlement_Store::put(
	array(
		'plan'   => 'growth',
		'status' => 'active',
		'items'  => array(
			array(
				'key'     => 'cloud.commerce',
				'allowed' => true,
				'limit'   => null,
			),
		),
	)
);
RWGC_Entitlements::set_provider( null );
rwgc_ent_assert( 'disconnected ignores cloud cache', 'standalone' === RWGC_Entitlements::source() );

if ( ! class_exists( 'RWGC_Cloud_Connection', false ) ) {
	/**
	 * Minimal connected stub for store::is_active().
	 */
	final class RWGC_Cloud_Connection {
		/** @var bool */
		public static $connected = false;

		/**
		 * @return bool
		 */
		public static function is_connected() {
			return self::$connected;
		}
	}
}

RWGC_Cloud_Connection::$connected = true;
RWGC_Entitlements::set_provider( null );
rwgc_ent_assert( 'connected uses cloud cache', RWGC_Entitlements::allows( 'cloud.commerce' ) );
rwgc_ent_assert( 'cloud source', 'cloud' === RWGC_Entitlements::source() );
rwgc_ent_assert( 'unknown cloud key denied', ! RWGC_Entitlements::allows( 'not.a.key' ) );
rwgc_ent_assert( 'connected still keeps standalone personalisation grant', RWGC_Entitlements::allows( 'cloud.personalisation' ) );

RWGC_Cloud_Entitlement_Store::put(
	array(
		'plan'   => 'starter',
		'status' => 'active',
		'items'  => array(
			array(
				'key'     => 'cloud.personalisation',
				'allowed' => true,
				'limit'   => null,
			),
			array(
				'key'     => 'cloud.commerce',
				'allowed' => false,
				'limit'   => null,
			),
			array(
				'key'     => 'sites.max',
				'allowed' => true,
				'limit'   => 1,
			),
		),
	)
);
RWGC_Entitlements::set_provider( null );
rwgc_ent_assert( 'starter cloud does not revoke standalone commerce', RWGC_Entitlements::allows( 'cloud.commerce' ) );
rwgc_ent_assert( 'starter cloud sites.max comes from cloud', 1 === RWGC_Entitlements::limit( 'sites.max' ) );

RWGC_Cloud_Entitlement_Store::put(
	array(
		'plan'   => 'growth',
		'status' => 'canceled',
		'items'  => array(
			array(
				'key'     => 'cloud.commerce',
				'allowed' => false,
				'limit'   => null,
			),
		),
	)
);
RWGC_Entitlements::set_provider( null );
rwgc_ent_assert( 'lapsed cloud does not revoke standalone commerce', RWGC_Entitlements::allows( 'cloud.commerce' ) );
rwgc_ent_assert( 'lapsed cloud source is standalone', 'standalone' === RWGC_Entitlements::source() );

$GLOBALS['rwgc_test_filters']['rwgc_standalone_entitlements'] = array();
add_filter(
	'rwgc_standalone_entitlements',
	static function ( $rows ) {
		$rows['cloud.commerce']['allowed'] = false;
		return $rows;
	}
);
RWGC_Cloud_Connection::$connected = true;
RWGC_Cloud_Entitlement_Store::put(
	array(
		'plan'   => 'growth',
		'status' => 'active',
		'items'  => array(
			array(
				'key'     => 'cloud.commerce',
				'allowed' => true,
				'limit'   => null,
			),
			array(
				'key'     => 'sites.max',
				'allowed' => true,
				'limit'   => 5,
			),
		),
	)
);
RWGC_Entitlements::set_provider( null );
rwgc_ent_assert( 'growth cloud grants commerce without standalone', RWGC_Entitlements::allows( 'cloud.commerce' ) );
rwgc_ent_assert( 'growth cloud sites.max comes from cloud', 5 === RWGC_Entitlements::limit( 'sites.max' ) );

RWGC_Cloud_Connection::$connected = false;
RWGC_Cloud_Entitlement_Store::clear();
RWGC_Entitlements::set_provider( null );
rwgc_ent_assert( 'clear returns to standalone', 'standalone' === RWGC_Entitlements::source() );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll entitlement smoke tests passed.\n";
exit( 0 );
