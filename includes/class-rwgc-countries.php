<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Country option provider shared by Elementor and Gutenberg controls.
 */
class RWGC_Countries {

	/**
	 * Get ISO2 => country name options.
	 *
	 * @return array
	 */
	public static function get_options() {
		$countries = array();

		// Prefer WooCommerce country list when available (usually complete and translated).
		if ( class_exists( 'WC_Countries' ) ) {
			$wc_countries = new WC_Countries();
			$wc_list      = $wc_countries->get_countries();
			if ( is_array( $wc_list ) && ! empty( $wc_list ) ) {
				$countries = $wc_list;
			}
		}

		// Fallback: broad ISO2 list.
		if ( empty( $countries ) ) {
			$countries = array(
				'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'IE' => 'Ireland', 'AU' => 'Australia',
				'NZ' => 'New Zealand', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy',
				'NL' => 'Netherlands', 'BE' => 'Belgium', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
				'FI' => 'Finland', 'CH' => 'Switzerland', 'AT' => 'Austria', 'PT' => 'Portugal', 'GR' => 'Greece',
				'PL' => 'Poland', 'CZ' => 'Czech Republic', 'SK' => 'Slovakia', 'HU' => 'Hungary', 'RO' => 'Romania',
				'BG' => 'Bulgaria', 'HR' => 'Croatia', 'SI' => 'Slovenia', 'EE' => 'Estonia', 'LV' => 'Latvia',
				'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MT' => 'Malta', 'CY' => 'Cyprus', 'IS' => 'Iceland',
				'UA' => 'Ukraine', 'TR' => 'Turkey', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'IL' => 'Israel',
				'QA' => 'Qatar', 'KW' => 'Kuwait', 'BH' => 'Bahrain', 'OM' => 'Oman', 'EG' => 'Egypt',
				'MA' => 'Morocco', 'TN' => 'Tunisia', 'ZA' => 'South Africa', 'NG' => 'Nigeria', 'KE' => 'Kenya',
				'GH' => 'Ghana', 'IN' => 'India', 'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'LK' => 'Sri Lanka',
				'NP' => 'Nepal', 'SG' => 'Singapore', 'MY' => 'Malaysia', 'TH' => 'Thailand', 'VN' => 'Vietnam',
				'PH' => 'Philippines', 'ID' => 'Indonesia', 'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China',
				'HK' => 'Hong Kong', 'TW' => 'Taiwan', 'BR' => 'Brazil', 'AR' => 'Argentina', 'CL' => 'Chile',
				'CO' => 'Colombia', 'PE' => 'Peru', 'MX' => 'Mexico', 'UY' => 'Uruguay', 'VE' => 'Venezuela',
			);
		}

		ksort( $countries );

		/**
		 * Filter available countries for geo visibility controls.
		 *
		 * @param array $countries ISO2 => country name.
		 */
		return apply_filters( 'rwgc_country_options', $countries );
	}
}

