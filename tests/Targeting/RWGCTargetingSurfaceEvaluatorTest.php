<?php
/**
 * Visibility rules mode resolution for popups and builder surfaces.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';

if ( ! class_exists( 'RWGC_Rule_Registry', false ) ) {
	/**
	 * Test stub for unresolved library IDs.
	 */
	class RWGC_Rule_Registry {
		/**
		 * @param array<string,mixed> $settings Settings.
		 * @return null
		 */
		public static function resolve_rule_set_from_settings( array $settings ) {
			unset( $settings );
			return null;
		}
	}
}

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

	public function test_unresolved_show_if_library_rule_fails_closed(): void {
		$result = RWGC_Targeting_Surface_Evaluator::evaluate(
			array(
				'rwgc_enable_visibility_rules'   => 'yes',
				'rwgc_visibility_rule_library'   => '123',
				'rwgc_visibility_rules_mode'     => 'show_if',
			)
		);

		$this->assertSame( 'visibility_rules_empty', $result['reason'] );
		$this->assertFalse( $result['portable_match'] );
		$this->assertFalse( $result['rules_match'] );
		$this->assertFalse( $result['should_render'] );
	}

	public function test_unresolved_hide_if_library_rule_does_not_hide_everyone(): void {
		$result = RWGC_Targeting_Surface_Evaluator::evaluate(
			array(
				'rwgc_enable_visibility_rules'   => 'yes',
				'rwgc_visibility_rule_library'   => '123',
				'rwgc_visibility_rules_mode'     => 'hide_if',
			)
		);

		$this->assertSame( 'visibility_rules_empty', $result['reason'] );
		$this->assertFalse( $result['portable_match'] );
		$this->assertFalse( $result['rules_match'] );
		$this->assertTrue( $result['should_render'] );
	}
}
