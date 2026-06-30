<?php
/**
 * Admin-only scenario preview for visibility rules (uses shared evaluator).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds snapshots and evaluates portable JSON for the rule editor test panel.
 */
class RWGC_Visibility_Rule_Preview {

	/**
	 * @param string               $portable_raw Portable JSON.
	 * @param array<string,string> $scenario     Test inputs.
	 * @return array<string,mixed>
	 */
	public static function evaluate( $portable_raw, array $scenario ) {
		$decoded = is_string( $portable_raw ) ? json_decode( $portable_raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			return array(
				'matches' => false,
				'error'   => __( 'Rule data is not valid JSON.', 'reactwoo-geocore' ),
			);
		}

		$snapshot = self::snapshot_from_scenario( $scenario );
		$matched  = class_exists( 'RWGC_Rule_Evaluator', false )
			? RWGC_Rule_Evaluator::should_render_content( $decoded, $snapshot )
			: false;

		return array(
			'matches' => (bool) $matched,
			'error'   => '',
		);
	}

	/**
	 * @param array<string,string> $scenario Scenario fields from the test form.
	 * @return RWGC_Context_Snapshot
	 */
	public static function snapshot_from_scenario( array $scenario ) {
		$country    = strtoupper( substr( sanitize_text_field( (string) ( $scenario['country'] ?? '' ) ), 0, 2 ) );
		$device     = strtolower( sanitize_key( (string) ( $scenario['device'] ?? '' ) ) );
		$page_type  = strtolower( sanitize_key( (string) ( $scenario['page_type'] ?? '' ) ) );
		$request_uri = strtolower( sanitize_text_field( (string) ( $scenario['request_uri'] ?? '' ) ) );
		$source     = strtolower( sanitize_text_field( (string) ( $scenario['utm_source'] ?? '' ) ) );
		$medium     = strtolower( sanitize_text_field( (string) ( $scenario['utm_medium'] ?? '' ) ) );

		$page_types = array();
		if ( '' !== $page_type ) {
			$page_types[] = $page_type;
		}

		return new RWGC_Context_Snapshot(
			array(
				'country'      => $country,
				'device_type'  => $device,
				'page_type'    => $page_type,
				'page_types'   => $page_types,
				'request_uri'  => $request_uri,
				'source'       => $source,
				'medium'       => $medium,
			)
		);
	}
}
