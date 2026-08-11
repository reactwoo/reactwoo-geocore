<?php
/**
 * Resolve a variant ID to a typed Variant (or structured failure).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Never throws for visitor path callers.
 */
final class RWGC_Variant_Resolver {

	/**
	 * @param string                             $variant_id Variant ID.
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @return array{ok: bool, variant: ?RWGC_Variant_Interface, reason: string}
	 */
	public static function resolve( $variant_id, $slot = null ) {
		$variant_id = trim( (string) $variant_id );

		if ( '' === $variant_id || 'default' === $variant_id || 'variant_original' === $variant_id ) {
			RWGC_Variant_Diagnostics::record( 'default', $variant_id, $slot ? $slot->id() : '' );
			return array(
				'ok'      => true,
				'variant' => self::make_default( $variant_id ? $variant_id : 'default' ),
				'reason'  => 'default',
			);
		}

		try {
			$variant = RWGC_Variant_Store::get( $variant_id );
			if ( ! $variant ) {
				/**
				 * Allow manifests / satellites to supply a variant by ID.
				 *
				 * @param array<string, mixed>|null $data Data.
				 * @param string                    $variant_id ID.
				 */
				$data = apply_filters( 'reactwoo_lookup_variant', null, $variant_id );
				if ( is_array( $data ) ) {
					$variant = RWGC_Variant_Factory::from_array( $data );
				}
			}

			if ( ! $variant ) {
				RWGC_Variant_Diagnostics::record( 'missing', $variant_id, $slot ? $slot->id() : '' );
				return array(
					'ok'      => false,
					'variant' => null,
					'reason'  => 'missing',
				);
			}

			if ( ! $variant->is_compatible_with_slot( $slot ) ) {
				RWGC_Variant_Diagnostics::record( 'incompatible', $variant_id, $slot ? $slot->id() : '', array( 'type' => $variant->type() ) );
				return array(
					'ok'      => false,
					'variant' => null,
					'reason'  => 'incompatible',
				);
			}

			return array(
				'ok'      => true,
				'variant' => $variant,
				'reason'  => 'ok',
			);
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			RWGC_Variant_Diagnostics::record(
				'invalid',
				$variant_id,
				$slot ? $slot->id() : '',
				array( 'message' => $e->getMessage() )
			);
			return array(
				'ok'      => false,
				'variant' => null,
				'reason'  => 'invalid',
			);
		}
	}

	/**
	 * @param string $id ID.
	 * @return RWGC_Default_Variant
	 */
	private static function make_default( $id ) {
		$contract = RWGC_Contract_Variant::from_array(
			array(
				'id'   => $id,
				'type' => RWGC_Contract_Variant::TYPE_DEFAULT,
			)
		);
		return new RWGC_Default_Variant( $contract );
	}
}
