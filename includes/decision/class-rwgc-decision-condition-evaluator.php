<?php
/**
 * Evaluate platform condition trees against a Context contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local-only condition evaluation (no Cloud, no page-render side effects).
 */
final class RWGC_Decision_Condition_Evaluator {

	/**
	 * @param RWGC_Contract_Condition_Group $group Group.
	 * @param RWGC_Contract_Context         $context Context.
	 * @param array<string, mixed>          $trace Trace accumulator (by ref).
	 * @return bool
	 */
	public static function matches_group( RWGC_Contract_Condition_Group $group, RWGC_Contract_Context $context, array &$trace = array() ) {
		try {
			$results = array();
			foreach ( $group->items() as $item ) {
				if ( $item instanceof RWGC_Contract_Condition_Group ) {
					$results[] = self::matches_group( $item, $context, $trace );
				} elseif ( $item instanceof RWGC_Contract_Condition ) {
					$results[] = self::matches_condition( $item, $context, $trace );
				} else {
					$results[] = false;
					$trace[]   = 'unknown_item_type';
				}
			}

			if ( empty( $results ) ) {
				// Empty group: treat as match-all (safe default for incomplete trees).
				return true;
			}

			if ( RWGC_Contract_Condition_Group::MATCH_ANY === $group->match() ) {
				foreach ( $results as $r ) {
					if ( $r ) {
						return true;
					}
				}
				return false;
			}

			foreach ( $results as $r ) {
				if ( ! $r ) {
					return false;
				}
			}
			return true;
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			$trace[] = 'group_exception:' . $e->getMessage();
			return false;
		}
	}

	/**
	 * @param RWGC_Contract_Condition $condition Condition.
	 * @param RWGC_Contract_Context   $context Context.
	 * @param array<string, mixed>    $trace Trace.
	 * @return bool
	 */
	public static function matches_condition( RWGC_Contract_Condition $condition, RWGC_Contract_Context $context, array &$trace = array() ) {
		try {
			$capability = $condition->capability();
			if ( '' === $capability || ! RWGC_Schema::is_valid_capability_id( $capability ) ) {
				$trace[] = 'invalid_capability';
				return false;
			}

			// When the registry has been seeded, unknown IDs fail closed.
			// Empty registry (early boot / isolated tests) skips this gate.
			if (
				class_exists( 'RWGC_Platform_Capability_Registry', false )
				&& ! empty( RWGC_Platform_Capability_Registry::all() )
				&& ! RWGC_Platform_Capability_Registry::has( $capability )
			) {
				$trace[] = 'missing_provider:' . $capability;
				return false;
			}

			$actual   = $context->get( $capability, null );
			$operator = $condition->operator();
			$expected = $condition->value();

			$ok = self::compare( $operator, $actual, $expected );
			$trace[] = array(
				'capability' => $capability,
				'operator'   => $operator,
				'expected'   => $expected,
				'actual'     => $actual,
				'result'     => $ok,
			);
			return $ok;
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			$trace[] = 'condition_exception:' . $e->getMessage();
			return false;
		}
	}

	/**
	 * Count leaf conditions (specificity).
	 *
	 * @param RWGC_Contract_Condition_Group $group Group.
	 * @return int
	 */
	public static function specificity( RWGC_Contract_Condition_Group $group ) {
		$n = 0;
		foreach ( $group->items() as $item ) {
			if ( $item instanceof RWGC_Contract_Condition_Group ) {
				$n += self::specificity( $item );
			} else {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * @param string $operator Operator.
	 * @param mixed  $actual Actual.
	 * @param mixed  $expected Expected.
	 * @return bool
	 */
	private static function compare( $operator, $actual, $expected ) {
		switch ( $operator ) {
			case 'exists':
				return null !== $actual && '' !== $actual;
			case 'not_exists':
				return null === $actual || '' === $actual;
			case 'equals':
			case 'eq':
			case 'is':
				return self::stringify( $actual ) === self::stringify( $expected );
			case 'not_equals':
			case 'neq':
			case 'is_not':
				return self::stringify( $actual ) !== self::stringify( $expected );
			case 'in':
				$list = self::as_list( $expected );
				if ( empty( $list ) ) {
					return true;
				}
				return in_array( self::stringify( $actual ), $list, true );
			case 'not_in':
				$list = self::as_list( $expected );
				if ( empty( $list ) ) {
					return true;
				}
				return ! in_array( self::stringify( $actual ), $list, true );
			case 'contains':
				return false !== strpos( self::stringify( $actual ), self::stringify( $expected ) );
			case 'gt':
				return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual > (float) $expected;
			case 'gte':
				return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual >= (float) $expected;
			case 'lt':
				return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual < (float) $expected;
			case 'lte':
				return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual <= (float) $expected;
			default:
				// Unknown operator fails safely.
				return false;
		}
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function stringify( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_array( $value ) ) {
			return '';
		}
		return strtolower( trim( (string) $value ) );
	}

	/**
	 * @param mixed $expected Expected.
	 * @return list<string>
	 */
	private static function as_list( $expected ) {
		if ( null === $expected || '' === $expected ) {
			return array();
		}
		if ( ! is_array( $expected ) ) {
			return array( self::stringify( $expected ) );
		}
		$out = array();
		foreach ( $expected as $v ) {
			$out[] = self::stringify( $v );
		}
		return $out;
	}
}
