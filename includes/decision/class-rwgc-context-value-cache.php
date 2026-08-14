<?php
/**
 * Request-scoped memo for expensive context providers (WP19).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lazy providers must wrap remote/DB work with remember().
 */
final class RWGC_Context_Value_Cache {

	/**
	 * @var array<string, mixed>
	 */
	private static $memo = array();

	/**
	 * @param string   $key Cache key.
	 * @param callable $producer Producer.
	 * @return mixed
	 */
	public static function remember( $key, $producer ) {
		$key = (string) $key;
		if ( ! array_key_exists( $key, self::$memo ) ) {
			self::$memo[ $key ] = call_user_func( $producer );
		}
		return self::$memo[ $key ];
	}

	/**
	 * @return void
	 */
	public static function reset() {
		self::$memo = array();
	}
}
