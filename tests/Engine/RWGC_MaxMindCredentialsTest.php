<?php

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WP_Error', false ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
	class WP_Error {
		/** @var string */
		public $code;
		/** @var string */
		public $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * MaxMind credential handling (401 on reactwoo.com, 2026-10-17).
 *
 * @covers RWGC_MaxMind
 * @covers RWGC_Settings
 */
final class RWGC_MaxMindCredentialsTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php';
	}

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_options'] );
	}

	public function test_invisible_characters_from_mobile_paste_are_removed(): void {
		$key = 'AbC123_fakeFakeFakeFakeFakeFakeFakeFake00_mmk';
		$this->assertSame( $key, RWGC_MaxMind::normalize_credential( "\u{200B}" . $key . "\u{00A0} \n" ) );
		$this->assertSame( '123456', RWGC_MaxMind::normalize_credential( " 123\u{FEFF}456\u{00A0}" ) );
	}

	public function test_case_is_preserved(): void {
		$this->assertSame( 'AbCdEf_GhIj', RWGC_MaxMind::normalize_credential( 'AbCdEf_GhIj' ) );
	}

	public function test_account_id_must_be_numeric(): void {
		$this->assertTrue( RWGC_MaxMind::validate_credentials( '123456', 'AbC123_key_mmk' ) );
		$error = RWGC_MaxMind::validate_credentials( 'me@example.com', 'AbC123_key_mmk' );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'rwgc_bad_account_id', $error->get_error_code() );
	}

	public function test_license_key_with_foreign_characters_is_rejected_before_calling_maxmind(): void {
		$error = RWGC_MaxMind::validate_credentials( '123456', 'AbC123•••' );
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'rwgc_bad_license_key', $error->get_error_code() );
	}

	public function test_settings_sanitize_normalizes_credentials(): void {
		$out = RWGC_Settings::sanitize_settings(
			array(
				'maxmind_account_id'  => "123456\u{00A0}",
				'maxmind_license_key' => "\u{200B}AbC123_key_mmk ",
			)
		);
		$this->assertSame( '123456', $out['maxmind_account_id'] );
		$this->assertSame( 'AbC123_key_mmk', $out['maxmind_license_key'] );
	}

	public function test_general_settings_form_does_not_wipe_maxmind_credentials(): void {
		$keys = RWGC_Settings::get_preserved_keys();
		foreach ( array( 'maxmind_account_id', 'maxmind_license_key', 'auto_update_db', 'db_file_path', 'db_last_updated' ) as $key ) {
			$this->assertContains( $key, $keys );
		}
		$page = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/settings-page.php' );
		$this->assertStringNotContainsString( '[maxmind_license_key]', $page, 'Settings → General has no MaxMind fields, so sanitize must preserve them.' );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php' );
		$this->assertStringContainsString( 'get_preserved_keys() as $key', $src );
	}

	public function test_saving_new_credentials_clears_the_stale_error(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php' );
		$this->assertMatchesRegularExpression( "/!== \\\$previous \\) \\{\\s*\\\$settings\\['db_last_error'\\] = '';/", $src );
	}

	public function test_credential_inputs_disable_mobile_autocapitalize_and_autofill(): void {
		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/integrations-maxmind-page.php' );
		$this->assertSame( 2, substr_count( $view, 'autocapitalize="none" autocorrect="off" spellcheck="false"' ) );
		$this->assertStringContainsString( 'name="maxmind_account_id" inputmode="numeric" autocomplete="off"', $view );
	}

	public function test_download_uses_basic_auth_and_does_not_forward_it_on_redirect(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php' );
		$this->assertStringContainsString( "https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz", $src );
		$this->assertStringContainsString( "'Basic ' . base64_encode( \$account_id . ':' . \$license )", $src );
		$this->assertStringContainsString( "'redirection' => 0", $src );
	}
}
