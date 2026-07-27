<?php
/**
 * CLI probe: simulate Elementor get_widgets_config control-stack build.
 *
 * Usage (from site public root):
 *   php wp-content/plugins/reactwoo-geocore/scripts/probe-elementor-widgets-config.php
 *
 * Requires Local/MySQL reachable via wp-config.php. Enables RW_ELEMENTOR_CONFIG_DEBUG.
 *
 * @package ReactWoo_Geo_Core
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

define( 'RW_ELEMENTOR_CONFIG_DEBUG', true );

$root = dirname( __DIR__, 4 ); // .../app/public
if ( ! is_readable( $root . '/wp-load.php' ) ) {
	// Fallback: scripts/ -> geocore -> plugins -> wp-content -> public
	$root = dirname( __DIR__, 4 );
}

$wp_load = $root . '/wp-load.php';
if ( ! is_readable( $wp_load ) ) {
	fwrite( STDERR, "Cannot find wp-load.php at {$wp_load}\n" );
	exit( 1 );
}

fwrite( STDOUT, "Loading WordPress from {$wp_load}\n" );

// Mimic admin AJAX context for Elementor.
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'DOING_AJAX', true );

require $wp_load;

if ( ! class_exists( '\Elementor\Plugin', false ) ) {
	fwrite( STDERR, "Elementor not active.\n" );
	exit( 1 );
}

wp_set_current_user( 1 );

$start_t = microtime( true );
$start_q = get_num_queries();
$start_m = memory_get_usage( true );

$widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
$count   = is_array( $widgets ) ? count( $widgets ) : 0;
fwrite( STDOUT, "Widget types: {$count}\n" );

$config = array();
$i      = 0;
foreach ( $widgets as $widget_key => $widget ) {
	++$i;
	$w_start = microtime( true );
	try {
		$stack = $widget->get_stack( false );
		$config[ $widget_key ] = isset( $stack['controls'] ) ? count( $stack['controls'] ) : 0;
	} catch ( Throwable $e ) {
		fwrite( STDERR, "FAIL {$widget_key}: " . $e->getMessage() . "\n" );
		$config[ $widget_key ] = -1;
	}
	$elapsed = (int) round( ( microtime( true ) - $w_start ) * 1000 );
	if ( $elapsed >= 50 || 0 === $i % 25 ) {
		fwrite( STDOUT, sprintf( "[%d/%d] %s controls=%s %dms peak=%d\n", $i, $count, $widget_key, (string) $config[ $widget_key ], $elapsed, memory_get_peak_usage( true ) ) );
	}
}

$total_ms = (int) round( ( microtime( true ) - $start_t ) * 1000 );
fwrite(
	STDOUT,
	wp_json_encode(
		array(
			'widgets'       => $count,
			'total_ms'      => $total_ms,
			'delta_queries' => get_num_queries() - $start_q,
			'mem_start'     => $start_m,
			'mem_end'       => memory_get_usage( true ),
			'peak'          => memory_get_peak_usage( true ),
		),
		JSON_PRETTY_PRINT
	) . "\n"
);
