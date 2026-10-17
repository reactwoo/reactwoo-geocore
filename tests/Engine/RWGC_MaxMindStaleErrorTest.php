<?php
/**
 * A stored 401 from an earlier download must not look like a failed save,
 * and a successful download or upload shows only success (1.9.14).
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
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
if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] ) && array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ) {
			return $GLOBALS['rwgc_test_options'][ $key ];
		}
		return $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		$GLOBALS['rwgc_test_options'][ $key ] = $value;
		return true;
	}
}

/**
 * @covers RWGC_MaxMind
 */
final class RWGC_MaxMindStaleErrorTest extends TestCase {

	const OLD_401 = 'MaxMind download failed (HTTP 401 Unauthorized). Response: Your account ID or license key could not be authenticated.';

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_options'] = array(
			'rwgc_cache_version' => 1,
			'rwgc_settings'      => array(
				'maxmind_account_id'  => '123456',
				'maxmind_license_key' => 'AbC123_key_mmk',
				'auto_update_db'      => 1,
				'db_last_error'       => self::OLD_401,
				'db_last_failure_at'  => '2026-10-17T10:00:00+00:00',
			),
		);
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_options'] );
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function stored() {
		return $GLOBALS['rwgc_test_options']['rwgc_settings'];
	}

	/**
	 * The live case: the same credentials saved again (key field still masked).
	 *
	 * @return void
	 */
	public function test_resaving_unchanged_credentials_clears_the_old_401_message(): void {
		$masked = RWGC_MaxMind::mask_license_key( 'AbC123_key_mmk' );
		$saved  = RWGC_MaxMind::apply_credentials_save( RWGC_Settings::get_settings(), '123456', $masked, true );

		$this->assertSame( '', $saved['db_last_error'] );
		$this->assertSame( 'AbC123_key_mmk', $saved['maxmind_license_key'], 'The masked key keeps the stored key.' );
		$this->assertSame( '123456', $saved['maxmind_account_id'] );
		$this->assertSame( '2026-10-17T10:00:00+00:00', $saved['db_last_failure_at'], 'The failure time stays until a download succeeds.' );
		$this->assertSame( 1, $saved['auto_update_db'] );
	}

	/**
	 * @return void
	 */
	public function test_a_save_without_fields_keeps_credentials_and_turns_auto_update_off(): void {
		$saved = RWGC_MaxMind::apply_credentials_save( RWGC_Settings::get_settings(), null, null, false );
		$this->assertSame( 'AbC123_key_mmk', $saved['maxmind_license_key'] );
		$this->assertSame( 0, $saved['auto_update_db'] );
		$this->assertSame( '', $saved['db_last_error'] );
	}

	/**
	 * @return void
	 */
	public function test_save_makes_no_maxmind_request(): void {
		$admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php' );
		$start = strpos( $admin, 'function handle_save_maxmind_settings' );
		$end   = strpos( $admin, 'function render_integrations_woocommerce' );
		$this->assertNotFalse( $start );
		$body = substr( $admin, (int) $start, (int) $end - (int) $start );
		$this->assertStringNotContainsString( 'wp_remote_', $body );
		$this->assertStringNotContainsString( 'update_database', $body );
		$this->assertStringContainsString( 'RWGC_MaxMind::apply_credentials_save(', $body );
	}

	/**
	 * @return void
	 */
	public function test_successful_download_clears_error_and_failure_time(): void {
		RWGC_MaxMind::record_download_success( '/x/GeoLite2-Country.mmdb' );
		$this->assertSame( '', $this->stored()['db_last_error'] );
		$this->assertSame( '', $this->stored()['db_last_failure_at'] );
		$this->assertNotSame( '', $this->stored()['db_last_success_at'] );
		$status = RWGC_MaxMind::get_status();
		$this->assertSame( '', $status['last_error'] );
		$this->assertSame( '', $status['last_failure_at'] );
	}

	/**
	 * @return void
	 */
	public function test_manual_upload_clears_error_and_failure_time(): void {
		RWGC_MaxMind::record_manual_upload( '/x/GeoLite2-Country.mmdb' );
		$this->assertSame( '', $this->stored()['db_last_error'] );
		$this->assertSame( '', $this->stored()['db_last_failure_at'] );
	}

	/**
	 * @return void
	 */
	public function test_maintenance_action_reports_failure_and_success_without_mixing_them(): void {
		$GLOBALS['rwgc_test_options']['rwgc_settings']['maxmind_license_key'] = '';
		$failed = RWGC_MaxMind::run_maintenance_action( 'update_db' );
		$this->assertSame( 'error', $failed['type'] );
		$this->assertSame( 'rwgc_no_credentials', $failed['code'] );
		$this->assertNotSame( '', $this->stored()['db_last_error'] );

		$cleared = RWGC_MaxMind::run_maintenance_action( 'clear_cache' );
		$this->assertSame( 'updated', $cleared['type'] );
		$this->assertSame( 2, (int) $GLOBALS['rwgc_test_options']['rwgc_cache_version'] );
		$this->assertSame( 'error', RWGC_MaxMind::run_maintenance_action( 'nope' )['type'] );
	}

	/**
	 * @return void
	 */
	public function test_refresh_runs_before_output_and_not_inside_the_view(): void {
		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/integrations-maxmind-page.php' );
		$this->assertStringNotContainsString( 'RWGC_MaxMind::update_database()', $view );
		$this->assertStringNotContainsString( 'RWGC_Cache::clear_all()', $view );
		$this->assertStringContainsString( 'Last MaxMind download failed', $view );

		$admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php' );
		$this->assertStringContainsString( "add_action( 'admin_init', array( __CLASS__, 'handle_maxmind_maintenance_action' ) );", $admin );
		$this->assertStringContainsString( "RWGC_MaxMind::run_maintenance_action( \$action )", $admin );
		$this->assertStringContainsString( "wp_verify_nonce( sanitize_text_field( wp_unslash( \$_GET['_wpnonce'] ) ), 'rwgc_maxmind_action_' . \$action )", $admin );
		$this->assertMatchesRegularExpression( "/'rwgc_upload_success'.*?credentials_saved_redirect\\(/s", $admin );
	}
}
