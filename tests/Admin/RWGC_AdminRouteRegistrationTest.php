<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'RWGC_PATH' ) ) {
	define( 'RWGC_PATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		global $rwgc_test_current_user_caps;
		return ! empty( $rwgc_test_current_user_caps[ $capability ] );
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
		global $rwgc_test_menu_pages;
		$rwgc_test_menu_pages[ $menu_slug ] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position' );
		return 'toplevel_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		global $rwgc_test_submenu_pages;
		$rwgc_test_submenu_pages[ $menu_slug ] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );
		return $parent_slug . '_page_' . $menu_slug;
	}
}

require_once RWGC_PATH . 'includes/class-rwgc-admin-platform.php';
require_once RWGC_PATH . 'includes/class-rwgc-admin-route-registry.php';
require_once RWGC_PATH . 'includes/class-rwgc-admin-section-hubs.php';
require_once RWGC_PATH . 'includes/functions-rwgc.php';
require_once RWGC_PATH . 'includes/class-rwgc-suite-admin.php';
require_once RWGC_PATH . 'includes/class-rwgc-admin.php';

/**
 * @covers RWGC_Admin
 * @covers RWGC_Admin_Route_Registry
 */
final class RWGC_AdminRouteRegistrationTest extends TestCase {

	protected function setUp(): void {
		global $rwgc_test_current_user_caps, $rwgc_test_menu_pages, $rwgc_test_submenu_pages;

		$rwgc_test_current_user_caps = array(
			'manage_options' => true,
			'edit_pages'     => true,
		);
		$rwgc_test_menu_pages    = array();
		$rwgc_test_submenu_pages = array();

		$this->reset_route_registry();
	}

	public function test_core_routes_include_create_variant_workflow(): void {
		RWGC_Admin_Route_Registry::register_core_routes();

		$routes = RWGC_Admin_Route_Registry::get_routes();

		$this->assertArrayHasKey( 'rwgc-workflow-variant', $routes );
		$this->assertSame( 'targeting', $routes['rwgc-workflow-variant']['section'] );
		$this->assertSame( 'create-variant', $routes['rwgc-workflow-variant']['route'] );
	}

	public function test_register_menu_registers_create_variant_workflow_page(): void {
		global $rwgc_test_submenu_pages;

		RWGC_Admin::register_menu();

		$this->assertArrayHasKey( 'rwgc-workflow-variant', $rwgc_test_submenu_pages );
		$this->assertSame(
			array( 'RWGC_Suite_Admin', 'render_workflow_variant' ),
			$rwgc_test_submenu_pages['rwgc-workflow-variant']['callback']
		);
	}

	public function test_suite_admin_accepts_hub_capability_for_shop_managers(): void {
		global $rwgc_test_current_user_caps;

		$rwgc_test_current_user_caps = array(
			'manage_woocommerce' => true,
		);

		$can_manage_suite = new ReflectionMethod( 'RWGC_Suite_Admin', 'can_manage_suite' );
		$can_manage_suite->setAccessible( true );

		$this->assertTrue( $can_manage_suite->invoke( null ) );
	}

	private function reset_route_registry(): void {
		$routes = new ReflectionProperty( 'RWGC_Admin_Route_Registry', 'routes' );
		$routes->setAccessible( true );
		$routes->setValue( null, array() );
	}
}
