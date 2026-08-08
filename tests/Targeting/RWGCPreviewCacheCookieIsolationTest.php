<?php
/**
 * Admin ?rwgc_preview_country= cache-cookie isolation regressions.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/**
	 * @return bool
	 */
	function is_user_logged_in() {
		return ! empty( $GLOBALS['rwgc_test_logged_in'] );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		unset( $cap );
		return ! empty( $GLOBALS['rwgc_test_can_manage_options'] );
	}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-preview.php';
require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-cache-compat.php';

/**
 * @covers RWGC_Preview
 * @covers RWGC_Cache_Compat
 */
final class RWGCPreviewCacheCookieIsolationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_logged_in']           = true;
		$GLOBALS['rwgc_test_can_manage_options'] = true;
		$_GET                                   = array();
		unset( $_COOKIE[ RWGC_Cache_Compat::COUNTRY_COOKIE ] );
	}

	protected function tearDown(): void {
		$_GET = array();
		unset( $_COOKIE[ RWGC_Cache_Compat::COUNTRY_COOKIE ] );
		unset( $GLOBALS['rwgc_test_logged_in'], $GLOBALS['rwgc_test_can_manage_options'] );
		parent::tearDown();
	}

	public function test_is_active_requires_authorized_preview_query(): void {
		$_GET[ RWGC_Preview::QUERY_VAR ] = 'FR';
		$this->assertTrue( RWGC_Preview::is_active() );

		$GLOBALS['rwgc_test_can_manage_options'] = false;
		$this->assertFalse( RWGC_Preview::is_active() );
	}

	public function test_active_admin_preview_does_not_persist_simulated_country_cookie(): void {
		$_GET[ RWGC_Preview::QUERY_VAR ] = 'FR';
		unset( $_COOKIE[ RWGC_Cache_Compat::COUNTRY_COOKIE ] );

		// Simulated country would otherwise come from rwgc_geo_data → get_visitor_country.
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			/**
			 * @return string
			 */
			function rwgc_get_visitor_country() {
				return 'FR';
			}
		}

		RWGC_Cache_Compat::maybe_set_country_cookie();

		$this->assertArrayNotHasKey( RWGC_Cache_Compat::COUNTRY_COOKIE, $_COOKIE );
	}
}
