<?php
/**
 * Decision Runtime smoke tests.
 *
 * Usage: php tests/test-rwgc-decision-runtime.php
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

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/class-rwgc-contracts.php';
RWGC_Contracts::load();
require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-platform-capability-registry.php';
require_once dirname( __DIR__ ) . '/includes/platform/functions-reactwoo-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/decision/class-rwgc-decision.php';
RWGC_Decision::load();

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_dec_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

RWGC_Platform_Capability_Registry::reset();
reactwoo_register_condition( 'geo.country', array( 'label' => 'Country', 'provider' => 'reactwoo-geocore' ) );
reactwoo_register_condition( 'visitor.device', array( 'label' => 'Device', 'provider' => 'reactwoo-geocore' ) );

/**
 * @param array<string, mixed> $overrides Overrides.
 * @return RWGC_Contract_Manifest
 */
function rwgc_test_manifest( array $overrides = array() ) {
	$base = array(
		'schema'      => '1.0',
		'revision'    => 1,
		'site'        => 'site_test',
		'audiences'   => array(
			array(
				'id'         => 'aud_uk',
				'name'       => 'UK',
				'conditions' => array(
					'all' => array(
						array(
							'capability' => 'geo.country',
							'operator'   => 'equals',
							'value'      => 'GB',
						),
					),
				),
			),
			array(
				'id'         => 'aud_uk_mobile',
				'name'       => 'UK Mobile',
				'conditions' => array(
					'all' => array(
						array(
							'capability' => 'geo.country',
							'operator'   => 'equals',
							'value'      => 'GB',
						),
						array(
							'capability' => 'visitor.device',
							'operator'   => 'equals',
							'value'      => 'mobile',
						),
					),
				),
			),
		),
		'experiences' => array(
			array(
				'id'          => 'exp_uk',
				'name'        => 'UK Hero',
				'audience_id' => 'aud_uk',
				'slot_id'     => 'slot_home',
				'variant_id'  => 'var_uk',
				'status'      => 'active',
				'priority'    => 40,
			),
		),
		'variants'    => array(
			array(
				'id'   => 'var_uk',
				'type' => 'content',
			),
			array(
				'id'   => 'var_b',
				'type' => 'content',
			),
		),
		'experiments' => array(),
		'goals'       => array(),
		'slots'       => array(),
	);
	return RWGC_Contract_Manifest::from_array( array_merge( $base, $overrides ) );
}

// One condition match.
$ctx_gb = RWGC_Contract_Context::from_array( array( 'geo.country' => 'GB', 'visitor.device' => 'desktop' ) );
$r1     = RWGC_Decision_Runtime::evaluate( rwgc_test_manifest(), $ctx_gb, array( 'debug' => true ) );
rwgc_dec_assert( 'one condition match audience', in_array( 'aud_uk', $r1->matched_audiences(), true ) );
rwgc_dec_assert( 'one condition selects experience', 'exp_uk' === $r1->selected_experiences()[0]['id'] );
rwgc_dec_assert( 'no remote calls', 0 === (int) $r1->debug()['remote_calls'] );

// No match.
$ctx_us = RWGC_Contract_Context::from_array( array( 'geo.country' => 'US' ) );
$r2     = RWGC_Decision_Runtime::evaluate( rwgc_test_manifest(), $ctx_us );
rwgc_dec_assert( 'no match audiences empty', array() === $r2->matched_audiences() );
rwgc_dec_assert( 'no match no experiences', array() === $r2->selected_experiences() );

// AND / OR / nested.
$and_group = RWGC_Contract_Condition_Group::from_array(
	array(
		'all' => array(
			array( 'capability' => 'geo.country', 'operator' => 'equals', 'value' => 'GB' ),
			array( 'capability' => 'visitor.device', 'operator' => 'equals', 'value' => 'mobile' ),
		),
	)
);
$trace = array();
rwgc_dec_assert( 'AND fails when device wrong', false === RWGC_Decision_Condition_Evaluator::matches_group( $and_group, $ctx_gb, $trace ) );
rwgc_dec_assert(
	'AND passes',
	true === RWGC_Decision_Condition_Evaluator::matches_group(
		$and_group,
		RWGC_Contract_Context::from_array( array( 'geo.country' => 'GB', 'visitor.device' => 'mobile' ) ),
		$trace
	)
);

$or_group = RWGC_Contract_Condition_Group::from_array(
	array(
		'any' => array(
			array( 'capability' => 'geo.country', 'operator' => 'equals', 'value' => 'GB' ),
			array( 'capability' => 'geo.country', 'operator' => 'equals', 'value' => 'IE' ),
		),
	)
);
rwgc_dec_assert( 'OR passes', true === RWGC_Decision_Condition_Evaluator::matches_group( $or_group, $ctx_gb, $trace ) );

$nested = RWGC_Contract_Condition_Group::from_array(
	array(
		'any' => array(
			array(
				'all' => array(
					array( 'capability' => 'geo.country', 'operator' => 'equals', 'value' => 'US' ),
				),
			),
			array( 'capability' => 'visitor.device', 'operator' => 'equals', 'value' => 'desktop' ),
		),
	)
);
rwgc_dec_assert( 'nested OR/AND', true === RWGC_Decision_Condition_Evaluator::matches_group( $nested, $ctx_gb, $trace ) );

