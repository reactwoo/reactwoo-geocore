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
 * Builds snapshots and evaluates portable JSON for the rule tester modal.
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

		$detailed = self::evaluate_detailed( $decoded, $scenario );
		return array(
			'matches' => ! empty( $detailed['matches'] ),
			'error'   => (string) ( $detailed['error'] ?? '' ),
		);
	}

	/**
	 * @param array<string,mixed>  $set      Decoded portable rule set.
	 * @param array<string,string> $scenario Test inputs.
	 * @return array<string,mixed>
	 */
	public static function evaluate_detailed( array $set, array $scenario ) {
		$snapshot = self::snapshot_from_scenario( $scenario );
		$conds    = isset( $set['rules'][0]['conditions'] ) && is_array( $set['rules'][0]['conditions'] )
			? $set['rules'][0]['conditions']
			: array();

		$results = array();
		foreach ( $conds as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$results[] = self::condition_result_row( $cond, $snapshot );
		}

		$matched = class_exists( 'RWGC_Rule_Evaluator', false )
			? RWGC_Rule_Evaluator::should_render_content( $set, $snapshot )
			: false;

		$failures = array_values(
			array_filter(
				$results,
				static function ( $row ) {
					return is_array( $row ) && 'fail' === ( $row['status'] ?? '' );
				}
			)
		);
		$passes   = array_values(
			array_filter(
				$results,
				static function ( $row ) {
					return is_array( $row ) && 'pass' === ( $row['status'] ?? '' );
				}
			)
		);

		return array(
			'status'            => $matched ? 'match' : 'no_match',
			'matches'           => (bool) $matched,
			'error'             => '',
			'condition_results' => $results,
			'summary_intro'     => $matched
				? __( 'This visitor/context matches because:', 'reactwoo-geocore' )
				: __( 'This visitor/context does not match because:', 'reactwoo-geocore' ),
			'summary_lines'     => $matched
				? array_map(
					static function ( $row ) {
						return (string) ( $row['detail'] ?? '' );
					},
					$passes
				)
				: array_map(
					static function ( $row ) {
						return (string) ( $row['detail'] ?? '' );
					},
					$failures
				),
		);
	}

	/**
	 * @param array<string,mixed>   $cond     Condition row.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return array<string,string>
	 */
	private static function condition_result_row( array $cond, RWGC_Context_Snapshot $snapshot ) {
		$label  = self::condition_label( $cond );
		$passed = class_exists( 'RWGC_Rule_Evaluator', false )
			? RWGC_Rule_Evaluator::matches_condition( $cond, $snapshot )
			: false;

		return array(
			'label'  => $label,
			'status' => $passed ? 'pass' : 'fail',
			'detail' => self::condition_detail( $cond, $passed, $snapshot, $label ),
		);
	}

	/**
	 * @param array<string,mixed> $cond Condition.
	 * @return string
	 */
	private static function condition_label( array $cond ) {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false ) ) {
			return (string) ( $cond['type'] ?? '' );
		}
		$compact = RWGC_Visibility_Rule_Logic_Preview::build_compact( array( 'rules' => array( array( 'conditions' => array( $cond ) ) ) ) );
		if ( empty( $compact[0]['text'] ) ) {
			return (string) ( $cond['type'] ?? '' );
		}
		return (string) $compact[0]['text'];
	}

	/**
	 * @param array<string,mixed>   $cond     Condition.
	 * @param bool                  $passed   Whether it passed.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @param string                $label    Human label.
	 * @return string
	 */
	private static function condition_detail( array $cond, $passed, RWGC_Context_Snapshot $snapshot, $label ) {
		unset( $label );
		$type = (string) ( $cond['type'] ?? '' );
		$op   = (string) ( $cond['operator'] ?? '' );

		if ( 'country' === $type ) {
			$cc = strtoupper( substr( (string) $snapshot->get( 'country', '' ), 0, 2 ) );
			if ( in_array( $op, array( 'not_in', 'is_not' ), true ) ) {
				return $passed
					? sprintf( __( 'Country %s is not excluded.', 'reactwoo-geocore' ), $cc )
					: sprintf( __( 'Country %s is excluded.', 'reactwoo-geocore' ), $cc );
			}
			return $passed
				? sprintf( __( 'Country %s is allowed.', 'reactwoo-geocore' ), $cc )
				: sprintf( __( 'Country %s is not in the allowed list.', 'reactwoo-geocore' ), $cc );
		}

		if ( in_array( $type, array( 'device', 'device_type' ), true ) ) {
			$device = ucfirst( (string) $snapshot->get( 'device_type', '' ) );
			return $passed
				? sprintf( __( 'Device %s is allowed.', 'reactwoo-geocore' ), $device )
				: sprintf( __( 'Device %s is not allowed.', 'reactwoo-geocore' ), $device );
		}

		if ( 'page_type' === $type ) {
			$page_slug = (string) $snapshot->get( 'page_type', '' );
			$page_type = ucwords( str_replace( '_', ' ', $page_slug ) );
			return $passed
				? sprintf( __( 'Page type %s is allowed.', 'reactwoo-geocore' ), $page_type )
				: sprintf( __( 'Page type %s does not match the rule.', 'reactwoo-geocore' ), $page_type );
		}

		if ( 'condition_group' === $type ) {
			if ( $passed ) {
				$branch = self::matching_traffic_branch( $cond, $snapshot );
				if ( '' !== $branch ) {
					return sprintf( __( '%s matched.', 'reactwoo-geocore' ), $branch );
				}
				return __( 'A traffic branch matched.', 'reactwoo-geocore' );
			}
			return __( 'Neither traffic branch matched.', 'reactwoo-geocore' );
		}

		return $passed
			? __( 'Condition matched.', 'reactwoo-geocore' )
			: __( 'Condition did not match.', 'reactwoo-geocore' );
	}

	/**
	 * @param array<string,mixed>   $cond     Group condition.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return string
	 */
	private static function matching_traffic_branch( array $cond, RWGC_Context_Snapshot $snapshot ) {
		$val = $cond['value'] ?? array();
		if ( ! is_array( $val ) || empty( $val['branches'] ) ) {
			return '';
		}
		foreach ( (array) $val['branches'] as $branch ) {
			if ( ! is_array( $branch ) ) {
				continue;
			}
			$rule = array(
				'match'      => isset( $branch['match'] ) ? $branch['match'] : 'all',
				'conditions' => isset( $branch['conditions'] ) && is_array( $branch['conditions'] ) ? $branch['conditions'] : array(),
			);
			if ( class_exists( 'RWGC_Rule_Evaluator', false ) && RWGC_Rule_Evaluator::matches_rule( $rule, $snapshot ) ) {
				$label = trim( (string) ( $branch['label'] ?? '' ) );
				if ( '' !== $label ) {
					return $label;
				}
				$conds = (array) ( $branch['conditions'] ?? array() );
				if ( class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false ) && RWGC_Visibility_Rule_Logic_Preview::is_google_ads_branch( $conds ) ) {
					return __( 'Google Ads standard UTM', 'reactwoo-geocore' );
				}
			}
		}
		return '';
	}

	/**
	 * @param array<string,string> $scenario Scenario fields from the test form.
	 * @return RWGC_Context_Snapshot
	 */
	public static function snapshot_from_scenario( array $scenario ) {
		$country     = strtoupper( substr( sanitize_text_field( (string) ( $scenario['country'] ?? '' ) ), 0, 2 ) );
		$device      = strtolower( sanitize_key( (string) ( $scenario['device'] ?? '' ) ) );
		$page_type   = strtolower( sanitize_key( (string) ( $scenario['page_type'] ?? '' ) ) );
		$request_uri = sanitize_text_field( (string) ( $scenario['request_uri'] ?? '' ) );
		$source      = strtolower( sanitize_text_field( (string) ( $scenario['utm_source'] ?? '' ) ) );
		$medium      = strtolower( sanitize_text_field( (string) ( $scenario['utm_medium'] ?? '' ) ) );

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
