<?php
/**
 * PHPUnit coverage for Page Version URL request routing.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function trailingslashit( $value ) {
		return rtrim( $value, '/' ) . '/';
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-page-version.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-page-version-routing.php';

/**
 * @covers RWGC_Page_Version_Routing
 */
class PageVersionRoutingTest extends TestCase {

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'] );
		$this->reset_parsed_request_cache();
		parent::tearDown();
	}

	public function test_query_var_alone_does_not_activate_page_version_request() {
		$_SERVER['REQUEST_URI'] = '/landing/?rwgc_page_version=partner-x';
		$this->reset_parsed_request_cache();

		$this->assertFalse( RWGC_Page_Version_Routing::is_page_version_request() );
		$this->assertSame( '', RWGC_Page_Version_Routing::get_active_version() );

		$filtered = RWGC_Page_Version_Routing::filter_request(
			array(
				'pagename'                                => 'landing',
				RWGC_Page_Version_Routing::QUERY_VAR      => 'partner-x',
			)
		);

		$this->assertSame( 'landing', $filtered['pagename'] );
		$this->assertArrayNotHasKey( RWGC_Page_Version_Routing::QUERY_VAR, $filtered );
	}

	public function test_branded_path_activates_page_version_request() {
		$_SERVER['REQUEST_URI'] = '/landing/_gc/partner-x/';
		$this->reset_parsed_request_cache();

		$this->assertTrue( RWGC_Page_Version_Routing::is_page_version_request() );
		$this->assertSame( 'partner-x', RWGC_Page_Version_Routing::get_active_version() );

		$filtered = RWGC_Page_Version_Routing::filter_request( array() );

		$this->assertSame( 'landing', $filtered['pagename'] );
		$this->assertSame( 'partner-x', $filtered[ RWGC_Page_Version_Routing::QUERY_VAR ] );
		$this->assertSame( 0, $filtered['page_id'] );
		$this->assertSame( '', $filtered['name'] );
	}

	/**
	 * @return void
	 */
	private function reset_parsed_request_cache() {
		$property = new ReflectionProperty( RWGC_Page_Version_Routing::class, 'parsed_request' );
		$property->setAccessible( true );
		$property->setValue( false );
	}
}
