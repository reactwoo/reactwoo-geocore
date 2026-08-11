<?php
/**
 * Loader for Experience Slot API.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires slot classes once.
 */
final class RWGC_Experience_Slots {

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
		require_once $dir . 'class-rwgc-experience-slot-id.php';
		require_once $dir . 'class-rwgc-experience-slot-registry.php';
		require_once $dir . 'class-rwgc-experience-slot-resolver.php';
		require_once $dir . 'class-rwgc-experience-slot-renderer.php';
		require_once $dir . 'functions-reactwoo-slots.php';
		require_once $dir . 'class-rwgc-experience-slots-admin.php';
	}

	/**
	 * @return void
	 */
	public static function init() {
		self::load();
		if ( is_admin() ) {
			RWGC_Experience_Slots_Admin::init();
		}
	}
}
