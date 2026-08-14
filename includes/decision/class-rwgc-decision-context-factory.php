<?php
/**
 * Build a request context with lazy capability resolvers (WP19).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eager values plus filter-provided lazy resolvers. Resolvers are memoized.
 */
final class RWGC_Decision_Context_Factory {

	/**
	 * @param array<string, mixed> $eager Already-known values (e.g. geo.country).
	 * @return RWGC_Contract_Context
	 */
	public static function for_request( array $eager = array() ) {
		/**
		 * Lazy context providers: capability ID => callable(): mixed.
		 * Wrap expensive work with {@see RWGC_Context_Value_Cache::remember()}.
		 *
		 * @param array<string, callable> $resolvers Resolvers.
		 */
		$resolvers = apply_filters( 'reactwoo_decision_context_resolvers', array() );
		if ( ! is_array( $resolvers ) ) {
			$resolvers = array();
		}

		$wrapped = array();
		foreach ( $resolvers as $id => $cb ) {
			if ( ! is_callable( $cb ) ) {
				continue;
			}
			$cap = (string) $id;
			$wrapped[ $cap ] = static function () use ( $cap, $cb ) {
				return RWGC_Context_Value_Cache::remember( 'ctx:' . $cap, $cb );
			};
		}

		return RWGC_Contract_Context::from_array( $eager )->with_resolvers( $wrapped );
	}
}
