<?php
/**
 * A new or recovered database drops the per-IP lookup cache (1.9.14).
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

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
		unset( $GLOBALS['rwgc_test_options'], $GLOBALS['rwgc_test_upload_basedir'] );
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
	public function test_flush_without_the_cache_class_still_bumps_the_option(): void {
		$GLOBALS['rwgc_test_options']['rwgc_cache_version'] = 0;
		RWGC_MaxMind::flush_lookup_cache();
		$this->assertGreaterThanOrEqual( 1, $this->cache_version() );
	}
}
