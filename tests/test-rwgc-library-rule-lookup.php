<?php
/**
 * Library rule lookup must not recurse on cache miss; unpublished rules must not target visitors.
 *
 * Usage: php tests/test-rwgc-library-rule-lookup.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGC_VERSION', '0.0-test' );

$GLOBALS['rwgc_test_posts']     = array();
$GLOBALS['rwgc_test_post_meta'] = array();
$GLOBALS['rwgc_meta_calls']     = 0;

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_status = '';
		/** @var string */
		public $post_title = '';
		/** @var string */
		public $post_type = '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return (int) abs( (float) $maybeint );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		unset( $special, $extra );
		return substr( md5( 'rwgc' ), 0, (int) $length );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists() {
		return false;
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts() {
		return array();
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		$id = (int) $id;
		return isset( $GLOBALS['rwgc_test_posts'][ $id ] ) ? $GLOBALS['rwgc_test_posts'][ $id ] : null;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) {
		++$GLOBALS['rwgc_meta_calls'];
		if ( $GLOBALS['rwgc_meta_calls'] > 30 ) {
			throw new RuntimeException( 'get_post_meta recursion guard' );
		}
		$id  = (int) $id;
		$key = (string) $key;
		if ( ! isset( $GLOBALS['rwgc_test_post_meta'][ $id ][ $key ] ) ) {
			return $single ? '' : array();
		}
		return $GLOBALS['rwgc_test_post_meta'][ $id ][ $key ];
	}
}

require_once dirname( __DIR__ ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-cpt.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-repository.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-rule-registry.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-variant-rule-applications.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_lookup_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

/**
 * @param int    $id ID.
 * @param string $status Status.
 * @param string $source Source type meta.
 * @return void
 */
function rwgc_lookup_seed_rule( $id, $status, $source = '' ) {
	$post              = new WP_Post();
	$post->ID          = $id;
	$post->post_status = $status;
	$post->post_type   = RWGC_Visibility_Rule_CPT::POST_TYPE;
	$post->post_title  = 'Rule ' . $id;
	$GLOBALS['rwgc_test_posts'][ $id ] = $post;
	$GLOBALS['rwgc_test_post_meta'][ $id ] = array(
		RWGC_Visibility_Rule_CPT::META_PORTABLE => wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show_if',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'r1',
						'label'      => 'UK',
						'match'      => 'any',
						'conditions' => array(
							array(
								'type'     => 'country',
								'operator' => 'in',
								'value'    => array( 'GB' ),
							),
						),
					),
				),
			)
		),
		RWGC_Variant_Rule_Applications::META_SOURCE_TYPE => $source,
		RWGC_Variant_Rule_Applications::META_LIFECYCLE   => '',
	);
}

rwgc_lookup_seed_rule( 501, 'publish' );
$GLOBALS['rwgc_meta_calls'] = 0;
$set = RWGC_Rule_Registry::get_rule_set_by_id( 501 );
rwgc_lookup_assert( 'cache-miss lookup returns sanitized set', is_array( $set ) && ! empty( $set['rules'] ) );
rwgc_lookup_assert( 'cache-miss lookup does not recurse', $GLOBALS['rwgc_meta_calls'] <= 10 );

$from_repo = RWGC_Visibility_Rule_Repository::get_rule_set( 501 );
rwgc_lookup_assert( 'repository reads meta without registry', is_array( $from_repo ) && ! empty( $from_repo['rules'] ) );

rwgc_lookup_assert( 'published library rule is active', RWGC_Variant_Rule_Applications::is_rule_active_for_frontend( 501 ) );

rwgc_lookup_seed_rule( 502, 'draft' );
rwgc_lookup_assert( 'draft library rule is not active', ! RWGC_Variant_Rule_Applications::is_rule_active_for_frontend( 502 ) );

rwgc_lookup_seed_rule( 503, 'trash' );
rwgc_lookup_assert( 'trashed library rule is not active', ! RWGC_Variant_Rule_Applications::is_rule_active_for_frontend( 503 ) );

rwgc_lookup_assert( 'deleted library rule is not active', ! RWGC_Variant_Rule_Applications::is_rule_active_for_frontend( 9999 ) );

$show_if_settings = array(
	'rwgc_enable_visibility_rules'  => 'yes',
	'rwgc_visibility_rule_library'  => '502',
	'rwgc_visibility_rules_mode'    => 'show_if',
);
$draft_eval = RWGC_Targeting_Surface_Evaluator::evaluate( $show_if_settings );
rwgc_lookup_assert( 'draft library show_if still renders', ! empty( $draft_eval['should_render'] ) );
rwgc_lookup_assert( 'draft library skip reason', 'library_rule_inactive' === ( $draft_eval['reason'] ?? '' ) );

$trash_eval = RWGC_Targeting_Surface_Evaluator::evaluate(
	array(
		'rwgc_enable_visibility_rules' => 'yes',
		'rwgc_visibility_rule_library' => '503',
		'rwgc_visibility_rules_mode'   => 'hide_if',
	)
);
rwgc_lookup_assert( 'trashed library hide_if does not hide everyone', ! empty( $trash_eval['should_render'] ) );

rwgc_lookup_seed_rule( 504, 'publish', 'page_variant' );
$GLOBALS['rwgc_test_post_meta'][504][ RWGC_Variant_Rule_Applications::META_LIFECYCLE ] = 'archived';
$variant_eval = RWGC_Targeting_Surface_Evaluator::evaluate(
	array(
		'rwgc_enable_visibility_rules' => 'yes',
		'rwgc_visibility_rule_library' => '504',
		'rwgc_visibility_rules_mode'   => 'show_if',
	)
);
rwgc_lookup_assert( 'archived variant rule still fail-closes', empty( $variant_eval['should_render'] ) );
rwgc_lookup_assert( 'archived variant reason', 'variant_rule_inactive' === ( $variant_eval['reason'] ?? '' ) );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll library-rule lookup tests passed.\n";
exit( 0 );
