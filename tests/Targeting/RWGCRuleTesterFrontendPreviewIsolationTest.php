<?php
/**
 * Rule Tester frontend preview isolation regressions.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-rule-tester-frontend-preview.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-routing.php';
require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-cache-compat.php';

/**
 * @covers RWGC_Routing
 * @covers RWGC_Cache_Compat
 */
final class RWGCRuleTesterFrontendPreviewIsolationTest extends TestCase {

	protected function tearDown(): void {
		$this->set_preview_payload( null );
		unset( $_COOKIE[ RWGC_Cache_Compat::COUNTRY_COOKIE ] );
		parent::tearDown();
	}

	public function test_active_preview_bypasses_geo_page_routing(): void {
		$this->set_preview_payload( array( 'context' => array( 'country' => 'DE' ) ) );

		$method = new ReflectionMethod( RWGC_Routing::class, 'should_bypass_request' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( null ) );
	}

	public function test_active_preview_does_not_persist_simulated_country_cookie(): void {
		$this->set_preview_payload( array( 'context' => array( 'country' => 'DE' ) ) );
		unset( $_COOKIE[ RWGC_Cache_Compat::COUNTRY_COOKIE ] );

		RWGC_Cache_Compat::maybe_set_country_cookie();

		$this->assertArrayNotHasKey( RWGC_Cache_Compat::COUNTRY_COOKIE, $_COOKIE );
	}

	/**
	 * @param array<string,mixed>|null $payload Preview payload.
	 * @return void
	 */
	private function set_preview_payload( $payload ) {
		$property = new ReflectionProperty( RWGC_Rule_Tester_Frontend_Preview::class, 'payload' );
		$property->setAccessible( true );
		$property->setValue( null, $payload );
	}
}
