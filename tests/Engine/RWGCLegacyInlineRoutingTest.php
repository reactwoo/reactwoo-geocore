<?php
/**
 * Legacy inline master mapping must not be wiped or shadow Suite variants.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-routing.php';

/**
 * @covers RWGC_Routing::route_config_from_meta_box_request
 * @covers RWGC_Routing::master_config_for_suite_variant
 */
final class RWGCLegacyInlineRoutingTest extends TestCase {

	public function test_meta_box_request_preserves_unposted_inline_country_page_id(): void {
		$existing = array(
			'enabled'         => true,
			'role'            => 'master',
			'country_iso2'    => 'US',
			'country_page_id' => 321,
			'default_page_id' => 0,
			'master_page_id'  => 0,
		);

		// Mirrors current UI: country select + role posted, country_page_id omitted.
		$request = array(
			'rwgc_route_enabled'        => '1',
			'rwgc_route_role'           => 'master',
			'rwgc_route_country_iso2'   => 'US',
			'rwgc_route_master_page_id' => '0',
		);

		$config = RWGC_Routing::route_config_from_meta_box_request( $request, $existing );

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 'master', $config['role'] );
		$this->assertSame( 'US', $config['country_iso2'] );
		$this->assertSame( 321, $config['country_page_id'] );
	}

	public function test_meta_box_request_honors_explicit_zero_country_page_id(): void {
		$existing = array(
			'enabled'         => true,
			'role'            => 'master',
			'country_iso2'    => 'US',
			'country_page_id' => 321,
			'default_page_id' => 0,
			'master_page_id'  => 0,
		);

		$request = array(
			'rwgc_route_enabled'         => '1',
			'rwgc_route_role'            => 'master',
			'rwgc_route_country_iso2'    => 'US',
			'rwgc_route_country_page_id' => '0',
			'rwgc_route_master_page_id'  => '0',
		);

		$config = RWGC_Routing::route_config_from_meta_box_request( $request, $existing );
		$this->assertSame( 0, $config['country_page_id'] );
	}

	public function test_suite_variant_clears_same_country_inline_mapping(): void {
		$master = array(
			'enabled'         => true,
			'role'            => 'master',
			'country_iso2'    => 'US',
			'country_page_id' => 123,
			'default_page_id' => 0,
			'master_page_id'  => 0,
		);

		$out = RWGC_Routing::master_config_for_suite_variant( $master, 'US' );

		$this->assertTrue( $out['enabled'] );
		$this->assertSame( 'master', $out['role'] );
		$this->assertSame( '', $out['country_iso2'] );
		$this->assertSame( 0, $out['country_page_id'] );
	}

	public function test_suite_variant_keeps_inline_mapping_for_other_country(): void {
		$master = array(
			'enabled'         => true,
			'role'            => 'master',
			'country_iso2'    => 'US',
			'country_page_id' => 123,
			'default_page_id' => 0,
			'master_page_id'  => 0,
		);

		$out = RWGC_Routing::master_config_for_suite_variant( $master, 'GB' );

		$this->assertSame( 'US', $out['country_iso2'] );
		$this->assertSame( 123, $out['country_page_id'] );
	}
}
