<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Named country lists for variant conditions (Phase 2).
 *
 * Stored in option `rwgc_country_groups` as:
 * `array( 'group_slug' => array( 'label' => string, 'countries' => string[] ) )`.
 * Pro / extensions may filter `rwgc_country_groups` to register groups.
 */
class RWGC_Country_Groups {

	const OPTION_KEY = 'rwgc_country_groups';

	/**
	 * Register hooks (default group expansion for legacy `country_groups` conditions).
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_expand_country_groups', array( __CLASS__, 'filter_expand_country_groups' ), 5, 3 );
	}

	/**
	 * Default: resolve `country_groups` slugs through the stored registry / filters.
	 *
	 * @param string[]       $iso2_codes Current include list (unused; available for overrides).
	 * @param string[]       $group_ids  Group slugs from conditions.
	 * @param RWGC_Context   $context    Visitor context.
	 * @return string[] Additional ISO2 codes to union into the include list.
	 */
	public static function filter_expand_country_groups( $iso2_codes, $group_ids, $context ) {
		unset( $iso2_codes, $context );
		if ( ! is_array( $group_ids ) || empty( $group_ids ) ) {
			return array();
		}
		return self::expand_groups_to_countries( $group_ids );
	}

	/**
	 * Raw registry after `rwgc_country_groups` filter.
	 *
	 * @return array<string, array{label?:string, countries?:string[]}>
	 */
	public static function get_all() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		/**
		 * Filter registered country groups (slug => config).
		 *
		 * @param array<string, array<string, mixed>> $raw Groups from the option store.
		 */
		return apply_filters( 'rwgc_country_groups', $raw );
	}

	/**
	 * ISO2 codes for one group slug.
	 *
	 * @param string $group_id Sanitized slug.
	 * @return string[]
	 */
	public static function get_countries_for_group( $group_id ) {
		$group_id = sanitize_key( (string) $group_id );
		if ( '' === $group_id ) {
			return array();
		}
		$all = self::get_all();
		if ( empty( $all[ $group_id ] ) || ! is_array( $all[ $group_id ] ) ) {
			return array();
		}
		$countries = isset( $all[ $group_id ]['countries'] ) && is_array( $all[ $group_id ]['countries'] )
			? $all[ $group_id ]['countries']
			: array();
		$out       = self::normalize_iso2_list( $countries );
		/**
		 * Filter resolved countries for a single group (e.g. merge dynamic lists).
		 *
		 * @param string[] $out      ISO2 codes.
		 * @param string   $group_id Group slug.
		 */
		return apply_filters( 'rwgc_country_group_countries', $out, $group_id );
	}

	/**
	 * Merge ISO2 lists from multiple group ids.
	 *
	 * @param string[] $group_ids Group slugs.
	 * @return string[]
	 */
	public static function expand_groups_to_countries( $group_ids ) {
		if ( ! is_array( $group_ids ) ) {
			return array();
		}
		$merged = array();
		foreach ( $group_ids as $gid ) {
			$gid = sanitize_key( (string) $gid );
			if ( '' === $gid ) {
				continue;
			}
			$merged = array_merge( $merged, self::get_countries_for_group( $gid ) );
		}
		return array_values( array_unique( $merged ) );
	}

	/**
	 * @param array<int|string, mixed> $list Raw codes.
	 * @return string[]
	 */
	public static function normalize_iso2_list( $list ) {
		if ( ! is_array( $list ) ) {
			return array();
		}
		$out = array();
		foreach ( $list as $c ) {
			$iso = strtoupper( substr( sanitize_text_field( (string) $c ), 0, 2 ) );
			if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
				$out[] = $iso;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
