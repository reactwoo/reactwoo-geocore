<?php
/**
 * Standalone contract smoke tests (no PHPUnit binary required).
 *
 * Usage: php tests/test-rwgc-contracts.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok    Assertion.
 * @return void
 */
function rwgc_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

rwgc_assert( 'schema version', 1 === RWGC_Schema::VERSION );
rwgc_assert( 'alias country', 'geo.country' === RWGC_Schema::normalize_capability_id( 'country' ) );
rwgc_assert( 'reject single segment', '' === RWGC_Schema::normalize_capability_id( 'single' ) );

try {
	RWGC_Contract_Condition::from_array( array( 'capability' => '!!!', 'operator' => 'equals', 'value' => 1 ) );
	rwgc_assert( 'invalid condition throws', false );
} catch ( RWGC_Contract_Exception $e ) {
	rwgc_assert( 'invalid condition throws', true );
}

$manifest = RWGC_Contract_Manifest::from_array(
	array(
		'schema'      => '1.0',
		'revision'    => 142,
		'site'        => 'site_123',
		'future_flag' => array( 'enabled' => true ),
		'audiences'   => array(
			array(
				'id'         => 'aud_1',
				'name'       => 'UK',
				'conditions' => array(
					'all' => array(
						array(
							'capability' => 'country',
							'operator'   => 'equals',
							'value'      => 'GB',
						),
					),
				),
			),
		),
		'experiences' => array(),
		'variants'    => array(),
		'experiments' => array(),
		'goals'       => array(
			array(
				'id'   => 'goal_purchase',
				'type' => 'commerce.purchase',
			),
		),
		'slots'       => array(),
	)
);

rwgc_assert( 'manifest revision', 142 === $manifest->revision() );
rwgc_assert( 'manifest future extras', ! empty( $manifest->extras()['future_flag']['enabled'] ) );
rwgc_assert( 'audience alias capability', 'geo.country' === $manifest->audiences()[0]->conditions()->items()[0]->capability() );

$round = RWGC_Contract_Manifest::from_json( $manifest->to_json() );
rwgc_assert( 'json round-trip revision', 142 === $round->revision() );
rwgc_assert( 'json round-trip extras', ! empty( $round->extras()['future_flag']['enabled'] ) );

try {
	RWGC_Contract_Manifest::from_array(
		array(
			'schema'   => '2.0',
			'revision' => 1,
			'site'     => 'site_1',
		)
	);
	rwgc_assert( 'schema 2 rejected', false );
} catch ( RWGC_Contract_Exception $e ) {
	rwgc_assert( 'schema 2 rejected', true );
}

$ctx = RWGC_Contract_Context::from_array( array( 'device' => 'mobile', '_x' => 1 ) );
rwgc_assert( 'context alias get', 'mobile' === $ctx->get( 'visitor.device' ) );
rwgc_assert( 'context unknown key extras', 1 === $ctx->extras()['_x'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}

echo "\nAll contract smoke tests passed.\n";
exit( 0 );
