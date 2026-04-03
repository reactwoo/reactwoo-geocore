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

	/**
	 * ISO3 currency code => label for select controls (fallback UI when WooCommerce is absent).
	 *
	 * @return array<string, string>
	 */
	private static function default_currency_options() {
		return array(
			'USD' => 'USD — US dollar',
			'EUR' => 'EUR — Euro',
			'GBP' => 'GBP — British pound',
			'CAD' => 'CAD — Canadian dollar',
			'AUD' => 'AUD — Australian dollar',
			'NZD' => 'NZD — New Zealand dollar',
			'CHF' => 'CHF — Swiss franc',
			'JPY' => 'JPY — Japanese yen',
			'CNY' => 'CNY — Chinese yuan',
			'INR' => 'INR — Indian rupee',
			'BRL' => 'BRL — Brazilian real',
			'MXN' => 'MXN — Mexican peso',
			'ZAR' => 'ZAR — South African rand',
			'SEK' => 'SEK — Swedish krona',
			'NOK' => 'NOK — Norwegian krone',
			'DKK' => 'DKK — Danish krone',
			'PLN' => 'PLN — Polish złoty',
			'SGD' => 'SGD — Singapore dollar',
			'HKD' => 'HKD — Hong Kong dollar',
			'KRW' => 'KRW — South Korean won',
			'TRY' => 'TRY — Turkish lira',
			'AED' => 'AED — UAE dirham',
			'SAR' => 'SAR — Saudi riyal',
			'ILS' => 'ILS — Israeli shekel',
			'THB' => 'THB — Thai baht',
			'MYR' => 'MYR — Malaysian ringgit',
			'PHP' => 'PHP — Philippine peso',
			'IDR' => 'IDR — Indonesian rupiah',
			'CZK' => 'CZK — Czech koruna',
			'HUF' => 'HUF — Hungarian forint',
			'RON' => 'RON — Romanian leu',
		);
	}

	/**
	 * Currency options for select inputs (WooCommerce list when available).
	 *
	 * @return array<string, string> ISO3 => label
	 */
	public static function get_currency_options() {
		if ( function_exists( 'get_woocommerce_currencies' ) ) {
			$wc = get_woocommerce_currencies();
			if ( is_array( $wc ) && ! empty( $wc ) ) {
				/**
				 * Filter currency dropdown options (ISO3 => label).
				 *
				 * @param array<string, string> $wc WooCommerce currencies.
				 */
				return apply_filters( 'rwgc_currency_options', $wc );
			}
		}
		$fallback = self::default_currency_options();
		/**
		 * Filter currency dropdown options when WooCommerce is not used.
		 *
		 * @param array<string, string> $fallback Built-in ISO3 labels.
		 */
		return apply_filters( 'rwgc_currency_options', $fallback );
	}
}

