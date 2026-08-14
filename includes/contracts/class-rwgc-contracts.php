<?php
/**
 * Loader for ReactWoo platform contracts (WP1).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires contract class files once.
 */
final class RWGC_Contracts {

	/**
	 * @var bool
	 */
	private static $loaded = false;

	/**
	 * Load all contract classes.
	 *
	 * @return void
	 */
	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;

		$dir = dirname( __FILE__ ) . '/';
		require_once $dir . 'class-rwgc-schema.php';
		require_once $dir . 'class-rwgc-contract-exception.php';
		require_once $dir . 'class-rwgc-contract.php';
		require_once $dir . 'class-rwgc-contract-capability.php';
		require_once $dir . 'class-rwgc-contract-context.php';
		require_once $dir . 'class-rwgc-contract-condition.php';
		require_once $dir . 'class-rwgc-contract-condition-group.php';
		require_once $dir . 'class-rwgc-contract-audience.php';
		require_once $dir . 'class-rwgc-contract-experience-slot.php';
		require_once $dir . 'class-rwgc-contract-variant.php';
		require_once $dir . 'class-rwgc-contract-experience.php';
		require_once $dir . 'class-rwgc-contract-experiment.php';
		require_once $dir . 'class-rwgc-contract-goal.php';
		require_once $dir . 'class-rwgc-contract-event.php';
		require_once $dir . 'class-rwgc-contract-entitlement.php';
		require_once $dir . 'class-rwgc-contract-recommendation.php';
		require_once $dir . 'class-rwgc-contract-manifest.php';
	}

	/**
	 * Schema version constant accessor.
	 *
	 * @return int
	 */
	public static function schema_version() {
		self::load();
		return RWGC_Schema::VERSION;
	}
}
