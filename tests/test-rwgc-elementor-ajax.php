<?php
/**
 * Smoke: RWGC_Elementor_Ajax bulk vs single-widget detection.
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', __DIR__ );

require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-ajax.php';

$fails = 0;

function rwgc_assert( $cond, $msg ) {
	global $fails;
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		++$fails;
		return;
	}
	fwrite( STDOUT, "OK: {$msg}\n" );
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

define( 'DOING_AJAX', true );

$_REQUEST = array( 'action' => 'elementor_ajax' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Ajax::is_elementor_ajax(), 'detects elementor_ajax' );
rwgc_assert( RWGC_Elementor_Ajax::is_heavy_elementor_ajax(), 'opaque actions → heavy' );

$_REQUEST['actions'] = '{"get_widgets_config":{"action":"get_widgets_config","data":{}}}';
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Ajax::is_heavy_elementor_ajax(), 'get_widgets_config → heavy' );

$_REQUEST['actions'] = '{"editor_get_widget_config":{"action":"editor_get_widget_config","data":{}}}';
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( ! RWGC_Elementor_Ajax::is_heavy_elementor_ajax(), 'editor_get_widget_config alone → light' );

$_REQUEST['actions'] = '{"a":{"action":"get_document_config"},"b":{"action":"editor_get_widget_config"}}';
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Ajax::is_heavy_elementor_ajax(), 'mixed with document config → heavy' );

require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-wp-abilities-adapter.php';
$_REQUEST = array( 'action' => 'elementor_ajax' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_WP_Abilities_Adapter::should_skip_registration(), 'skip abilities on elementor_ajax' );

$_REQUEST = array( 'action' => 'heartbeat' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( ! RWGC_WP_Abilities_Adapter::should_skip_registration(), 'do not skip abilities on other ajax' );

exit( $fails > 0 ? 1 : 0 );
