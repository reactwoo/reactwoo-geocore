<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Picks the first matching variant from an ordered list, then falls back to default.
 */
class RWGC_Fallback_Resolver {

	/**
	 * Sort variants by priority descending.
	 *
	 * @param RWGC_Variant[] $variants Variants.
	 * @return RWGC_Variant[]
	 */
	public static function sort_variants( $variants ) {
		if ( ! is_array( $variants ) ) {
			return array();
		}
		usort(
			$variants,
			static function ( $a, $b ) {
				$pa = $a instanceof RWGC_Variant ? (int) $a->priority : 0;
				$pb = $b instanceof RWGC_Variant ? (int) $b->priority : 0;
				return $pb <=> $pa;
			}
		);
		return $variants;
	}

	/**
	 * First variant that matches context, or null.
	 *
	 * @param RWGC_Variant[] $variants Variants (any order; will be sorted).
	 * @param RWGC_Context   $context  Context.
	 * @return RWGC_Variant|null
	 */
	public static function first_matching_variant( $variants, $context ) {
		foreach ( self::sort_variants( $variants ) as $variant ) {
			if ( $variant instanceof RWGC_Variant && $variant->matches( $context ) ) {
				return $variant;
			}
		}
		return null;
	}
}
