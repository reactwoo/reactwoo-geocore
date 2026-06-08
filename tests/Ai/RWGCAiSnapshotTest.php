<?php
/**
 * Site intelligence snapshot schema tests (no full WordPress load).
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/ai/class-rwgc-ai-snapshot-schema.php';

/**
 * @covers RWGC_AI_Snapshot_Schema
 */
class RWGCAiSnapshotTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function sample_payload() {
		return array(
			'schema_version'    => RWGC_AI_Snapshot_Schema::VERSION,
			'generated_at_gmt'  => '2026-06-08T12:00:00+00:00',
			'site'              => array(
				'url'  => 'https://example.test/',
				'name' => 'Demo Site',
			),
			'plugins'           => array(
				'geocore_version' => '1.8.37',
				'satellites'      => array(),
			),
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
	}

	public function test_normalize_adds_missing_top_level_keys() {
		$normalized = RWGC_AI_Snapshot_Schema::normalize( array( 'site' => array( 'url' => 'https://x.test/' ) ) );
		$this->assertTrue( RWGC_AI_Snapshot_Schema::is_valid_shape( $normalized ) );
		$this->assertSame( RWGC_AI_Snapshot_Schema::VERSION, (int) $normalized['schema_version'] );
		foreach ( RWGC_AI_Snapshot_Schema::TOP_LEVEL_KEYS as $key ) {
			$this->assertArrayHasKey( $key, $normalized, 'Missing key: ' . $key );
		}
	}

	public function test_compute_hash_is_stable_and_excludes_snapshot_hash_key() {
		$payload = $this->sample_payload();
		$hash_a  = RWGC_AI_Snapshot_Schema::compute_hash( $payload );
		$payload['snapshot_hash'] = 'should-not-affect-hash';
		$hash_b  = RWGC_AI_Snapshot_Schema::compute_hash( $payload );
		$this->assertSame( $hash_a, $hash_b );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $hash_a );
	}

	public function test_strip_sensitive_removes_excluded_fields_recursively() {
		$dirty = array(
			'site' => array(
				'url'   => 'https://example.test/',
				'email' => 'admin@example.test',
			),
			'rules' => array(
				array(
					'id'             => '1',
					'post_content'   => 'Full page body must not ship.',
					'_elementor_data' => '{"version":"3"}',
					'license_key'    => 'secret-key',
				),
			),
			'visitor_ip' => '203.0.113.10',
		);

		$clean = RWGC_AI_Snapshot_Schema::strip_sensitive( $dirty );

		$this->assertArrayNotHasKey( 'email', $clean['site'] );
		$this->assertArrayNotHasKey( 'post_content', $clean['rules'][0] );
		$this->assertArrayNotHasKey( '_elementor_data', $clean['rules'][0] );
		$this->assertArrayNotHasKey( 'license_key', $clean['rules'][0] );
		$this->assertArrayNotHasKey( 'visitor_ip', $clean );
	}

	public function test_strip_sensitive_scrubs_email_and_ip_strings() {
		$clean = RWGC_AI_Snapshot_Schema::strip_sensitive(
			array(
				'note' => 'Contact user@example.com from 203.0.113.10',
			)
		);
		$this->assertStringContainsString( '[redacted_email]', $clean['note'] );
		$this->assertStringContainsString( '[redacted_ip]', $clean['note'] );
	}

	public function test_summarize_rule_set_returns_condition_counts_not_raw_values() {
		$summary = RWGC_AI_Snapshot_Schema::summarize_rule_set(
			array(
				'enabled' => true,
				'mode'    => 'show_if',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'r1',
						'conditions' => array(
							array(
								'type'     => 'country',
								'operator' => 'in',
								'value'    => array( 'GB', 'IE' ),
							),
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

		$this->assertIsArray( $summary );
		$this->assertSame( 1, $summary['rule_count'] );
		$this->assertCount( 1, $summary['conditions'] );
		$this->assertSame( 'country', $summary['conditions'][0]['type'] );
		$this->assertSame( 2, $summary['conditions'][0]['count'] );
		$this->assertArrayNotHasKey( 'value', $summary['conditions'][0] );
	}

	public function test_default_excluded_fields_includes_pii_and_content_keys() {
		$excluded = array_map( 'strtolower', RWGC_AI_Snapshot_Schema::default_excluded_fields() );
		foreach ( array( 'post_content', 'elementor_data', 'email', 'ip_address', 'license_key', 'orders' ) as $field ) {
			$this->assertContains( $field, $excluded, 'Expected excluded field: ' . $field );
		}
	}
}
