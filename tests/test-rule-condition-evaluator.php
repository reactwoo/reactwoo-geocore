<?php
/**
 * CLI regression tests for RWGC_Rule_Condition_Evaluator (no WordPress test suite required).
 *
 * Run from plugin root: php tests/test-rule-condition-evaluator.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str String.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_string( $str ) ? $str : '';
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

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return is_string( $key ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) : '';
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( 'rwgc_expand_country_groups' === $hook ) {
			$group_ids = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : array();
			return RWGC_Country_Groups::expand_groups_to_countries( $group_ids );
		}
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( 'rwgc_country_groups' === $key ) {
			return array(
				'test_eu' => array(
					'label'     => 'Test EU',
					'countries' => array( 'DE', 'FR' ),
				),
			);
		}
		return $default;
	}
}

require_once dirname( __DIR__ ) . '/includes/engine/class-rwgc-context.php';
require_once dirname( __DIR__ ) . '/includes/engine/class-rwgc-country-groups.php';
require_once dirname( __DIR__ ) . '/includes/rules/class-rwgc-rule-condition-evaluator.php';

$failed = 0;
$passed = 0;

/**
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function rwgc_test_assert( $cond, $msg ) {
	global $failed, $passed;
	if ( $cond ) {
		++$passed;
		echo "OK: {$msg}\n";
	} else {
		++$failed;
		echo "FAIL: {$msg}\n";
	}
}

$us = new RWGC_Context( 'US', array() );
$de = new RWGC_Context( 'DE', array() );

rwgc_test_assert(
	RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'countries_include' => array( 'US' ) ),
		$us
	),
	'include US matches US'
);

rwgc_test_assert(
	! RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'countries_include' => array( 'US' ) ),
		$de
	),
	'include US does not match DE'
);

rwgc_test_assert(
	! RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'countries_include' => array( 'US' ), 'countries_exclude' => array( 'US' ) ),
		$us
	),
	'exclude blocks include'
);

rwgc_test_assert(
	! RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'countries_include' => array( 'FR' ), 'countries_exclude' => array( 'DE' ) ),
		$de
	),
	'DE visitor not in FR include'
);

$fr = new RWGC_Context( 'FR', array() );
rwgc_test_assert(
	RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'countries_include' => array( 'FR' ), 'countries_exclude' => array( 'DE' ) ),
		$fr
	),
	'FR matches with DE in exclude list'
);

$empty = new RWGC_Context( '', array() );
rwgc_test_assert(
	! RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'countries_include' => array( 'US' ) ),
		$empty
	),
	'empty country never matches'
);

rwgc_test_assert(
	RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'country_groups_include' => array( 'test_eu' ) ),
		$de
	),
	'country_groups_include resolves DE from registry'
);

rwgc_test_assert(
	! RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array(
			'country_groups_include' => array( 'test_eu' ),
			'country_groups_exclude' => array( 'test_eu' ),
		),
		$de
	),
	'country_groups_exclude blocks DE when same group used both ways'
);

rwgc_test_assert(
	RWGC_Rule_Condition_Evaluator::context_matches_conditions(
		array( 'country_groups' => array( 'test_eu' ) ),
		$de
	),
	'legacy country_groups expands via rwgc_expand_country_groups'
);

echo "\nPassed: {$passed}, Failed: {$failed}\n";
exit( $failed > 0 ? 1 : 0 );
