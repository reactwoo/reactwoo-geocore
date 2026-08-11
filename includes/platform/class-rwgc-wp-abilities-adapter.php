<?php
/**
 * Optional bridge from ReactWoo capabilities → WordPress Abilities API (WP 6.9+).
 *
 * Internal registry remains authoritative. This adapter is best-effort only.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a subset of ReactWoo capabilities as WP Abilities when the API exists.
 */
final class RWGC_WP_Abilities_Adapter {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_bridge' ), 20 );
	}

	/**
	 * @return bool
	 */
	public static function is_supported() {
		return function_exists( 'wp_register_ability' );
	}

	/**
	 * @return void
	 */
	public static function maybe_bridge() {
		if ( ! self::is_supported() ) {
			return;
		}
		if ( ! apply_filters( 'reactwoo_bridge_wp_abilities', true ) ) {
			return;
		}

		foreach ( RWGC_Platform_Capability_Registry::all() as $id => $row ) {
			$ability_name = 'reactwoo/' . str_replace( '.', '-', $id );
			/**
			 * Filter ability args before registration.
			 *
			 * @param array<string, mixed>|null $args Null to skip.
			 * @param string                    $id   Capability ID.
			 * @param array<string, mixed>      $row  Registry row.
			 */
			$args = apply_filters(
				'reactwoo_wp_ability_args',
				array(
					'label'       => isset( $row['label'] ) ? (string) $row['label'] : $id,
					'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
					'category'    => 'reactwoo',
					'input_schema' => isset( $row['input_schema'] ) && is_array( $row['input_schema'] ) ? $row['input_schema'] : array(),
				),
				$id,
				$row
			);
			if ( ! is_array( $args ) ) {
				continue;
			}
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WP core API when present.
			wp_register_ability( $ability_name, $args );
		}
	}
}
