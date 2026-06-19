<?php
/**
 * Regression contracts for the Targeting Assistant admin asset.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TargetingAssistantContractTest extends TestCase {

	/**
	 * @return string
	 */
	private function assistant_script() {
		$script = file_get_contents( dirname( __DIR__ ) . '/admin/js/rwgc-targeting-assistant.js' );

		$this->assertIsString( $script );

		return $script;
	}

	public function test_execute_failures_stay_in_assistant_instead_of_opening_workflow() {
		$script = $this->assistant_script();

		$this->assertStringNotContainsString(
			".fail( function () {\n\t\t\t\tgoWorkflowFromProposal();",
			$script
		);
		$this->assertStringContainsString(
			'.fail( function ( xhr ) {' . "\n\t\t\t\t" . 'showExecutionError( executionErrorMessage( xhr ) );',
			$script
		);
	}

	public function test_create_setup_requires_explicit_executable_state() {
		$script = $this->assistant_script();

		$this->assertStringContainsString( "if ( typeof proposal.can_execute === 'boolean' )", $script );
		$this->assertStringContainsString( "if ( typeof proposal.proposal_ready === 'boolean' )", $script );
		$this->assertMatchesRegularExpression(
			"/function responseCanExecute[\\s\\S]+?return false;\\n\\t}/",
			$script
		);
	}

	public function test_server_confirm_actions_are_gated_by_readiness() {
		$script = $this->assistant_script();

		$this->assertStringContainsString( "var ready = responseCanExecute( proposal );", $script );
		$this->assertStringContainsString( "if ( 'confirm' === key && ! ready )", $script );
		$this->assertStringContainsString( "if ( ! responseCanExecute( state.proposal ) )", $script );
	}
}
