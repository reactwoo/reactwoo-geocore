<?php
/**
 * Structured Variant diagnostics (never visitor-facing).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects fallback reasons for admin / tests.
 */
final class RWGC_Variant_Diagnostics {

	/**
	 * @var list<array<string, mixed>>
	 */
	private static $events = array();

	/**
	 * @return void
	 */
	public static function reset() {
		self::$events = array();
	}

	/**
	 * @param string               $code Reason code.
	 * @param string               $variant_id Variant ID.
	 * @param string               $slot_id Slot ID.
	 * @param array<string, mixed> $extra Extra.
	 * @return void
	 */
	public static function record( $code, $variant_id = '', $slot_id = '', array $extra = array() ) {
		self::$events[] = array_merge(
			array(
				'code'       => (string) $code,
				'variant_id' => (string) $variant_id,
				'slot_id'    => (string) $slot_id,
				'time'       => function_exists( 'gmdate' ) ? gmdate( 'c' ) : date( 'c' ),
			),
			$extra
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function events() {
		return self::$events;
	}

	/**
	 * @param string $code Code.
	 * @return int
	 */
	public static function count_code( $code ) {
		$n = 0;
		foreach ( self::$events as $e ) {
			if ( isset( $e['code'] ) && $e['code'] === $code ) {
				++$n;
			}
		}
		return $n;
	}
}
