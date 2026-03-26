<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rwgc_is_ready' ) ) {
	/**
	 * Whether Geo Core is ready for use.
	 *
	 * @return bool
	 */
	function rwgc_is_ready() {
		return RWGC_API::is_ready();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_data' ) ) {
	/**
	 * Get full visitor geo payload.
	 *
	 * @return array
	 */
	function rwgc_get_visitor_data() {
		return RWGC_API::get_visitor_data();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * Get visitor country code.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return RWGC_API::get_visitor_country();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country_name' ) ) {
	/**
	 * Get visitor country name.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_country_name() {
		return RWGC_API::get_visitor_country_name();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_region' ) ) {
	/**
	 * Get visitor region.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_region() {
		return RWGC_API::get_visitor_region();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_city' ) ) {
	/**
	 * Get visitor city.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_city() {
		return RWGC_API::get_visitor_city();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_currency' ) ) {
	/**
	 * Get visitor currency.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_currency() {
		return RWGC_API::get_visitor_currency();
	}
}

if ( ! function_exists( 'rwgc_get_currency_for_country' ) ) {
	/**
	 * Get suggested currency for a country.
	 *
	 * @param string $country_code ISO2.
	 * @return string
	 */
	function rwgc_get_currency_for_country( $country_code ) {
		return RWGC_API::get_currency_for_country( $country_code );
	}
}

if ( ! function_exists( 'rwgc_has_country' ) ) {
	/**
	 * Whether a country is present in the mapping.
	 *
	 * @param string $country_code ISO2.
	 * @return bool
	 */
	function rwgc_has_country( $country_code ) {
		return RWGC_API::has_country( $country_code );
	}
}

