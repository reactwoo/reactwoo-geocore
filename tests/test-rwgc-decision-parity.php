<?php
/**
 * Gate A: portable country rule vs Decision Runtime audience.
 *
 * Usage: php tests/test-rwgc-decision-parity.php
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

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

$base = dirname( __DIR__ ) . '/includes/';
require_once $base . 'contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once $base . 'platform/class-rwgc-platform-capability-registry.php';
require_once $base . 'platform/functions-reactwoo-capabilities.php';
require_once $base . 'targeting/class-rwgc-context-snapshot.php';
require_once $base . 'targeting/class-rwgc-target-operators.php';
require_once $base . 'targeting/class-rwgc-targeting-rule-set-schema.php';
require_once $base . 'targeting/class-rwgc-rule-evaluator.php';
require_once $base . 'decision/class-rwgc-decision.php';
RWGC_Decision::load();

RWGC_Platform_Capability_Registry::reset();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_parity_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

$match = RWGC_Decision_Parity::compare_country_in_rule( 'GB', array( 'GB', 'IE' ) );
rwgc_parity_assert( 'GB matches portable', true === $match['portable'] );
rwgc_parity_assert( 'GB matches decision', true === $match['decision'] );
rwgc_parity_assert( 'GB equivalent (Gate A)', true === $match['equivalent'] );

$miss = RWGC_Decision_Parity::compare_country_in_rule( 'US', array( 'GB', 'IE' ) );
rwgc_parity_assert( 'US misses portable', false === $miss['portable'] );
rwgc_parity_assert( 'US misses decision', false === $miss['decision'] );
rwgc_parity_assert( 'US equivalent (Gate A)', true === $miss['equivalent'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nGate A parity smoke tests passed.\n";
exit( 0 );
