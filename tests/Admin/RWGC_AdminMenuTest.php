<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Admin
 */
final class RWGC_AdminMenuTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_submenu_pages']         = array();
		$GLOBALS['rwgc_test_removed_submenu_pages'] = array();
	}

	public function test_hidden_suite_pages_are_registered_before_being_removed_from_visible_menu(): void {
		RWGC_Admin::register_menu();

		$registered = $GLOBALS['rwgc_test_submenu_pages'];
		$removed    = $GLOBALS['rwgc_test_removed_submenu_pages'];

		foreach ( array( 'rwgc-suite-home', 'rwgc-getting-started', 'rwgc-workflow-variant' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $registered );
			$this->assertSame( 'rwgc-dashboard', $registered[ $slug ]['parent_slug'] );
			$this->assertTrue( is_callable( $registered[ $slug ]['callback'] ) );
			$this->assertContains( $slug, $removed );
		}
	}
}
