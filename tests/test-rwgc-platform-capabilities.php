<?php
/**
 * Platform capability registry smoke tests.
 *
 * Usage: php tests/test-rwgc-platform-capabilities.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  ...$args Args.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-platform-capability-registry.php';
require_once dirname( __DIR__ ) . '/includes/platform/functions-reactwoo-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-platform-capabilities-bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-wp-abilities-adapter.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_cap_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Platform_Capability_Registry::reset();

rwgc_cap_assert(
	'register condition',
	reactwoo_register_condition(
		'geo.country',
		array(
			'label'    => 'Country',
			'provider' => 'reactwoo-geocore',
		)
	)
);

rwgc_cap_assert( 'has capability', reactwoo_has_capability( 'geo.country' ) );
rwgc_cap_assert( 'has via alias', reactwoo_has_capability( 'country' ) );

$dup = reactwoo_register_condition(
	'geo.country',
	array(
		'label'    => 'Country hijack',
		'provider' => 'evil-plugin',
	)
);
rwgc_cap_assert( 'collision rejected', false === $dup );
rwgc_cap_assert( 'collision recorded', count( RWGC_Platform_Capability_Registry::collisions() ) >= 1 );
rwgc_cap_assert(
	'original provider kept',
	'reactwoo-geocore' === reactwoo_get_capability( 'geo.country' )['provider']
);

reactwoo_register_action(
	'commerce.product.promote',
	array(
		'label'    => 'Promote product',
		'provider' => 'reactwoo-geo-commerce',
	)
);
rwgc_cap_assert( 'action registered', reactwoo_has_capability( 'commerce.product.promote' ) );

reactwoo_register_goal(
	'goal.purchase',
	array(
		'label'    => 'Purchase',
		'provider' => 'reactwoo-geo-optimise',
	)
);
rwgc_cap_assert( 'goal registered', reactwoo_has_capability( 'goal.purchase' ) );

rwgc_cap_assert( 'abilities unsupported here', false === RWGC_WP_Abilities_Adapter::is_supported() );

$report = RWGC_Platform_Capability_Registry::export_for_report();
rwgc_cap_assert( 'export count', count( $report ) >= 3 );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll platform capability smoke tests passed.\n";
exit( 0 );
