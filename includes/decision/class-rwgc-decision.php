<?php
/**
 * Loader for Decision Runtime classes.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires decision engine files once.
 */
final class RWGC_Decision {

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
		require_once $dir . 'class-rwgc-decision-result.php';
		require_once $dir . 'class-rwgc-context-value-cache.php';
		require_once $dir . 'class-rwgc-decision-context-factory.php';
		require_once $dir . 'class-rwgc-decision-condition-evaluator.php';
		require_once $dir . 'class-rwgc-decision-experiment-assigner.php';
		require_once $dir . 'class-rwgc-decision-runtime.php';
		require_once $dir . 'class-rwgc-decision-parity.php';
		require_once $dir . 'class-rwgc-request-decision.php';
	}
}
