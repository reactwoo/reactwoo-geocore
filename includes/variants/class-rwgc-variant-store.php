<?php
/**
 * Local Variant catalog (option-backed + request overrides).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores variants for local / test use before Cloud manifests.
 */
final class RWGC_Variant_Store {

	const OPTION = 'rwgc_variants';

	/**
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $cache = null;

	/**
	 * In-request overrides (manifest / runtime inject).
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $runtime = array();

	/**
	 * @return void
	 */
	public static function reset_cache() {
		self::$cache   = null;
		self::$runtime = array();
	}

	/**
	 * @param array<string, mixed> $data Variant array.
	 * @return RWGC_Variant_Interface|false
	 */
	public static function register( array $data ) {
		$variant = RWGC_Variant_Factory::from_array( $data );
		if ( ! $variant ) {
			return false;
		}
		$row = $variant->contract()->to_array();
		self::$runtime[ $variant->id() ] = $row;

		$raw = self::all_raw();
		$raw[ $variant->id() ] = $row;
		self::$cache           = $raw;
		update_option( self::OPTION, $raw, false );
		return $variant;
	}

	/**
	 * Inject without persisting (manifest evaluation).
	 *
	 * @param array<string, mixed> $data Data.
	 * @return RWGC_Variant_Interface|false
	 */
	public static function put_runtime( array $data ) {
		$variant = RWGC_Variant_Factory::from_array( $data );
		if ( ! $variant ) {
			return false;
		}
		self::$runtime[ $variant->id() ] = $variant->contract()->to_array();
		return $variant;
	}

	/**
	 * @param string $id ID.
	 * @return RWGC_Variant_Interface|null
	 */
	public static function get( $id ) {
		$id = (string) $id;
		if ( isset( self::$runtime[ $id ] ) && is_array( self::$runtime[ $id ] ) ) {
			return RWGC_Variant_Factory::from_array( self::$runtime[ $id ] );
		}
		$raw = self::all_raw();
		if ( ! isset( $raw[ $id ] ) || ! is_array( $raw[ $id ] ) ) {
			return null;
		}
		return RWGC_Variant_Factory::from_array( $raw[ $id ] );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all_raw() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		self::$cache = $stored;
		return self::$cache;
	}
}