// Invalid / missing capability fails safely.
$bad = RWGC_Contract_Condition::from_array(
	array(
		'capability' => 'geo.country',
		'operator'   => 'equals',
		'value'      => 'GB',
	)
);
// Unknown capability ID via forged context evaluation path:
$unk_trace = array();
$unk       = RWGC_Contract_Condition_Group::from_array(
	array(
		'all' => array(
			array(
				'capability' => 'geo.unknown_thing',
				'operator'   => 'equals',
				'value'      => 'x',
			),
		),
	)
);
rwgc_dec_assert( 'missing provider fails closed', false === RWGC_Decision_Condition_Evaluator::matches_group( $unk, $ctx_gb, $unk_trace ) );
rwgc_dec_assert( 'missing provider traced', false !== strpos( implode( ',', $unk_trace ), 'missing_provider' ) );

// Priority + conflict.
$conflict_manifest = rwgc_test_manifest(
	array(
		'experiences' => array(
			array(
				'id'          => 'exp_low',
				'name'        => 'Low',
				'audience_id' => 'aud_uk',
				'slot_id'     => 'slot_home',
				'variant_id'  => 'var_uk',
				'status'      => 'active',
				'priority'    => 10,
			),
			array(
				'id'          => 'exp_high',
				'name'        => 'High',
				'audience_id' => 'aud_uk',
				'slot_id'     => 'slot_home',
				'variant_id'  => 'var_b',
				'status'      => 'active',
				'priority'    => 90,
			),
		),
	)
);
$r3 = RWGC_Decision_Runtime::evaluate( $conflict_manifest, $ctx_gb );
rwgc_dec_assert( 'priority wins conflict', 'exp_high' === $r3->selected_experiences()[0]['id'] );
rwgc_dec_assert( 'conflict reason recorded', false !== strpos( implode( ',', $r3->reasons() ), 'conflict_resolved' ) );

// Specificity tie-break when priority equal.
$spec_manifest = rwgc_test_manifest(
	array(
		'experiences' => array(
			array(
				'id'          => 'exp_broad',
				'name'        => 'Broad',
				'audience_id' => 'aud_uk',
				'slot_id'     => 'slot_home',
				'variant_id'  => 'var_uk',
				'status'      => 'active',
				'priority'    => 50,
			),
			array(
				'id'          => 'exp_specific',
				'name'        => 'Specific',
				'audience_id' => 'aud_uk_mobile',
				'slot_id'     => 'slot_home',
				'variant_id'  => 'var_b',
				'status'      => 'active',
				'priority'    => 50,
			),
		),
	)
);
$ctx_mobile = RWGC_Contract_Context::from_array( array( 'geo.country' => 'GB', 'visitor.device' => 'mobile' ) );
$r4         = RWGC_Decision_Runtime::evaluate( $spec_manifest, $ctx_mobile );
rwgc_dec_assert( 'specificity wins tie', 'exp_specific' === $r4->selected_experiences()[0]['id'] );

// Schedule.
$sched = rwgc_test_manifest(
	array(
		'experiences' => array(
			array(
				'id'          => 'exp_future',
				'name'        => 'Future',
				'audience_id' => 'aud_uk',
				'slot_id'     => 'slot_home',
				'variant_id'  => 'var_uk',
				'status'      => 'active',
				'priority'    => 50,
				'schedule'    => array(
					'starts' => '2099-01-01T00:00:00Z',
					'ends'   => '',
				),
			),
		),
	)
);
$r5 = RWGC_Decision_Runtime::evaluate( $sched, $ctx_gb, array( 'now' => strtotime( '2026-08-11T00:00:00Z' ) ) );
rwgc_dec_assert( 'scheduled future skipped', array() === $r5->selected_experiences() );

// Experiment assignment stable.
$exp_manifest = rwgc_test_manifest(
	array(
		'experiences' => array(
			array(
				'id'            => 'exp_ab',
				'name'          => 'AB',
				'audience_id'   => 'aud_uk',
				'slot_id'       => 'slot_home',
				'variant_id'    => 'var_uk',
				'status'        => 'active',
				'priority'      => 50,
				'experiment_id' => 'ex1',
			),
		),
		'experiments' => array(
			array(
				'id'       => 'ex1',
				'control'  => 'var_uk',
				'variants' => array(
					array(
						'id'         => 'var_b',
						'allocation' => 50,
					),
				),
			),
		),
	)
);
$a = RWGC_Decision_Runtime::evaluate( $exp_manifest, $ctx_gb, array( 'visitor_id' => 'visitor-stable-1' ) );
$b = RWGC_Decision_Runtime::evaluate( $exp_manifest, $ctx_gb, array( 'visitor_id' => 'visitor-stable-1' ) );
rwgc_dec_assert( 'experiment assignment stable', $a->variant_for_slot( 'slot_home' ) === $b->variant_for_slot( 'slot_home' ) );
rwgc_dec_assert( 'experiment assignment non-empty', '' !== $a->variant_for_slot( 'slot_home' ) );

$bucket1 = RWGC_Decision_Experiment_Assigner::bucket( 'ex1', 'visitor-stable-1' );
$bucket2 = RWGC_Decision_Experiment_Assigner::bucket( 'ex1', 'visitor-stable-1' );
rwgc_dec_assert( 'bucket deterministic', $bucket1 === $bucket2 );

// Deterministic full result for identical context.
$d1 = RWGC_Decision_Runtime::evaluate( rwgc_test_manifest(), $ctx_gb, array( 'visitor_id' => 'v1' ) );
$d2 = RWGC_Decision_Runtime::evaluate( rwgc_test_manifest(), $ctx_gb, array( 'visitor_id' => 'v1' ) );
rwgc_dec_assert( 'deterministic selected', $d1->to_array()['selected_experiences'] === $d2->to_array()['selected_experiences'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll decision runtime smoke tests passed.\n";
exit( 0 );
