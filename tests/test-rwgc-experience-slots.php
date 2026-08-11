<?php
/**
 * Experience Slot API smoke tests.
 *
 * Usage: php tests/test-rwgc-experience-slots.php
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

if ( ! function_exists( 'gmdate' ) ) {
	// native
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();
require_once dirname( __DIR__ ) . '/includes/slots/class-rwgc-experience-slots.php';
RWGC_Experience_Slots::load();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_slot_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Experience_Slot_Registry::reset_cache();
RWGC_Experience_Slot_Resolver::reset_diagnostics();

$result = reactwoo_register_experience_slot(
	array(
		'name'     => 'Homepage Hero',
		'adapter'  => 'elementor',
		'page'     => '/',
		'metadata' => array( 'binding_key' => 'elementor:el_abc' ),
	)
);
rwgc_slot_assert( 'register returns payload', is_array( $result ) && isset( $result['slot'] ) );
$id = $result['slot']->id();
rwgc_slot_assert( 'generated valid id', RWGC_Experience_Slot_Id::is_valid( $id ) );
rwgc_slot_assert( 'get by id', null !== reactwoo_get_experience_slot( $id ) );

// Same binding updates in place.
$again = reactwoo_register_experience_slot(
	array(
		'id'       => $id,
		'name'     => 'Homepage Hero',
		'adapter'  => 'elementor',
		'page'     => '/',
		'metadata' => array( 'binding_key' => 'elementor:el_abc' ),
	)
);
rwgc_slot_assert( 'same binding keeps id', $again['slot']->id() === $id && false === $again['regenerated'] );

// Clone: same id, different binding → regenerate.
$clone = reactwoo_register_experience_slot(
	array(
		'id'       => $id,
		'name'     => 'Homepage Hero',
		'adapter'  => 'elementor',
		'page'     => '/',
		'metadata' => array( 'binding_key' => 'elementor:el_cloned' ),
	)
);
rwgc_slot_assert( 'clone regenerates id', true === $clone['regenerated'] && $clone['slot']->id() !== $id );

// Missing slot → default content.
$html = reactwoo_render_experience_slot( 'rw_missing_zzzzz', '<p>DEFAULT</p>' );
rwgc_slot_assert( 'missing falls back to default', '<p>DEFAULT</p>' === $html );
rwgc_slot_assert( 'missing recorded', in_array( 'rw_missing_zzzzz', RWGC_Experience_Slot_Resolver::missing_ids(), true ) );

// Active slot + default variant → default content (Gate B).
$decision = new RWGC_Decision_Result(
	array(),
	array(),
	array( $id => 'default' ),
	array(),
	array(),
	array( 'remote_calls' => 0 ),
	1.0
);
$html2 = reactwoo_render_experience_slot( $id, static function () { return '<div>NATIVE</div>'; }, $decision );
rwgc_slot_assert( 'default variant keeps native', '<div>NATIVE</div>' === $html2 );

// Unavailable soft-delete.
RWGC_Experience_Slot_Registry::mark_unavailable( $id );
$html3 = reactwoo_render_experience_slot( $id, '<p>SAFE</p>' );
rwgc_slot_assert( 'unavailable falls back', '<p>SAFE</p>' === $html3 );

$diag = RWGC_Experience_Slot_Registry::diagnostics();
rwgc_slot_assert( 'diagnostics counts', $diag['total'] >= 2 && $diag['unavailable'] >= 1 );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll experience slot smoke tests passed.\n";
exit( 0 );
