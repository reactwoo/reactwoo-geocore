<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Variant
 */
final class RWGC_VariantTest extends TestCase {

	public function test_matches_country_include_list(): void {
		$v = new RWGC_Variant(
			array(
				'conditions' => array(
					'countries_include' => array( 'GB', 'IE' ),
				),
			)
		);
		$this->assertTrue( $v->matches( new RWGC_Context( 'GB' ) ) );
		$this->assertFalse( $v->matches( new RWGC_Context( 'US' ) ) );
	}

	public function test_matches_legacy_country_key(): void {
		$v = new RWGC_Variant(
			array(
				'conditions' => array(
					'country' => 'DE',
				),
			)
		);
		$this->assertTrue( $v->matches( new RWGC_Context( 'DE' ) ) );
	}

	public function test_matches_countries_exclude(): void {
		$v = new RWGC_Variant(
			array(
				'conditions' => array(
					'countries_include' => array( 'US', 'CA' ),
					'countries_exclude' => array( 'CA' ),
				),
			)
		);
		$this->assertTrue( $v->matches( new RWGC_Context( 'US' ) ) );
		$this->assertFalse( $v->matches( new RWGC_Context( 'CA' ) ) );
	}

	public function test_empty_conditions_never_matches(): void {
		$v = new RWGC_Variant( array( 'conditions' => array() ) );
		$this->assertFalse( $v->matches( new RWGC_Context( 'US' ) ) );
	}

	public function test_non_context_returns_false(): void {
		$v = new RWGC_Variant(
			array(
				'conditions' => array( 'countries_include' => array( 'US' ) ),
			)
		);
		$this->assertFalse( $v->matches( null ) );
	}
}
