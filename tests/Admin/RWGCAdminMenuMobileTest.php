<?php
/**
 * Mobile wp-admin menu tap and app-shell offset.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['rwgc_test_is_admin'] );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * @param string $handle Handle.
	 * @param string $src    Source.
	 * @param array  $deps   Dependencies.
	 * @param mixed  $ver    Version.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
		$GLOBALS['rwgc_test_styles'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * @param string $handle    Handle.
	 * @param string $src       Source.
	 * @param array  $deps      Dependencies.
	 * @param mixed  $ver       Version.
	 * @param bool   $in_footer Footer.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['rwgc_test_scripts'][ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $ver,
			'in_footer' => $in_footer,
		);
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	/**
	 * @param string $handle Handle.
	 * @param string $css    CSS.
	 * @return void
	 */
	function wp_add_inline_style( $handle, $css ) {
		$GLOBALS['rwgc_test_inline_styles'][ $handle ] = (string) $css;
	}
}

/**
 * @coversNothing
 */
class RWGCAdminMenuMobileTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_is_admin']      = true;
		$GLOBALS['rwgc_test_styles']        = array();
		$GLOBALS['rwgc_test_scripts']       = array();
		$GLOBALS['rwgc_test_inline_styles'] = array();
		unset( $GLOBALS['rwgc_test_filters']['rwgc_admin_sidebar_collapsed'] );
		if ( ! defined( 'RWGC_URL' ) ) {
			define( 'RWGC_URL', 'https://example.test/wp-content/plugins/reactwoo-geocore/' );
		}
		if ( ! class_exists( 'RWGC_Admin_Platform', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin-platform.php';
		}
	}

	/**
	 * @return void
	 */
	public function test_collapsed_menu_enqueues_style_and_tap_script_on_every_admin_screen() {
		RWGC_Admin_Platform::enqueue_collapsed_menu_styles( 'edit.php' );

		$this->assertArrayHasKey( 'rwgc-admin-menu', $GLOBALS['rwgc_test_styles'] );
		$this->assertStringContainsString( 'admin/css/rwgc-admin-menu.css', $GLOBALS['rwgc_test_styles']['rwgc-admin-menu']['src'] );
		$this->assertArrayHasKey( 'rwgc-admin-menu', $GLOBALS['rwgc_test_scripts'] );
		$this->assertStringContainsString( 'admin/js/rwgc-admin-menu.js', $GLOBALS['rwgc_test_scripts']['rwgc-admin-menu']['src'] );
		$this->assertTrue( $GLOBALS['rwgc_test_scripts']['rwgc-admin-menu']['in_footer'] );
	}

	/**
	 * @return void
	 */
	public function test_tap_script_is_not_loaded_when_the_sidebar_is_expanded() {
		$GLOBALS['rwgc_test_filters']['rwgc_admin_sidebar_collapsed'] = static function () {
			return false;
		};

		RWGC_Admin_Platform::enqueue_collapsed_menu_styles( 'index.php' );

		$this->assertSame( array(), $GLOBALS['rwgc_test_styles'] );
		$this->assertSame( array(), $GLOBALS['rwgc_test_scripts'] );
	}

	/**
	 * @return void
	 */
	public function test_tap_script_stops_the_collapsed_top_level_item_only() {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/js/rwgc-admin-menu.js' );

		$this->assertStringContainsString( "#toplevel_page_rwgc-dashboard > a", $js );
		$this->assertStringContainsString( 'rwgc-admin-sidebar-collapsed', $js );
		$this->assertStringContainsString( "addEventListener('click', stop, true)", $js );
		$this->assertStringContainsString( "addEventListener('touchstart', stop, true)", $js );
		$this->assertStringContainsString( 'stopPropagation', $js );
	}

	/**
	 * @return void
	 */
	public function test_app_shell_css_offsets_the_module_nav_on_a_phone() {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/css/rwgc-app-shell.css' );

		$this->assertStringContainsString( 'body.rwgc-app-shell-active #wpcontent', $css );
		$this->assertStringContainsString( 'padding-top: 46px', $css );
		$this->assertStringContainsString( 'body.rwgc-app-shell-active #wpbody', $css );
		$this->assertStringContainsString( 'padding-top: 0', $css );
		$this->assertStringContainsString( '.rwgc-app-shell__module-nav', $css );
		$this->assertStringContainsString( 'box-sizing: border-box', $css );
		$this->assertStringContainsString( '.rwgc-app-shell', $css );
		$this->assertStringContainsString( 'margin-right: 0', $css );
		$this->assertMatchesRegularExpression( '/@media screen and \(max-width: 600px\)/', $css );
		$this->assertMatchesRegularExpression( '/@media \(max-width: 960px\)/', $css );
		$this->assertMatchesRegularExpression( '/@media screen and \(max-width: 782px\)/', $css );
	}
}
