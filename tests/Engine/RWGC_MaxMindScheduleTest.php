<?php
/**
 * The MaxMind download schedule heals itself after an auto-update (1.9.14).
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'RWGC_VERSION' ) ) {
	define( 'RWGC_VERSION', '1.9.14' );
}

/*
 * Cron stubs. The first four match RWGC_MaxMindFollowupsTest, whichever file loads first.
 * $GLOBALS['rwgc_test_cron'][ hook ] holds one event; $GLOBALS['rwgc_test_cron_extra'][ hook ]
 * counts duplicate events queued by a race.
 */
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
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return int
	 */
	function wp_clear_scheduled_hook( $hook ) {
		unset( $GLOBALS['rwgc_test_cron'][ $hook ], $GLOBALS['rwgc_test_cron_extra'][ $hook ] );
		return 0;
	}
}
if ( ! function_exists( '_get_cron_array' ) ) {
	/**
	 * @return array<int, array<string, array<string, array<string, mixed>>>>
	 */
	function _get_cron_array() {
		$out = array();
		foreach ( isset( $GLOBALS['rwgc_test_cron'] ) ? (array) $GLOBALS['rwgc_test_cron'] : array() as $hook => $event ) {
			$out[ (int) $event['timestamp'] ][ $hook ][ md5( 'main' ) ] = array( 'schedule' => $event['recurrence'] );
		}
		foreach ( isset( $GLOBALS['rwgc_test_cron_extra'] ) ? (array) $GLOBALS['rwgc_test_cron_extra'] : array() as $hook => $n ) {
			for ( $i = 1; $i <= (int) $n; $i++ ) {
				$out[ 2000000000 + $i ][ $hook ][ md5( 'dup' . $i ) ] = array( 'schedule' => 'rwgc_twice_weekly' );
			}
		}
		return $out;
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
final class RWGC_MaxMindScheduleTest extends TestCase {

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
		$GLOBALS['rwgc_test_cron']       = array();
		$GLOBALS['rwgc_test_cron_extra'] = array();
		$GLOBALS['rwgc_test_options']    = array(
			'rwgc_settings' => array(
				'auto_update_db'      => 1,
				'maxmind_account_id'  => '123456',
				'maxmind_license_key' => 'AbC123_key_mmk',
			),
		);
		$GLOBALS['rwgc_test_filters']['rwgc_maxmind_cron_jitter'] = static function () {
			return 0;
		};
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_cron'], $GLOBALS['rwgc_test_cron_extra'], $GLOBALS['rwgc_test_options'], $GLOBALS['rwgc_test_filters']['rwgc_maxmind_cron_jitter'] );
		parent::tearDown();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function event() {
		return isset( $GLOBALS['rwgc_test_cron'][ RWGC_MaxMind::CRON_HOOK ] ) ? $GLOBALS['rwgc_test_cron'][ RWGC_MaxMind::CRON_HOOK ] : null;
	}

	/**
	 * The reactwoo.com case: auto-updated from 1.9.12, no activation hook, no event.
	 *
	 * @return void
	 */
	public function test_version_change_schedules_the_missing_event_and_records_the_version(): void {
		$GLOBALS['rwgc_test_options'][ RWGC_MaxMind::SCHEDULE_VERSION_OPTION ] = '1.9.12';
		RWGC_MaxMind::maybe_sync_schedule();

		$this->assertNotNull( $this->event() );
		$this->assertSame( RWGC_MaxMind::CRON_SCHEDULE, $this->event()['recurrence'] );
		$this->assertSame( RWGC_VERSION, $GLOBALS['rwgc_test_options'][ RWGC_MaxMind::SCHEDULE_VERSION_OPTION ] );
		$this->assertSame( 1, RWGC_MaxMind::scheduled_count() );
	}

	/**
	 * @return void
	 */
	public function test_first_load_without_a_stored_version_schedules(): void {
		RWGC_MaxMind::maybe_sync_schedule();
		$this->assertNotNull( $this->event() );
		$this->assertSame( RWGC_VERSION, $GLOBALS['rwgc_test_options'][ RWGC_MaxMind::SCHEDULE_VERSION_OPTION ] );
	}

	/**
	 * @return void
	 */
	public function test_missing_event_is_restored_on_a_normal_load(): void {
		$GLOBALS['rwgc_test_options'][ RWGC_MaxMind::SCHEDULE_VERSION_OPTION ] = RWGC_VERSION;
		RWGC_MaxMind::maybe_sync_schedule();
		$this->assertNotNull( $this->event() );
	}

	/**
	 * @return void
	 */
	public function test_repeated_loads_never_double_schedule_or_move_the_event(): void {
		RWGC_MaxMind::maybe_sync_schedule();
		$first = $this->event();
		for ( $i = 0; $i < 5; $i++ ) {
			RWGC_MaxMind::maybe_sync_schedule();
			RWGC_MaxMind::sync_schedule();
		}
		$this->assertSame( $first, $this->event(), 'An existing event keeps its time.' );
		$this->assertSame( 1, RWGC_MaxMind::scheduled_count() );
	}

	/**
	 * @return void
	 */
	public function test_duplicate_events_from_a_race_collapse_to_one(): void {
		$GLOBALS['rwgc_test_options'][ RWGC_MaxMind::SCHEDULE_VERSION_OPTION ] = RWGC_VERSION;
		RWGC_MaxMind::sync_schedule();
		$GLOBALS['rwgc_test_cron_extra'][ RWGC_MaxMind::CRON_HOOK ] = 2;
		$this->assertSame( 3, RWGC_MaxMind::scheduled_count() );

		RWGC_MaxMind::maybe_sync_schedule();
		$this->assertSame( 1, RWGC_MaxMind::scheduled_count() );
		$this->assertNotNull( $this->event() );
	}

	/**
	 * @return void
	 */
	public function test_turning_auto_update_off_unschedules_on_the_next_load(): void {
		$GLOBALS['rwgc_test_options'][ RWGC_MaxMind::SCHEDULE_VERSION_OPTION ] = RWGC_VERSION;
		RWGC_MaxMind::sync_schedule();
		$GLOBALS['rwgc_test_cron_extra'][ RWGC_MaxMind::CRON_HOOK ] = 1;

		$GLOBALS['rwgc_test_options']['rwgc_settings']['auto_update_db'] = 0;
		RWGC_MaxMind::maybe_sync_schedule();
		$this->assertSame( 0, RWGC_MaxMind::scheduled_count() );
		$this->assertNull( $this->event() );
	}

	/**
	 * @return void
	 */
	public function test_missing_credentials_never_schedule(): void {
		$GLOBALS['rwgc_test_options']['rwgc_settings']['maxmind_license_key'] = '';
		RWGC_MaxMind::maybe_sync_schedule();
		RWGC_MaxMind::maybe_sync_schedule();
		$this->assertSame( 0, RWGC_MaxMind::scheduled_count() );
		$this->assertFalse( RWGC_MaxMind::auto_update_wanted() );
	}

	/**
	 * @return void
	 */
	public function test_check_is_hooked_on_init_and_the_version_option_is_removed_on_uninstall(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-maxmind.php' );
		$this->assertStringContainsString( "add_action( 'init', array( __CLASS__, 'maybe_sync_schedule' ), 20 );", $src );
		$uninstall = (string) file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );
		$this->assertStringContainsString( "delete_option( 'rwgc_maxmind_schedule_version' );", $uninstall );
	}
}
