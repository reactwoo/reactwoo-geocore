<?php
/**
 * Public GET /location: no visitor IP for the public, never cached (1.9.14).
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-rest.php';

/**
 * @covers RWGC_REST
 */
final class RWGCRestLocationPrivacyTest extends TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function visitor() {
		return array(
			'ip'           => '89.160.20.112',
			'country_code' => 'SE',
			'country_name' => 'Sweden',
			'region'       => '',
			'city'         => '',
			'currency'     => 'SEK',
			'source'       => 'maxmind',
			'cached'       => false,
		);
	}

	public function test_public_visitor_gets_no_ip_field(): void {
		$out = RWGC_REST::location_payload_for_viewer( $this->visitor(), false );
		$this->assertArrayNotHasKey( 'ip', $out );
		$this->assertSame( 'SE', $out['country_code'] );
		$this->assertSame( 'SEK', $out['currency'] );
		$this->assertCount( 7, $out );
	}

	public function test_site_manager_keeps_ip_for_debugging(): void {
		$out = RWGC_REST::location_payload_for_viewer( $this->visitor(), true );
		$this->assertSame( '89.160.20.112', $out['ip'] );
	}

	public function test_empty_ip_from_disabled_geo_is_also_removed(): void {
		$out = RWGC_REST::location_payload_for_viewer( array( 'ip' => '', 'country_code' => 'US' ), false );
		$this->assertSame( array( 'country_code' => 'US' ), $out );
	}

	public function test_headers_forbid_every_cache(): void {
		$h = RWGC_REST::location_no_store_headers();
		$this->assertStringContainsString( 'no-store', $h['Cache-Control'] );
		$this->assertStringContainsString( 'private', $h['Cache-Control'] );
		$this->assertStringContainsString( 'no-cache', $h['Cache-Control'] );
		$this->assertStringContainsString( 'max-age=0', $h['Cache-Control'] );
		$this->assertSame( 'no-cache', $h['Pragma'] );
		$this->assertSame( 'no-cache', $h['X-LiteSpeed-Cache-Control'] );
		$this->assertNotEmpty( $h['Expires'] );
	}

	public function test_get_location_applies_ip_gate_headers_and_litespeed_hint(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-rest.php' );
		$start = strpos( $src, 'public static function get_location(' );
		$body  = substr( $src, $start, strpos( $src, 'public static function location_payload_for_viewer' ) - $start );
		$this->assertStringContainsString( "current_user_can( 'manage_options' )", $body );
		$this->assertStringContainsString( 'self::location_payload_for_viewer(', $body );
		$this->assertStringContainsString( 'self::location_no_store_headers()', $body );
		$this->assertStringContainsString( '$response->header( $name, $value )', $body );
		$this->assertStringContainsString( "'litespeed_control_set_nocache'", $body );
	}
}
