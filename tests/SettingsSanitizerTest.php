<?php
/**
 * Tests for settings sanitization.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-rwgc-settings.php';

/**
 * @covers RWGC_Settings
 */
final class SettingsSanitizerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_options'] = array();
	}

	public function test_general_settings_save_preserves_omitted_maxmind_and_database_metadata(): void {
		$stored = array_merge(
			RWGC_Settings::get_defaults(),
			array(
				'maxmind_account_id'  => '12345',
				'maxmind_license_key' => 'existing-license',
				'auto_update_db'      => 0,
				'db_last_updated'     => '2026-06-04T10:00:00+00:00',
				'db_file_path'        => '/srv/geolite/GeoLite2-Country.mmdb',
				'db_last_error'       => 'previous error',
				'migration_completed' => 1,
			)
		);
		update_option( RWGC_Settings::OPTION_KEY, $stored );

		$sanitized = RWGC_Settings::sanitize_settings(
			array(
				'enabled'           => 1,
				'cache_enabled'     => 1,
				'cache_ttl'         => 600,
				'fallback_country'  => 'US',
				'fallback_currency' => 'USD',
				'rest_enabled'      => 1,
			)
		);

		$this->assertSame( '12345', $sanitized['maxmind_account_id'] );
		$this->assertSame( 'existing-license', $sanitized['maxmind_license_key'] );
		$this->assertSame( 0, $sanitized['auto_update_db'] );
		$this->assertSame( '2026-06-04T10:00:00+00:00', $sanitized['db_last_updated'] );
		$this->assertSame( '/srv/geolite/GeoLite2-Country.mmdb', $sanitized['db_file_path'] );
		$this->assertSame( 'previous error', $sanitized['db_last_error'] );
		$this->assertSame( 1, $sanitized['migration_completed'] );
	}

	public function test_explicit_maxmind_fields_can_still_be_cleared_from_integration_save(): void {
		update_option(
			RWGC_Settings::OPTION_KEY,
			array_merge(
				RWGC_Settings::get_defaults(),
				array(
					'maxmind_account_id'  => '12345',
					'maxmind_license_key' => 'existing-license',
					'auto_update_db'      => 1,
				)
			)
		);

		$sanitized = RWGC_Settings::sanitize_settings(
			array(
				'enabled'             => 1,
				'maxmind_account_id'  => '',
				'maxmind_license_key' => '',
				'auto_update_db'      => 0,
				'cache_enabled'       => 1,
				'cache_ttl'           => 600,
				'fallback_country'    => 'US',
				'fallback_currency'   => 'USD',
				'rest_enabled'        => 1,
			)
		);

		$this->assertSame( '', $sanitized['maxmind_account_id'] );
		$this->assertSame( '', $sanitized['maxmind_license_key'] );
		$this->assertSame( 0, $sanitized['auto_update_db'] );
	}
}
