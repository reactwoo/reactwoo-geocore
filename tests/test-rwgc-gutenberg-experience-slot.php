<?php
/**
 * Gutenberg Experience Slot sync smoke tests.
 *
 * Usage: php tests/test-rwgc-gutenberg-experience-slot.php
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
	 * @return true
	 */
	function add_filter() {
		return true;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * @param string $s Path.
	 * @return string
	 */
	function trailingslashit( $s ) {
		return rtrim( (string) $s, '/\\' ) . '/';
	}
}

if ( ! defined( 'RWGC_PATH' ) ) {
	define( 'RWGC_PATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();
require_once dirname( __DIR__ ) . '/includes/slots/class-rwgc-experience-slots.php';
RWGC_Experience_Slots::load();
require_once dirname( __DIR__ ) . '/includes/integrations/gutenberg/class-rwgc-gutenberg-experience-slot.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_gb_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Experience_Slot_Registry::reset_cache();
$seen = array();

$first = RWGC_Gutenberg_Experience_Slot::sync_attributes(
	array(
		'slotName'        => 'Homepage Hero',
		'managementMode'  => 'local',
	),
	'/',
	$seen
);
rwgc_gb_assert( 'creates instance + slot id', '' !== $first['attrs']['instanceId'] && RWGC_Experience_Slot_Id::is_valid( $first['attrs']['slotId'] ) );
rwgc_gb_assert( 'first sync changed', true === $first['changed'] );

$seen_again = array();
$again      = RWGC_Gutenberg_Experience_Slot::sync_attributes(
	$first['attrs'],
	'/',
	$seen_again
);
rwgc_gb_assert( 'stable on resync', ! $again['regenerated'] && $again['attrs']['slotId'] === $first['attrs']['slotId'] );

// Duplicate block in same document: same attrs including instanceId → new instance + new slot.
$seen_doc    = array();
$first_doc   = RWGC_Gutenberg_Experience_Slot::sync_attributes( $first['attrs'], '/', $seen_doc );
$clone_attrs = $first_doc['attrs'];
$clone       = RWGC_Gutenberg_Experience_Slot::sync_attributes( $clone_attrs, '/', $seen_doc );
rwgc_gb_assert( 'duplicate instance regenerates', $clone['regenerated'] && $clone['attrs']['slotId'] !== $first['attrs']['slotId'] );
rwgc_gb_assert( 'duplicate gets new instanceId', $clone['attrs']['instanceId'] !== $first['attrs']['instanceId'] );

// render_block filter Gate B.
$html = RWGC_Gutenberg_Experience_Slot::filter_render_block(
	'<div class="reactwoo-experience-slot"><p>NATIVE</p></div>',
	array(
		'blockName' => 'reactwoo/experience-slot',
		'attrs'     => array( 'slotId' => $first['attrs']['slotId'] ),
	)
);
rwgc_gb_assert( 'render keeps default content', false !== strpos( $html, 'NATIVE' ) );

$missing = RWGC_Gutenberg_Experience_Slot::filter_render_block(
	'<p>SAFE</p>',
	array(
		'blockName' => 'reactwoo/experience-slot',
		'attrs'     => array( 'slotId' => 'rw_missing_zzzzz' ),
	)
);
rwgc_gb_assert( 'missing slot keeps markup', '<p>SAFE</p>' === $missing );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Gutenberg experience slot smoke tests passed.\n";
exit( 0 );
