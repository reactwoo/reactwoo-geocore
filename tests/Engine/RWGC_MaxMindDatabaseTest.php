<?php

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_MaxMind
 */
final class RWGC_MaxMindDatabaseTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php';
	}

	public function test_notice_is_silent_when_a_database_is_usable_without_a_license_key(): void {
		$this->assertSame( 'auto_update_hint', RWGC_MaxMind::admin_notice_code( true, false, false ) );
	}

	public function test_notice_warns_only_when_database_and_license_key_are_both_missing(): void {
		$this->assertSame( 'no_database', RWGC_MaxMind::admin_notice_code( false, false, false ) );
	}

	public function test_notice_asks_for_the_file_when_a_license_key_is_saved(): void {
		$this->assertSame( 'missing_file', RWGC_MaxMind::admin_notice_code( false, true, false ) );
	}

	public function test_notice_mentions_a_stale_database_only_when_a_key_can_refresh_it(): void {
		$this->assertSame( 'stale', RWGC_MaxMind::admin_notice_code( true, true, true ) );
		$this->assertSame( 'auto_update_hint', RWGC_MaxMind::admin_notice_code( true, false, true ) );
	}

	public function test_usable_database_requires_the_maxmind_marker(): void {
		$dir = sys_get_temp_dir() . '/rwgc-mmdb-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir );
		$good = $dir . '/GeoLite2-Country.mmdb';
		$bad  = $dir . '/notes.mmdb';
		file_put_contents( $good, str_repeat( 'a', 200 ) . "\xab\xcd\xefMaxMind.com" );
		file_put_contents( $bad, str_repeat( 'b', 200 ) );

		$this->assertTrue( RWGC_MaxMind::db_is_usable( $good ) );
		$this->assertFalse( RWGC_MaxMind::db_is_usable( $bad ) );
		$this->assertFalse( RWGC_MaxMind::db_is_usable( $dir . '/missing.mmdb' ) );

		unlink( $good );
		unlink( $bad );
		rmdir( $dir );
	}
}
