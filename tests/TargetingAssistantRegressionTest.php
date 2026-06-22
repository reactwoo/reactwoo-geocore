<?php
/**
 * Regression coverage for Targeting Assistant client-side safety checks.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TargetingAssistantRegressionTest extends TestCase {

	/**
	 * @return string
	 */
	private function targeting_assistant_js() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/admin/js/rwgc-targeting-assistant.js' );
	}

	public function test_execute_proposal_fails_closed_on_missing_or_failed_confirmation() {
		$js = $this->targeting_assistant_js();

		$this->assertMatchesRegularExpression(
			'/if \( ! state\.proposalId \|\| ! cfg\.executeUrl \) \{\s*showExecutionError\(\);\s*return;\s*\}/',
			$js
		);
		$this->assertMatchesRegularExpression(
			'/\.done\( function \( response \) \{\s*if \( ! response \|\| ! response\.success \) \{\s*showExecutionError/',
			$js
		);
		$this->assertMatchesRegularExpression(
			'/\.fail\( function \( xhr \) \{\s*showExecutionError/',
			$js
		);
		$this->assertDoesNotMatchRegularExpression(
			'/\.fail\( function \( xhr \) \{\s*goWorkflowFromProposal\(\);/',
			$js
		);
	}

	public function test_confirm_action_requires_explicit_executable_proposal() {
		$js = $this->targeting_assistant_js();

		$this->assertStringContainsString( 'var ready = responseCanExecute( proposal );', $js );
		$this->assertMatchesRegularExpression(
			'/if \( \'confirm\' === key && ! ready \) \{\s*return;\s*\}/',
			$js
		);
		$this->assertMatchesRegularExpression(
			'/if \( typeof proposal\.proposal_ready === \'boolean\' \) \{\s*return proposal\.proposal_ready;\s*\}\s*return false;/',
			$js
		);
		$this->assertStringNotContainsString( 'return proposal.proposal_ready !== false;', $js );
	}

	public function test_ambiguity_remove_condition_is_sent_as_an_explicit_resolution() {
		$js = $this->targeting_assistant_js();

		$this->assertStringContainsString( 'resolutions[ field ] = value == null ? \'\' : String( value );', $js );
		$this->assertStringContainsString( 'Object.prototype.hasOwnProperty.call( resolutions, copy.field )', $js );
		$this->assertMatchesRegularExpression(
			'/if \( ! resolutions\[ copy\.field \] \) \{\s*delete copy\.likely;\s*\}/',
			$js
		);
	}

	public function test_confirm_interpretation_uses_ai_draft_conditions_and_logic() {
		$js = $this->targeting_assistant_js();

		$this->assertStringContainsString( 'var draftRule = aiInterpretation && aiInterpretation.proposal_draft && aiInterpretation.proposal_draft.rule ? aiInterpretation.proposal_draft.rule : {};', $js );
		$this->assertStringContainsString( 'var baseConditions = state.proposal && state.proposal.conditions && state.proposal.conditions.length ? state.proposal.conditions : ( draftRule.conditions || [] );', $js );
		$this->assertStringContainsString( 'var baseConditionMatch = state.proposal && state.proposal.condition_match ? state.proposal.condition_match : ( draftRule.logic || \'all\' );', $js );
		$this->assertStringContainsString( 'condition_match: baseConditionMatch', $js );
	}
}
