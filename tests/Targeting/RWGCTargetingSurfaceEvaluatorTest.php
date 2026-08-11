<?php
/**
 * Visibility rules mode resolution for popups and builder surfaces.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';

final class RWGCTargetingSurfaceEvaluatorTest extends TestCase {

	public function test_library_rule_show_if_mode_wins_over_surface_hide_if(): void {
		$settings = array(
			'rwgc_visibility_mode'        => 'hide_if',
			'rwgc_enable_visibility_rules' => 'yes',
		);
		$rule_set = array(
			'mode' => 'show_if',
		);

		$this->assertSame(
			'show_if',
			RWGC_Targeting_Surface_Evaluator::get_visibility_rules_mode( $settings, $rule_set )
		);
	}

	public function test_explicit_visibility_rules_mode_overrides_library_rule(): void {
		$settings = array(
			'rwgc_visibility_rules_mode'   => 'hide_if',
			'rwgc_enable_visibility_rules'   => 'yes',
		);
		$rule_set = array(
			'mode' => 'show_if',
		);

		$this->assertSame(
			'hide_if',
			RWGC_Targeting_Surface_Evaluator::get_visibility_rules_mode( $settings, $rule_set )
		);
	}

	public function test_show_if_portable_non_match_suppresses_render(): void {
		$this->assertFalse(
			rwgc_visibility_mode_allows_render(
				RWGC_Targeting_Surface_Evaluator::get_visibility_rules_mode(
					array( 'rwgc_visibility_mode' => 'hide_if' ),
					array( 'mode' => 'show_if' )
				),
				false
			)
		);
	}

	public function test_explicit_off_ignores_stale_library_id_after_normalize(): void {
		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'rwgc_enable_visibility_rules' => array(
					'$$type' => 'boolean',
					'value'  => false,
				),
				'rwgc_visibility_rule_library' => '42',
			)
		);

		$this->assertTrue( ! empty( $normalized['_rwgc_visibility_rules_explicit_off'] ) );
		$this->assertFalse(
			RWGC_Targeting_Surface_Evaluator::is_visibility_rules_enabled( $normalized )
		);
	}

	public function test_explicit_classic_off_ignores_stale_portable_json(): void {
		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'rwgc_enable_visibility_rules' => '',
				'rwgc_portable_geo_targeting'  => '{"mode":"show_if","rules":[]}',
			)
		);

		$this->assertFalse(
			RWGC_Targeting_Surface_Evaluator::is_visibility_rules_enabled( $normalized )
		);
	}

	public function test_legacy_payload_only_without_enable_key_still_active(): void {
		if ( ! class_exists( 'RWGC_Rule_Registry', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-rule-registry.php';
		}

		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'rwgc_visibility_rule_library' => '42',
			)
		);

		$this->assertArrayHasKey( '_rwgc_visibility_rules_explicit_off', $normalized );
		$this->assertFalse( ! empty( $normalized['_rwgc_visibility_rules_explicit_off'] ) );
		$this->assertTrue(
			RWGC_Targeting_Surface_Evaluator::is_visibility_rules_enabled( $normalized )
		);
	}

	public function test_raw_explicit_off_without_normalize_ignores_library(): void {
		$this->assertFalse(
			RWGC_Targeting_Surface_Evaluator::is_visibility_rules_enabled(
				array(
					'rwgc_enable_visibility_rules' => '',
					'rwgc_visibility_rule_library' => '42',
				)
			)
		);
	}

	public function test_use_portable_flag_still_enables_when_modern_enable_empty(): void {
		$normalized = RWGC_Surface_Settings::normalize(
			array(
				'rwgc_enable_visibility_rules'     => '',
				'rwgc_use_portable_geo_targeting'  => 'yes',
				'rwgc_portable_geo_targeting'      => '{"mode":"show_if","rules":[]}',
			)
		);

		$this->assertSame( 'yes', $normalized['rwgc_enable_visibility_rules'] );
		$this->assertTrue(
			RWGC_Targeting_Surface_Evaluator::is_visibility_rules_enabled( $normalized )
		);
	}
}
