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
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		return 'manage_options' === $cap;
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * @return string
	 */
	function add_menu_page() {
		return 'toplevel_page_rwgc-dashboard';
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * @param string|null $parent_slug Parent slug.
	 * @param string      $page_title Page title.
	 * @param string      $menu_title Menu title.
	 * @param string      $capability Capability.
	 * @param string      $menu_slug Menu slug.
	 * @param callable    $callback Callback.
	 * @return string
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = null ) {
		$GLOBALS['rwgc_test_submenus'][] = array(
			'parent_slug' => $parent_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
			'menu_slug'   => $menu_slug,
			'callback'    => $callback,
		);
		return 'admin_page_' . $menu_slug;
	}
}

if ( ! class_exists( 'RWGC_Suite_Admin', false ) ) {
	class RWGC_Suite_Admin {
		public static function render_suite_variants() {}
		public static function render_suite_home() {}
		public static function render_getting_started() {}
		public static function render_workflow_variant() {}
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php';

/**
 * @covers RWGC_Admin::register_menu
 */
final class RWGC_AdminMenuRegistrationTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_submenus'] = array();
	}

	public function test_suite_direct_workflow_pages_remain_registered_as_hidden_pages(): void {
		RWGC_Admin::register_menu();

		$hidden_slugs = array();
		foreach ( $GLOBALS['rwgc_test_submenus'] as $submenu ) {
			if ( null === $submenu['parent_slug'] ) {
				$hidden_slugs[] = $submenu['menu_slug'];
			}
		}

		$this->assertContains( 'rwgc-suite-home', $hidden_slugs );
		$this->assertContains( 'rwgc-getting-started', $hidden_slugs );
		$this->assertContains( 'rwgc-workflow-variant', $hidden_slugs );
	}
}
