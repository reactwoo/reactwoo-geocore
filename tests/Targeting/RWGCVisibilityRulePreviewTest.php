<?php
/**
 * Free Delivery visibility rule preview + evaluator scenarios.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-context-snapshot.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-rule-evaluator.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-visibility-rule-preview.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-visibility-rule-logic-preview.php';

/**
 * @covers RWGC_Visibility_Rule_Preview
 * @covers RWGC_Visibility_Rule_Logic_Preview
 */
final class RWGCVisibilityRulePreviewTest extends TestCase {

	/**
	 * Portable rule set matching Geo AI converter output for the Free Delivery popup.
	 *
	 * @return array<string,mixed>
	 */
	private function free_delivery_rule_set() {
		return array(
			'enabled' => true,
			'mode'    => 'show_if',
			'match'   => 'all',
			'rules'   => array(
				array(
					'id'         => 'free-delivery',
					'label'      => 'Free Delivery',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'country',
							'operator' => 'in',
							'value'    => array( 'IE', 'GB' ),
						),
						array(
							'type'     => 'device_type',
							'operator' => 'in',
							'value'    => array( 'desktop' ),
						),
						array(
							'type'     => 'page_type',
							'operator' => 'in',
							'value'    => array( 'product' ),
						),
						array(
							'type'     => 'country',
							'operator' => 'not_in',
							'value'    => array( 'FR', 'DE' ),
						),
						array(
							'type'     => 'condition_group',
							'operator' => 'match',
							'value'    => array(
								'match'    => 'any',
								'label'    => 'Google Ads or URL contains /winter-sale',
								'branches' => array(
									array(
										'label'      => 'Google Ads standard UTM',
										'match'      => 'all',
										'conditions' => array(
											array(
												'type'     => 'utm_source',
												'operator' => 'is',
												'value'    => array( 'google' ),
											),
											array(
												'type'     => 'utm_medium',
												'operator' => 'is',
												'value'    => array( 'cpc' ),
											),
										),
									),
									array(
										'label'      => 'URL contains /winter-sale',
										'match'      => 'all',
										'conditions' => array(
											array(
												'type'     => 'request_uri',
												'operator' => 'contains',
												'value'    => array( '/winter-sale' ),
											),
										),
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @param array<string,string> $scenario Scenario inputs.
	 * @return bool
	 */
	private function evaluate_scenario( array $scenario ) {
		$json = wp_json_encode( $this->free_delivery_rule_set() );
		$this->assertIsString( $json );

		$result = RWGC_Visibility_Rule_Preview::evaluate( $json, $scenario );
		$this->assertSame( '', $result['error'] ?? '' );

		return ! empty( $result['matches'] );
	}

	public function test_free_delivery_case_1_google_ads_match(): void {
		$this->assertTrue(
			$this->evaluate_scenario(
				array(
					'country'     => 'IE',
					'device'      => 'desktop',
					'page_type'   => 'product',
					'request_uri' => '/shop/product-a',
					'utm_source'  => 'google',
					'utm_medium'  => 'cpc',
				)
			)
		);
	}

	public function test_free_delivery_case_2_winter_sale_url_match(): void {
		$this->assertTrue(
			$this->evaluate_scenario(
				array(
					'country'     => 'GB',
					'device'      => 'desktop',
					'page_type'   => 'product',
					'request_uri' => '/winter-sale',
				)
			)
		);
	}

	public function test_free_delivery_case_3_mobile_device_no_match(): void {
		$this->assertFalse(
			$this->evaluate_scenario(
				array(
					'country'     => 'IE',
					'device'      => 'mobile',
					'page_type'   => 'product',
					'request_uri' => '/winter-sale',
				)
			)
		);
	}

	public function test_free_delivery_case_4_excluded_country_no_match(): void {
		$this->assertFalse(
			$this->evaluate_scenario(
				array(
					'country'     => 'FR',
					'device'      => 'desktop',
					'page_type'   => 'product',
					'request_uri' => '/winter-sale',
				)
			)
		);
	}

	public function test_free_delivery_case_5_wrong_country_no_match(): void {
		$this->assertFalse(
			$this->evaluate_scenario(
				array(
					'country'     => 'US',
					'device'      => 'desktop',
					'page_type'   => 'product',
					'utm_source'  => 'google',
					'utm_medium'  => 'cpc',
				)
			)
		);
	}

	public function test_free_delivery_case_6_wrong_page_type_no_match(): void {
		$this->assertFalse(
			$this->evaluate_scenario(
				array(
					'country'     => 'GB',
					'device'      => 'desktop',
					'page_type'   => 'homepage',
					'request_uri' => '/winter-sale',
				)
			)
		);
	}

	public function test_free_delivery_case_7_traffic_branch_miss_no_match(): void {
		$this->assertFalse(
			$this->evaluate_scenario(
				array(
					'country'     => 'GB',
					'device'      => 'desktop',
					'page_type'   => 'product',
					'request_uri' => '/normal-product',
					'utm_source'  => 'google',
					'utm_medium'  => 'organic',
				)
			)
		);
	}

	public function test_logic_preview_lists_free_delivery_branches(): void {
		$preview = RWGC_Visibility_Rule_Logic_Preview::build( $this->free_delivery_rule_set(), 'the Free Delivery popup' );

		$this->assertStringContainsString( 'Free Delivery popup', $preview['intro'] );
		$this->assertCount( 5, $preview['lines'] );

		$traffic = $preview['lines'][4];
		$this->assertStringContainsString( 'Traffic matches either', $traffic['text'] );
		$this->assertCount( 2, $traffic['children'] );
		$this->assertStringContainsString( 'utm_source=google', $traffic['children'][0] );
		$this->assertStringContainsString( '/winter-sale', $traffic['children'][1] );
	}
}
