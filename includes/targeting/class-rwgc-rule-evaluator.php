<?php
/**
 * Central portable targeting evaluator (single source of truth for rule matching).
 *
 * Elementor, Gutenberg, shortcodes, and admin previews should call this class —
 * not duplicate matching logic.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates sanitized rule sets produced by {@see RWGC_Targeting_Rule_Set_Schema::sanitize()}.
 */
class RWGC_Rule_Evaluator {

	/**
	 * Registered condition resolver map (type => callable( op, val, snapshot ): bool ).
	 *
	 * @var array<string, callable>|null
	 */
	private static $resolver_cache = null;

	/**
	 * Reset resolver cache (tests).
	 *
	 * @return void
	 */
	public static function reset_resolver_cache() {
		self::$resolver_cache = null;
	}

	/**
	 * Default built-in resolvers merged before the `rwgc_rule_condition_resolvers` filter.
	 *
	 * @return array<string, callable>
	 */
	private static function default_resolvers() {
		return array(
			'country'        => array( __CLASS__, 'eval_country' ),
			'country_group'  => array( __CLASS__, 'eval_country_group' ),
			'language'       => array( __CLASS__, 'eval_language' ),
			'locale'         => array( __CLASS__, 'eval_locale' ),
			'device'         => array( __CLASS__, 'eval_device_type' ),
			'device_type'    => array( __CLASS__, 'eval_device_type' ),
			'time_of_day'    => array( __CLASS__, 'eval_time_of_day' ),
			'day_of_week'    => array( __CLASS__, 'eval_day_of_week' ),
			'logged_in'        => array( __CLASS__, 'eval_logged_in' ),
			'page_version_url' => array( __CLASS__, 'eval_page_version_url' ),
		);
	}

	/**
	 * Effective resolver map after filters.
	 *
	 * @return array<string, callable>
	 */
	public static function get_condition_resolvers() {
		if ( is_array( self::$resolver_cache ) ) {
			return self::$resolver_cache;
		}
		$base = self::default_resolvers();
		/**
		 * Register callable resolvers for portable targeting condition types.
		 *
		 * Each resolver: `function( string $operator, mixed $value, RWGC_Context_Snapshot $snapshot ): bool`.
		 *
		 * @param array<string, callable> $base Default Geo Core resolvers.
		 */
		$merged = apply_filters( 'rwgc_rule_condition_resolvers', $base );
		self::$resolver_cache = is_array( $merged ) ? $merged : $base;
		return self::$resolver_cache;
	}

