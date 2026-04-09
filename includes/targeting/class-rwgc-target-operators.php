<?php
/**
 * Operators for Geo Core target conditions (shared with satellites).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Operator labels and evaluation helpers.
 */
class RWGC_Target_Operators {

	/**
	 * Built-in operator slugs.
	 *
	 * @return string[]
	 */
	public static function all() {
		return array(
			'is',
			'is_not',
			'in',
			'not_in',
			'contains',
			'not_contains',
			'greater_than',
			'less_than',
			'between',
		);
	}

	/**
	 * Default operators by value mode.
	 *
	 * @param string $value_mode single|multi|boolean|range|text.
	 * @return string[]
	 */
	public static function for_value_mode( $value_mode ) {
		$mode = (string) $value_mode;
		switch ( $mode ) {
			case 'boolean':
				return array( 'is', 'is_not' );
			case 'multi':
				return array( 'in', 'not_in', 'contains', 'not_contains' );
			case 'range':
				return array( 'between', 'greater_than', 'less_than', 'is' );
			case 'text':
				return array( 'is', 'is_not', 'contains', 'not_contains' );
			case 'single':
			default:
				return array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains', 'greater_than', 'less_than', 'between' );
		}
	}

	/**
	 * Whether an operator string is known.
	 *
	 * @param string $operator Operator.
	 * @return bool
	 */
	public static function is_valid( $operator ) {
		return in_array( (string) $operator, self::all(), true );
	}

	/**
	 * Evaluate a single condition against a resolved actual value.
	 *
	 * @param mixed  $actual Resolved context value (scalar or array).
	 * @param string $operator Operator slug.
	 * @param mixed  $expected Stored rule value (scalar or array for range / multi).
	 * @return bool
	 */
	public static function evaluate( $actual, $operator, $expected ) {
		$op = (string) $operator;
		switch ( $op ) {
			case 'is':
				return self::normalize_scalar( $actual ) === self::normalize_scalar( $expected );
			case 'is_not':
				return self::normalize_scalar( $actual ) !== self::normalize_scalar( $expected );
			case 'in':
				return self::actual_in_expected_list( $actual, $expected, true );
			case 'not_in':
				return ! self::actual_in_expected_list( $actual, $expected, true );
			case 'contains':
				return self::contains( $actual, $expected );
			case 'not_contains':
				return ! self::contains( $actual, $expected );
			case 'greater_than':
				return self::to_float( $actual ) > self::to_float( $expected );
			case 'less_than':
				return self::to_float( $actual ) < self::to_float( $expected );
			case 'between':
				return self::between( $actual, $expected );
			default:
				return false;
		}
	}

	/**
	 * @param mixed $v Value.
	 * @return string|float|int|bool|null
	 */
	private static function normalize_scalar( $v ) {
		if ( is_bool( $v ) || is_int( $v ) || is_float( $v ) ) {
			return $v;
		}
		if ( is_array( $v ) ) {
			return '';
		}
		return is_string( $v ) ? strtolower( trim( $v ) ) : $v;
	}

	/**
	 * @param mixed $actual Actual.
	 * @param mixed $needle Needle / list.
	 * @param bool  $strict_list Whether expected must be list for `in`.
	 * @return bool
	 */
	private static function actual_in_expected_list( $actual, $needle, $strict_list ) {
		$list = self::listify( $needle );
		if ( empty( $list ) ) {
			return false;
		}
		if ( is_array( $actual ) ) {
			$flat = array_map( 'strval', $actual );
			foreach ( $list as $want ) {
				if ( in_array( (string) $want, $flat, true ) ) {
					return true;
				}
			}
			return false;
		}
		$a = strtolower( trim( (string) $actual ) );
		foreach ( $list as $want ) {
			if ( $a === strtolower( trim( (string) $want ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $actual Actual.
	 * @param mixed $needle Needle.
	 * @return bool
	 */
	private static function contains( $actual, $needle ) {
		if ( is_array( $actual ) ) {
			$hay = array_map( 'strval', $actual );
			$n   = strtolower( trim( (string) $needle ) );
			foreach ( $hay as $h ) {
				if ( false !== strpos( strtolower( (string) $h ), $n ) ) {
					return true;
				}
			}
			return false;
		}
		$a = (string) $actual;
		$n = (string) $needle;
		return '' !== $n && false !== stripos( $a, $n );
	}

	/**
	 * @param mixed $actual Numeric or string.
	 * @param mixed $expected array(min,max) or array with keys min/max.
	 * @return bool
	 */
	private static function between( $actual, $expected ) {
		$v = self::to_float( $actual );
		$min = null;
		$max = null;
		if ( is_array( $expected ) ) {
			if ( isset( $expected['min'], $expected['max'] ) ) {
				$min = self::to_float( $expected['min'] );
				$max = self::to_float( $expected['max'] );
			} elseif ( isset( $expected[0], $expected[1] ) ) {
				$min = self::to_float( $expected[0] );
				$max = self::to_float( $expected[1] );
			}
		}
		if ( null === $min || null === $max ) {
			return false;
		}
		return $v >= $min && $v <= $max;
	}

	/**
	 * @param mixed $v Value.
	 * @return float
	 */
	private static function to_float( $v ) {
		if ( is_numeric( $v ) ) {
			return (float) $v;
		}
		return floatval( preg_replace( '/[^0-9.\-]/', '', (string) $v ) );
	}

	/**
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	private static function listify( $v ) {
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $item ) {
				$out[] = (string) $item;
			}
			return $out;
		}
		if ( is_string( $v ) && strpos( $v, ',' ) !== false ) {
			return array_map( 'trim', explode( ',', $v ) );
		}
		return array( (string) $v );
	}
}
