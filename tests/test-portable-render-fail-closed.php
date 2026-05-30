<?php
/**
 * CLI regression tests for portable targeting render fail-closed behavior.
 *
 * Run: php tests/test-portable-render-fail-closed.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		return $content;
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	function rwgc_get_visitor_country() {
		return 'US';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return false;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular() {
		return true;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return 10;
	}
}

if ( ! function_exists( 'rwgc_is_builder_edit_request' ) ) {
	function rwgc_is_builder_edit_request( $post_id = null ) {
		unset( $post_id );
		return false;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $post_id, $key, $single );
		return array(
			'egp_enable_geo_targeting'       => 'yes',
			'rwgc_use_portable_geo_targeting' => 'yes',
			'rwgc_portable_geo_targeting'   => '{invalid json',
			'egp_countries'                 => array(),
		);
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-gutenberg.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-elementor.php';

/**
 * @param bool   $condition Assertion.
 * @param string $message Failure message.
 * @return void
 */
function rwgc_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$block = RWGC_Gutenberg::render_geo_content_block(
	array(
		'usePortableTargeting' => true,
		'portableTargeting'    => '{invalid json',
	),
	'secret block'
);
rwgc_test_assert( '' === $block, 'Gutenberg portable targeting must fail closed for non-empty invalid JSON.' );

$elementor = RWGC_Elementor::filter_document_content( 'secret document' );
rwgc_test_assert( '' === $elementor, 'Elementor portable targeting must fail closed for non-empty invalid JSON.' );

fwrite( STDOUT, "OK: Portable targeting fail-closed render tests passed.\n" );
exit( 0 );
