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
