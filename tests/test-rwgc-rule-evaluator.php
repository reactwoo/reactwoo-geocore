<?php
/**
 * CLI regression tests for {@see RWGC_Rule_Evaluator} (minimal WP stubs).
 *
 * Run: php tests/test-rwgc-rule-evaluator.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return is_string( $key ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) : '';
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $hook, $args );
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-context-snapshot.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-rule-evaluator.php';

/**
 * @param string $msg Message.
 * @return void
 */
function rwgc_test_fail( $msg ) {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
}

$snap = new RWGC_Context_Snapshot(
	array(
		'country'       => 'GB',
		'campaign'      => 'spring_sale',
		'device_type'   => 'mobile',
		'time_of_day'   => 'evening',
		'day_of_week'   => 'saturday',
		'language'      => 'en',
	)
);

$set_show = array(
	'enabled' => true,
	'mode'    => 'show',
	'match'   => 'any',
	'rules'   => array(
		array(
			'id'         => 'r1',
			'label'      => 'UK evening',
			'match'      => 'all',
			'conditions' => array(
				array(
					'type'     => 'country',
					'operator' => 'in',
					'value'    => array( 'GB', 'IE' ),
				),
				array(
					'type'     => 'time_of_day',
					'operator' => 'in',
					'value'    => array( 'evening', 'night' ),
				),
			),
		),
	),
);

if ( ! RWGC_Rule_Evaluator::matches( $set_show, $snap ) ) {
	rwgc_test_fail( 'Expected UK + evening to match.' );
}

$set_hide = $set_show;
$set_hide['mode'] = 'hide';
if ( RWGC_Rule_Evaluator::should_render_content( $set_hide, $snap ) ) {
	rwgc_test_fail( 'Hide mode should suppress when rule matches.' );
}

$set_top_all = array(
	'enabled' => true,
	'mode'    => 'show',
	'match'   => 'all',
	'rules'   => array(
		array(
			'id'         => 'a',
			'match'      => 'all',
			'conditions' => array(
				array(
					'type'     => 'country',
					'operator' => 'in',
					'value'    => array( 'GB' ),
				),
			),
		),
		array(
			'id'         => 'b',
			'match'      => 'all',
			'conditions' => array(
				array(
					'type'     => 'country',
					'operator' => 'in',
					'value'    => array( 'FR' ),
				),
			),
		),
	),
);
if ( RWGC_Rule_Evaluator::matches( $set_top_all, $snap ) ) {
	rwgc_test_fail( 'Top-level all should fail when second rule fails.' );
}

$empty_country = array(
	'enabled' => true,
	'mode'    => 'show',
	'match'   => 'all',
	'rules'   => array(
		array(
			'id'         => 'c',
			'match'      => 'all',
			'conditions' => array(
				array(
					'type'     => 'country',
					'operator' => 'in',
					'value'    => array(),
				),
			),
		),
	),
);
if ( ! RWGC_Rule_Evaluator::matches( $empty_country, $snap ) ) {
	rwgc_test_fail( 'Empty country list should match all.' );
}

fwrite( STDOUT, "OK: RWGC_Rule_Evaluator CLI tests passed.\n" );
exit( 0 );
