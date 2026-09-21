<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Context_Attribution
 */
final class RWGC_ContextAttributionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$_GET    = array();
		$_COOKIE = array();
		RWGC_Context_Attribution::reset();
	}

	public function test_first_visit_is_not_returning_even_with_utm(): void {
		$_GET['utm_source'] = 'google';
		$payload            = RWGC_Context_Attribution::resolve();
		$this->assertFalse( $payload['returning_visitor'] );
	}

	public function test_prior_returning_cookie_marks_returning_visitor(): void {
		$_COOKIE['rwgc_returning'] = '1700000000';
		$payload                   = RWGC_Context_Attribution::resolve();
		$this->assertTrue( $payload['returning_visitor'] );
	}

	public function test_prior_first_touch_cookie_marks_returning_visitor(): void {
		$_COOKIE['rwgc_ft'] = rawurlencode(
			wp_json_encode(
				array(
					'source'   => 'newsletter',
					'medium'   => 'email',
					'campaign' => 'spring',
					'content'  => '',
					'term'     => '',
					'gclid'    => '',
				)
			)
		);
		$payload = RWGC_Context_Attribution::resolve();
		$this->assertTrue( $payload['returning_visitor'] );
	}

	public function test_second_resolve_in_same_request_stays_new(): void {
		$first  = RWGC_Context_Attribution::resolve();
		$second = RWGC_Context_Attribution::resolve();
		$this->assertFalse( $first['returning_visitor'] );
		$this->assertFalse( $second['returning_visitor'] );
	}

	public function test_second_http_request_in_first_visit_stays_new(): void {
		$_GET['utm_source'] = 'google';
		$first              = RWGC_Context_Attribution::resolve();
		$this->assertFalse( $first['returning_visitor'] );
		$this->assertSame( 'n', $_COOKIE['rwgc_rv'] ?? '' );
		$this->assertNotEmpty( $_COOKIE['rwgc_ft'] ?? '' );

		// Next document or Store API / wc-ajax request in the same visit.
		$_COOKIE['rwgc_returning'] = (string) time();
		RWGC_Context_Attribution::reset();
		$second = RWGC_Context_Attribution::resolve();
		$this->assertFalse( $second['returning_visitor'] );
	}

	public function test_next_session_after_first_visit_is_returning(): void {
		$first = RWGC_Context_Attribution::resolve();
		$this->assertFalse( $first['returning_visitor'] );

		$_COOKIE['rwgc_returning'] = (string) time();
		unset( $_COOKIE['rwgc_rv'] );
		RWGC_Context_Attribution::reset();
		$next = RWGC_Context_Attribution::resolve();
		$this->assertTrue( $next['returning_visitor'] );
	}

	public function test_resolve_uses_request_values_for_attribution_fields(): void {
		$_GET['utm_source']   = 'google';
		$_GET['utm_medium']   = 'cpc';
		$_GET['utm_campaign'] = 'uk_launch';
		$_GET['utm_content']  = 'hero_a';
		$_GET['utm_term']     = 'enterprise crm';
		$_GET['gclid']        = 'abc123';

		$payload = RWGC_Context_Attribution::resolve();

		$this->assertSame( 'google', $payload['source'] );
		$this->assertSame( 'cpc', $payload['medium'] );
		$this->assertSame( 'uk_launch', $payload['campaign'] );
		$this->assertSame( 'hero_a', $payload['content'] );
		$this->assertSame( 'enterprise crm', $payload['term'] );
		$this->assertSame( 'abc123', $payload['gclid'] );
		$this->assertFalse( empty( $payload['first_touch']['source'] ) );
		$this->assertFalse( empty( $payload['session_touch']['source'] ) );
	}

	public function test_resolve_reads_google_ads_campaign_id_from_request(): void {
		$_GET['gclid']       = 'abc123';
		$_GET['campaignid']  = '9876543210';
		$_GET['utm_campaign'] = 'ignored_when_id_present';

		$payload = RWGC_Context_Attribution::resolve();

		$this->assertSame( 'abc123', $payload['gclid'] );
		$this->assertSame( '9876543210', $payload['campaign_id'] );
		$this->assertSame( 'ignored_when_id_present', $payload['campaign'] );
	}

	public function test_resolve_uses_cookie_snapshot_when_request_missing(): void {
		$_COOKIE['rwgc_ft'] = rawurlencode(
			wp_json_encode(
				array(
					'source'   => 'newsletter',
					'medium'   => 'email',
					'campaign' => 'spring',
					'content'  => 'header_cta',
					'term'     => '',
					'gclid'    => '',
				)
			)
		);
		$_COOKIE['rwgc_st'] = rawurlencode(
			wp_json_encode(
				array(
					'source'   => 'meta',
					'medium'   => 'paid_social',
					'campaign' => 'remarketing',
					'content'  => '',
					'term'     => '',
					'gclid'    => '',
				)
			)
		);

		$payload = RWGC_Context_Attribution::resolve();

		$this->assertSame( 'meta', $payload['source'] );
		$this->assertSame( 'paid_social', $payload['medium'] );
		$this->assertSame( 'remarketing', $payload['campaign'] );
		$this->assertSame( 'newsletter', $payload['first_touch']['source'] );
	}
}
