<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin-platform.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-suite-admin.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php';

/**
 * @covers RWGC_Admin
 * @covers RWGC_Suite_Admin
 */
final class RWGC_AdminMenuTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_user_caps']     = array();
		$GLOBALS['rwgc_test_menu_pages']    = array();
		$GLOBALS['rwgc_test_submenu_pages'] = array();
	}

	public function test_register_menu_restores_hidden_variant_workflow_page(): void {
		$GLOBALS['rwgc_test_user_caps'] = array(
			'manage_options' => true,
			'edit_pages'     => true,
		);

		RWGC_Admin::register_menu();

		$workflow = $this->get_submenu_by_slug( 'rwgc-workflow-variant' );

		$this->assertIsArray( $workflow );
		$this->assertNull( $workflow['parent_slug'] );
		$this->assertSame( 'edit_pages', $workflow['capability'] );
		$this->assertSame( array( 'RWGC_Suite_Admin', 'render_workflow_variant' ), $workflow['callback'] );
	}

	public function test_suite_pages_use_shop_manager_capability_when_available(): void {
		$GLOBALS['rwgc_test_user_caps'] = array(
			'manage_woocommerce' => true,
		);

		RWGC_Admin::register_menu();

		foreach ( array( 'rwgc-getting-started', 'rwgc-suite-home', 'rwgc-suite-variants' ) as $slug ) {
			$submenu = $this->get_submenu_by_slug( $slug );
			$this->assertIsArray( $submenu );
			$this->assertSame( 'manage_woocommerce', $submenu['capability'], $slug );
		}

		$method = new ReflectionMethod( 'RWGC_Suite_Admin', 'can_manage_suite' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null ) );
	}

	/**
	 * @param string $slug Menu slug.
	 * @return array<string, mixed>|null
	 */
	private function get_submenu_by_slug( $slug ): ?array {
		foreach ( $GLOBALS['rwgc_test_submenu_pages'] as $submenu ) {
			if ( isset( $submenu['menu_slug'] ) && $slug === $submenu['menu_slug'] ) {
				return $submenu;
			}
		}
		return null;
	}
}
