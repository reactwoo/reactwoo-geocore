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

$_REQUEST['actions'] = '{"refresh_widgets_config":{"action":"refresh_widgets_config","data":{}}}';
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Ajax::is_heavy_elementor_ajax(), 'refresh_widgets_config → heavy' );

$_REQUEST['actions'] = '{"x":{"action":"unknown_editor_boot","data":{}}}';
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Ajax::is_heavy_elementor_ajax(), 'unknown elementor_ajax → heavy' );

$_REQUEST['actions'] = '{"f":{"action":"enqueue_google_fonts","data":{}}}';
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( ! RWGC_Elementor_Ajax::is_heavy_elementor_ajax(), 'enqueue_google_fonts → light' );

require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-wp-abilities-adapter.php';
$_REQUEST = array( 'action' => 'elementor_ajax' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_WP_Abilities_Adapter::should_skip_registration(), 'skip abilities on elementor_ajax' );

$_REQUEST = array( 'action' => 'heartbeat' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( ! RWGC_WP_Abilities_Adapter::should_skip_registration(), 'do not skip abilities on other ajax' );

require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-widgets-config.php';
rwgc_assert( ! RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'heading', 'Elementor\\Widget_Heading' ), 'keep Elementor core heading' );
rwgc_assert( ! RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'form', 'ElementorPro\\Modules\\Forms\\Widgets\\Form' ), 'keep Elementor Pro form' );
rwgc_assert( RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'rwa-carousel', 'ReactWoo\\Atomic\\Widgets\\Carousel' ), 'skip Atomic stack on bulk path' );
rwgc_assert( RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'whmcs_products', 'RW_Elementor_WHM_Products_Widget' ), 'skip WHMCS stack on bulk path' );
rwgc_assert( RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'woocommerce-products', 'ElementorPro\\Modules\\Woocommerce\\Widgets\\Products' ), 'skip Pro Woo stack on bulk path' );
rwgc_assert( RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'ucaddon_slider', 'UniteCreatorElementorWidget' ), 'skip Unlimited Elements' );
rwgc_assert( RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'eael-info-box', 'Essential_Addons_Elementor\\Classes\\Helper' ), 'skip Essential Addons' );

$slim = RWGC_Elementor_Widgets_Config::slim_controls(
	array(
		'country' => array(
			'type'    => 'select',
			'options' => array_fill_keys( range( 1, 80 ), 'x' ),
		),
		'tiny'    => array(
			'type'    => 'select',
			'options' => array( 'a' => 'A', 'b' => 'B' ),
		),
	)
);
rwgc_assert( RWGC_Elementor_Widgets_Config::MAX_SELECT_OPTIONS === count( $slim['country']['options'] ), 'slim caps large option maps' );
rwgc_assert( 2 === count( $slim['tiny']['options'] ), 'slim leaves small option maps' );
rwgc_assert( RWGC_Elementor_Widgets_Config::is_heavy_addon_registrar( 'UniteCreatorElementorIntegrate' ), 'UE integrate is heavy registrar' );
rwgc_assert( RWGC_Elementor_Widgets_Config::is_heavy_addon_registrar( 'Essential_Addons_Elementor\\Classes\\Bootstrap' ), 'EA registrar is heavy' );
rwgc_assert( RWGC_Elementor_Widgets_Config::is_heavy_addon_registrar( 'ACPT_Elementor' ), 'ACPT Elementor registrar is heavy' );
rwgc_assert( RWGC_Elementor_Widgets_Config::is_heavy_addon_registrar( 'RW_WHMCS_Bridge' ), 'WHMCS registrar is heavy' );
rwgc_assert( ! RWGC_Elementor_Widgets_Config::is_heavy_addon_registrar( 'ElementorPro\\Plugin' ), 'Elementor Pro is not a heavy registrar' );

require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-config-debug.php';
$_REQUEST = array( 'action' => 'heartbeat' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( ! RWGC_Elementor_Config_Debug::is_elementor_ajax_request(), 'heartbeat is not elementor_ajax' );
rwgc_assert( ! RWGC_Elementor_Config_Debug::enabled(), 'debug disabled off elementor_ajax' );
$_REQUEST = array( 'action' => 'elementor_ajax', 'actions' => '{"f":{"action":"enqueue_google_fonts"}}' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Config_Debug::is_elementor_ajax_request(), 'fonts are elementor_ajax' );
rwgc_assert( ! RWGC_Elementor_Config_Debug::should_trace(), 'fonts are not traced' );
$_REQUEST = array( 'action' => 'elementor_ajax', 'actions' => '{"get_widgets_config":{"action":"get_widgets_config"}}' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Config_Debug::is_elementor_ajax_request(), 'elementor_ajax is traced' );
rwgc_assert( RWGC_Elementor_Config_Debug::should_trace(), 'widgets-config is traced' );
$cut_stats = array( 'loop_start' => microtime( true ) - 3 );
rwgc_assert( RWGC_Elementor_Widgets_Config::should_cut_stacks( $cut_stats ), 'stack budget cuts after 400ms' );
RWGC_Elementor_Config_Debug::set_summary( 'started_at', microtime( true ) - 10 );
rwgc_assert( RWGC_Elementor_Widgets_Config::should_skip_all_stacks(), 'late boot skips all get_stack' );
rwgc_assert( RWGC_Elementor_Config_Debug::is_our_entry( 'RW_Elementor_WHM_Products_Widget' ), 'WHMCS widget is our entry' );
rwgc_assert( RWGC_Elementor_Config_Debug::is_our_entry( 'ReactWoo\\Atomic\\Widgets\\Carousel' ), 'Atomic widget is our entry' );
rwgc_assert( ! RWGC_Elementor_Config_Debug::is_our_entry( 'Elementor\\Widget_Heading' ), 'core heading is not our entry' );
RWGC_Elementor_Config_Debug::set_summary( 'kept', 3 );
RWGC_Elementor_Config_Debug::checkpoint( 'test_cp', array( 'ok' => 1 ) );
rwgc_assert( true, 'debug checkpoint does not fatal' );

exit( $fails > 0 ? 1 : 0 );
