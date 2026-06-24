<?php
/**
 * Visibility rules mode resolution for popups and builder surfaces.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
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
}
