<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves page-level redirect target from a bundle + context.
 *
 * Mirrors legacy RWGC_Routing::maybe_route_request decision logic.
 */
class RWGC_Page_Route_Resolver {

	/**
	 * Compute redirect target page id and reason.
	 *
	 * @param RWGC_Page_Route_Bundle $bundle  Bundle.
	 * @param RWGC_Context           $context Context.
	 * @return array{target_page_id:int, reason:string, page_id:int, country:string, variant_id:string}
	 */
	public static function resolve( $bundle, $context ) {
		$page_id = isset( $bundle->page_id ) ? absint( $bundle->page_id ) : 0;
		$country = $context instanceof RWGC_Context ? $context->country_iso2 : '';

		$decision = array(
			'target_page_id' => 0,
			'reason'         => 'none',
			'page_id'        => $page_id,
			'country'        => $country,
			'variant_id'     => '',
		);

		if ( ! $bundle instanceof RWGC_Page_Route_Bundle || empty( $bundle->enabled ) ) {
			return $decision;
		}

		if ( '' === $country ) {
			return $decision;
		}

		if ( 'variant' === $bundle->role ) {
			if ( $page_id > 0 ) {
				$decision['variant_id'] = 'legacy_variant_' . $page_id;
			}
			$iso    = strtoupper( (string) $bundle->variant_country_iso2 );
			$master = absint( $bundle->master_page_id );
			if ( '' !== $iso && $country !== $iso && $master > 0 ) {
				$decision['target_page_id'] = $master;
				$decision['reason']         = 'variant_fallback_to_master';
			}
			return $decision;
		}

		$matched = RWGC_Fallback_Resolver::first_matching_variant( $bundle->variants, $context );
		if ( $matched instanceof RWGC_Variant && $matched->target_page_id > 0 ) {
			$decision['target_page_id'] = (int) $matched->target_page_id;
			$decision['reason']         = 'master_country_variant_match';
			$decision['variant_id']     = (string) $matched->id;
			return $decision;
		}

		return $decision;
	}
}
