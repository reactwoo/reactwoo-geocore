<?php
/**
 * Evaluates portable targeting rule sets against {@see RWGC_Context_Snapshot}.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single entry point for show/hide and block visibility.
 */
class RWGC_Targeting_Rule_Set_Evaluator {

	/**
	 * True when the visitor satisfies the rule set (independent of show/hide mode).
	 *
	 * @param array<string, mixed>   $set      Sanitized rule set from {@see RWGC_Targeting_Rule_Set_Schema::sanitize()}.
	 * @param RWGC_Context_Snapshot  $snapshot Visitor snapshot.
	 * @return bool
	 */
	public static function matches( array $set, RWGC_Context_Snapshot $snapshot ) {
		if ( empty( $set['enabled'] ) ) {
			return true;
		}

		$rules = isset( $set['rules'] ) && is_array( $set['rules'] ) ? $set['rules'] : array();
		if ( empty( $rules ) ) {
			return true;
		}

		$top = isset( $set['match'] ) ? sanitize_key( (string) $set['match'] ) : 'any';
		$top = 'all' === $top ? 'all' : 'any';

		$results = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$results[] = self::evaluate_rule( $rule, $snapshot );
		}

		if ( empty( $results ) ) {
			return true;
		}

		if ( 'all' === $top ) {
			foreach ( $results as $r ) {
				if ( ! $r ) {
					return false;
				}
			}
			return true;
		}

		foreach ( $results as $r ) {
			if ( $r ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether content should be rendered for the given mode.
	 *
	 * @param array<string, mixed>  $set      Sanitized rule set.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function should_render_content( array $set, RWGC_Context_Snapshot $snapshot ) {
		$m = isset( $set['mode'] ) ? sanitize_key( (string) $set['mode'] ) : 'show';
		$m = 'hide' === $m ? 'hide' : 'show';

		$matched = self::matches( $set, $snapshot );

		if ( 'hide' === $m ) {
			return ! $matched;
		}
		return $matched;
	}

	/**
	 * @param array<string, mixed>  $rule     Single rule.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	private static function evaluate_rule( array $rule, RWGC_Context_Snapshot $snapshot ) {
		$conds = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		if ( empty( $conds ) ) {
			return true;
		}

		$mode = isset( $rule['match'] ) ? sanitize_key( (string) $rule['match'] ) : 'all';
		$mode = 'any' === $mode ? 'any' : 'all';

		$passes = array();
		foreach ( $conds as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$passes[] = self::evaluate_condition( $c, $snapshot );
		}

		if ( empty( $passes ) ) {
			return true;
		}

		if ( 'any' === $mode ) {
			foreach ( $passes as $p ) {
				if ( $p ) {
					return true;
				}
			}
			return false;
		}

		foreach ( $passes as $p ) {
			if ( ! $p ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string, mixed>  $condition Condition.
	 * @param RWGC_Context_Snapshot $snapshot  Snapshot.
	 * @return bool
	 */
	private static function evaluate_condition( array $condition, RWGC_Context_Snapshot $snapshot ) {
		$type = RWGC_Targeting_Rule_Set_Schema::sanitize_condition_type_string( isset( $condition['type'] ) ? $condition['type'] : '' );
		if ( '' === $type ) {
			return true;
		}

		$op   = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'in';
		$val  = isset( $condition['value'] ) ? $condition['value'] : null;
		$hook = apply_filters( 'rwgc_targeting_evaluate_condition', null, $type, $op, $val, $snapshot );
		if ( is_bool( $hook ) ) {
			return $hook;
		}

		if ( 'country' === $type ) {
			return self::evaluate_country( $op, $val, $snapshot );
		}

		return false;
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected value(s).
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	private static function evaluate_country( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		$list = self::normalize_string_list( $val );
		if ( empty( $list ) ) {
			return true;
		}

		$cc = strtoupper( substr( (string) $snapshot->get( 'country', '' ), 0, 2 ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $cc ) ) {
			return false;
		}

		return RWGC_Target_Operators::evaluate( $cc, $op, $list );
	}

	/**
	 * @param mixed $val Scalar or list.
	 * @return string[]
	 */
	private static function normalize_string_list( $val ) {
		if ( is_array( $val ) ) {
			$out = array();
			foreach ( $val as $v ) {
				$out[] = strtoupper( trim( (string) $v ) );
			}
			return array_values( array_filter( $out ) );
		}
		if ( is_string( $val ) && '' !== trim( $val ) ) {
			return array( strtoupper( trim( $val ) ) );
		}
		return array();
	}
}
