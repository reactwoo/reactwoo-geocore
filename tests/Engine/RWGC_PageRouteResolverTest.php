<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Page_Route_Resolver
 */
final class RWGC_PageRouteResolverTest extends TestCase {

	public function test_master_match_sets_variant_id(): void {
		$bundle                  = new RWGC_Page_Route_Bundle();
		$bundle->page_id         = 100;
		$bundle->enabled         = true;
		$bundle->role            = 'master';
		$bundle->variants        = array(
			new RWGC_Variant(
				array(
					'id'             => 'legacy_variant_200',
					'target_page_id' => 200,
					'priority'       => 50,
					'conditions'     => array( 'countries_include' => array( 'US' ) ),
				)
			),
		);
		$ctx = new RWGC_Context( 'US' );
		$d   = RWGC_Page_Route_Resolver::resolve( $bundle, $ctx );
		$this->assertSame( 'master_country_variant_match', $d['reason'] );
		$this->assertSame( 200, $d['target_page_id'] );
		$this->assertSame( 'legacy_variant_200', $d['variant_id'] );
	}

	public function test_variant_page_sets_legacy_variant_id(): void {
		$bundle                          = new RWGC_Page_Route_Bundle();
		$bundle->page_id                 = 300;
		$bundle->enabled                 = true;
		$bundle->role                    = 'variant';
		$bundle->variant_country_iso2    = 'GB';
		$bundle->master_page_id          = 100;
		$ctx                             = new RWGC_Context( 'US' );
		$d                               = RWGC_Page_Route_Resolver::resolve( $bundle, $ctx );
		$this->assertSame( 'legacy_variant_300', $d['variant_id'] );
		$this->assertSame( 'variant_fallback_to_master', $d['reason'] );
		$this->assertSame( 100, $d['target_page_id'] );
	}
}
