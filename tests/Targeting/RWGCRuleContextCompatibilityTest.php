<?php
/**
 * Rule/context compatibility helper tests.
 *
 * @package ReactWooGeoCore
 */

use PHPUnit\Framework\TestCase;

/**
 * RWGC_Rule_Context_Compatibility tests.
 */
class RWGC_Rule_Context_Compatibility_Test extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'RWGC_Rule_Context_Compatibility', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-rule-context-compatibility.php';
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function product_page_rule_set() {
		return array(
			'rules' => array(
				array(
					'conditions' => array(
						array(
							'type'     => 'page_type',
							'operator' => 'in',
							'value'    => array( 'product' ),
						),
					),
				),
			),
		);
	}

	/**
	 * @return void
	 */
	public function test_country_only_rule_is_compatible_everywhere() {
		$set = array(
			'rules' => array(
				array(
					'conditions' => array(
						array(
							'type'     => 'country',
							'operator' => 'in',
							'value'    => array( 'IE' ),
						),
					),
				),
			),
		);
		$result = RWGC_Rule_Context_Compatibility::evaluate(
			$set,
			array(
				'page_type' => 'homepage',
			)
		);
		$this->assertSame( 'compatible', $result['status'] );
		$this->assertSame( 'Any content', $result['scope_summary'] );
	}

	/**
	 * @return void
	 */
	public function test_product_rule_incompatible_on_homepage() {
		$result = RWGC_Rule_Context_Compatibility::evaluate(
			$this->product_page_rule_set(),
			array(
				'page_type' => 'homepage',
			)
		);
		$this->assertSame( 'incompatible', $result['status'] );
		$this->assertNotEmpty( $result['reasons'] );
		$this->assertStringContainsString( 'Product pages', $result['scope_summary'] );
	}

	/**
	 * @return void
	 */
	public function test_product_rule_compatible_on_product_context() {
		$result = RWGC_Rule_Context_Compatibility::evaluate(
			$this->product_page_rule_set(),
			array(
				'page_type' => 'product',
			)
		);
		$this->assertSame( 'compatible', $result['status'] );
	}

	/**
	 * @return void
	 */
	public function test_uri_constraint_warns_when_url_unknown() {
		$set = array(
			'rules' => array(
				array(
					'conditions' => array(
						array(
							'type'     => 'request_uri',
							'operator' => 'contains',
							'value'    => array( '/winter-sale' ),
						),
					),
				),
			),
		);
		$result = RWGC_Rule_Context_Compatibility::evaluate( $set, array( 'request_uri' => '' ) );
		$this->assertSame( 'warning', $result['status'] );
	}

	/**
	 * @return void
	 */
	public function test_assignment_visibility_modes() {
		if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
		}
		$this->assertFalse( rwgc_visibility_mode_allows_render( 'show_if', false ) );
		$this->assertTrue( rwgc_visibility_mode_allows_render( 'show_if', true ) );
		$this->assertTrue( rwgc_visibility_mode_allows_render( 'hide_if', false ) );
		$this->assertFalse( rwgc_visibility_mode_allows_render( 'hide_if', true ) );
	}
}
