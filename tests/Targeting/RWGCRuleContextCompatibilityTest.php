<?php
/**
 * Rule/context compatibility helper tests.
 *
 * @package ReactWooGeoCore
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wc_get_page_id' ) ) {
	/**
	 * @param string $page Page type.
	 * @return int
	 */
	function wc_get_page_id( $page ) {
		return isset( $GLOBALS['rwgc_test_wc_page_ids'][ $page ] )
			? (int) $GLOBALS['rwgc_test_wc_page_ids'][ $page ]
			: -1;
	}
}

/**
 * RWGC_Rule_Context_Compatibility tests.
 */
class RWGCRuleContextCompatibilityTest extends TestCase {

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
	public function test_woocommerce_cart_and_checkout_pages_keep_specific_page_types() {
		$GLOBALS['rwgc_test_wc_page_ids'] = array(
			'shop'     => 10,
			'cart'     => 20,
			'checkout' => 30,
		);

		$method = new ReflectionMethod( RWGC_Rule_Context_Compatibility::class, 'infer_page_type_for_post' );
		$method->setAccessible( true );

		$cart            = new class() extends WP_Post {
			/** @var string */
			public $post_type = 'page';
		};
		$cart->ID        = 20;
		$checkout        = new class() extends WP_Post {
			/** @var string */
			public $post_type = 'page';
		};
		$checkout->ID    = 30;

		$this->assertSame( 'cart', $method->invoke( null, $cart ) );
		$this->assertSame( 'checkout', $method->invoke( null, $checkout ) );

		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-visibility-rule-tester.php';
		$this->assertSame( 'cart', RWGC_Visibility_Rule_Tester::page_type_for_post_public( $cart ) );
		$this->assertSame( 'checkout', RWGC_Visibility_Rule_Tester::page_type_for_post_public( $checkout ) );
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
