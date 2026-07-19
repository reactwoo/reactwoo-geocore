<?php
/**
 * Structural regression tests for the visibility-rule preview script.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class RWGCVisibilityRulePreviewScriptRegressionTest extends TestCase {

	/**
	 * @return void
	 */
	public function test_preview_refresh_binding_has_a_function_definition() {
		$path = dirname( __DIR__, 2 ) . '/admin/js/rwgc-visibility-rule-preview.js';
		$this->assertFileExists( $path );
		$js = (string) file_get_contents( $path );

		$this->assertStringContainsString( 'function bindTextareaRefresh()', $js );
		$this->assertStringContainsString(
			"document.addEventListener('DOMContentLoaded', bindTextareaRefresh);",
			$js
		);
		$this->assertStringNotContainsString( 'bindTestPanel', $js );
	}
}
