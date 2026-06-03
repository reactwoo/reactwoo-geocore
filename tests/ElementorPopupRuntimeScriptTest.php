<?php
/**
 * Regression tests for Elementor popup runtime guard generation.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/integrations/elementor/class-rwgc-elementor-popups.php';

/**
 * @covers RWGC_Elementor_Popups
 */
class ElementorPopupRuntimeScriptTest extends TestCase {

	public function test_dismiss_tracking_is_limited_to_geo_popup_ids() {
		$method = new ReflectionMethod( 'RWGC_Elementor_Popups', 'build_popup_runtime_script' );
		$method->setAccessible( true );

		$script = $method->invoke(
			null,
			array(
				123 => array(
					'countries'       => array( 'US' ),
					'visibility_mode' => 'show_if',
					'rwgc_show'       => true,
				),
			),
			'US',
			array()
		);

		$this->assertStringContainsString( 'function isDismissed(pid){if(!pid||!meta(pid)){return false;}', $script );
		$this->assertStringContainsString( 'function markDismissed(pid){if(!pid||!meta(pid)){return;}', $script );
		$this->assertStringContainsString( 'function shouldShowForPopup(pid){var m=meta(pid);if(!m){return true;}if(isDismissed(pid)){return false;}', $script );
	}
}
