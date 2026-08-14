<?php
/**
 * Entitlement facade loader (WP15).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature code: RWGC_Entitlements::allows( 'cloud.commerce' ).
 */
final class RWGC_Entitlements {

	/** @var bool */
	private static $loaded = false;

	/** @var RWGC_Entitlement_Provider_Interface|null */
	private static $provider = null;

	/**
	 * @return void
	 */
	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;
		$dir          = dirname( __FILE__ ) . '/';
		require_once $dir . 'interface-rwgc-entitlement-provider.php';
		require_once $dir . 'class-rwgc-cloud-entitlement-store.php';
		require_once $dir . 'class-rwgc-standalone-license-provider.php';
		require_once $dir . 'class-rwgc-cloud-entitlement-provider.php';
		require_once $dir . 'class-rwgc-composite-entitlement-provider.php';
		require_once $dir . 'functions-reactwoo-entitlements.php';
	}

	/**
	 * @return RWGC_Entitlement_Provider_Interface
	 */
	public static function provider() {
		self::load();
		if ( self::$provider instanceof RWGC_Entitlement_Provider_Interface ) {
			return self::$provider;
		}
		$composite = new RWGC_Composite_Entitlement_Provider(
			new RWGC_Standalone_License_Provider(),
			new RWGC_Cloud_Entitlement_Provider()
		);
		/**
		 * @param RWGC_Entitlement_Provider_Interface $provider Provider.
		 */
		self::$provider = apply_filters( 'rwgc_entitlement_provider', $composite );
		return self::$provider;
	}

	/**
	 * Tests / adapters.
	 *
	 * @param RWGC_Entitlement_Provider_Interface|null $provider Provider.
	 * @return void
	 */
	public static function set_provider( $provider ) {
		self::$provider = $provider;
	}

	/**
	 * @param string $key Key.
	 * @return bool
	 */
	public static function allows( $key ) {
		return self::provider()->allows( $key );
	}

	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function limit( $key ) {
		return self::provider()->limit( $key );
	}

	/**
	 * @return string
	 */
	public static function source() {
		return self::provider()->source();
	}

	/**
	 * @return array<int, RWGC_Contract_Entitlement>
	 */
	public static function all() {
		return self::provider()->all();
	}
}
