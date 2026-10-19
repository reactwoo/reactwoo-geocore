<?php
/**
 * A new or recovered database drops the per-IP lookup cache (1.9.14).
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
		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		/**
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}
		/**
		 * @return string
		 */
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

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( isset( $GLOBALS['rwgc_test_get_option'] ) && is_callable( $GLOBALS['rwgc_test_get_option'] ) ) {
			return $GLOBALS['rwgc_test_get_option']( $key, $default );
		}
		if ( isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] ) && array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ) {
			return $GLOBALS['rwgc_test_options'][ $key ];
		}
		return $default;
	}
}
if ( ! function_exists( 'wp_cache_delete' ) ) {
	/**
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool
	 */
	function wp_cache_delete( $key, $group = '' ) {
		unset( $group );
		if ( isset( $GLOBALS['rwgc_test_on_cache_delete'] ) && is_callable( $GLOBALS['rwgc_test_on_cache_delete'] ) ) {
			$GLOBALS['rwgc_test_on_cache_delete']( $key );
		}
		return true;
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
if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	/**
	 * @return array<string, string>
	 */
	function wp_get_upload_dir() {
		$base = isset( $GLOBALS['rwgc_test_upload_basedir'] ) ? (string) $GLOBALS['rwgc_test_upload_basedir'] : sys_get_temp_dir();
		return array( 'basedir' => $base );
	}
}

/**
 * @covers RWGC_MaxMind
 * @covers RWGC_Cache
 */
final class RWGC_MaxMindCacheFlushTest extends TestCase {

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_options'] = array(
			'rwgc_settings'      => array( 'db_file_path' => '' ),
			'rwgc_cache_version' => 4,
		);
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['rwgc_test_options'],
			$GLOBALS['rwgc_test_upload_basedir'],
			$GLOBALS['rwgc_test_get_option'],
			$GLOBALS['rwgc_test_on_cache_delete'],
			$GLOBALS['rwgc_settings_cache_busted']
		);
		parent::tearDown();
	}

	/**
	 * @return int
	 */
	private function cache_version() {
		return (int) $GLOBALS['rwgc_test_options']['rwgc_cache_version'];
	}

	/**
	 * @return void
	 */
	public function test_successful_download_bumps_the_cache_version_and_saves_the_path(): void {
		$saved = RWGC_MaxMind::record_download_success( '/x/GeoLite2-Country.mmdb' );
		$this->assertSame( 5, $this->cache_version() );
		$this->assertSame( '/x/GeoLite2-Country.mmdb', $GLOBALS['rwgc_test_options']['rwgc_settings']['db_file_path'] );
		$this->assertNotSame( '', $saved['db_last_success_at'] );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php' );
		$this->assertStringContainsString( 'self::record_download_success( $db_path );', $src );
	}

	/**
	 * @return void
	 */
	public function test_manual_upload_bumps_the_cache_version(): void {
		RWGC_MaxMind::record_manual_upload( '/x/GeoLite2-Country.mmdb' );
		$this->assertSame( 5, $this->cache_version() );
		$this->assertSame( '/x/GeoLite2-Country.mmdb', $GLOBALS['rwgc_test_options']['rwgc_settings']['db_file_path'] );
		$admin = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-admin.php' );
		$this->assertStringContainsString( 'RWGC_MaxMind::record_manual_upload( $dest_path );', $admin );
	}

	/**
	 * @return void
	 */
	public function test_failed_download_leaves_the_cache_alone(): void {
		$result = RWGC_MaxMind::update_database();
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 4, $this->cache_version() );
	}

	/**
	 * @return void
	 */
	public function test_legacy_migration_bumps_the_cache_version_once(): void {
		$uploads = sys_get_temp_dir() . '/rwgc-flush-' . bin2hex( random_bytes( 4 ) );
		$dated   = $uploads . '/reactwoo-geocore/GeoLite2-Country_20260605';
		mkdir( $dated, 0755, true );
		file_put_contents( $dated . '/GeoLite2-Country.mmdb', str_repeat( 'a', 200 ) . "\xab\xcd\xefMaxMind.com" );
		$GLOBALS['rwgc_test_upload_basedir'] = $uploads;

		RWGC_MaxMind::get_db_path();
		$this->assertSame( 5, $this->cache_version() );
		RWGC_MaxMind::get_db_path();
		RWGC_MaxMind::db_exists();
		$this->assertSame( 5, $this->cache_version(), 'Later lookups do not flush again.' );

		unlink( $dated . '/GeoLite2-Country.mmdb' );
		rmdir( $dated );
		unlink( $uploads . '/reactwoo-geocore/GeoLite2-Country.mmdb' );
		rmdir( $uploads . '/reactwoo-geocore' );
		rmdir( $uploads );
	}

	/**
	 * @return void
	 */
	/**
	 * The download request cached rwgc_settings at bootstrap. An admin save landed
	 * in the database during the HTTP call. The write after the download must keep
	 * that save. Runs in its own process so this file's get_option stub is the one
	 * that notices the cache delete.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @return void
	 */
	public function test_download_save_keeps_a_license_key_saved_during_the_request(): void {
		$GLOBALS['rwgc_settings_cache_busted'] = false;
		$GLOBALS['rwgc_test_options']          = array(
			'rwgc_cache_version' => 4,
		);
		$GLOBALS['rwgc_test_on_cache_delete'] = static function ( $key ) {
			if ( 'alloptions' === $key || 'rwgc_settings' === $key ) {
				$GLOBALS['rwgc_settings_cache_busted'] = true;
			}
		};
		$GLOBALS['rwgc_test_get_option'] = static function ( $key, $default ) {
			if ( 'rwgc_cache_version' === $key ) {
				return $GLOBALS['rwgc_test_options']['rwgc_cache_version'];
			}
			if ( 'rwgc_settings' !== $key ) {
				return $default;
			}
			if ( ! empty( $GLOBALS['rwgc_settings_cache_busted'] ) ) {
				return array(
					'maxmind_account_id'  => '999',
					'maxmind_license_key' => 'rotated_key',
					'fallback_country'    => 'DE',
				);
			}
			return array(
				'maxmind_account_id'  => '111',
				'maxmind_license_key' => 'old_key',
				'fallback_country'    => 'US',
			);
		};

		$saved = RWGC_MaxMind::record_download_success( '/x/GeoLite2-Country.mmdb' );

		$this->assertSame( 'rotated_key', $saved['maxmind_license_key'] );
		$this->assertSame( '999', $saved['maxmind_account_id'] );
		$this->assertSame( '/x/GeoLite2-Country.mmdb', $saved['db_file_path'] );
		$this->assertSame( 'rotated_key', $GLOBALS['rwgc_test_options']['rwgc_settings']['maxmind_license_key'] );
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php' );
		$this->assertSame( 4, substr_count( $src, 'RWGC_Settings::get_settings_fresh()' ) );
	}

	/**
	 * @return void
	 */
	public function test_flush_without_the_cache_class_still_bumps_the_option(): void {
		$GLOBALS['rwgc_test_options']['rwgc_cache_version'] = 0;
		RWGC_MaxMind::flush_lookup_cache();
		$this->assertGreaterThanOrEqual( 1, $this->cache_version() );
	}
}
