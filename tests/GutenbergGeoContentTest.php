<?php
/**
 * Tests for Gutenberg Geo Content rendering.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-gutenberg.php';

/**
 * @covers RWGC_Gutenberg
 * @covers RWGC_Surface_Settings
 * @covers RWGC_Targeting_Surface_Evaluator
 */
final class GutenbergGeoContentTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_visitor_country'] = 'US';
	}

	public function test_legacy_hide_countries_block_hides_matching_visitor(): void {
		$GLOBALS['rwgc_test_visitor_country'] = 'GB';

		$this->assertSame(
			'',
			RWGC_Gutenberg::render_geo_content_block(
				array(
					'hideCountries' => array( 'GB' ),
				),
				'Restricted content'
			)
		);
	}

	public function test_legacy_hide_countries_block_shows_non_matching_visitor(): void {
		$GLOBALS['rwgc_test_visitor_country'] = 'US';

		$this->assertSame(
			'<div class="rwgc-geo-content">Restricted content</div>',
			RWGC_Gutenberg::render_geo_content_block(
				array(
					'hideCountries' => array( 'GB' ),
				),
				'Restricted content'
			)
		);
	}

	public function test_legacy_hide_mode_with_show_countries_hides_matching_visitor(): void {
		$GLOBALS['rwgc_test_visitor_country'] = 'GB';

		$this->assertSame(
			'',
			RWGC_Gutenberg::render_geo_content_block(
				array(
					'mode'          => 'hide',
					'showCountries' => array( 'GB' ),
				),
				'Restricted content'
			)
		);
	}
}
