<?php
/**
 * Stable experiment bucket assignment (local only).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deterministic: hash(experimentId + anonymousVisitorId) → bucket.
 */
final class RWGC_Decision_Experiment_Assigner {

	/**
	 * Pick a variant ID for an experiment.
	 *
	 * @param RWGC_Contract_Experiment $experiment Experiment.
	 * @param string                   $anonymous_visitor_id Visitor ID.
	 * @return string Variant ID (control when empty visitor or no variants).
	 */
	public static function assign( RWGC_Contract_Experiment $experiment, $anonymous_visitor_id ) {
		$control = $experiment->control();
		$visitor = trim( (string) $anonymous_visitor_id );
		if ( '' === $visitor ) {
			return $control;
		}

		$pool = array();
		// Control gets remaining allocation if not listed.
		$listed = 0.0;
		foreach ( $experiment->variants() as $row ) {
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' === $id ) {
				continue;
			}
			$alloc = isset( $row['allocation'] ) ? (float) $row['allocation'] : 0;
			if ( $alloc < 0 ) {
				$alloc = 0;
			}
			$pool[]  = array( 'id' => $id, 'allocation' => $alloc );
			$listed += $alloc;
		}

		if ( empty( $pool ) ) {
			return $control;
		}

		$control_alloc = max( 0, 100 - $listed );
		array_unshift(
			$pool,
			array(
				'id'         => $control,
				'allocation' => $control_alloc > 0 ? $control_alloc : 0,
			)
		);

		// Deduplicate control if also listed in variants.
		$seen = array();
		$norm = array();
		foreach ( $pool as $row ) {
			if ( isset( $seen[ $row['id'] ] ) ) {
				continue;
			}
			$seen[ $row['id'] ] = true;
			$norm[]             = $row;
		}

		$total = 0.0;
		foreach ( $norm as $row ) {
			$total += (float) $row['allocation'];
		}
		if ( $total <= 0 ) {
			return $control;
		}

		$bucket = self::bucket( $experiment->id(), $visitor ); // 0–9999
		$cursor = 0.0;
		foreach ( $norm as $row ) {
			$share  = ( (float) $row['allocation'] / $total ) * 10000;
			$cursor += $share;
			if ( $bucket < $cursor ) {
				return (string) $row['id'];
			}
		}

		return (string) $norm[ count( $norm ) - 1 ]['id'];
	}

	/**
	 * Stable bucket 0–9999.
	 *
	 * @param string $experiment_id Experiment ID.
	 * @param string $visitor_id Visitor ID.
	 * @return int
	 */
	public static function bucket( $experiment_id, $visitor_id ) {
		$hash = md5( (string) $experiment_id . "\0" . (string) $visitor_id );
		// Use first 8 hex chars → 32-bit int space, mod 10000.
		$n = hexdec( substr( $hash, 0, 8 ) );
		return (int) ( $n % 10000 );
	}
}
