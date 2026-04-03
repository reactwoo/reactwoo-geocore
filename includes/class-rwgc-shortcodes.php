<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes for ReactWoo Geo Core.
 */
class RWGC_Shortcodes {

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'rwgc_country', array( __CLASS__, 'render_country' ) );
		add_shortcode( 'rwgc_country_code', array( __CLASS__, 'render_country_code' ) );
		add_shortcode( 'rwgc_currency', array( __CLASS__, 'render_currency' ) );
		add_shortcode( 'rwgc_city', array( __CLASS__, 'render_city' ) );
		add_shortcode( 'rwgc_region', array( __CLASS__, 'render_region' ) );
		add_shortcode( 'rwgc_if', array( __CLASS__, 'render_conditional' ) );
	}

	public static function render_country() {
		return esc_html( rwgc_get_visitor_country_name() );
	}

	public static function render_country_code() {
		return esc_html( rwgc_get_visitor_country() );
	}

	public static function render_currency() {
		return esc_html( rwgc_get_visitor_currency() );
	}

	public static function render_city() {
		return esc_html( rwgc_get_visitor_city() );
	}

	public static function render_region() {
		return esc_html( rwgc_get_visitor_region() );
	}

	/**
	 * Conditional shortcode based on visitor country (aligned with {@see RWGC_Rule_Condition_Evaluator} when includes are set).
	 *
	 * Include list (visitor must match):
	 * `[rwgc_if country="GB,US"]…[/rwgc_if]`
	 * `[rwgc_if groups="slug_a,slug_b"]…[/rwgc_if]` — uses {@see RWGC_Country_Groups}.
	 *
	 * Exclude (combined with includes): visitor must match include rules and not be in exclude lists:
	 * `[rwgc_if country="GB,DE" exclude="FR"]…[/rwgc_if]`
	 * `[rwgc_if groups="eu" groups_exclude="uk"]…[/rwgc_if]`
	 *
	 * Exclude-only (visitor must have a country and it must not be listed):
	 * `[rwgc_if exclude="US,CA"]…[/rwgc_if]`
	 * `[rwgc_if groups_exclude="blocked"]…[/rwgc_if]`
	 *
	 * @param array       $atts    Attributes.
	 * @param string|null $content Wrapped content.
	 * @return string
	 */
	public static function render_conditional( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'country'         => '',
				'exclude'         => '',
				'groups'          => '',
				'groups_exclude'  => '',
			),
			$atts,
			'rwgc_if'
		);

		$country_csv         = trim( (string) $atts['country'] );
		$exclude_csv         = trim( (string) $atts['exclude'] );
		$groups_csv          = trim( (string) $atts['groups'] );
		$groups_exclude_csv  = trim( (string) $atts['groups_exclude'] );

		$has_include = '' !== $country_csv || '' !== $groups_csv;
		$has_exclude_attrs = '' !== $exclude_csv || '' !== $groups_exclude_csv;

		if ( ! $has_include && ! $has_exclude_attrs ) {
			return '';
		}

		if ( ! $has_include && $has_exclude_attrs ) {
			return self::render_conditional_exclude_only( $exclude_csv, $groups_exclude_csv, $content );
		}

		if ( ! class_exists( 'RWGC_Rule_Condition_Evaluator', false ) || ! class_exists( 'RWGC_Context', false ) ) {
			return '';
		}

		$conditions = array();
		if ( '' !== $country_csv ) {
			$conditions['countries_include'] = self::parse_iso_csv( $country_csv );
		}
		if ( '' !== $exclude_csv ) {
			$conditions['countries_exclude'] = self::parse_iso_csv( $exclude_csv );
		}
		if ( '' !== $groups_csv ) {
			$conditions['country_groups_include'] = self::parse_slug_csv( $groups_csv );
		}
		if ( '' !== $groups_exclude_csv ) {
			$conditions['country_groups_exclude'] = self::parse_slug_csv( $groups_exclude_csv );
		}

		$context = RWGC_Context::from_visitor();
		$match   = RWGC_Rule_Condition_Evaluator::context_matches_conditions( $conditions, $context );

		if ( ! $match ) {
			return '';
		}

		return do_shortcode( (string) $content );
	}

	/**
	 * Show inner content when visitor country exists and is not in exclude lists.
	 *
	 * @param string      $exclude_csv        Comma ISO2 codes.
	 * @param string      $groups_exclude_csv Comma group slugs.
	 * @param string|null $content            Inner content.
	 * @return string
	 */
	private static function render_conditional_exclude_only( $exclude_csv, $groups_exclude_csv, $content ) {
		$visitor = strtoupper( (string) rwgc_get_visitor_country() );
		if ( '' === $visitor ) {
			return '';
		}

		$exclude = self::parse_iso_csv( $exclude_csv );
		if ( class_exists( 'RWGC_Country_Groups', false ) && '' !== $groups_exclude_csv ) {
			$exclude = array_merge(
				$exclude,
				RWGC_Country_Groups::expand_groups_to_countries( self::parse_slug_csv( $groups_exclude_csv ) )
			);
		}
		if ( class_exists( 'RWGC_Rule_Condition_Evaluator', false ) ) {
			$exclude = RWGC_Rule_Condition_Evaluator::normalize_iso2_list( $exclude );
		} else {
			$exclude = array_unique( array_filter( array_map( 'strtoupper', $exclude ) ) );
		}

		if ( ! empty( $exclude ) && in_array( $visitor, $exclude, true ) ) {
			return '';
		}

		return do_shortcode( (string) $content );
	}

	/**
	 * @param string $csv Comma-separated ISO2 codes.
	 * @return string[]
	 */
	private static function parse_iso_csv( $csv ) {
		$parts = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) $csv ) ) ) );
		if ( class_exists( 'RWGC_Rule_Condition_Evaluator', false ) ) {
			return RWGC_Rule_Condition_Evaluator::normalize_iso2_list( $parts );
		}
		return $parts;
	}

	/**
	 * @param string $csv Comma-separated slugs.
	 * @return string[]
	 */
	private static function parse_slug_csv( $csv ) {
		$parts = array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) );
		return array_map( 'sanitize_key', $parts );
	}
}

