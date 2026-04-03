<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Fallback_Resolver
 */
final class RWGC_FallbackResolverTest extends TestCase {

	public function test_sort_variants_orders_by_priority_desc(): void {
		$low  = new RWGC_Variant( array( 'priority' => 10, 'id' => 'a' ) );
		$high = new RWGC_Variant( array( 'priority' => 90, 'id' => 'b' ) );
		$sorted = RWGC_Fallback_Resolver::sort_variants( array( $low, $high ) );
		$this->assertSame( 90, $sorted[0]->priority );
		$this->assertSame( 10, $sorted[1]->priority );
	}

	public function test_first_matching_variant_prefers_higher_priority(): void {
		$us_only = new RWGC_Variant(
			array(
				'priority'       => 50,
				'target_page_id' => 100,
				'conditions'     => array( 'countries_include' => array( 'US' ) ),
			)
		);
		$us_high = new RWGC_Variant(
			array(
				'priority'       => 100,
				'target_page_id' => 200,
				'conditions'     => array( 'countries_include' => array( 'US' ) ),
			)
		);
		$ctx = new RWGC_Context( 'US' );
		$first = RWGC_Fallback_Resolver::first_matching_variant( array( $us_only, $us_high ), $ctx );
		$this->assertInstanceOf( RWGC_Variant::class, $first );
		$this->assertSame( 200, $first->target_page_id );
	}

	public function test_first_matching_variant_returns_null_when_none_match(): void {
		$v = new RWGC_Variant(
			array(
				'conditions' => array( 'countries_include' => array( 'GB' ) ),
			)
		);
		$this->assertNull(
			RWGC_Fallback_Resolver::first_matching_variant(
				array( $v ),
				new RWGC_Context( 'JP' )
			)
		);
	}
}
