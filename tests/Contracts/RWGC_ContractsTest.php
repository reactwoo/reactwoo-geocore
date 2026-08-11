<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Schema
 * @covers RWGC_Contract_Manifest
 * @covers RWGC_Contract_Audience
 * @covers RWGC_Contract_Capability
 * @covers RWGC_Contract_Context
 * @covers RWGC_Contract_Condition
 * @covers RWGC_Contract_Entitlement
 * @covers RWGC_Contract_Event
 * @covers RWGC_Contract_Variant
 */
final class RWGC_ContractsTest extends TestCase {

	public function test_schema_version_is_one(): void {
		$this->assertSame( 1, RWGC_Schema::VERSION );
		$this->assertSame( 1, RWGC_Contracts::schema_version() );
	}

	public function test_legacy_condition_alias_to_capability_id(): void {
		$this->assertSame( 'geo.country', RWGC_Schema::normalize_capability_id( 'country' ) );
		$this->assertSame( 'visitor.device', RWGC_Schema::normalize_capability_id( 'device' ) );
		$this->assertSame( 'geo.country', RWGC_Schema::normalize_capability_id( 'geo.country' ) );
	}

	public function test_unknown_capability_id_rejected_by_normalizer(): void {
		$this->assertSame( '', RWGC_Schema::normalize_capability_id( 'Not Valid!' ) );
		$this->assertSame( '', RWGC_Schema::normalize_capability_id( 'single' ) );
	}

	public function test_condition_round_trip_and_alias(): void {
		$c = RWGC_Contract_Condition::from_array(
			array(
				'capability' => 'country',
				'operator'   => 'equals',
				'value'      => 'GB',
				'ui_hint'    => 'chip',
			)
		);
		$this->assertSame( 'geo.country', $c->capability() );
		$arr = $c->to_array();
		$this->assertSame( 'geo.country', $arr['capability'] );
		$this->assertSame( 'chip', $arr['ui_hint'] );
	}

	public function test_invalid_condition_rejected(): void {
		$this->expectException( RWGC_Contract_Exception::class );
		RWGC_Contract_Condition::from_array(
			array(
				'capability' => '!!!',
				'operator'   => 'equals',
				'value'      => 1,
			)
		);
	}

	public function test_capability_requires_dotted_id_and_type(): void {
		$cap = RWGC_Contract_Capability::from_array(
			array(
				'id'       => 'geo.country',
				'type'     => 'condition',
				'label'    => 'Country',
				'provider' => 'reactwoo-geocore',
			)
		);
		$this->assertSame( 'geo.country', $cap->id() );

		$this->expectException( RWGC_Contract_Exception::class );
		RWGC_Contract_Capability::from_array(
			array(
				'id'    => 'geo.country',
				'type'  => 'widget',
				'label' => 'Bad',
			)
		);
	}

	public function test_context_normalizes_keys_and_keeps_unknown(): void {
		$ctx = RWGC_Contract_Context::from_array(
			array(
				'country'        => 'GB',
				'visitor.device' => 'mobile',
				'_debug'         => true,
			)
		);
		$this->assertSame( 'GB', $ctx->get( 'geo.country' ) );
		$this->assertSame( 'GB', $ctx->get( 'country' ) );
		$this->assertSame( 'mobile', $ctx->get( 'visitor.device' ) );
		$this->assertTrue( $ctx->extras()['_debug'] );
	}