	/**
	 * Top-level ruleset match (ignores show/hide mode).
	 *
	 * @param array<string, mixed>   $set      Sanitized rule set.
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
			$results[] = self::matches_rule( $rule, $snapshot );
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
	 * Whether content should render for show/hide mode.
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
	 * Evaluate a single rule (conditions + per-rule match mode).
	 *
	 * @param array<string, mixed>  $rule     Rule document.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function matches_rule( array $rule, RWGC_Context_Snapshot $snapshot ) {
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
			$passes[] = self::matches_condition( $c, $snapshot );
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
	 * Evaluate one condition row.
	 *
	 * @param array<string, mixed>  $condition Condition.
	 * @param RWGC_Context_Snapshot $snapshot  Snapshot.
	 * @return bool
	 */
	public static function matches_condition( array $condition, RWGC_Context_Snapshot $snapshot ) {
		$type = RWGC_Targeting_Rule_Set_Schema::sanitize_condition_type_string( isset( $condition['type'] ) ? $condition['type'] : '' );
		if ( '' === $type ) {
			return true;
		}

		$op  = isset( $condition['operator'] ) ? sanitize_key( (string) $condition['operator'] ) : 'in';
		$val = isset( $condition['value'] ) ? $condition['value'] : null;

		/**
		 * Legacy extension hook (GeoCore Pro and satellites). Return a boolean to short-circuit.
		 *
		 * @param bool|null               $result   Prior result (null).
		 * @param string                  $type     Condition type.
		 * @param string                  $operator Operator.
		 * @param mixed                   $value    Condition value.
		 * @param RWGC_Context_Snapshot   $snapshot Snapshot.
		 */
		$hook = apply_filters( 'rwgc_targeting_evaluate_condition', null, $type, $op, $val, $snapshot );
		if ( is_bool( $hook ) ) {
			return $hook;
		}

		$resolvers = self::get_condition_resolvers();
		if ( isset( $resolvers[ $type ] ) && is_callable( $resolvers[ $type ] ) ) {
			try {
				return (bool) call_user_func( $resolvers[ $type ], $op, $val, $snapshot );
			} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
				if ( class_exists( 'RWGC_Settings', false ) && RWGC_Settings::get( 'debug_mode', 0 ) && function_exists( 'error_log' ) ) {
					error_log( 'RWGC_Rule_Evaluator resolver error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return false;
			}
		}

		return false;
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected boolean as 1/0, true/false, or yes/no list.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_logged_in( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		unset( $snapshot );
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			return true;
		}
		$actual = is_user_logged_in();
		if ( is_array( $val ) && array() === $val ) {
			return true;
		}
		$expect = true;
		if ( is_array( $val ) && array() !== $val ) {
			$expect = self::coerce_to_bool( $val[0] );
		} elseif ( null !== $val && '' !== $val ) {
			$expect = self::coerce_to_bool( $val );
		}

		switch ( (string) $op ) {
			case 'is_not':
				return $actual !== $expect;
			case 'is':
			default:
				return $actual === $expect;
		}
	}

	/**
	 * @param mixed $raw Raw.
	 * @return bool
	 */
	private static function coerce_to_bool( $raw ) {
		if ( is_bool( $raw ) ) {
			return $raw;
		}
		$s = strtolower( trim( (string) $raw ) );
		if ( in_array( $s, array( '1', 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $s, array( '0', 'false', 'no', 'off', '' ), true ) ) {
			return false;
		}
		return (bool) $raw;
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected ISO2 list.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_country( $op, $val, RWGC_Context_Snapshot $snapshot ) {
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
	 * Match visitor country against named country groups (OR across selected groups for `in`).
	 *
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Group slug list.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_country_group( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		$slugs = array();
		if ( is_array( $val ) ) {
			foreach ( $val as $s ) {
				$s = sanitize_key( (string) $s );
				if ( '' !== $s ) {
					$slugs[] = $s;
				}
			}
		} elseif ( is_string( $val ) && '' !== trim( $val ) ) {
			$slugs[] = sanitize_key( trim( $val ) );
		}

		if ( empty( $slugs ) ) {
			return true;
		}

		if ( ! class_exists( 'RWGC_Country_Groups', false ) ) {
			return false;
		}

		$countries = RWGC_Country_Groups::expand_groups_to_countries( $slugs );
		$countries = RWGC_Country_Groups::normalize_iso2_list( $countries );

		$cc = strtoupper( substr( (string) $snapshot->get( 'country', '' ), 0, 2 ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $cc ) ) {
			return false;
		}

		$in_group = in_array( $cc, $countries, true );

		switch ( (string) $op ) {
			case 'not_in':
				return ! $in_group;
			case 'in':
			case 'is':
				return $in_group;
			case 'is_not':
				return ! $in_group;
			default:
				return RWGC_Target_Operators::evaluate( $cc, $op, $countries );
		}
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected language codes.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_language( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		$actual = strtolower( trim( (string) $snapshot->get( 'language', '' ) ) );
		$list   = self::normalize_lower_string_list( $val );
		if ( empty( $list ) ) {
			return true;
		}
		if ( '' === $actual ) {
			return false;
		}
		return RWGC_Target_Operators::evaluate( $actual, $op, $list );
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected locale string(s).
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_locale( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		$actual = strtolower( trim( (string) $snapshot->get( 'locale', '' ) ) );
		$list   = self::normalize_lower_string_list( $val );
		if ( empty( $list ) ) {
			return true;
		}
		if ( '' === $actual ) {
			return false;
		}
		return RWGC_Target_Operators::evaluate( $actual, $op, $list );
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected device types.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_device_type( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		$actual = strtolower( trim( (string) $snapshot->get( 'device_type', '' ) ) );
		$list   = self::normalize_lower_string_list( $val );
		if ( empty( $list ) ) {
			return true;
		}
		if ( '' === $actual ) {
			return false;
		}
		return RWGC_Target_Operators::evaluate( $actual, $op, $list );
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected buckets.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_time_of_day( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		$actual = strtolower( trim( (string) $snapshot->get( 'time_of_day', '' ) ) );
		$list   = self::normalize_lower_string_list( $val );
		if ( empty( $list ) ) {
			return true;
		}
		if ( '' === $actual ) {
			return false;
		}
		return RWGC_Target_Operators::evaluate( $actual, $op, $list );
	}

	/**
	 * @param string                $op       Operator.
	 * @param mixed                 $val      Expected weekdays.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_day_of_week( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		$actual = strtolower( trim( (string) $snapshot->get( 'day_of_week', '' ) ) );
		$list   = self::normalize_lower_string_list( $val );
		if ( empty( $list ) ) {
			return true;
		}
		if ( '' === $actual ) {
			return false;
		}
		return RWGC_Target_Operators::evaluate( $actual, $op, $list );
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

	/**
	 * @param mixed $val Scalar or list.
	 * @return string[]
	 */
	private static function normalize_lower_string_list( $val ) {
		if ( is_array( $val ) ) {
			$out = array();
			foreach ( $val as $v ) {
				$out[] = strtolower( trim( (string) $v ) );
			}
			return array_values( array_filter( $out ) );
		}
		if ( is_string( $val ) && '' !== trim( $val ) ) {
			return array( strtolower( trim( $val ) ) );
		}
		return array();
	}

	/**
	 * Page Version URL: match active `/_gc/{version}` on the bound base page.
	 *
	 * @param string                $op       Operator (`equals`, `is`, `is_not`).
	 * @param mixed                 $val      `{ page_id, version }`.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function eval_page_version_url( $op, $val, RWGC_Context_Snapshot $snapshot ) {
		if ( ! class_exists( 'RWGC_Page_Version', false ) ) {
			return false;
		}

		$expected = RWGC_Page_Version::sanitize_condition_value( $val );
		if ( null === $expected ) {
			return false;
		}

		$active  = (bool) $snapshot->get( 'page_version_active', false );
		$version = (string) $snapshot->get( 'page_version', '' );
		$page_id = (int) $snapshot->get( 'page_version_page_id', 0 );

		$match = $active
			&& $page_id > 0
			&& $page_id === (int) $expected['page_id']
			&& $version === $expected['version'];

		switch ( (string) $op ) {
			case 'is_not':
				return ! $match;
			case 'equals':
			case 'is':
			default:
				return $match;
		}
	}
}
