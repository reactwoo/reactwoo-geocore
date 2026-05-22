<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text, $domain = null ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param array<string, mixed> $args     Arguments.
	 * @param array<string, mixed> $defaults Defaults.
	 * @return array<string, mixed>
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @return bool
	 */
	function current_user_can( $capability = '' ) {
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
	 * @return string
	 */
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
		unset( $page_title, $menu_title, $capability, $callback, $icon_url, $position );
		return 'toplevel_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * @param string         $parent_slug Parent slug.
	 * @param string         $page_title  Page title.
	 * @param string         $menu_title  Menu title.
	 * @param string         $capability  Capability.
	 * @param string         $menu_slug   Menu slug.
	 * @param callable|array $callback    Callback.
	 * @return string
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback, $position = null ) {
		unset( $position );
		$GLOBALS['rwgc_admin_menu_test_submenus'][] = array(
			'parent_slug' => $parent_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
			'menu_slug'   => $menu_slug,
			'callback'    => $callback,
		);

		return $parent_slug . '_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'rw_geo_register_app_route' ) ) {
	/**
	 * @param array<string, mixed> $args Route args.
	 * @return string|false
	 */
	function rw_geo_register_app_route( array $args ) {
		if ( empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}

		$GLOBALS['rwgc_admin_menu_test_routes'][] = $args;

		return add_submenu_page(
			'rwgc-dashboard',
			isset( $args['page_title'] ) ? (string) $args['page_title'] : (string) ( $args['label'] ?? '' ),
			isset( $args['menu_title'] ) ? (string) $args['menu_title'] : (string) ( $args['label'] ?? '' ),
			(string) $args['capability'],
			(string) $args['menu_slug'],
			$args['callback']
		);
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-suite-admin.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin-section-hubs.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php';

/**
 * @covers RWGC_Admin::register_menu
 */
final class RWGC_AdminMenuTest extends TestCase {

	public function test_register_menu_registers_create_geo_rule_workflow(): void {
		$GLOBALS['rwgc_admin_menu_test_routes']   = array();
		$GLOBALS['rwgc_admin_menu_test_submenus'] = array();

		RWGC_Admin::register_menu();

		$matches = array_values(
			array_filter(
				$GLOBALS['rwgc_admin_menu_test_routes'],
				static function ( $route ) {
					return isset( $route['menu_slug'] ) && 'rwgc-workflow-variant' === $route['menu_slug'];
				}
			)
		);

		$this->assertCount( 1, $matches );
		$this->assertSame( 'edit_pages', $matches[0]['capability'] );
		$this->assertSame( array( 'RWGC_Suite_Admin', 'render_workflow_variant' ), $matches[0]['callback'] );
		$this->assertFalse( $matches[0]['is_section_nav'] );
	}
}
