<?php
/**
 * Schedules Cloud maintenance off the visitor render path.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-Cron only — never HTTP during template render.
 */
final class RWGC_Cloud_Scheduler {

	const HOOK = 'rwgc_cloud_sync_cron';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'cron_tick' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_schedule' ), 30 );
	}

	/**
	 * @return void
	 */
	public static function ensure_schedule() {
		if ( ! RWGC_Cloud_Connection::is_connected() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 120, 'hourly', self::HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function clear_schedule() {
		$ts = wp_next_scheduled( self::HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
			$ts = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function cron_tick() {
		// Hard guard: never run during front-end HTML generation.
		if ( function_exists( 'wp_doing_cron' ) && ! wp_doing_cron() && ! is_admin() ) {
			return;
		}
		RWGC_Cloud_Sync::run_maintenance();
	}
}
