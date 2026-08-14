<?php
/**
 * WP20 advisory recommendations.
 *
 * Usage: php tests/test-rwgc-recommendations.php
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

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/cloud/class-rwgc-cloud-recommendations.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok Assertion.
 * @return void
 */
function rwgc_rec_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

$rec = RWGC_Contract_Recommendation::from_array(
	array(
		'id'                  => 'rec_1',
		'observation'         => 'No experiences yet.',
		'suggested_action'    => 'Create a draft geo experience.',
		'proposed_experience' => array(
			'name'   => 'Draft home',
			'status' => 'active',
		),
		'confidence'          => array(
			'score'       => 1.4,
			'explanation' => 'First draft.',
		),
		'provenance'          => array(
			'provider' => 'reactwoo.rules',
			'model'    => 'heuristic-v1',
			'action'   => 'generate',
		),
		'live'                => true,
	)
);

rwgc_rec_assert( 'proposed status default', 'proposed' === $rec->status() );
rwgc_rec_assert( 'proposed experience forced draft', 'draft' === $rec->proposed_experience()['status'] );
rwgc_rec_assert( 'confidence clamped', 1.0 === $rec->confidence()['score'] );
rwgc_rec_assert( 'never live', false === $rec->is_live() );
rwgc_rec_assert( 'to_array live false', false === $rec->to_array()['live'] );

$round = RWGC_Contract_Recommendation::from_array( $rec->to_array() );
rwgc_rec_assert( 'round-trip id', 'rec_1' === $round->id() );

try {
	RWGC_Contract_Recommendation::from_array( array( 'id' => 'rec_x', 'observation' => 'x' ) );
	rwgc_rec_assert( 'missing action throws', false );
} catch ( RWGC_Contract_Exception $e ) {
	rwgc_rec_assert( 'missing action throws', true );
}

RWGC_Cloud_Recommendations::store(
	array(
		array(
			'id'               => 'rec_cached',
			'status'           => 'proposed',
			'observation'      => 'Cached',
			'suggested_action' => 'Review',
			'live'             => true,
		),
	)
);
$cached = RWGC_Cloud_Recommendations::current();
rwgc_rec_assert( 'cache stored', 1 === count( $cached ) );
rwgc_rec_assert( 'cache live stripped', false === $cached[0]['live'] );

$approve = RWGC_Cloud_Recommendations::approve( 'rec_cached' );
rwgc_rec_assert( 'approve without connection fails closed', ! $approve['ok'] && 'not_connected' === $approve['error'] );
rwgc_rec_assert( 'approve still not live', false === $approve['live'] && false === $approve['compiled'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}

echo "\nAll recommendation tests passed.\n";
exit( 0 );
