<?php
/**
 * Shapes flat {@see RWGC_Context_Resolver} arrays into a marketer-facing portable snapshot.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds nested structures (`time`, `attribution`, geo coordinates when present).
 */
class RWGC_Context_Snapshot_Formatter {

	/**
	 * Normalize merged resolver values into the portable snapshot shape used by tools and Pro.
	 *
	 * @param array<string, mixed> $flat Flat snapshot from {@see RWGC_Context_Snapshot::to_array()}.
	 * @return array<string, mixed>
	 */
	public static function enrich( array $flat ) {
		$visitor = function_exists( 'rwgc_get_visitor_data' ) ? rwgc_get_visitor_data() : array();

		$lat = isset( $visitor['latitude'] ) ? $visitor['latitude'] : ( isset( $flat['latitude'] ) ? $flat['latitude'] : null );
		$lon = isset( $visitor['longitude'] ) ? $visitor['longitude'] : ( isset( $flat['longitude'] ) ? $flat['longitude'] : null );

		if ( is_numeric( $lat ) ) {
			$flat['latitude'] = (float) $lat;
		}
		if ( is_numeric( $lon ) ) {
			$flat['longitude'] = (float) $lon;
		}

		$tz_used = '';
		if ( function_exists( 'wp_timezone_string' ) ) {
			$tz_used = (string) wp_timezone_string();
		}
		if ( '' === $tz_used && function_exists( 'wp_timezone' ) ) {
			try {
				$tz_used = wp_timezone()->getName();
			} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
				unset( $e );
			}
		}

		$flat['timezone'] = isset( $flat['timezone'] ) && is_string( $flat['timezone'] ) && '' !== $flat['timezone']
			? $flat['timezone']
			: $tz_used;

		$site_ts = function_exists( 'current_time' ) ? (string) current_time( 'mysql' ) : '';
		$flat['time']    = isset( $flat['time'] ) && is_array( $flat['time'] ) ? $flat['time'] : array();
		$flat['time']    = array_merge(
			array(
				'site_timestamp'    => $site_ts,
				'visitor_timestamp' => $site_ts,
				'timezone_used'     => $tz_used,
			),
			$flat['time']
		);

		if ( ! isset( $flat['attribution'] ) || ! is_array( $flat['attribution'] ) ) {
			$flat['attribution'] = array(
				'source'      => isset( $flat['source'] ) ? (string) $flat['source'] : '',
				'medium'      => isset( $flat['medium'] ) ? (string) $flat['medium'] : '',
				'campaign'    => isset( $flat['campaign'] ) ? (string) $flat['campaign'] : '',
				'campaign_id' => isset( $flat['campaign_id'] ) ? (string) $flat['campaign_id'] : '',
				'content'     => isset( $flat['content'] ) ? (string) $flat['content'] : '',
				'term'        => isset( $flat['term'] ) ? (string) $flat['term'] : '',
				'gclid'       => isset( $flat['gclid'] ) ? (string) $flat['gclid'] : '',
			);
		}

		$aud = isset( $flat['ga_audience'] ) && is_array( $flat['ga_audience'] ) ? $flat['ga_audience'] : array();
		if ( isset( $flat['google_analytics_audience'] ) && is_array( $flat['google_analytics_audience'] ) ) {
			$aud = array_merge( $aud, $flat['google_analytics_audience'] );
		}
		$flat['audiences'] = array_values( array_unique( array_map( 'strval', $aud ) ) );

		if ( ! isset( $flat['weather'] ) || ! is_array( $flat['weather'] ) ) {
			$flat['weather'] = array(
				'available' => false,
			);
		}

		/**
		 * Extended portable snapshot for diagnostics / builder previews.
		 *
		 * @param array<string, mixed> $flat Snapshot rows.
		 */
		return apply_filters( 'rwgc_context_snapshot_portable', $flat );
	}
}
