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

	public function test_country_rule_matches_and_time_of_day_does_not() {
		$combined = $this->uk_evening_rule_set();
		$this->assertFalse(
			RWGC_Rule_Evaluator::matches( $combined, $this->uk_evening_snapshot() )
		);
		$country = $combined;
		$country['rules'][0]['conditions'] = array( $combined['rules'][0]['conditions'][0] );
		$this->assertTrue(
			RWGC_Rule_Evaluator::matches( $country, $this->uk_evening_snapshot() )
		);
	}

	public function test_hide_mode_suppresses_when_country_rule_matches() {
		$set         = $this->uk_evening_rule_set();
		$set['mode'] = 'hide';
		$set['rules'][0]['conditions'] = array( $set['rules'][0]['conditions'][0] );
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

	public function test_returning_visitor_matches_when_snapshot_flag_is_true(): void {
		RWGC_Rule_Evaluator::reset_resolver_cache();
		$set = array(
			'enabled' => true,
			'mode'    => 'show',
			'match'   => 'all',
			'rules'   => array(
				array(
					'id'         => 'rv',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'returning_visitor',
							'operator' => 'is',
							'value'    => array( 'yes' ),
						),
					),
				),
			),
		);
		$returning = new RWGC_Context_Snapshot( array( 'returning_visitor' => true, 'new_visitor' => false ) );
		$fresh     = new RWGC_Context_Snapshot( array( 'returning_visitor' => false, 'new_visitor' => true ) );
		$this->assertFalse( RWGC_Rule_Evaluator::matches( $set, $returning ) );
		$this->assertFalse( RWGC_Rule_Evaluator::matches( $set, $fresh ) );
	}

	public function test_new_visitor_matches_first_visit_snapshot(): void {
		RWGC_Rule_Evaluator::reset_resolver_cache();
		$set = array(
			'enabled' => true,
			'mode'    => 'show',
			'match'   => 'all',
			'rules'   => array(
				array(
					'id'         => 'nv',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'new_visitor',
							'operator' => 'is',
							'value'    => true,
						),
					),
				),
			),
		);
		$fresh     = new RWGC_Context_Snapshot( array( 'returning_visitor' => false ) );
		$returning = new RWGC_Context_Snapshot( array( 'returning_visitor' => true, 'new_visitor' => false ) );
		$this->assertFalse( RWGC_Rule_Evaluator::matches( $set, $fresh ) );
		$this->assertFalse( RWGC_Rule_Evaluator::matches( $set, $returning ) );
	}

	public function test_sanitize_keeps_returning_visitor_without_pro(): void {
		$raw = array(
			'enabled' => true,
			'mode'    => 'show',
			'match'   => 'all',
			'rules'   => array(
				array(
					'id'         => 'rv',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'returning_visitor',
							'operator' => 'is',
							'value'    => array( 'yes' ),
						),
					),
				),
			),
		);
		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
		$this->assertIsArray( $set );
		$this->assertSame( 'returning_visitor', $set['rules'][0]['conditions'][0]['type'] );
	}
}
