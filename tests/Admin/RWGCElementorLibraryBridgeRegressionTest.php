<?php
/**
 * Structural regression tests for the Elementor visibility-rule bridge.
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

	/**
	 * Opening an incompatible existing assignment must remain read-only.
	 *
	 * @return void
	 */
	public function test_rebuild_does_not_clear_incompatible_existing_assignment() {
		$rebuild = $this->js_between( $this->bridge_js(), 'rebuildLibrarySelect', 'showCompatibilityNotice' );

		$this->assertStringContainsString( '$select.val(current);', $rebuild );
		$this->assertStringNotContainsString( 'persistAppliedRuleId', $rebuild );
	}

	/**
	 * Compatibility rejection must not write an empty assignment.
	 *
	 * @return void
	 */
	public function test_incompatible_change_guard_does_not_clear_assignment() {
		$bind = $this->js_between( $this->bridge_js(), 'bindLibrarySelect', 'scan' );
		$from = strpos( $bind, "status === 'incompatible'" );
		$this->assertNotFalse( $from );
		$guard = substr( $bind, $from, strpos( $bind, 'if (row && row.json)', $from ) - $from );

		$this->assertStringNotContainsString( "persistAppliedRuleId(\$panel, '')", $guard );
	}
}
