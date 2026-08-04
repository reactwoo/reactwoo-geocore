<?php
/**
 * Atomic / legacy country normalization for surface settings.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';

final class RWGCSurfaceSettingsAtomicCountriesTest extends TestCase {

	public function test_legacy_atomic_csv_string_envelope_is_preserved(): void {
		$settings = RWGC_Surface_Settings::normalize(
			array(
				'egp_enable_geo_targeting' => array(
					'$$type' => 'boolean',
					'value'  => true,
				),
				'rwgc_country_visibility_mode' => array(
					'$$type' => 'string',
					'value'  => 'show_if',
				),
				'egp_countries' => array(
					'$$type' => 'string',
					'value'  => 'US, GB, de',
				),
			)
		);

		$this->assertSame( 'yes', $settings['egp_enable_geo_targeting'] );
		$this->assertSame( array( 'US', 'GB', 'DE' ), $settings['egp_countries'] );
	}

	public function test_string_array_envelope_with_nested_string_props(): void {
		$settings = RWGC_Surface_Settings::normalize(
			array(
				'egp_enable_geo_targeting' => true,
				'egp_countries'            => array(
					'$$type' => 'string-array',
					'value'  => array(
						array(
							'$$type' => 'string',
							'value'  => 'fr',
						),
						array(
							'$$type' => 'string',
							'value'  => 'IT',
						),
					),
				),
			)
		);

		$this->assertSame( array( 'FR', 'IT' ), $settings['egp_countries'] );
	}

	public function test_chip_option_shaped_rows_keep_iso_codes_not_labels(): void {
		$settings = RWGC_Surface_Settings::normalize(
			array(
				'egp_enable_geo_targeting' => 'yes',
				'egp_countries'            => array(
					array(
						'value' => 'US',
						'label' => 'United States',
					),
					array(
						'value' => 'DE',
						'label' => 'Germany',
					),
				),
			)
		);

		$this->assertSame( array( 'US', 'DE' ), $settings['egp_countries'] );
	}

	public function test_dropped_atomic_countries_are_empty_while_legacy_string_stays_restricted(): void {
		// Simulates Atomic resolver dropping legacy $$type:string against string-array schema.
		$dropped = RWGC_Surface_Settings::normalize(
			array(
				'egp_enable_geo_targeting'     => 'yes',
				'rwgc_country_visibility_mode' => 'show_if',
				'egp_countries'                => null,
			)
		);
		$this->assertSame( array(), $dropped['egp_countries'] );
		$this->assertSame(
			array(),
			RWGC_Targeting_Surface_Evaluator::parse_countries( $dropped )
		);

		$legacy = RWGC_Surface_Settings::normalize(
			array(
				'egp_enable_geo_targeting'     => 'yes',
				'rwgc_country_visibility_mode' => 'show_if',
				'egp_countries'                => array(
					'$$type' => 'string',
					'value'  => 'US,GB',
				),
			)
		);
		$this->assertSame( array( 'US', 'GB' ), $legacy['egp_countries'] );
		$this->assertSame(
			array( 'US', 'GB' ),
			RWGC_Targeting_Surface_Evaluator::parse_countries( $legacy )
		);

		// Empty countries + show_if fails open; non-empty list can suppress non-matching visitors.
		$this->assertTrue( rwgc_visibility_mode_allows_render( 'show_if', true ) );
		$this->assertFalse( rwgc_visibility_mode_allows_render( 'show_if', false ) );
	}
}
