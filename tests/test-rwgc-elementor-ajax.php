<?php
/**
 * Smoke: Elementor editor context detection, opt-in profiling, bounded options.
 *
 * Geo Core no longer classifies Elementor AJAX actions, replaces Elementor
 * actions, or unhooks another plugin's registrars. These tests pin that
 * contract so the workaround cannot come back unnoticed.
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', __DIR__ );

$fails = 0;

/**
 * @param bool   $cond Assertion.
 * @param string $msg  Description.
 * @return void
 */
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
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-ajax.php';
require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-profiler.php';
require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-options.php';

define( 'DOING_AJAX', true );

/* 1. Context detection is read-only and has no heavy/light classification. */

$_REQUEST = array( 'action' => 'elementor_ajax' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Ajax::is_elementor_ajax(), 'detects elementor_ajax' );

$_REQUEST = array( 'action' => 'elementor_ajax', 'actions' => '{"a":{"action":"get_widgets_config"}}' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( RWGC_Elementor_Ajax::is_elementor_ajax(), 'get_widgets_config is still just elementor_ajax' );

$_REQUEST = array( 'action' => 'heartbeat' );
RWGC_Elementor_Ajax::reset_for_tests();
rwgc_assert( ! RWGC_Elementor_Ajax::is_elementor_ajax(), 'heartbeat is not elementor_ajax' );

/* 2. The workaround API is gone and must not return. */

foreach ( array(
	'is_heavy_elementor_ajax',
	'is_bulk_widgets_config',
	'is_widget_hydrate_ajax',
	'is_constrained_elementor_ajax',
	'early_widgets_config_responses',
	'hydrate_widget_name',
) as $gone ) {
	rwgc_assert(
		! method_exists( 'RWGC_Elementor_Ajax', $gone ),
		"RWGC_Elementor_Ajax::{$gone}() stays removed"
	);
}

rwgc_assert( ! class_exists( 'RWGC_Elementor_Widgets_Config' ), 'widgets-config override stays removed' );
rwgc_assert( ! class_exists( 'RWGC_Elementor_Config_Debug' ), 'always-on config debug stays removed' );
rwgc_assert(
	! file_exists( dirname( __DIR__ ) . '/assets/js/rwgc-elementor-widget-hydrate.js' ),
	'inspector hydration script stays removed'
);
rwgc_assert(
	! file_exists( dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-widgets-config.php' ),
	'widgets-config file stays removed'
);

/* 3. Profiling is opt-in and transparent. */

RWGC_Elementor_Profiler::reset_for_tests();
rwgc_assert( ! RWGC_Elementor_Profiler::enabled(), 'profiler is off by default' );

$ran = 0;
$out = RWGC_Elementor_Profiler::measure(
	'test',
	static function () use ( &$ran ) {
		++$ran;
		return 'value';
	}
);
rwgc_assert( 'value' === $out && 1 === $ran, 'profiler passes the result through when off' );
rwgc_assert( array() === RWGC_Elementor_Profiler::rows(), 'profiler records nothing when off' );

define( 'RWGC_ELEMENTOR_PROFILE', true );
RWGC_Elementor_Profiler::reset_for_tests();
rwgc_assert( RWGC_Elementor_Profiler::enabled(), 'constant enables the profiler' );
RWGC_Elementor_Profiler::measure( 'RWGC_Test::work', static fn() => array( 1, 2, 3 ) );
$rows = RWGC_Elementor_Profiler::rows();
rwgc_assert( 1 === count( $rows ), 'profiler records one row per measured callback' );
rwgc_assert( 'RWGC_Test::work' === $rows[0]['cb'], 'row is labelled with the ReactWoo callback' );
foreach ( array( 'ms', 'mem_delta', 'peak', 'q_delta', 'http' ) as $metric ) {
	rwgc_assert( array_key_exists( $metric, $rows[0] ), "row reports {$metric}" );
}
rwgc_assert( 3 === $rows[0]['rows'], 'row reports array size' );

/* 4. Option providers are memoized and bounded, and never hit the network. */

RWGC_Elementor_Options::reset_for_tests();
$built = 0;
$first = RWGC_Elementor_Options::visitor_preview(
	static function () use ( &$built ) {
		++$built;
		return '<div>preview</div>';
	}
);
$second = RWGC_Elementor_Options::visitor_preview(
	static function () use ( &$built ) {
		++$built;
		return '<div>preview</div>';
	}
);
rwgc_assert( 1 === $built, 'visitor preview resolves at most once per request' );
rwgc_assert( $first === $second, 'memoized visitor preview is stable' );

RWGC_Elementor_Options::reset_for_tests();
rwgc_assert( array() === RWGC_Elementor_Options::countries(), 'countries degrade to empty without RWGC_Countries' );
rwgc_assert( array() === RWGC_Elementor_Options::country_chips(), 'country chips degrade to empty' );

$select = RWGC_Elementor_Options::visibility_library_select();
rwgc_assert( isset( $select[''] ), 'library select always offers the empty choice' );
$chips = RWGC_Elementor_Options::visibility_library_chips();
rwgc_assert( isset( $chips[0]['value'] ) && '' === $chips[0]['value'], 'library chips keep the empty choice first' );

rwgc_assert( RWGC_Elementor_Options::MAX_LIBRARY_RULES > 0, 'library rows are bounded' );
rwgc_assert( RWGC_Elementor_Options::MAX_MASTER_PAGES > 0, 'master pages are bounded' );

/* 5. WP Abilities register on their own hooks, not around Elementor. */

require_once dirname( __DIR__ ) . '/includes/platform/class-rwgc-wp-abilities-adapter.php';
rwgc_assert(
	! method_exists( 'RWGC_WP_Abilities_Adapter', 'should_skip_registration' ),
	'abilities no longer opt out of Elementor requests'
);
rwgc_assert(
	! RWGC_WP_Abilities_Adapter::is_supported(),
	'abilities stay inert without the WordPress Abilities API'
);

exit( $fails > 0 ? 1 : 0 );
