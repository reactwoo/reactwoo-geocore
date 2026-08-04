<?php
/**
 * Atomic / legacy country prop normalization for surface targeting.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';

final class RWGCSurfaceSettingsCountriesTest extends TestCase {

	public function test_normalize_legacy_atomic_string_country(): void {
		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'egp_enable_geo_targeting' => array(
					'$$type' => 'boolean',
					'value'  => true,
				),
				'egp_countries'            => array(
					'$$type' => 'string',
					'value'  => 'FR',
				),
			)
		);

		$this->assertSame( 'yes', $normalized['egp_enable_geo_targeting'] );
		$this->assertSame( array( 'FR' ), $normalized['egp_countries'] );
	}

	public function test_normalize_string_array_chips_countries(): void {
		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'egp_countries' => array(
					'$$type' => 'string-array',
					'value'  => array(
						array(
							'$$type' => 'string',
							'value'  => 'FR',
						),
						array(
							'$$type' => 'string',
							'value'  => 'DE',
						),
					),
				),
			)
		);

		$this->assertSame( array( 'FR', 'DE' ), $normalized['egp_countries'] );
	}

	public function test_parse_countries_accepts_legacy_csv_string(): void {
		$this->assertSame(
			array( 'FR', 'GB' ),
			RWGC_Targeting_Surface_Evaluator::parse_countries(
				array( 'egp_countries' => 'FR, GB' )
			)
		);
	}
}
