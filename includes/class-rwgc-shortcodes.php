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
	 * Conditional shortcode based on visitor country.
	 *
	 * Usage: [rwgc_if country="GB,US"]Content[/rwgc_if]
	 *
	 * @param array       $atts    Attributes.
	 * @param string|null $content Wrapped content.
	 * @return string
	 */
	public static function render_conditional( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'country' => '',
			),
			$atts,
			'rwgc_if'
		);

		$country   = strtoupper( rwgc_get_visitor_country() );
		$allowed   = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) $atts['country'] ) ) ) );
		$has_match = $country && in_array( $country, $allowed, true );

		if ( ! $has_match ) {
			return '';
		}
		return do_shortcode( (string) $content );
	}
}

