<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Context
 */
final class RWGC_ContextTest extends TestCase {

	public function test_normalizes_valid_iso2(): void {
		$ctx = new RWGC_Context( 'gb' );
		$this->assertSame( 'GB', $ctx->country_iso2 );
		$this->assertSame( array(), $ctx->extra );
	}

	public function test_invalid_country_becomes_empty(): void {
		$ctx = new RWGC_Context( 'X' );
		$this->assertSame( '', $ctx->country_iso2 );
	}

	public function test_extra_bag_preserved(): void {
		$ctx = new RWGC_Context( 'US', array( 'lang' => 'en' ) );
		$this->assertSame( 'US', $ctx->country_iso2 );
		$this->assertSame( array( 'lang' => 'en' ), $ctx->extra );
	}

	public function test_to_snapshot_matches_public_fields(): void {
		$ctx = new RWGC_Context( 'DE', array( 'device' => 'mobile' ) );
		$this->assertSame(
			array(
				'country_iso2' => 'DE',
				'extra'        => array( 'device' => 'mobile' ),
			),
			$ctx->to_snapshot()
		);
	}
}
