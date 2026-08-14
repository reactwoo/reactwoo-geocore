<?php
/**
 * ReactWoo Decision Runtime v1 — local, deterministic, no Cloud calls.
 *
 * Additive: not wired into Elementor/Gutenberg render paths yet (WP5–9).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates a Manifest (or equivalent arrays) against Context.
 */
final class RWGC_Decision_Runtime {

	/**
	 * Per-request audience match cache (manifest object + context object + audience id).
	 *
	 * @var array<string, bool>
	 */
	private static $audience_match_cache = array();

	/**
	 * @return void
	 */
	public static function reset_request_cache() {
		self::$audience_match_cache = array();
	}

	/**
	 * Evaluate decisions for a visitor context.
	 *
	 * @param RWGC_Contract_Manifest $manifest Manifest.
	 * @param RWGC_Contract_Context  $context Context.
	 * @param array<string, mixed>   $options Options: visitor_id, now (unix), debug (bool), slot_id (limit).
	 * @return RWGC_Decision_Result
	 */
	public static function evaluate( RWGC_Contract_Manifest $manifest, RWGC_Contract_Context $context, array $options = array() ) {
		$started = microtime( true );
		$debug_on = ! empty( $options['debug'] ) || self::is_debug_request();
		$visitor  = isset( $options['visitor_id'] ) ? (string) $options['visitor_id'] : '';
		$now      = isset( $options['now'] ) ? (int) $options['now'] : time();
		$slot_filter = isset( $options['slot_id'] ) ? (string) $options['slot_id'] : '';
		$http_before = class_exists( 'RWGC_Cloud_Http', false ) ? RWGC_Cloud_Http::attempt_count() : 0;

		$reasons = array();
		$debug   = array(
			'remote_calls'         => 0,
			'audiences_total'      => 0,
			'audiences_evaluated'  => 0,
			'steps'                => array(),
		);

		/**
		 * Before decision evaluation (local only).
		 *
		 * @param RWGC_Contract_Manifest $manifest Manifest.
		 * @param RWGC_Contract_Context  $context Context.
		 * @param array<string, mixed>   $options Options.
		 */
		do_action( 'reactwoo_decision_before_evaluate', $manifest, $context, $options );

		$audience_map = array();
		foreach ( $manifest->audiences() as $audience ) {
			$audience_map[ $audience->id() ] = $audience;
		}
		$debug['audiences_total'] = count( $audience_map );

		$experiment_map = array();
		foreach ( $manifest->experiments() as $exp ) {
			$experiment_map[ $exp->id() ] = $exp;
		}

		$by_slot             = array();
		$needed_audience_ids = array();
		foreach ( $manifest->experiences() as $exp ) {
			if ( '' !== $slot_filter && $exp->slot_id() !== $slot_filter ) {
				continue;
			}

			$status = $exp->status();
			if ( ! in_array( $status, array( 'active', 'scheduled' ), true ) ) {
				$reasons[] = 'skipped_status:' . $exp->id() . ':' . $status;
				continue;
			}

			if ( ! self::schedule_allows( $exp->schedule(), $now ) ) {
				$reasons[] = 'skipped_schedule:' . $exp->id();
				continue;
			}

			$aud = $exp->audience_id();
			if ( '' === $aud ) {
				$reasons[] = 'skipped_audience:' . $exp->id();
				continue;
			}

			$needed_audience_ids[ $aud ] = true;
			$slot                        = $exp->slot_id();
			if ( '' === $slot ) {
				$slot = '_default';
			}
			$by_slot[ $slot ][] = $exp;
		}

		$matched_audiences = array();
		foreach ( array_keys( $needed_audience_ids ) as $id ) {
			if ( ! isset( $audience_map[ $id ] ) ) {
				continue;
			}
			$cache_key = spl_object_id( $audience_map[ $id ] ) . ':' . spl_object_id( $context );
			$cached    = isset( self::$audience_match_cache[ $cache_key ] );
			if ( ! $cached ) {
				++$debug['audiences_evaluated'];
			}
			$trace = array();
			$ok    = self::audience_matches( $audience_map[ $id ], $context, $trace, $debug_on );
			if ( $debug_on ) {
				$debug['steps'][] = array(
					'audience' => $id,
					'matched'  => $ok,
					'trace'    => $trace,
				);
			}
			if ( $ok ) {
				$matched_audiences[] = $id;
			}
		}

		/**
		 * Filter matched audience IDs.
		 *
		 * @param list<string>           $matched_audiences IDs.
		 * @param RWGC_Contract_Manifest $manifest Manifest.
		 * @param RWGC_Contract_Context  $context Context.
		 */
		$matched_audiences = apply_filters( 'reactwoo_decision_matched_audiences', $matched_audiences, $manifest, $context );
		if ( ! is_array( $matched_audiences ) ) {
			$matched_audiences = array();
		}

		$matched_lookup = array_fill_keys( $matched_audiences, true );

		$ranked = array();
		foreach ( $by_slot as $slot => $experiences ) {
			foreach ( $experiences as $exp ) {
				$aud = $exp->audience_id();
				if ( ! isset( $matched_lookup[ $aud ] ) ) {
					$reasons[] = 'skipped_audience:' . $exp->id();
					continue;
				}
				$specificity = 0;
				if ( isset( $audience_map[ $aud ] ) ) {
					$specificity = RWGC_Decision_Condition_Evaluator::specificity( $audience_map[ $aud ]->conditions() );
				}
				$ranked[ $slot ][] = array(
					'experience'  => $exp,
					'specificity' => $specificity,
				);
			}
		}

		$selected_experiences = array();
		$selected_variants    = array();
		$actions              = array();

		foreach ( $ranked as $slot => $candidates ) {
			usort(
				$candidates,
				static function ( $a, $b ) {
					/** @var RWGC_Contract_Experience $ea */
					$ea = $a['experience'];
					/** @var RWGC_Contract_Experience $eb */
					$eb = $b['experience'];
					if ( $ea->priority() !== $eb->priority() ) {
						return $eb->priority() <=> $ea->priority();
					}
					if ( (int) $a['specificity'] !== (int) $b['specificity'] ) {
						return (int) $b['specificity'] <=> (int) $a['specificity'];
					}
					return strcmp( $ea->id(), $eb->id() );
				}
			);

			if ( count( $candidates ) > 1 ) {
				$reasons[] = 'conflict_resolved:' . $slot . ':' . $candidates[0]['experience']->id();
			}

			/** @var RWGC_Contract_Experience $winner */
			$winner     = $candidates[0]['experience'];
			$variant_id = $winner->variant_id();

			$exp_id = $winner->experiment_id();
			if ( '' !== $exp_id && isset( $experiment_map[ $exp_id ] ) ) {
				$variant_id = RWGC_Decision_Experiment_Assigner::assign( $experiment_map[ $exp_id ], $visitor );
				$reasons[]  = 'experiment_assigned:' . $exp_id . ':' . $variant_id;
			}

			if ( '' === $variant_id ) {
				$variant_id = 'default';
			}

			$row = array(
				'id'          => $winner->id(),
				'slot_id'     => $slot,
				'audience_id' => $winner->audience_id(),
				'variant_id'  => $variant_id,
				'priority'    => $winner->priority(),
				'goal_id'     => $winner->goal_id(),
			);
			$selected_experiences[]     = $row;
			$selected_variants[ $slot ] = $variant_id;
			$actions[]                  = array(
				'type'       => 'variant.apply',
				'slot_id'    => $slot,
				'variant_id' => $variant_id,
			);
		}

		$elapsed = ( microtime( true ) - $started ) * 1000;
		$http_after = class_exists( 'RWGC_Cloud_Http', false ) ? RWGC_Cloud_Http::attempt_count() : $http_before;
		$debug['remote_calls'] = max( 0, $http_after - $http_before );
		$debug['elapsed_ms']   = $elapsed;
		if ( method_exists( $context, 'resolve_count' ) ) {
			$debug['context_resolves'] = $context->resolve_count();
		}

		$compact = array(
			'elapsed_ms'          => $elapsed,
			'remote_calls'        => $debug['remote_calls'],
			'audiences_evaluated' => $debug['audiences_evaluated'],
		);

		$result = new RWGC_Decision_Result(
			array_values( $matched_audiences ),
			$selected_experiences,
			$selected_variants,
			$actions,
			$reasons,
			$debug_on ? $debug : $compact,
			$elapsed
		);

		/**
		 * Filter final decision result.
		 *
		 * @param RWGC_Decision_Result   $result Result.
		 * @param RWGC_Contract_Manifest $manifest Manifest.
		 * @param RWGC_Contract_Context  $context Context.
		 */
		$filtered = apply_filters( 'reactwoo_decision_result', $result, $manifest, $context );
		if ( $filtered instanceof RWGC_Decision_Result ) {
			$result = $filtered;
		}

		/**
		 * After decision evaluation.
		 *
		 * @param RWGC_Decision_Result   $result Result.
		 * @param RWGC_Contract_Manifest $manifest Manifest.
		 * @param RWGC_Contract_Context  $context Context.
		 */
		do_action( 'reactwoo_decision_after_evaluate', $result, $manifest, $context );

		return $result;
	}

