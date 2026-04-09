<?php
/**
 * Target availability metadata for admin and rule builders.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves availability using definition callbacks and provider state.
 */
class RWGC_Target_Availability {

	/**
	 * @param array<string, mixed> $definition Target definition.
	 * @return bool
	 */
	public static function is_available( array $definition ) {
		if ( ! empty( $definition['is_available_callback'] ) && is_callable( $definition['is_available_callback'] ) ) {
			try {
				return (bool) call_user_func( $definition['is_available_callback'], $definition );
			} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented -- defensive.
				return false;
			}
		}
		return true;
	}

	/**
	 * Human-readable status for admin tables.
	 *
	 * @param array<string, mixed> $definition Target definition.
	 * @return array{code: string, label: string}
	 */
	public static function describe( array $definition ) {
		$available = self::is_available( $definition );
		if ( $available ) {
			return array(
				'code'  => 'available',
				'label' => __( 'Available', 'reactwoo-geocore' ),
			);
		}
		return array(
			'code'  => 'unavailable',
			'label' => __( 'Unavailable', 'reactwoo-geocore' ),
		);
	}
}
