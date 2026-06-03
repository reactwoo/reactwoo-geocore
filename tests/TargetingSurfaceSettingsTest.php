<?php
/**
 * Regression tests for block targeting surface normalization.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';

/**
 * @covers RWGC_Surface_Settings
 * @covers RWGC_Targeting_Surface_Evaluator
 */
class TargetingSurfaceSettingsTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_visitor_country'] );
		parent::tearDown();
	}

	public function test_legacy_hide_countries_hide_matching_visitors() {
		$GLOBALS['rwgc_test_visitor_country'] = 'US';

		$settings = RWGC_Surface_Settings::from_block_attributes(
			array(
				'hideCountries' => array( 'US', 'CA' ),
			)
		);
		$result   = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );

		$this->assertSame( 'yes', $settings['egp_enable_geo_targeting'] );
		$this->assertSame( 'hide_if', $settings['rwgc_country_visibility_mode'] );
		$this->assertSame( array( 'US', 'CA' ), $settings['egp_countries'] );
		$this->assertFalse( $result['should_render'] );
	}

	public function test_legacy_show_country_hide_mode_is_preserved() {
		$GLOBALS['rwgc_test_visitor_country'] = 'US';

		$settings = RWGC_Surface_Settings::from_block_attributes(
			array(
				'showCountries' => array( 'US' ),
				'mode'          => 'hide',
			)
		);
		$result   = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );

		$this->assertSame( 'hide_if', $settings['rwgc_country_visibility_mode'] );
		$this->assertFalse( $result['should_render'] );
	}

	public function test_legacy_portable_targeting_does_not_and_with_stale_country_list() {
		$settings = RWGC_Surface_Settings::from_block_attributes(
			array(
				'usePortableTargeting' => true,
				'portableTargeting'    => '{"enabled":true,"mode":"show","rules":[]}',
				'showCountries'        => array( 'GB' ),
			)
		);

		$this->assertSame( '', $settings['egp_enable_geo_targeting'] );
		$this->assertSame( 'yes', $settings['rwgc_enable_visibility_rules'] );
		$this->assertSame( array( 'GB' ), $settings['egp_countries'] );
	}
}
