<?php
/**
 * Regression tests for app-shell admin route access.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-rwgc-admin-route-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-admin-platform.php';

/**
 * @covers RWGC_Admin_Route_Registry
 * @covers RWGC_Admin_Platform
 */
class AdminRouteRegressionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$_GET                         = array();
		$GLOBALS['rwgc_test_filters'] = array();
		$GLOBALS['rwgc_test_is_admin'] = true;
		$GLOBALS['submenu']           = array();
		$GLOBALS['parent_file']       = '';
		$this->reset_route_registry();
		$this->reset_platform_registries();
	}

	/**
	 * @param string $class Class name.
	 * @param string $property Property name.
	 * @param mixed  $value New value.
	 * @return void
	 */
	private function set_static_property( $class, $property, $value ) {
		$ref  = new ReflectionClass( $class );
		$prop = $ref->getProperty( $property );
		$prop->setAccessible( true );
		$prop->setValue( null, $value );
	}

	/**
	 * @return void
	 */
	private function reset_route_registry() {
		$this->set_static_property( 'RWGC_Admin_Route_Registry', 'routes', array() );
		$this->set_static_property( 'RWGC_Admin_Route_Registry', 'sections', array() );
		$this->set_static_property( 'RWGC_Admin_Route_Registry', 'modules', array() );
	}

	/**
	 * @return void
	 */
	private function reset_platform_registries() {
		$this->set_static_property( 'RWGC_Admin_Platform', 'collapsed_page_registry', array() );
		$this->set_static_property( 'RWGC_Admin_Platform', 'shell_only_page_registry', array() );
	}

	public function test_overview_url_defaults_to_dashboard_even_when_setup_wizard_has_lower_order() {
		RWGC_Admin_Route_Registry::register_route(
			array(
				'section'   => 'overview',
				'route'     => 'setup',
				'menu_slug' => 'rwgc-getting-started',
				'label'     => 'Setup wizard',
				'order'     => 5,
			)
		);
		RWGC_Admin_Route_Registry::register_route(
			array(
				'section'   => 'overview',
				'route'     => 'dashboard',
				'menu_slug' => 'rwgc-dashboard',
				'label'     => 'Overview',
				'order'     => 10,
			)
		);

		$this->assertSame(
			'http://example.test/wp-admin/admin.php?page=rwgc-dashboard',
			RWGC_Admin_Route_Registry::get_url( 'overview' )
		);
	}

	public function test_core_routes_include_hidden_variant_workflow_route() {
		RWGC_Admin_Route_Registry::register_core_routes();

		$routes = RWGC_Admin_Route_Registry::get_routes();

		$this->assertArrayHasKey( 'rwgc-workflow-variant', $routes );
		$this->assertFalse( $routes['rwgc-workflow-variant']['is_section_nav'] );
	}

	public function test_shell_only_pages_keep_access_rows_when_sidebar_collapse_is_disabled() {
		add_filter( 'rwgc_admin_sidebar_collapsed', '__return_false' );

		RWGC_Admin_Platform::register_shell_only_page(
			array(
				'menu_slug'  => 'rwgc-settings',
				'menu_title' => 'Settings',
				'capability' => 'manage_options',
				'callback'   => '__return_null',
			)
		);

		$_GET['page'] = 'rwgc-settings';
		RWGC_Admin_Platform::ensure_collapsed_hub_page_access();

		$this->assertSame( 'rwgc-dashboard', $GLOBALS['parent_file'] );
		$this->assertNotEmpty( $GLOBALS['submenu']['rwgc-dashboard'] );
		$this->assertSame( 'rwgc-settings', $GLOBALS['submenu']['rwgc-dashboard'][0][2] );
	}
}
