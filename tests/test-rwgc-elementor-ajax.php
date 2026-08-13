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
rwgc_assert( ! RWGC_Elementor_Widgets_Config::should_skip_full_stack( 'rwa-carousel', 'ReactWoo\\Atomic\\Widgets\\Carousel' ), 'keep ReactWoo Atomic' );
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
rwgc_assert( ! RWGC_Elementor_Widgets_Config::is_heavy_addon_registrar( 'ElementorPro\\Plugin' ), 'Elementor Pro is not a heavy registrar' );

exit( $fails > 0 ? 1 : 0 );
