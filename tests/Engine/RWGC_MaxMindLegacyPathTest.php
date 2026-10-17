<?php
/**
 * Database path resolution for sites that still have a dated GeoLite2 folder (1.9.14).
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
	 * Uploads base directory. Tests set rwgc_test_upload_basedir; otherwise the system temp dir.
	 *
	 * @return array<string, string>
	 */
	function wp_get_upload_dir() {
		$base = isset( $GLOBALS['rwgc_test_upload_basedir'] ) ? (string) $GLOBALS['rwgc_test_upload_basedir'] : sys_get_temp_dir();
		return array( 'basedir' => $base );
	}
}

/**
 * @covers RWGC_MaxMind
 * @covers RWGC_GeoIP
 */
final class RWGC_MaxMindLegacyPathTest extends TestCase {

	/** @var string */
	private $uploads = '';

	/** @var string */
	private $storage = '';

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-geoip.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->uploads = sys_get_temp_dir() . '/rwgc-legacy-' . bin2hex( random_bytes( 4 ) );
		$this->storage = $this->uploads . '/reactwoo-geocore';
		mkdir( $this->storage, 0755, true );
		$GLOBALS['rwgc_test_upload_basedir'] = $this->uploads;
		$GLOBALS['rwgc_test_options']        = array( 'rwgc_settings' => array( 'db_file_path' => '' ) );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_upload_basedir'], $GLOBALS['rwgc_test_options'] );
		$this->remove( $this->uploads );
		parent::tearDown();
	}

	/**
	 * @param string $path  File.
	 * @param string $label Content marker so copies can be told apart.
	 * @return string
	 */
	private function write_db( $path, $label = 'db' ) {
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0755, true );
		}
		file_put_contents( $path, str_pad( $label, 200, '.' ) . "\xab\xcd\xefMaxMind.com" );
		return $path;
	}

	/**
	 * @param string $dir Directory.
	 * @return void
	 */
	private function remove( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $file ) {
			if ( $file->isDir() ) {
				chmod( $file->getPathname(), 0755 );
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}
		chmod( $dir, 0755 );
		rmdir( $dir );
	}

	/**
	 * @return string
	 */
	private function stored_path() {
		return (string) $GLOBALS['rwgc_test_options']['rwgc_settings']['db_file_path'];
	}

	/**
	 * The reactwoo.com layout: only a dated folder, and an empty db_file_path.
	 *
	 * @return void
	 */
	public function test_dated_folder_is_found_and_copied_to_the_canonical_path_once(): void {
		$legacy    = $this->write_db( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb', 'june' );
		$canonical = $this->storage . '/GeoLite2-Country.mmdb';

		$this->assertSame( $canonical, RWGC_MaxMind::get_canonical_db_path() );
		$this->assertSame( $canonical, RWGC_MaxMind::get_db_path() );
		$this->assertFileExists( $canonical );
		$this->assertSame( file_get_contents( $legacy ), file_get_contents( $canonical ) );
		$this->assertFileExists( $legacy, 'The dated folder is left in place.' );
		$this->assertSame( $canonical, $this->stored_path() );
		$this->assertNotEmpty( $GLOBALS['rwgc_test_options']['rwgc_settings']['db_last_updated'] );
		$this->assertSame( array(), glob( $this->storage . '/*.new' ), 'No temporary copy is left behind.' );

		// Second call reads the saved path without copying again.
		$mtime = filemtime( $canonical );
		$this->assertSame( $canonical, RWGC_MaxMind::get_db_path() );
		$this->assertSame( $mtime, filemtime( $canonical ) );
		$this->assertTrue( RWGC_MaxMind::db_exists() );
	}

	/**
	 * @return void
	 */
	public function test_stale_configured_path_falls_back_to_the_dated_folder(): void {
		$GLOBALS['rwgc_test_options']['rwgc_settings']['db_file_path'] = '/old/host/wp-content/uploads/reactwoo-geocore/GeoLite2-Country_20250101/GeoLite2-Country.mmdb';
		$this->write_db( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb' );

		$this->assertSame( $this->storage . '/GeoLite2-Country.mmdb', RWGC_MaxMind::get_db_path() );
		$this->assertSame( $this->storage . '/GeoLite2-Country.mmdb', $this->stored_path() );
	}

	/**
	 * @return void
	 */
	public function test_configured_file_wins_when_it_exists(): void {
		$custom = $this->write_db( $this->uploads . '/custom/GeoLite2-Country.mmdb', 'custom' );
		$this->write_db( $this->storage . '/GeoLite2-Country.mmdb', 'canonical' );
		$this->write_db( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb', 'june' );
		$GLOBALS['rwgc_test_options']['rwgc_settings']['db_file_path'] = $custom;

		$this->assertSame( $custom, RWGC_MaxMind::get_db_path() );
	}

	/**
	 * A configured dated path (what 1.9.12 and earlier saved) keeps working unchanged.
	 *
	 * @return void
	 */
	public function test_configured_dated_path_from_older_versions_still_resolves(): void {
		$legacy = $this->write_db( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb' );
		$GLOBALS['rwgc_test_options']['rwgc_settings']['db_file_path'] = $legacy;

		$this->assertSame( $legacy, RWGC_MaxMind::get_db_path() );
		$this->assertFileDoesNotExist( $this->storage . '/GeoLite2-Country.mmdb' );
	}

	/**
	 * @return void
	 */
	public function test_canonical_file_wins_over_dated_folders_without_a_settings_write(): void {
		$canonical = $this->write_db( $this->storage . '/GeoLite2-Country.mmdb', 'canonical' );
		$this->write_db( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb', 'june' );

		$this->assertSame( $canonical, RWGC_MaxMind::get_db_path() );
		$this->assertSame( '', $this->stored_path() );
		$this->assertStringStartsWith( 'canonical', file_get_contents( $canonical ) );
	}

	/**
	 * @return void
	 */
	public function test_newest_usable_dated_folder_is_chosen_and_unrelated_folders_are_ignored(): void {
		$this->write_db( $this->storage . '/GeoLite2-Country_20260101/GeoLite2-Country.mmdb', 'january' );
		$this->write_db( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb', 'june' );
		// Newest folder holds a broken file (no MaxMind metadata marker).
		mkdir( $this->storage . '/GeoLite2-Country_20261001' );
		file_put_contents( $this->storage . '/GeoLite2-Country_20261001/GeoLite2-Country.mmdb', str_repeat( 'x', 300 ) );
		$this->write_db( $this->storage . '/GeoLite2-City_20261010/GeoLite2-Country.mmdb', 'wrong-edition-folder' );
		$this->write_db( $this->storage . '/tmp-abcd1234/GeoLite2-Country_20261012/GeoLite2-Country.mmdb', 'work-dir' );

		$this->assertSame( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb', RWGC_MaxMind::find_legacy_db_path() );
		RWGC_MaxMind::get_db_path();
		$this->assertStringStartsWith( 'june', file_get_contents( $this->storage . '/GeoLite2-Country.mmdb' ) );
	}

	/**
	 * @return void
	 */
	public function test_nothing_on_disk_returns_the_configured_or_canonical_path_without_creating_files(): void {
		$this->assertNull( RWGC_MaxMind::find_legacy_db_path() );
		$this->assertSame( $this->storage . '/GeoLite2-Country.mmdb', RWGC_MaxMind::get_db_path() );
		$this->assertFalse( RWGC_MaxMind::db_exists() );

		$GLOBALS['rwgc_test_options']['rwgc_settings']['db_file_path'] = '/gone/GeoLite2-Country.mmdb';
		$this->assertSame( '/gone/GeoLite2-Country.mmdb', RWGC_MaxMind::get_db_path() );
		$this->assertSame( array( '.', '..' ), scandir( $this->storage ) );
		$this->assertNull( RWGC_MaxMind::find_legacy_db_path( $this->uploads . '/does-not-exist' ) );
	}

	/**
	 * @return void
	 */
	public function test_unwritable_storage_saves_the_dated_path_instead(): void {
		if ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) {
			$this->markTestSkipped( 'Root ignores directory permissions.' );
		}
		$legacy = $this->write_db( $this->storage . '/GeoLite2-Country_20260605/GeoLite2-Country.mmdb' );
		chmod( $this->storage, 0555 );

		$this->assertSame( $legacy, RWGC_MaxMind::get_db_path() );
		$this->assertSame( $legacy, $this->stored_path() );
		$this->assertFileDoesNotExist( $this->storage . '/GeoLite2-Country.mmdb' );
		chmod( $this->storage, 0755 );
	}

	/**
	 * @return void
	 */
	public function test_missing_database_fallbacks_are_not_cached_per_ip(): void {
		$this->assertFalse( RWGC_GeoIP::should_cache_lookup( array( 'source' => 'fallback_db_missing' ) ) );
		$this->assertFalse( RWGC_GeoIP::should_cache_lookup( array( 'source' => 'fallback_no_reader' ) ) );
		$this->assertFalse( RWGC_GeoIP::should_cache_lookup( null ) );
		$this->assertTrue( RWGC_GeoIP::should_cache_lookup( array( 'source' => 'maxmind' ) ) );
		$this->assertTrue( RWGC_GeoIP::should_cache_lookup( array( 'source' => 'fallback_invalid_ip' ) ) );
	}
}
