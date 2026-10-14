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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}

if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
	function rwgc_visibility_mode_allows_render( $mode, $matched ) {
		$m = sanitize_key( (string) $mode );
		if ( in_array( $m, array( 'hide', 'hide_if' ), true ) ) {
			return ! $matched;
		}
		return (bool) $matched;
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

RWGC_Rule_Evaluator::reset_resolver_cache();
$returning_set = array(
	'enabled' => true,
	'mode'    => 'show',
	'match'   => 'all',
	'rules'   => array(
		array(
			'id'         => 'rv',
			'match'      => 'all',
			'conditions' => array(
				array(
					'type'     => 'returning_visitor',
					'operator' => 'is',
					'value'    => array( 'yes' ),
				),
			),
		),
	),
);
$returning_snap = new RWGC_Context_Snapshot( array( 'returning_visitor' => true, 'new_visitor' => false ) );
$fresh_snap     = new RWGC_Context_Snapshot( array( 'returning_visitor' => false, 'new_visitor' => true ) );
if ( ! RWGC_Rule_Evaluator::matches( $returning_set, $returning_snap ) ) {
	rwgc_test_fail( 'Returning visitor should match when snapshot flag is true.' );
}
if ( RWGC_Rule_Evaluator::matches( $returning_set, $fresh_snap ) ) {
	rwgc_test_fail( 'Returning visitor should not match a first visit.' );
}

$new_set = array(
	'enabled' => true,
	'mode'    => 'show',
	'match'   => 'all',
	'rules'   => array(
		array(
			'id'         => 'nv',
			'match'      => 'all',
			'conditions' => array(
				array(
					'type'     => 'new_visitor',
					'operator' => 'is',
					'value'    => true,
				),
			),
		),
	),
);
if ( ! RWGC_Rule_Evaluator::matches( $new_set, $fresh_snap ) ) {
	rwgc_test_fail( 'New visitor should match a first visit.' );
}
if ( RWGC_Rule_Evaluator::matches( $new_set, $returning_snap ) ) {
	rwgc_test_fail( 'New visitor should not match a returning snapshot.' );
}

$sanitized = RWGC_Targeting_Rule_Set_Schema::sanitize( $returning_set );
if ( ! is_array( $sanitized ) || 'returning_visitor' !== $sanitized['rules'][0]['conditions'][0]['type'] ) {
	rwgc_test_fail( 'Sanitize should keep returning_visitor when Pro is off.' );
}

if ( class_exists( 'RWGC_Page_Version', false ) ) {
	rwgc_test_fail( 'Page version class must stay out of Geo Core.' );
}

$page_version_set = array(
	'enabled' => true,
	'mode'    => 'show',
	'match'   => 'all',
	'rules'   => array(
		array(
			'id'         => 'pv',
			'match'      => 'all',
			'conditions' => array(
				array(
					'type'     => 'page_version_url',
					'operator' => 'equals',
					'value'    => array(
						'page_id' => 42,
						'version' => 'fr',
					),
				),
			),
		),
	),
);
$san_pv = RWGC_Targeting_Rule_Set_Schema::sanitize( $page_version_set );
if ( ! is_array( $san_pv ) || 'page_version_url' !== ( $san_pv['rules'][0]['conditions'][0]['type'] ?? '' ) ) {
	rwgc_test_fail( 'Sanitize should keep page_version_url when the page-version class is absent.' );
} else {
	$pv_val = $san_pv['rules'][0]['conditions'][0]['value'];
	if ( ! is_array( $pv_val ) || 42 !== (int) $pv_val['page_id'] || 'fr' !== $pv_val['version'] ) {
		rwgc_test_fail( 'Sanitize should keep the stored page id and version slug.' );
	}
}
if ( RWGC_Rule_Evaluator::matches( $page_version_set, $snap ) ) {
	rwgc_test_fail( 'page_version_url must not match while the page-version class is absent.' );
}

$bad_page_version = $page_version_set;
$bad_page_version['rules'][0]['conditions'][0]['value'] = array(
	'page_id' => 0,
	'version' => '../x',
);
if ( null !== RWGC_Targeting_Rule_Set_Schema::sanitize( $bad_page_version ) ) {
	rwgc_test_fail( 'Sanitize should drop an invalid page version condition.' );
}

fwrite( STDOUT, "OK: RWGC_Rule_Evaluator CLI tests passed.\n" );
exit( 0 );
