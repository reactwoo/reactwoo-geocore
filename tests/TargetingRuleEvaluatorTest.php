<?php
/**
 * PHPUnit coverage for portable rule-set evaluation (mirrors tests/test-rwgc-rule-evaluator.php).
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-context-snapshot.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-rule-evaluator.php';

/**
 * @covers RWGC_Rule_Evaluator
 */
class TargetingRuleEvaluatorTest extends TestCase {

	/**
	 * @return RWGC_Context_Snapshot
	 */
	private function uk_evening_snapshot() {
		return new RWGC_Context_Snapshot(
			array(
				'country'     => 'GB',
				'campaign'    => 'spring_sale',
				'device_type' => 'mobile',
				'time_of_day' => 'evening',
				'day_of_week' => 'saturday',
				'language'    => 'en',
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function uk_evening_rule_set() {
		return array(
			'enabled' => true,
			'mode'    => 'show',
			'match'   => 'any',
			'rules'   => array(
				array(
					'id'         => 'r1',
					'label'      => 'UK evening',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'country',
							'operator' => 'in',
							'value'    => array( 'GB', 'IE' ),
						),
						array(
							'type'     => 'time_of_day',
							'operator' => 'in',
							'value'    => array( 'evening', 'night' ),
						),
					),
				),
			),
		);
	}

	public function test_uk_evening_matches_show_mode() {
		$this->assertTrue(
			RWGC_Rule_Evaluator::matches( $this->uk_evening_rule_set(), $this->uk_evening_snapshot() )
		);
	}

	public function test_hide_mode_suppresses_when_rule_matches() {
		$set          = $this->uk_evening_rule_set();
		$set['mode']  = 'hide';
		$this->assertFalse(
			RWGC_Rule_Evaluator::should_render_content( $set, $this->uk_evening_snapshot() )
		);
	}

	public function test_empty_country_list_matches_all() {
		$set = array(
			'enabled' => true,
			'mode'    => 'show',
			'match'   => 'all',
			'rules'   => array(
				array(
					'id'         => 'c',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'country',
							'operator' => 'in',
							'value'    => array(),
						),
					),
				),
			),
		);
		$this->assertTrue( RWGC_Rule_Evaluator::matches( $set, $this->uk_evening_snapshot() ) );
	}
}
