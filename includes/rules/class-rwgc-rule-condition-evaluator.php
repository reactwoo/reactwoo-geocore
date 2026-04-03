<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates portable condition arrays against {@see RWGC_Context}.
 *
 * Used by {@see RWGC_Variant::matches()} and {@see RWGC_Rule::matches_context()}.
 *
 * Condition keys (extensible):
 * - countries_include — list of ISO2 codes (or single legacy `country`).
 * - countries_exclude — ISO2 codes that block a match.
 * - country_groups_exclude — slugs; countries from {@see RWGC_Country_Groups} unioned into exclude.
 * - country_groups_include — slugs; countries from {@see RWGC_Country_Groups} unioned into include.
 * - country_groups — legacy ids; additional ISO2 codes via `rwgc_expand_country_groups` (merged after; first arg is current include list).
 */
class RWGC_Rule_Condition_Evaluator {

	/**
	 * Whether the visitor context satisfies the condition set.
	 *
	 * @param array<string, mixed> $conditions Normalized condition bag.
	 * @param RWGC_Context         $context    Context.
	 * @return bool
	 */
	public static function context_matches_conditions( array $conditions, $context ) {
		if ( ! $context instanceof RWGC_Context ) {
			return false;
		}

		$country = $context->country_iso2;
		if ( '' === $country ) {
			return false;
		}

		$include = self::includes_from_conditions( $conditions );

		if ( class_exists( 'RWGC_Country_Groups', false )
			&& ! empty( $conditions['country_groups_include'] )
			&& is_array( $conditions['country_groups_include'] ) ) {
			$include = array_unique(
				array_merge(
					$include,
					self::normalize_iso2_list( RWGC_Country_Groups::expand_groups_to_countries( $conditions['country_groups_include'] ) )
				)
			);
		}

		$group_ids = array();
		if ( ! empty( $conditions['country_groups'] ) && is_array( $conditions['country_groups'] ) ) {
			$group_ids = array_map( 'sanitize_key', array_filter( array_map( 'strval', $conditions['country_groups'] ) ) );
		}

		if ( ! empty( $group_ids ) ) {
			/**
			 * Additional ISO2 codes to union into the include list for legacy `country_groups` ids.
			 *
			 * @param string[]       $iso2_codes Codes already in the include list (for integrator context).
			 * @param string[]       $group_ids  Sanitized group slugs.
			 * @param RWGC_Context   $context    Context.
			 */
			$from_groups = apply_filters( 'rwgc_expand_country_groups', $include, $group_ids, $context );
			$include     = array_unique(
				array_merge(
					$include,
					self::normalize_iso2_list( is_array( $from_groups ) ? $from_groups : array() )
				)
			);
		}

		$include = array_values( array_unique( self::normalize_iso2_list( $include ) ) );

		if ( empty( $include ) ) {
			return false;
		}

		$exclude = self::normalize_iso2_list(
			isset( $conditions['countries_exclude'] ) && is_array( $conditions['countries_exclude'] )
				? $conditions['countries_exclude']
				: array()
		);
		if ( class_exists( 'RWGC_Country_Groups', false )
			&& ! empty( $conditions['country_groups_exclude'] )
			&& is_array( $conditions['country_groups_exclude'] ) ) {
			$exclude = array_merge( $exclude, RWGC_Country_Groups::expand_groups_to_countries( $conditions['country_groups_exclude'] ) );
		}
		$exclude = array_values( array_unique( self::normalize_iso2_list( $exclude ) ) );

		if ( ! empty( $exclude ) && in_array( $country, $exclude, true ) ) {
			return false;
		}

		return in_array( $country, $include, true );
	}

	/**
	 * Build include list from countries_include / legacy country.
	 *
	 * @param array<string, mixed> $conditions Conditions.
	 * @return string[] Uppercase ISO2 codes.
	 */
	public static function includes_from_conditions( array $conditions ) {
		$include = array();
		if ( ! empty( $conditions['countries_include'] ) && is_array( $conditions['countries_include'] ) ) {
			$include = $conditions['countries_include'];
		} elseif ( ! empty( $conditions['country'] ) ) {
			$include = array( (string) $conditions['country'] );
		}
		return self::normalize_iso2_list( $include );
	}

	/**
	 * @param array<int, mixed> $list Raw codes.
	 * @return string[]
	 */
	public static function normalize_iso2_list( $list ) {
		if ( ! is_array( $list ) ) {
			return array();
		}
		$out = array_map(
			static function ( $c ) {
				return strtoupper( substr( sanitize_text_field( (string) $c ), 0, 2 ) );
			},
			$list
		);
		$out = array_filter(
			array_unique( $out ),
			static function ( $c ) {
				return (bool) preg_match( '/^[A-Z]{2}$/', $c );
			}
		);
		return array_values( $out );
	}
}
