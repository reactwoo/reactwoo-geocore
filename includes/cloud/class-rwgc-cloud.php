<?php
/**
 * Loader for ReactWoo Cloud Connector (WP10).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin/cron Cloud connectivity. Forbidden on visitor page-render path.
 */
final class RWGC_Cloud {

	/** @var bool */
	private static $loaded = false;

	/**
	 * @return void
	 */
	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;
		$dir          = dirname( __FILE__ ) . '/';
		require_once $dir . 'class-rwgc-cloud-config.php';
		require_once $dir . 'class-rwgc-cloud-credentials.php';
		require_once $dir . 'class-rwgc-cloud-connection.php';
		require_once $dir . 'class-rwgc-cloud-http.php';
		require_once $dir . 'class-rwgc-cloud-manifest-store.php';
		require_once $dir . 'class-rwgc-cloud-pairing.php';
		require_once $dir . 'class-rwgc-cloud-sync.php';
		require_once $dir . 'class-rwgc-cloud-scheduler.php';
		require_once $dir . 'class-rwgc-cloud-event-queue.php';
		require_once $dir . 'class-rwgc-cloud-telemetry.php';
		require_once $dir . 'class-rwgc-cloud-health.php';
		require_once $dir . 'class-rwgc-cloud-migration-translator.php';
		require_once $dir . 'class-rwgc-cloud-migration.php';
		require_once $dir . 'class-rwgc-cloud-admin.php';
		require_once $dir . 'functions-reactwoo-cloud.php';
	}

	/**
	 * @return void
	 */
	public static function init() {
		self::load();
		RWGC_Cloud_Http::register_hooks();
		RWGC_Cloud_Scheduler::init();
		RWGC_Cloud_Telemetry::init();
		if ( is_admin() ) {
			RWGC_Cloud_Admin::init();
		}
	}
}
