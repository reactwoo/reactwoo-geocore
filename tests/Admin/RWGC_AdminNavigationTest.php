<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		unset( $capability );
		return true;
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * @param string $page_title Page title.
	 * @param string $menu_title Menu title.
	 * @param string $capability Capability.
	 * @param string $menu_slug  Menu slug.
	 * @param callable|null $callback Callback.
	 * @param string        $icon_url Icon URL.
	 * @param int|null      $position Position.
	 * @return string
	 */
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = null, $icon_url = '', $position = null ) {
		$GLOBALS['rwgc_test_menu_pages'][ $menu_slug ] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position' );
		return 'toplevel_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * @param string|null $parent_slug Parent slug.
	 * @param string      $page_title  Page title.
	 * @param string      $menu_title  Menu title.
	 * @param string      $capability  Capability.
	 * @param string      $menu_slug   Menu slug.
	 * @param callable    $callback    Callback.
	 * @param int|null    $position    Position.
	 * @return string
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback, $position = null ) {
		$GLOBALS['rwgc_test_submenu_pages'][ $menu_slug ] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );
		return ( null === $parent_slug ? 'admin' : $parent_slug ) . '_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'remove_submenu_page' ) ) {
	/**
	 * @param string $parent_slug Parent slug.
	 * @param string $menu_slug   Menu slug.
	 * @return array<int, mixed>|false
	 */
	function remove_submenu_page( $parent_slug, $menu_slug ) {
		global $submenu;
		if ( empty( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
			return false;
		}
		foreach ( $submenu[ $parent_slug ] as $index => $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) && $menu_slug === (string) $entry[2] ) {
				unset( $submenu[ $parent_slug ][ $index ] );
				$submenu[ $parent_slug ] = array_values( $submenu[ $parent_slug ] );
				return $entry;
			}
		}
		return false;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin-platform.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin-route-registry.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-suite-admin.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php';

/**
 * @covers RWGC_Admin
 * @covers RWGC_Admin_Platform
 * @covers RWGC_Admin_Route_Registry
 */
final class RWGC_AdminNavigationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_menu_pages']    = array();
		$GLOBALS['rwgc_test_submenu_pages'] = array();
		$GLOBALS['submenu']                 = array();
		$this->reset_route_registry();
	}

	public function test_register_menu_registers_hidden_variant_workflow_page(): void {
		RWGC_Admin::register_menu();

		$this->assertArrayHasKey( 'rwgc-workflow-variant', $GLOBALS['rwgc_test_submenu_pages'] );
		$page = $GLOBALS['rwgc_test_submenu_pages']['rwgc-workflow-variant'];

		$this->assertNull( $page['parent_slug'] );
		$this->assertSame( 'edit_pages', $page['capability'] );
		$this->assertSame( array( 'RWGC_Suite_Admin', 'render_workflow_variant' ), $page['callback'] );
	}

	public function test_core_routes_include_variant_workflow_page(): void {
		RWGC_Admin_Route_Registry::register_core_routes();
		$routes = RWGC_Admin_Route_Registry::get_routes();

		$this->assertArrayHasKey( 'rwgc-workflow-variant', $routes );
		$this->assertSame( 'core', $routes['rwgc-workflow-variant']['module'] );
		$this->assertSame( 'create-page-version', $routes['rwgc-workflow-variant']['route'] );
	}

	public function test_collapsed_submenu_keeps_dashboard_by_default(): void {
		global $submenu;
		$submenu['rwgc-dashboard'] = array(
			array( 'Dashboard', 'manage_options', 'rwgc-dashboard' ),
			array( 'Settings', 'manage_options', 'rwgc-settings' ),
			array( 'Tools', 'manage_options', 'rwgc-tools' ),
		);

		RWGC_Admin_Platform::collapse_hub_submenu();

		$this->assertSame(
			array(
				array( 'Dashboard', 'manage_options', 'rwgc-dashboard' ),
			),
			$submenu['rwgc-dashboard']
		);
	}

	private function reset_route_registry(): void {
		$routes = new ReflectionProperty( 'RWGC_Admin_Route_Registry', 'routes' );
		$routes->setAccessible( true );
		$routes->setValue( null, array() );
	}
}
