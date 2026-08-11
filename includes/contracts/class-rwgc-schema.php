<?php
/**
 * ReactWoo platform schema version and legacy capability aliases.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema constants shared by platform contracts (WP1).
 *
 * Does not change runtime behaviour; aliases prepare WP2–4 migration.
 */
final class RWGC_Schema {

	/**
	 * Platform contract schema version (`reactwoo_schema_version`).
	 */
	const VERSION = 1;

	/**
	 * Manifest document schema string.
	 */
	const MANIFEST_SCHEMA = '1.0';

	/**
	 * Map legacy portable condition slugs → dotted capability IDs.
	 *
	 * @var array<string, string>
	 */
	const LEGACY_CONDITION_ALIASES = array(
		'country'              => 'geo.country',
		'country_group'        => 'geo.country_group',
		'language'             => 'visitor.language',
		'locale'               => 'visitor.locale',
		'device'               => 'visitor.device',
		'device_type'          => 'visitor.device',
		'logged_in'            => 'visitor.logged_in',
		'time_of_day'          => 'time.of_day',
		'day_of_week'          => 'time.day_of_week',
		'time'                 => 'time.local',
		'day'                  => 'time.day',
		'date'                 => 'time.date',
		'utm_source'           => 'traffic.utm_source',
		'utm_medium'           => 'traffic.utm_medium',
		'utm_campaign'         => 'traffic.utm_campaign',
		'campaign'             => 'traffic.campaign',
		'source'               => 'traffic.source',
		'medium'               => 'traffic.medium',
		'audience'             => 'traffic.audience',
		'weather_facet'        => 'weather.facet',
		'weather_condition'    => 'weather.condition',
		'temperature'          => 'weather.temperature',
		'precipitation_probability' => 'weather.precipitation_probability',
		'wind_speed'           => 'weather.wind_speed',
		'humidity'             => 'weather.humidity',
		'page_type'            => 'page.type',
		'request_uri'          => 'page.request_uri',
		'page_version_url'     => 'page.version_url',
	);

	/**
	 * @return int
	 */
	public static function version() {
		return self::VERSION;
	}

	/**
	 * Normalize a capability ID; resolve known legacy aliases.
	 *
	 * @param mixed $raw Raw ID.
	 * @return string Empty when invalid.
	 */
	public static function normalize_capability_id( $raw ) {
		$id = strtolower( trim( (string) $raw ) );
		if ( '' === $id || strlen( $id ) > 160 ) {
			return '';
		}
		if ( isset( self::LEGACY_CONDITION_ALIASES[ $id ] ) ) {
			$id = self::LEGACY_CONDITION_ALIASES[ $id ];
		}
		if ( ! preg_match( '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $id ) ) {
			return '';
		}
		return $id;
	}

	/**
	 * Whether a capability ID is well-formed (does not check registration).
	 *
	 * @param mixed $raw Raw ID.
	 * @return bool
	 */
	public static function is_valid_capability_id( $raw ) {
		return '' !== self::normalize_capability_id( $raw );
	}

	/**
	 * Split known keys from forward-compatible extras.
	 *
	 * @param array<string, mixed> $data Input.
	 * @param string[]             $known Known keys.
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>} [known_values, extras]
	 */
	public static function partition( array $data, array $known ) {
		$known_flip = array_fill_keys( $known, true );
		$core       = array();
		$extras     = array();
		foreach ( $data as $key => $value ) {
			$key = (string) $key;
			if ( isset( $known_flip[ $key ] ) ) {
				$core[ $key ] = $value;
			} else {
				$extras[ $key ] = $value;
			}
		}
		return array( $core, $extras );
	}
}
