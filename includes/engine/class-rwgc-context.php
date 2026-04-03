<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalized request context for rules and variant resolution.
 *
 * Phase 1: country + extension bag. Additional signals attach without breaking callers.
 */
class RWGC_Context {

	/**
	 * ISO 3166-1 alpha-2 country code (uppercase) or empty string.
	 *
	 * @var string
	 */
	public $country_iso2 = '';

	/**
	 * Arbitrary extension fields (device, language, UTM, etc.).
	 *
	 * @var array<string, mixed>
	 */
	public $extra = array();

	/**
	 * @param string               $country_iso2 ISO2 country.
	 * @param array<string, mixed> $extra Optional extensions.
	 */
	public function __construct( $country_iso2 = '', $extra = array() ) {
		$this->country_iso2 = is_string( $country_iso2 ) ? strtoupper( substr( sanitize_text_field( $country_iso2 ), 0, 2 ) ) : '';
		if ( ! preg_match( '/^[A-Z]{2}$/', $this->country_iso2 ) ) {
			$this->country_iso2 = '';
		}
		$this->extra = is_array( $extra ) ? $extra : array();
	}

	/**
	 * Build context from current visitor geo.
	 *
	 * @return self
	 */
	public static function from_visitor() {
		$country = function_exists( 'rwgc_get_visitor_country' ) ? (string) rwgc_get_visitor_country() : '';
		return new self( $country, array() );
	}

	/**
	 * Portable snapshot for events and logging (no object references).
	 *
	 * @return array<string, mixed>
	 */
	public function to_snapshot() {
		return array(
			'country_iso2' => $this->country_iso2,
			'extra'        => $this->extra,
		);
	}
}
