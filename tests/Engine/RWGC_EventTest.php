<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Event
 */
final class RWGC_EventTest extends TestCase {

	public function test_to_array_round_trip(): void {
		$e = new RWGC_Event(
			array(
				'event_type'    => RWGC_Event::TYPE_CLICK,
				'variant_id'    => 'legacy_variant_12',
				'experiment_id' => 'exp_a',
				'context'       => array( 'country_iso2' => 'US', 'extra' => array() ),
				'subject'       => array( 'page_id' => 5 ),
				'meta'          => array( 'x' => 1 ),
			)
		);
		$copy = RWGC_Event::from_array( $e->to_array() );
		$this->assertSame( $e->to_array(), $copy->to_array() );
	}

	public function test_from_route_decision_maps_variant_and_subject(): void {
		$decision = array(
			'page_id'        => 10,
			'target_page_id' => 20,
			'reason'         => 'master_country_variant_match',
			'country'        => 'US',
			'variant_id'     => 'legacy_variant_20',
		);
		$ctx = new RWGC_Context( 'US' );
		$e   = RWGC_Event::from_route_decision( $decision, $ctx, RWGC_Event::TYPE_IMPRESSION );
		$this->assertSame( RWGC_Event::TYPE_IMPRESSION, $e->event_type );
		$this->assertSame( 'legacy_variant_20', $e->variant_id );
		$this->assertSame( 'US', $e->subject['country'] );
		$this->assertSame( 10, $e->subject['page_id'] );
		$this->assertSame( 'US', $e->context['country_iso2'] );
	}

	public function test_route_redirect_event_type_constant(): void {
		$this->assertSame( 'route_redirect', RWGC_Event::TYPE_ROUTE_REDIRECT );
	}

	public function test_known_event_types_includes_core_slugs(): void {
		$types = RWGC_Event::known_event_types();
		$this->assertContains( 'impression', $types );
		$this->assertContains( 'route_redirect', $types );
	}
}
