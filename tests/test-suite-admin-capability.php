<?php
/**
 * CLI regression tests for suite admin capability checks.
 *
 * Run from plugin root: php tests/test-suite-admin-capability.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $capability Capability name.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		global $rwgc_test_current_user_caps;
		return ! empty( $rwgc_test_current_user_caps[ $capability ] );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-admin.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-suite-admin.php';

/**
 * @param array<string, bool> $caps Capabilities granted to the current test user.
 * @return void
 */
function rwgc_test_set_caps( array $caps ) {
	global $rwgc_test_current_user_caps;
	$rwgc_test_current_user_caps = $caps;
}

/**
 * @param bool   $condition Assertion condition.
 * @param string $message Assertion message.
 * @return void
 */
function rwgc_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/**
 * @return bool
 */
function rwgc_test_suite_can_manage() {
	$method = new ReflectionMethod( 'RWGC_Suite_Admin', 'can_manage_suite' );
	$method->setAccessible( true );
	return (bool) $method->invoke( null );
}

rwgc_test_set_caps(
	array(
		'manage_woocommerce' => true,
	)
);
rwgc_test_assert(
	'manage_woocommerce' === RWGC_Admin::required_capability(),
	'Shop managers should be the required capability when they lack manage_options.'
);
rwgc_test_assert(
	RWGC_Admin::can_manage(),
	'Shop managers should be able to manage Geo Core admin screens.'
);
rwgc_test_assert(
	rwgc_test_suite_can_manage(),
	'Suite screens should allow supported shop-manager access.'
);

rwgc_test_set_caps(
	array(
		'edit_posts' => true,
	)
);
rwgc_test_assert(
	! rwgc_test_suite_can_manage(),
	'Suite screens should continue denying users without the Geo Core admin capability.'
);

fwrite( STDOUT, "OK: RWGC_Suite_Admin capability tests passed.\n" );
exit( 0 );
