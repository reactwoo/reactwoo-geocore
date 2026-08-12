<?php
/**
 * Cache vary helpers — server-side keys must not trust client cookies.
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-rwgc-cache-compat.php';

final class RWGCCacheCompatTest extends TestCase {

	public function test_country_vary_group_rejects_invalid_codes(): void {
		$this->assertSame( '', RWGC_Cache_Compat::country_vary_group( '' ) );
		$this->assertSame( '', RWGC_Cache_Compat::country_vary_group( 'U' ) );
		$this->assertSame( '', RWGC_Cache_Compat::country_vary_group( '12' ) );
		// Matches Core ISO2 truncation elsewhere (USA → US).
		$this->assertSame( 'rwgc_cc_US', RWGC_Cache_Compat::country_vary_group( 'USA' ) );
	}

	public function test_country_vary_group_normalizes_iso2(): void {
		$this->assertSame( 'rwgc_cc_FR', RWGC_Cache_Compat::country_vary_group( 'fr' ) );
		$this->assertSame( 'rwgc_cc_US', RWGC_Cache_Compat::country_vary_group( 'US' ) );
	}

	public function test_page_version_vary_group_uses_base_marker(): void {
		$this->assertSame( 'rwgc_pv_-', RWGC_Cache_Compat::page_version_vary_group( '' ) );
		$this->assertSame( 'rwgc_pv_-', RWGC_Cache_Compat::page_version_vary_group( RWGC_Cache_Compat::VERSION_COOKIE_BASE ) );
		$this->assertSame( 'rwgc_pv_campaign_a', RWGC_Cache_Compat::page_version_vary_group( 'Campaign_A' ) );
	}

	public function test_resolve_page_version_vary_key_defaults_to_base_without_routing_class(): void {
		$this->assertSame(
			RWGC_Cache_Compat::VERSION_COOKIE_BASE,
			RWGC_Cache_Compat::resolve_page_version_vary_key()
		);
	}
}