	public function test_audience_and_manifest_serialisation(): void {
		$manifest = RWGC_Contract_Manifest::from_array(
			array(
				'schema'      => '1.0',
				'revision'    => 142,
				'site'        => 'site_123',
				'future_flag' => array( 'enabled' => true ),
				'audiences'   => array(
					array(
						'id'         => 'aud_uk_paid_mobile',
						'name'       => 'UK Paid Mobile',
						'conditions' => array(
							'all' => array(
								array(
									'capability' => 'geo.country',
									'operator'   => 'equals',
									'value'      => 'GB',
								),
								array(
									'capability' => 'visitor.device',
									'operator'   => 'equals',
									'value'      => 'mobile',
								),
							),
						),
					),
				),
				'experiences' => array(
					array(
						'id'          => 'exp_summer',
						'name'        => 'UK Summer',
						'audience_id' => 'aud_uk_paid_mobile',
						'slot_id'     => 'slot_home_hero',
						'variant_id'  => 'variant_b',
						'status'      => 'active',
						'priority'    => 50,
					),
				),
				'variants'    => array(
					array(
						'id'      => 'variant_b',
						'type'    => 'content',
						'content' => array( 'heading' => 'Made for British Summer' ),
					),
				),
				'experiments' => array(
					array(
						'id'       => 'exp_ab',
						'control'  => 'variant_original',
						'variants' => array(
							array(
								'id'         => 'variant_b',
								'allocation' => 50,
							),
						),
					),
				),
				'goals'       => array(
					array(
						'id'    => 'goal_purchase',
						'type'  => 'commerce.purchase',
						'value' => 'revenue',
					),
				),
				'slots'       => array(
					array(
						'id'      => 'slot_home_hero',
						'name'    => 'Homepage Hero',
						'page'    => '/',
						'adapter' => 'elementor',
					),
				),
			)
		);

		$this->assertSame( 142, $manifest->revision() );
		$this->assertSame( 'site_123', $manifest->site() );
		$this->assertCount( 1, $manifest->audiences() );
		$this->assertTrue( $manifest->extras()['future_flag']['enabled'] );

		$round = RWGC_Contract_Manifest::from_json( $manifest->to_json() );
		$this->assertSame( 142, $round->revision() );
		$this->assertSame( 'aud_uk_paid_mobile', $round->audiences()[0]->id() );
		$this->assertTrue( $round->extras()['future_flag']['enabled'] );
		$this->assertSame( 1, $round->to_array()['reactwoo_schema_version'] );
	}

	public function test_manifest_rejects_unsupported_major_schema(): void {
		$this->expectException( RWGC_Contract_Exception::class );
		RWGC_Contract_Manifest::from_array(
			array(
				'schema'   => '2.0',
				'revision' => 1,
				'site'     => 'site_1',
			)
		);
	}

	public function test_manifest_rejects_missing_revision(): void {
		$this->expectException( RWGC_Contract_Exception::class );
		RWGC_Contract_Manifest::from_array(
			array(
				'schema' => '1.0',
				'site'   => 'site_1',
			)
		);
	}

	public function test_entitlement_defaults(): void {
		$e = RWGC_Contract_Entitlement::from_array(
			array(
				'key'   => 'cloud.commerce',
				'limit' => 3,
			)
		);
		$this->assertTrue( $e->allowed() );
		$this->assertSame( 'standalone', $e->to_array()['source'] );
		$this->assertSame( 3, $e->to_array()['limit'] );
	}

	public function test_event_and_variant_types(): void {
		$event = RWGC_Contract_Event::from_array(
			array(
				'type'        => 'goal.purchase',
				'experience'  => 'exp_summer',
				'variant'     => 'variant_b',
				'value'       => 89.99,
				'visitor_id'  => 'anon_1',
			)
		);
		$this->assertSame( 'anon_1', $event->to_array()['anonymous_visitor_id'] );

		$variant = RWGC_Contract_Variant::from_array(
			array(
				'id'        => 'v1',
				'type'      => 'reactwoo_component',
				'component' => 'hero',
				'props'     => array( 'heading' => 'Hi' ),
			)
		);
		$this->assertSame( 'reactwoo_component', $variant->type() );

		$this->expectException( RWGC_Contract_Exception::class );
		RWGC_Contract_Variant::from_array(
			array(
				'id'   => 'v2',
				'type' => 'unknown_kind',
			)
		);
	}

	public function test_nested_condition_groups(): void {
		$group = RWGC_Contract_Condition_Group::from_array(
			array(
				'any' => array(
					array(
						'all' => array(
							array(
								'capability' => 'geo.country',
								'operator'   => 'equals',
								'value'      => 'GB',
							),
						),
					),
					array(
						'capability' => 'visitor.device',
						'operator'   => 'equals',
						'value'      => 'mobile',
					),
				),
			)
		);
		$this->assertSame( 'any', $group->match() );
		$this->assertCount( 2, $group->items() );
		$this->assertInstanceOf( RWGC_Contract_Condition_Group::class, $group->items()[0] );
	}
}
