<?php
/**
 * CLI regression tests for builder targeting integration edge cases.
 *
 * Run: php tests/test-targeting-integration-regressions.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $domain );
		return $text;
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
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $hook, $args );
		return $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		unset( $special_chars, $extra_special_chars );
		return str_repeat( 'a', (int) $length );
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	function rwgc_get_visitor_country() {
		return isset( $GLOBALS['rwgc_test_country'] ) ? $GLOBALS['rwgc_test_country'] : '';
	}
}

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		public $ID;
		public $post_type;
		public $post_title;
		public $post_status;

		public function __construct( $id, $post_type, $title = '', $status = 'publish' ) {
			$this->ID          = (int) $id;
			$this->post_type   = (string) $post_type;
			$this->post_title  = (string) $title;
			$this->post_status = (string) $status;
		}
	}
}

$GLOBALS['rwgc_test_posts']        = array();
$GLOBALS['rwgc_test_post_meta']    = array();
$GLOBALS['rwgc_test_update_calls'] = array();

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		$post_id = (int) $post_id;
		return isset( $GLOBALS['rwgc_test_posts'][ $post_id ] ) ? $GLOBALS['rwgc_test_posts'][ $post_id ] : null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $single );
		$post_id = (int) $post_id;
		return isset( $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] : '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['rwgc_test_post_meta'][ (int) $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $data, $wp_error = false ) {
		unset( $wp_error );
		$GLOBALS['rwgc_test_update_calls'][] = $data;
		return isset( $data['ID'] ) ? (int) $data['ID'] : 0;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $data, $wp_error = false ) {
		unset( $wp_error );
		$id = 500 + count( $GLOBALS['rwgc_test_posts'] );
		$GLOBALS['rwgc_test_posts'][ $id ] = new WP_Post( $id, $data['post_type'], $data['post_title'], $data['post_status'] );
		return $id;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return false;
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists( $post_type ) {
		unset( $post_type );
		return false;
	}
}

if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
	class RWGC_Context_Resolver {
		public static function resolve_current() {
			return new RWGC_Context_Snapshot( array( 'country' => isset( $GLOBALS['rwgc_test_country'] ) ? $GLOBALS['rwgc_test_country'] : '' ) );
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-context-snapshot.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-rule-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-cpt.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-repository.php';
require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-popups.php';
require_once dirname( __DIR__ ) . '/includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php';

$failed = 0;
$passed = 0;

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

function rwgc_private_static( $class, $method, array $args = array() ) {
	$ref = new ReflectionMethod( $class, $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( null, $args );
}

$GLOBALS['rwgc_test_country'] = 'US';

rwgc_test_assert(
	RWGC_Elementor_Popups::class && rwgc_private_static( 'RWGC_Elementor_Popups', 'visitor_matches_countries', array( array( 'US' ), 'show' ) ),
	'popup show mode allows selected visitor country'
);
rwgc_test_assert(
	! rwgc_private_static( 'RWGC_Elementor_Popups', 'visitor_matches_countries', array( array( 'US' ), 'hide' ) ),
	'popup hide mode blocks selected visitor country'
);
rwgc_test_assert(
	rwgc_private_static( 'RWGC_Elementor_Popups', 'visitor_matches_countries', array( array( 'CA' ), 'hide' ) ),
	'popup hide mode allows unselected visitor country'
);
$GLOBALS['rwgc_test_country'] = '';
rwgc_test_assert(
	rwgc_private_static( 'RWGC_Elementor_Popups', 'visitor_matches_countries', array( array( 'US' ), 'hide' ) ),
	'popup hide mode allows unresolved visitor country'
);
$GLOBALS['rwgc_test_country'] = 'US';

$GLOBALS['rwgc_test_post_meta'][42]['_elementor_page_settings'] = array(
	'egp_enable_geo_targeting' => 'yes',
	'egp_countries'            => 'us, ca gb',
);
$settings = rwgc_private_static( 'RWGC_Elementor_Popups', 'get_popup_page_geo_settings', array( 42 ) );
rwgc_test_assert(
	is_array( $settings ) && array( 'US', 'CA', 'GB' ) === $settings['countries'],
	'popup page settings parse legacy comma/space country strings'
);

$hide_rule = wp_json_encode(
	array(
		'enabled' => true,
		'mode'    => 'hide',
		'match'   => 'any',
		'rules'   => array(
			array(
				'id'         => 'r1',
				'match'      => 'all',
				'conditions' => array(
					array(
						'type'     => 'country',
						'operator' => 'in',
						'value'    => array( 'US' ),
					),
				),
			),
		),
	)
);

$GLOBALS['rwgc_test_post_meta'][43]['_elementor_page_settings'] = array(
	'egp_enable_geo_targeting'        => 'yes',
	'rwgc_use_portable_geo_targeting' => 'yes',
	'rwgc_portable_geo_targeting'     => $hide_rule,
);
$portable_settings = rwgc_private_static( 'RWGC_Elementor_Popups', 'get_popup_page_geo_settings', array( 43 ) );
rwgc_test_assert(
	is_array( $portable_settings ) && false === $portable_settings['portable_decision'],
	'popup page settings evaluate portable hide rules before country fallback'
);

rwgc_test_assert(
	false === rwgc_private_static(
		'RWGC_Gutenberg_Post_Geo',
		'portable_settings_should_render',
		array(
			array(
				'egp_use_portable_geo_targeting' => 'yes',
				'egp_portable_geo_targeting'     => $hide_rule,
			),
		)
	),
	'Gutenberg portable hide rules suppress matching visitor content'
);

$GLOBALS['rwgc_test_posts'][10] = new WP_Post( 10, RWGC_Visibility_Rule_CPT::POST_TYPE, 'Existing' );
$GLOBALS['rwgc_test_posts'][99] = new WP_Post( 99, 'page', 'Unrelated' );
$valid_rule = wp_json_encode(
	array(
		'enabled' => true,
		'mode'    => 'show',
		'match'   => 'any',
		'rules'   => array(
			array(
				'id'         => 'r2',
				'match'      => 'all',
				'conditions' => array(
					array(
						'type'     => 'country',
						'operator' => 'in',
						'value'    => array( 'CA' ),
					),
				),
			),
		),
	)
);

$saved_id = RWGC_Visibility_Rule_Repository::save( 'Valid', 'publish', $valid_rule, 10 );
rwgc_test_assert( 10 === $saved_id, 'valid visibility-rule update succeeds' );
rwgc_test_assert(
	'' !== (string) get_post_meta( 10, RWGC_Visibility_Rule_CPT::META_PORTABLE, true ),
	'valid visibility-rule update stores sanitized portable JSON'
);

$GLOBALS['rwgc_test_update_calls'] = array();
$invalid_id = RWGC_Visibility_Rule_Repository::save( 'Invalid', 'publish', '{"rules":', 10 );
rwgc_test_assert( 0 === $invalid_id, 'invalid non-empty visibility-rule JSON is rejected' );
rwgc_test_assert( array() === $GLOBALS['rwgc_test_update_calls'], 'invalid visibility-rule JSON does not update the post' );

$foreign_id = RWGC_Visibility_Rule_Repository::save( 'Foreign', 'publish', $valid_rule, 99 );
rwgc_test_assert( 0 === $foreign_id, 'visibility-rule updates reject non-library post IDs' );

echo "\nPassed: {$passed}, Failed: {$failed}\n";
exit( $failed > 0 ? 1 : 0 );
