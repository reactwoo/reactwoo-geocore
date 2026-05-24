<?php
/**
 * CLI regression tests for portable targeting render behavior.
 *
 * Run: php tests/test-portable-renderers.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'RWGC_PATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return is_string( $key ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) : '';
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		return $content;
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	function rwgc_get_visitor_country() {
		return 'GB';
	}
}

class RWGC_Targeting_Rule_Set_Schema {
	public static function sanitize( $raw ) {
		$decoded = json_decode( (string) $raw, true );
		return is_array( $decoded ) && ! empty( $decoded['rules'] ) ? $decoded : null;
	}
}

class RWGC_Context_Snapshot {}

class RWGC_Context_Resolver {
	public static function resolve_current() {
		return new RWGC_Context_Snapshot();
	}
}

class RWGC_Rule_Evaluator {
	public static function should_render_content( array $set, RWGC_Context_Snapshot $snapshot ) {
		unset( $snapshot );
		return isset( $set['mode'] ) && 'hide' === $set['mode'] ? false : true;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-gutenberg.php';

function rwgc_portable_renderer_fail( $msg ) {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
}

$content = '<strong>Secret sale</strong>';

$invalid = RWGC_Gutenberg::render_geo_content_block(
	array(
		'usePortableTargeting' => true,
		'portableTargeting'    => '{"enabled":true,',
	),
	$content
);
if ( '' !== $invalid ) {
	rwgc_portable_renderer_fail( 'Invalid portable JSON should fail closed.' );
}

$empty = RWGC_Gutenberg::render_geo_content_block(
	array(
		'usePortableTargeting' => true,
		'portableTargeting'    => '',
	),
	$content
);
if ( '' !== $empty ) {
	rwgc_portable_renderer_fail( 'Empty portable JSON should fail closed when portable mode is enabled.' );
}

$valid_json_show = json_encode(
	array(
		'enabled' => true,
		'mode'    => 'show',
		'rules'   => array(
			array(
				'id'         => 'r1',
				'match'      => 'all',
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
);

$hidden_by_block_mode = RWGC_Gutenberg::render_geo_content_block(
	array(
		'usePortableTargeting' => true,
		'portableTargeting'    => $valid_json_show,
		'mode'                 => 'hide',
	),
	$content
);
if ( '' !== $hidden_by_block_mode ) {
	rwgc_portable_renderer_fail( 'Block mode should override stale portable JSON mode.' );
}

fwrite( STDOUT, "OK: portable renderer CLI tests passed.\n" );
exit( 0 );
