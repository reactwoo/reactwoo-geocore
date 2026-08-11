<?php
/**
 * Elementor Experience Slot adapter sync smoke tests (no Elementor bootstrap).
 *
 * Usage: php tests/test-rwgc-elementor-experience-slots.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_options'] = array();

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
if ( ! function_exists( 'wp_rand' ) ) {
	/**
	 * @param int $min Min.
	 * @param int $max Max.
	 * @return int
	 */
	function wp_rand( $min = 0, $max = 0 ) {
		return mt_rand( (int) $min, (int) $max );
	}
}
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
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'did_action' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return int
	 */
	function did_action( $hook ) {
		return 0;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook Hook.
	 * @param callable $cb Callback.
	 * @param int      $prio Priority.
	 * @param int      $args Args.
	 * @return true
	 */
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();
require_once dirname( __DIR__ ) . '/includes/slots/class-rwgc-experience-slots.php';
RWGC_Experience_Slots::load();
require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-experience-slots.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_el_slot_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Experience_Slot_Registry::reset_cache();

$disabled = RWGC_Elementor_Experience_Slots::sync_settings(
	array( RWGC_Elementor_Experience_Slots::SETTING_ENABLE => '' ),
	'abc123',
	'/'
);
rwgc_el_slot_assert( 'disabled skips registry', false === $disabled['enabled'] && '' === $disabled['slot_id'] );

$first = RWGC_Elementor_Experience_Slots::sync_settings(
	array(
		RWGC_Elementor_Experience_Slots::SETTING_ENABLE => 'yes',
		RWGC_Elementor_Experience_Slots::SETTING_NAME   => 'Homepage Hero',
		RWGC_Elementor_Experience_Slots::SETTING_MODE   => 'local',
	),
	'el_aaa',
	'/'
);
rwgc_el_slot_assert( 'enable generates slot id', $first['enabled'] && RWGC_Experience_Slot_Id::is_valid( $first['slot_id'] ) );
rwgc_el_slot_assert( 'binding stored', 'elementor:el_aaa' === $first['settings'][ RWGC_Elementor_Experience_Slots::SETTING_BINDING ] );

$again = RWGC_Elementor_Experience_Slots::sync_settings(
	array(
		RWGC_Elementor_Experience_Slots::SETTING_ENABLE  => 'yes',
		RWGC_Elementor_Experience_Slots::SETTING_NAME    => 'Homepage Hero',
		RWGC_Elementor_Experience_Slots::SETTING_ID      => $first['slot_id'],
		RWGC_Elementor_Experience_Slots::SETTING_BINDING => 'elementor:el_aaa',
		RWGC_Elementor_Experience_Slots::SETTING_MODE    => 'local',
	),
	'el_aaa',
	'/'
);
rwgc_el_slot_assert( 'same element keeps id', ! $again['regenerated'] && $again['slot_id'] === $first['slot_id'] );

$clone = RWGC_Elementor_Experience_Slots::sync_settings(
	array(
		RWGC_Elementor_Experience_Slots::SETTING_ENABLE  => 'yes',
		RWGC_Elementor_Experience_Slots::SETTING_NAME    => 'Homepage Hero',
		RWGC_Elementor_Experience_Slots::SETTING_ID      => $first['slot_id'],
		RWGC_Elementor_Experience_Slots::SETTING_BINDING => 'elementor:el_aaa',
		RWGC_Elementor_Experience_Slots::SETTING_MODE    => 'local',
	),
	'el_bbb',
	'/'
);
rwgc_el_slot_assert( 'clone regenerates id', $clone['regenerated'] && $clone['slot_id'] !== $first['slot_id'] );
rwgc_el_slot_assert( 'clone binding updated', 'elementor:el_bbb' === $clone['settings'][ RWGC_Elementor_Experience_Slots::SETTING_BINDING ] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Elementor experience slot smoke tests passed.\n";
exit( 0 );
