<?php
/**
 * MaxMind automatic updates, save notice, phone layout, archive choice, and key mask.
 *
 * @package ReactWoo_Geo_Core
 */

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

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook ) {
		return isset( $GLOBALS['rwgc_test_cron'][ $hook ]['timestamp'] ) ? $GLOBALS['rwgc_test_cron'][ $hook ]['timestamp'] : false;
	}
}

if ( ! function_exists( 'wp_get_schedule' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return string|false
	 */
	function wp_get_schedule( $hook ) {
		return isset( $GLOBALS['rwgc_test_cron'][ $hook ]['recurrence'] ) ? $GLOBALS['rwgc_test_cron'][ $hook ]['recurrence'] : false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * @param int    $timestamp  Timestamp.
	 * @param string $recurrence Recurrence.
	 * @param string $hook       Hook.
	 * @return bool
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook ) {
		$GLOBALS['rwgc_test_cron'][ $hook ] = array(
			'timestamp'  => $timestamp,
			'recurrence' => $recurrence,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	/**
	 * @param int    $timestamp Timestamp.
	 * @param string $hook      Hook.
	 * @return bool
	 */
	function wp_unschedule_event( $timestamp, $hook ) {
		unset( $timestamp );
		unset( $GLOBALS['rwgc_test_cron'][ $hook ] );
		return true;
	}
}

/**
 * @covers RWGC_MaxMind
 */
final class RWGC_MaxMindFollowupsTest extends TestCase {

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'RWGC_PATH' ) ) {
			define( 'RWGC_PATH', dirname( __DIR__, 2 ) . '/' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_cron']    = array();
		$GLOBALS['rwgc_test_options'] = array();
		unset( $GLOBALS['rwgc_test_filters']['rwgc_maxmind_cron_jitter'], $GLOBALS['rwgc_test_filters']['rwgc_maxmind_extract_parents'], $GLOBALS['rwgc_test_filters']['rwgc_maxmind_extract_work_dir'] );
	}

	/**
	 * @return void
	 */
	public function test_auto_update_schedules_twice_weekly_with_jitter_and_unschedules_when_off(): void {
		$schedules = RWGC_MaxMind::cron_schedules( array() );
		$this->assertArrayHasKey( RWGC_MaxMind::CRON_SCHEDULE, $schedules );
		$this->assertSame( (int) ( 3.5 * 86400 ), $schedules[ RWGC_MaxMind::CRON_SCHEDULE ]['interval'] );

		$GLOBALS['rwgc_test_filters']['rwgc_maxmind_cron_jitter'] = static function () {
			return 50;
		};
		$GLOBALS['rwgc_test_options']['rwgc_settings'] = array(
			'auto_update_db'      => 1,
			'maxmind_account_id'  => '123456',
			'maxmind_license_key' => 'AbC123_key_mmk',
		);
		$before = time();
		RWGC_MaxMind::sync_schedule();
		$event = $GLOBALS['rwgc_test_cron'][ RWGC_MaxMind::CRON_HOOK ];
		$this->assertSame( RWGC_MaxMind::CRON_SCHEDULE, $event['recurrence'] );
		$this->assertGreaterThanOrEqual( $before + 3600 + 50, $event['timestamp'] );
		$this->assertLessThanOrEqual( time() + 3600 + 50, $event['timestamp'] );

		$GLOBALS['rwgc_test_options']['rwgc_settings']['auto_update_db'] = 0;
		RWGC_MaxMind::sync_schedule();
		$this->assertArrayNotHasKey( RWGC_MaxMind::CRON_HOOK, $GLOBALS['rwgc_test_cron'] );

		$plugin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-plugin.php' );
		$this->assertStringContainsString( 'RWGC_MaxMind::sync_schedule();', $plugin );
		$this->assertStringContainsString( 'RWGC_MaxMind::unschedule();', $plugin );
	}

	/**
	 * @return void
	 */
	public function test_scheduled_update_skips_quietly_without_credentials(): void {
		$GLOBALS['rwgc_test_options']['rwgc_settings'] = array(
			'auto_update_db'     => 1,
			'maxmind_account_id' => '',
			'maxmind_license_key' => '',
			'db_last_error'      => 'sentinel',
			'db_last_failure_at' => '',
		);
		RWGC_MaxMind::run_scheduled_update();
		$stored = $GLOBALS['rwgc_test_options']['rwgc_settings'];
		$this->assertSame( 'sentinel', $stored['db_last_error'] );
		$this->assertSame( '', $stored['db_last_failure_at'] );
	}

	/**
	 * @return void
	 */
	public function test_a_failed_download_records_the_failure_time_and_success_is_stored(): void {
		$GLOBALS['rwgc_test_options']['rwgc_settings'] = array(
			'maxmind_account_id'  => '',
			'maxmind_license_key' => '',
		);
		$result = RWGC_MaxMind::update_database();
		$this->assertInstanceOf( WP_Error::class, $result );
		$stored = $GLOBALS['rwgc_test_options']['rwgc_settings'];
		$this->assertNotSame( '', $stored['db_last_failure_at'] );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php' );
		$this->assertStringContainsString( "\$settings['db_last_success_at']", $src );
		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/integrations-maxmind-page.php' );
		$this->assertStringContainsString( 'Last successful download', $view );
		$this->assertStringContainsString( 'Last failed download', $view );
	}

	/**
	 * @return void
	 */
	public function test_credentials_saved_redirect_includes_settings_updated(): void {
		$url = RWGC_MaxMind::credentials_saved_redirect( 'https://example.test/wp-admin/admin.php?page=rwgc-integrations-maxmind' );
		$this->assertStringContainsString( 'settings-updated=true', $url );
		$this->assertSame( $url, RWGC_MaxMind::credentials_saved_redirect( $url ) );
		$admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php' );
		$this->assertStringContainsString( 'credentials_saved_redirect(', $admin );
		$this->assertStringContainsString( 'RWGC_MaxMind::sync_schedule();', $admin );
	}

	/**
	 * @return void
	 */
	public function test_maxmind_screen_css_stops_the_table_from_widening_a_phone(): void {
		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/integrations-maxmind-page.php' );
		$css  = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/css/admin.css' );
		$this->assertStringContainsString( 'rwgc-maxmind-screen', $view );
		$this->assertStringContainsString( '.rwgc-maxmind-screen .form-table', $css );
		$this->assertStringContainsString( 'box-sizing: border-box', $css );
		$this->assertStringContainsString( 'overflow-x: clip', $css );
		$this->assertStringContainsString( 'max-width: 782px', $css );
	}

	/**
	 * @return void
	 */
	public function test_archive_selection_uses_the_newest_dated_edition_file(): void {
		$paths = array(
			'GeoLite2-Country_20240101/GeoLite2-Country.mmdb',
			'GeoLite2-Country_20240101/COPYRIGHT.txt',
			'GeoLite2-City_20261001/GeoLite2-City.mmdb',
			'old/GeoLite2-Country.mmdb',
			'GeoLite2-Country_20261017/GeoLite2-Country.mmdb',
		);
		$this->assertSame(
			'GeoLite2-Country_20261017/GeoLite2-Country.mmdb',
			RWGC_MaxMind::select_archive_mmdb( $paths, 'GeoLite2-Country' )
		);
		$this->assertSame(
			'GeoLite2-City_20261001/GeoLite2-City.mmdb',
			RWGC_MaxMind::select_archive_mmdb( $paths, 'GeoLite2-City' )
		);
		$this->assertNull( RWGC_MaxMind::select_archive_mmdb( $paths, 'GeoLite2-ASN' ) );
	}

	/**
	 * @return void
	 */
	public function test_database_is_opened_with_the_reader_before_it_can_replace_the_live_file(): void {
		$dir  = sys_get_temp_dir() . '/rwgc-mmdb-' . bin2hex( random_bytes( 3 ) );
		mkdir( $dir );
		$path = $dir . '/GeoLite2-Country.mmdb';
		file_put_contents( $path, str_repeat( "\0", 200 ) . "\xab\xcd\xefMaxMind.com" );
		$result = RWGC_MaxMind::validate_database_file( $path );
		unlink( $path );
		rmdir( $dir );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rwgc_mmdb_invalid', $result->get_error_code() );
		$this->assertStringContainsString( 'could not be opened', $result->get_error_message() );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php' );
		$this->assertStringContainsString( 'new \\GeoIp2\\Database\\Reader', $src );
		$this->assertStringContainsString( '$dest . \'.new\'', $src );
	}

	/**
	 * @return void
	 */
	public function test_install_replaces_the_live_file_and_leaves_no_temporary_sibling(): void {
		$dir = sys_get_temp_dir() . '/rwgc-install-' . bin2hex( random_bytes( 3 ) );
		mkdir( $dir );
		$source = $dir . '/incoming.mmdb';
		$dest   = $dir . '/GeoLite2-Country.mmdb';
		file_put_contents( $dest, 'old' );
		file_put_contents( $source, 'new-database' );
		$installed = RWGC_MaxMind::install_database_file( $source, $dest );
		$this->assertSame( $dest, $installed );
		$this->assertSame( 'new-database', (string) file_get_contents( $dest ) );
		$this->assertFileDoesNotExist( $dest . '.new' );
		unlink( $source );
		unlink( $dest );
		rmdir( $dir );
	}

	/**
	 * @return void
	 */
	public function test_masked_license_key_does_not_replace_the_stored_key(): void {
		$stored = 'AbC123_key_mmk';
		$mask   = RWGC_MaxMind::mask_license_key( $stored );
		$this->assertSame( str_repeat( '•', strlen( $stored ) - 4 ) . '_mmk', $mask );
		$this->assertSame( $stored, RWGC_MaxMind::license_key_from_post( '', $stored ) );
		$this->assertSame( $stored, RWGC_MaxMind::license_key_from_post( $mask, $stored ) );
		$this->assertSame( 'NewKey_123_mmk', RWGC_MaxMind::license_key_from_post( 'NewKey_123_mmk', $stored ) );
		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/integrations-maxmind-page.php' );
		$this->assertStringContainsString( 'mask_license_key(', $view );
	}

	/**
	 * @return void
	 */
	public function test_unlistable_temp_dir_fails_cleanly_and_leaves_the_live_file(): void {
		$dir = sys_get_temp_dir() . '/rwgc-nolist-' . bin2hex( random_bytes( 3 ) );
		mkdir( $dir, 0333 );
		$live_dir = sys_get_temp_dir() . '/rwgc-live-' . bin2hex( random_bytes( 3 ) );
		mkdir( $live_dir );
		$live = $live_dir . '/GeoLite2-Country.mmdb';
		file_put_contents( $live, 'live-bytes' );
		$GLOBALS['rwgc_test_filters']['rwgc_maxmind_extract_parents'] = static function () use ( $dir ) {
			return array( $dir );
		};

		$result = RWGC_MaxMind::extract_database_archive( $live_dir . '/missing.tar.gz', $live_dir );

		chmod( $dir, 0755 );
		rmdir( $dir );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rwgc_extract_failed', $result->get_error_code() );
		$this->assertSame( 'live-bytes', (string) file_get_contents( $live ) );
		unlink( $live );
		rmdir( $live_dir );
	}

	/**
	 * @return void
	 */
	public function test_listing_failure_is_a_saved_style_error_and_temp_files_are_removed(): void {
		$parent = sys_get_temp_dir() . '/rwgc-work-' . bin2hex( random_bytes( 3 ) );
		mkdir( $parent );
		$blocked = $parent . '/blocked';
		mkdir( $blocked, 0333 );
		$live_dir = $parent . '/live';
		mkdir( $live_dir );
		$live = $live_dir . '/GeoLite2-Country.mmdb';
		file_put_contents( $live, 'live-bytes' );
		$download = $parent . '/download.bin';
		file_put_contents( $download, 'not-an-archive' );
		$GLOBALS['rwgc_test_filters']['rwgc_maxmind_extract_parents'] = static function () use ( $parent ) {
			return array( $parent );
		};

		$listed = RWGC_MaxMind::extract_database_archive( $download, $live_dir );

		$this->assertInstanceOf( WP_Error::class, $listed );
		$this->assertFileExists( $download );
		$this->assertFileDoesNotExist( $download . '.tar.gz' );
		$this->assertFileDoesNotExist( $download . '.tar' );
		$this->assertSame( array(), glob( $parent . '/tmp-*' ) );
		$this->assertSame( 'live-bytes', (string) file_get_contents( $live ) );

		$GLOBALS['rwgc_test_filters']['rwgc_maxmind_extract_work_dir'] = static function () use ( $blocked ) {
			return $blocked;
		};
		$thrown = RWGC_MaxMind::extract_database_archive( $download, $live_dir );
		chmod( $blocked, 0755 );
		$this->assertInstanceOf( WP_Error::class, $thrown );
		$this->assertSame( 'live-bytes', (string) file_get_contents( $live ) );
		$this->assertFileDoesNotExist( $download . '.tar.gz' );

		unlink( $download );
		unlink( $live );
		rmdir( $live_dir );
		rmdir( $blocked );
		rmdir( $parent );
	}

	/**
	 * @return void
	 */
	public function test_last_error_message_survives_reload_until_success(): void {
		$message = "MaxMind download failed (HTTP 401 Unauthorized). Response: denied \xFF";
		$kept    = RWGC_Settings::sanitize_status_message( $message );
		$this->assertStringContainsString( '401', $kept );
		$this->assertStringNotContainsString( "\xFF", $kept );

		RWGC_Settings::update(
			array(
				'db_last_error'      => $message,
				'db_last_failure_at' => '2026-10-17T12:00:00+00:00',
				'maxmind_account_id' => '123456',
				'maxmind_license_key' => 'AbC123_key_mmk',
			)
		);
		$this->assertStringContainsString( '401', (string) RWGC_Settings::get( 'db_last_error', '' ) );
		$this->assertSame( '2026-10-17T12:00:00+00:00', (string) RWGC_Settings::get( 'db_last_failure_at', '' ) );

		$view = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/integrations-maxmind-page.php' );
		$this->assertStringContainsString( 'notice notice-error', $view );
		$this->assertMatchesRegularExpression( '/Last failed download.*last_error/s', $view );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php' );
		$this->assertStringContainsString( "\$settings['db_last_error']      = '';", $src );
	}
}
