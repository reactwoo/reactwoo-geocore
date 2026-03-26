<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public API wrapper for Geo Core.
 */
class RWGC_API {

	/**
	 * Whether Geo Core is "ready" (enabled + DB present or sensible fallback).
	 *
	 * @return bool
	 */
	public static function is_ready() {
		if ( ! RWGC_Settings::get( 'enabled', 1 ) ) {
			return false;
		}
		// Even without DB, we can provide fallback country/currency; still count as ready.
		return true;
	}

	/**
	 * Get full visitor data payload.
	 *
	 * @return array
	 */
	public static function get_visitor_data() {
		if ( ! self::is_ready() ) {
			$country  = strtoupper( (string) apply_filters( 'rwgc_fallback_country', RWGC_Settings::get( 'fallback_country', 'US' ) ) );
			$currency = strtoupper( (string) apply_filters( 'rwgc_fallback_currency', RWGC_Settings::get( 'fallback_currency', 'USD' ) ) );
			$data     = array(
				'ip'           => '',
				'country_code' => $country,
				'country_name' => RWGC_GeoIP::get_country_name( $country ),
				'region'       => '',
				'city'         => '',
				'currency'     => $currency,
				'source'       => 'disabled',
				'cached'       => false,
			);
			return apply_filters( 'rwgc_geo_data', $data );
		}

		return RWGC_GeoIP::resolve_visitor();
	}

	/**
	 * Get visitor country code (ISO2).
	 *
	 * @return string
	 */
	public static function get_visitor_country() {
		$data = self::get_visitor_data();
		return isset( $data['country_code'] ) ? strtoupper( (string) $data['country_code'] ) : '';
	}

	/**
	 * Get visitor country name.
	 *
	 * @return string
	 */
	public static function get_visitor_country_name() {
		$data = self::get_visitor_data();
		return isset( $data['country_name'] ) ? (string) $data['country_name'] : '';
	}

	/**
	 * Get visitor region.
	 *
	 * @return string
	 */
	public static function get_visitor_region() {
		$data = self::get_visitor_data();
		return isset( $data['region'] ) ? (string) $data['region'] : '';
	}

	/**
	 * Get visitor city.
	 *
	 * @return string
	 */
	public static function get_visitor_city() {
		$data = self::get_visitor_data();
		return isset( $data['city'] ) ? (string) $data['city'] : '';
	}

	/**
	 * Get visitor currency.
	 *
	 * @return string
	 */
	public static function get_visitor_currency() {
		$data = self::get_visitor_data();
		return isset( $data['currency'] ) ? strtoupper( (string) $data['currency'] ) : '';
	}

	/**
	 * Get currency for a given country using default mapping.
	 *
	 * @param string $country_code ISO2.
	 * @return string
	 */
	public static function get_currency_for_country( $country_code ) {
		$map     = self::get_country_currency_map();
		$country = strtoupper( (string) $country_code );
		return isset( $map[ $country ] ) ? strtoupper( $map[ $country ] ) : '';
	}

	/**
	 * Whether a country code exists in the mapping.
	 *
	 * @param string $country_code ISO2.
	 * @return bool
	 */
	public static function has_country( $country_code ) {
		$map     = self::get_country_currency_map();
		$country = strtoupper( (string) $country_code );
		return isset( $map[ $country ] );
	}

	/**
	 * Default country → currency map with filter.
	 *
	 * @return array
	 */
	public static function get_country_currency_map() {
		$map = array(
			'GB' => 'GBP',
			'US' => 'USD',
			'ZA' => 'ZAR',
			'AU' => 'AUD',
			'CA' => 'CAD',
			'DE' => 'EUR',
		);

		/**
		 * Filter country → currency map.
		 *
		 * @param array $map Map of ISO2 => ISO3 currency code.
		 */
		$map = apply_filters( 'rwgc_country_currency_map', $map );
		return is_array( $map ) ? $map : array();
	}
}

