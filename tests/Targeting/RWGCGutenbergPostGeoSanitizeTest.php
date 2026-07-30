<?php
/**
 * Gutenberg post-level portable meta must not wipe on failed sanitize.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__, 2 ) . '/includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php';

/**
 * @covers RWGC_Gutenberg_Post_Geo::sanitize_portable
 */
final class RWGCGutenbergPostGeoSanitizeTest extends TestCase {

	public function test_explicit_empty_string_clears_portable_meta(): void {
		$this->assertSame( '', RWGC_Gutenberg_Post_Geo::sanitize_portable( '' ) );
		$this->assertSame( '', RWGC_Gutenberg_Post_Geo::sanitize_portable( '   ' ) );
	}

	public function test_valid_country_rule_is_normalized(): void {
		$raw = wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'country_rule',
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

		$out = RWGC_Gutenberg_Post_Geo::sanitize_portable( $raw );
		$this->assertNotSame( '', $out );
		$decoded = json_decode( $out, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'show_if', $decoded['mode'] );
		$this->assertNotEmpty( $decoded['rules'] );
	}

	public function test_pro_only_json_is_preserved_when_pro_inactive(): void {
		$pro_only = wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show_if',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'campaign_rule',
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'campaign',
								'operator' => 'in',
								'value'    => array( 'spring_sale' ),
							),
						),
					),
				),
			)
		);

		$this->assertFalse( RWGC_Targeting_Rule_Set_Schema::is_pro_active() );
		$this->assertNull( RWGC_Targeting_Rule_Set_Schema::sanitize( $pro_only ) );

		$out = RWGC_Gutenberg_Post_Geo::sanitize_portable( $pro_only );
		$this->assertSame( $pro_only, $out, 'Block-editor saves must not wipe Pro-only portable targeting to empty string.' );
	}

	public function test_invalid_non_empty_json_is_preserved(): void {
		$raw = '{"enabled":true,"mode":"show_if","match":"any","rules":[]}';
		$this->assertNull( RWGC_Targeting_Rule_Set_Schema::sanitize( $raw ) );
		$this->assertSame( $raw, RWGC_Gutenberg_Post_Geo::sanitize_portable( $raw ) );
	}
}