	/**
	 * @param RWGC_Contract_Audience $audience Audience.
	 * @param RWGC_Contract_Context  $context Context.
	 * @param array<string, mixed>   $trace Trace.
	 * @param bool                   $debug_on Debug.
	 * @return bool
	 */
	private static function audience_matches( RWGC_Contract_Audience $audience, RWGC_Contract_Context $context, array &$trace, $debug_on ) {
		$key = spl_object_id( $audience ) . ':' . spl_object_id( $context );
		if ( ! $debug_on && isset( self::$audience_match_cache[ $key ] ) ) {
			return self::$audience_match_cache[ $key ];
		}
		$ok = RWGC_Decision_Condition_Evaluator::matches_group( $audience->conditions(), $context, $trace );
		self::$audience_match_cache[ $key ] = $ok;
		return $ok;
	}

	/**
	 * @param array<string, mixed> $schedule Schedule.
	 * @param int                  $now Unix now.
	 * @return bool
	 */
	public static function schedule_allows( array $schedule, $now ) {
		if ( empty( $schedule ) ) {
			return true;
		}
		$starts = isset( $schedule['starts'] ) ? (string) $schedule['starts'] : '';
		$ends   = isset( $schedule['ends'] ) ? (string) $schedule['ends'] : '';

		if ( '' !== $starts ) {
			$ts = strtotime( $starts );
			if ( false !== $ts && $now < $ts ) {
				return false;
			}
		}
		if ( '' !== $ends ) {
			$ts = strtotime( $ends );
			if ( false !== $ts && $now > $ts ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Admin-only debug via query arg `rwgc_decision_debug=1`.
	 *
	 * @return bool
	 */
	public static function is_debug_request() {
		if ( empty( $_GET['rwgc_decision_debug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( function_exists( 'current_user_can' ) ) {
			$cap = class_exists( 'RWGC_Admin', false ) ? RWGC_Admin::required_capability() : 'manage_options';
			return current_user_can( $cap );
		}
		return false;
	}
}
