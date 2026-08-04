<?php
/**
 * Frontend Atomic countries raw-settings fallback.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/integrations/elementor/class-rwgc-elementor-frontend.php';

final class RWGCElementorFrontendAtomicCountriesFallbackTest extends TestCase {

	/**
	 * @param string               $method Method name.
	 * @param array<int, mixed>    $args   Args.
	 * @return mixed
	 */
	private function call_private( $method, array $args = array() ) {
		$ref = new ReflectionMethod( 'RWGC_Elementor_Frontend', $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	public function test_raw_fallback_when_atomic_resolver_drops_legacy_string(): void {
		$atomic = array(
			'egp_enable_geo_targeting' => true,
			'egp_countries'            => null,
		);
		$raw    = array(
			'egp_countries' => array(
				'$$type' => 'string',
				'value'  => 'US,GB',
			),
		);

		$this->assertTrue(
			(bool) $this->call_private( 'atomic_countries_need_raw_fallback', array( $atomic, $raw ) )
		);
	}

	public function test_no_fallback_when_atomic_already_has_countries(): void {
		$atomic = array(
			'egp_countries' => array( 'US', 'GB' ),
		);
		$raw    = array(
			'egp_countries' => array(
				'$$type' => 'string',
				'value'  => 'US,GB',
			),
		);

		$this->assertFalse(
			(bool) $this->call_private( 'atomic_countries_need_raw_fallback', array( $atomic, $raw ) )
		);
	}

	public function test_no_fallback_without_raw_countries(): void {
		$atomic = array(
			'egp_countries' => null,
		);
		$raw    = array(
			'egp_enable_geo_targeting' => true,
		);

		$this->assertFalse(
			(bool) $this->call_private( 'atomic_countries_need_raw_fallback', array( $atomic, $raw ) )
		);
	}
}
