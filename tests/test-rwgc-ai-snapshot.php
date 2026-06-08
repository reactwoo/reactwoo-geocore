<?php
/**
 * CLI regression tests for site intelligence snapshot schema.
 *
 * Run: php tests/test-rwgc-ai-snapshot.php
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

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $hook, $args );
		return $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

require_once dirname( __DIR__ ) . '/includes/ai/class-rwgc-ai-snapshot-schema.php';

/**
 * @param string $msg Message.
 * @return void
 */
function rwgc_ai_snapshot_test_fail( $msg ) {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
}

$sample = array(
	'schema_version'    => RWGC_AI_Snapshot_Schema::VERSION,
	'generated_at_gmt'  => '2026-06-08T12:00:00+00:00',
	'site'              => array( 'url' => 'https://example.test/' ),
	'plugins'           => array(),
	'modules'           => array(),
	'target_providers'  => array(),
	'rules'             => array(),
	'conditions'        => array(),
	'variants'          => array(),
	'parent_pages'      => array(),
	'popups'            => array(),
	'forms'             => array(),
	'tracking_events'   => array(),
	'conversion_events' => array(),
	'relationships'     => array(),
);

$normalized = RWGC_AI_Snapshot_Schema::normalize( array( 'site' => array( 'url' => 'https://x.test/' ) ) );
if ( ! RWGC_AI_Snapshot_Schema::is_valid_shape( $normalized ) ) {
	rwgc_ai_snapshot_test_fail( 'normalize() must produce valid v1 shape' );
}

foreach ( RWGC_AI_Snapshot_Schema::TOP_LEVEL_KEYS as $key ) {
	if ( ! array_key_exists( $key, $normalized ) ) {
		rwgc_ai_snapshot_test_fail( 'Missing top-level key after normalize: ' . $key );
	}
}

$hash_a = RWGC_AI_Snapshot_Schema::compute_hash( $sample );
$sample['snapshot_hash'] = 'ignored';
$hash_b = RWGC_AI_Snapshot_Schema::compute_hash( $sample );
if ( $hash_a !== $hash_b || ! preg_match( '/^[a-f0-9]{64}$/', $hash_a ) ) {
	rwgc_ai_snapshot_test_fail( 'compute_hash() must be stable and 64-char hex' );
}

$dirty = array(
	'site' => array( 'email' => 'admin@example.test' ),
	'rules' => array(
		array(
			'post_content'    => 'secret body',
			'_elementor_data' => '{}',
			'license_key'     => 'abc',
		),
	),
	'visitor_ip' => '203.0.113.10',
);
$clean = RWGC_AI_Snapshot_Schema::strip_sensitive( $dirty );
if ( isset( $clean['site']['email'] ) || isset( $clean['rules'][0]['post_content'] ) || isset( $clean['visitor_ip'] ) ) {
	rwgc_ai_snapshot_test_fail( 'strip_sensitive() must remove excluded keys' );
}

$note = RWGC_AI_Snapshot_Schema::strip_sensitive( array( 'note' => 'mail user@example.com ip 203.0.113.10' ) );
if ( false === strpos( $note['note'], '[redacted_email]' ) || false === strpos( $note['note'], '[redacted_ip]' ) ) {
	rwgc_ai_snapshot_test_fail( 'strip_sensitive() must scrub email and IP substrings' );
}

$summary = RWGC_AI_Snapshot_Schema::summarize_rule_set(
	array(
		'enabled' => true,
		'mode'    => 'show_if',
		'match'   => 'any',
		'rules'   => array(
			array(
				'conditions' => array(
					array( 'type' => 'country', 'operator' => 'in', 'value' => array( 'GB' ) ),
					array( 'type' => 'country', 'operator' => 'in', 'value' => array( 'US' ) ),
				),
			),
		),
	)
);
if ( empty( $summary['conditions'][0]['count'] ) || 2 !== (int) $summary['conditions'][0]['count'] ) {
	rwgc_ai_snapshot_test_fail( 'summarize_rule_set() must count conditions without exporting values' );
}
if ( isset( $summary['conditions'][0]['value'] ) ) {
	rwgc_ai_snapshot_test_fail( 'summarize_rule_set() must not include raw condition values' );
}

$excluded = array_map( 'strtolower', RWGC_AI_Snapshot_Schema::default_excluded_fields() );
foreach ( array( 'post_content', 'elementor_data', 'email', 'license_key' ) as $field ) {
	if ( ! in_array( $field, $excluded, true ) ) {
		rwgc_ai_snapshot_test_fail( 'default_excluded_fields missing: ' . $field );
	}
}

fwrite( STDOUT, "OK: rwgc-ai-snapshot schema tests passed.\n" );
exit( 0 );
