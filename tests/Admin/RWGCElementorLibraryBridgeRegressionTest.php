<?php
/**
 * Regression tests for Elementor visibility-rule library bridge behavior.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class RWGCElementorLibraryBridgeRegressionTest extends TestCase {

	/**
	 * @return string
	 */
	private function bridge_js() {
		$path = dirname( __DIR__, 2 ) . '/assets/js/rwgc-elementor-library-bridge.js';
		$this->assertFileExists( $path );
		return (string) file_get_contents( $path );
	}

	/**
	 * @param string $js    Script source.
	 * @param string $start Function name to slice from.
	 * @param string $end   Function name to slice until.
	 * @return string
	 */
	private function js_between( $js, $start, $end ) {
		$from = strpos( $js, 'function ' . $start );
		$this->assertNotFalse( $from, 'Missing function ' . $start );
		$to = strpos( $js, 'function ' . $end, $from + 1 );
		$this->assertNotFalse( $to, 'Missing function ' . $end );
		return substr( $js, $from, $to - $from );
	}

	public function test_rebuild_preserves_current_incompatible_assignment(): void {
		$js      = $this->bridge_js();
		$rebuild = $this->js_between( $js, 'rebuildLibrarySelect', 'showCompatibilityNotice' );

		$this->assertStringContainsString( 'String(row.id) !== current', $rebuild );
		$this->assertStringContainsString( '$select.val(current)', $rebuild );
		$this->assertStringNotContainsString( "persistAppliedRuleId(\$('#elementor-panel-inner'), '')", $rebuild );
	}

	public function test_incompatible_selection_guard_does_not_clear_saved_rule_id(): void {
		$js   = $this->bridge_js();
		$bind = $this->js_between( $js, 'bindLibrarySelect', 'scan' );

		$this->assertStringContainsString( "row.compatibility.status === 'incompatible'", $bind );
		$this->assertStringContainsString( 'showCompatibilityNotice($panel, row)', $bind );

		$branch_start = strpos( $bind, "row.compatibility.status === 'incompatible'" );
		$this->assertNotFalse( $branch_start );
		$branch_end = strpos( $bind, 'if (row && row.json)', $branch_start );
		$this->assertNotFalse( $branch_end );
		$branch = substr( $bind, $branch_start, $branch_end - $branch_start );

		$this->assertStringNotContainsString( "\$(this).val('')", $branch );
		$this->assertStringNotContainsString( "persistAppliedRuleId(\$panel, '')", $branch );
	}
}
