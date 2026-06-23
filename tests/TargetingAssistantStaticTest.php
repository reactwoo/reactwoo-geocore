<?php

use PHPUnit\Framework\TestCase;

/**
 * Static safety checks for the Targeting Assistant browser flow.
 */
final class TargetingAssistantStaticTest extends TestCase {

	private static function assistant_script(): string {
		$path = dirname( __DIR__ ) . '/admin/js/rwgc-targeting-assistant.js';
		$js   = file_get_contents( $path );

		self::assertIsString( $js );

		return $js;
	}

	private static function slice_between( string $haystack, string $start, string $end ): string {
		$start_pos = strpos( $haystack, $start );
		self::assertNotFalse( $start_pos, 'Missing start marker: ' . $start );

		$end_pos = strpos( $haystack, $end, $start_pos );
		self::assertNotFalse( $end_pos, 'Missing end marker: ' . $end );

		return substr( $haystack, $start_pos, $end_pos - $start_pos );
	}

	public function test_card_setup_does_not_execute_unapproved_proposal_id_directly(): void {
		$finalize = self::slice_between(
			self::assistant_script(),
			"\tfunction finalizeCardSetup()",
			"\n\tfunction updateSetupPanel"
		);

		$this->assertStringContainsString(
			'if ( state.proposalId && responseCanExecute( state.proposal || {} ) ) {',
			$finalize
		);
		$this->assertStringContainsString(
			'if ( cfg.confirmInterpretationUrl ) {',
			$finalize
		);
	}

	public function test_execute_proposal_fails_closed_when_proposal_is_not_executable(): void {
		$execute = self::slice_between(
			self::assistant_script(),
			"\tfunction executeProposal()",
			"\n\tfunction persistPortableAndGo"
		);

		$this->assertStringContainsString(
			'if ( ! responseCanExecute( state.proposal || {} ) ) {',
			$execute
		);
		$this->assertStringContainsString(
			'appendAssistant( esc( msg ) );',
			$execute
		);
		$this->assertStringNotContainsString(
			".fail( function () {\n\t\t\t\tgoWorkflowFromProposal();",
			$execute
		);
	}

	public function test_card_footer_requires_execute_ready_or_server_confirmation(): void {
		$script = self::assistant_script();

		$this->assertStringContainsString(
			'var canExecute = responseCanExecute( proposal );',
			$script
		);
		$this->assertStringContainsString(
			'} else if ( canExecute || cfg.confirmInterpretationUrl ) {',
			$script
		);
	}
}
