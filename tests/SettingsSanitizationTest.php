<?php
/**
 * Regression tests for settings sanitization.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-rwgc-settings.php';

/**
 * @covers RWGC_Settings
 */
class SettingsSanitizationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_options'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_options'] );
		parent::tearDown();
	}

	public function test_general_settings_save_preserves_maxmind_and_database_fields() {
		update_option(
			RWGC_Settings::OPTION_KEY,
			array(
				'enabled'             => 1,
				'maxmind_account_id'  => '12345',
				'maxmind_license_key' => 'license-key',
				'auto_update_db'      => 1,
				'cache_enabled'       => 1,
				'cache_ttl'           => 7200,
				'fallback_country'    => 'US',
				'fallback_currency'   => 'USD',
				'rest_enabled'        => 1,
				'debug_mode'          => 0,
				'db_last_updated'     => '2026-06-03T10:00:00+00:00',
				'db_file_path'        => '/tmp/GeoLite2-Country.mmdb',
				'db_last_error'       => '',
				'migration_completed' => 1,
			)
		);

		$sanitized = RWGC_Settings::sanitize_settings(
			array(
				'enabled'           => '1',
				'cache_enabled'     => '1',
				'cache_ttl'         => '3600',
				'fallback_country'  => 'US',
				'fallback_currency' => 'USD',
				'rest_enabled'      => '1',
			)
		);

		$this->assertSame( '12345', $sanitized['maxmind_account_id'] );
		$this->assertSame( 'license-key', $sanitized['maxmind_license_key'] );
		$this->assertSame( 1, $sanitized['auto_update_db'] );
		$this->assertSame( '2026-06-03T10:00:00+00:00', $sanitized['db_last_updated'] );
		$this->assertSame( '/tmp/GeoLite2-Country.mmdb', $sanitized['db_file_path'] );
		$this->assertSame( 1, $sanitized['migration_completed'] );
	}

	public function test_visible_general_settings_checkboxes_can_still_be_cleared() {
		update_option(
			RWGC_Settings::OPTION_KEY,
			array(
				'enabled'      => 1,
				'cache_enabled' => 1,
				'rest_enabled' => 1,
				'debug_mode'   => 1,
			)
		);

		$sanitized = RWGC_Settings::sanitize_settings(
			array(
				'cache_ttl'          => '3600',
				'fallback_country'   => 'US',
				'fallback_currency'  => 'USD',
			)
		);

		$this->assertSame( 0, $sanitized['enabled'] );
		$this->assertSame( 0, $sanitized['cache_enabled'] );
		$this->assertSame( 0, $sanitized['rest_enabled'] );
		$this->assertSame( 0, $sanitized['debug_mode'] );
	}
}
