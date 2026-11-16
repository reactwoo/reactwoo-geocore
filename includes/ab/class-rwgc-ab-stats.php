<?php
/**
 * Results and significance for free A/B tests.
 *
 * The two-proportion z-test, the normal CDF, and the error function are the
 * same maths as RWGO_Winner_Policy in ReactWoo Geo Optimise. Optimise can call
 * these methods instead of keeping its own copy.
 *
 * Totals come from {prefix}rwgc_ab_daily. The chart is the last 30 days. The
 * verdict uses every stored day. There is no saved date range.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregate views, conversion rate, uplift, and a two-proportion verdict.
 */
class RWGC_AB_Stats {

	const DEFAULT_ALPHA = 0.05;

	const MIN_PER_ARM = 100;

	const CHART_DAYS = 30;

	/**
	 * Two-proportion z-test (two-sided).
	 *
	 * Successes are conversions. Trials are views. $s1/$n1 are variant A and
	 * $s2/$n2 are variant B, matching RWGO_Winner_Policy::two_proportion_test().
	 *
	 * @param int   $s1    Successes A.
	 * @param int   $n1    Trials A.
	 * @param int   $s2    Successes B.
	 * @param int   $n2    Trials B.
	 * @param float $alpha Alpha.
	 * @return array{z:float,p_value:float,significant:bool,alpha:float}
	 */
	public static function two_proportion_test( $s1, $n1, $s2, $n2, $alpha = 0.05 ) {
		$s1 = max( 0, (int) $s1 );
		$n1 = max( 1, (int) $n1 );
		$s2 = max( 0, (int) $s2 );
		$n2 = max( 1, (int) $n2 );
		$alpha = (float) $alpha;
		if ( $alpha <= 0 || $alpha >= 1 ) {
			$alpha = self::DEFAULT_ALPHA;
		}

		$p1 = $s1 / $n1;
		$p2 = $s2 / $n2;
		$p  = ( $s1 + $s2 ) / ( $n1 + $n2 );
		if ( $p <= 0.0 || $p >= 1.0 ) {
			return array(
				'z'           => 0.0,
				'p_value'     => 1.0,
				'significant' => false,
				'alpha'       => $alpha,
			);
		}
		$se = sqrt( $p * ( 1.0 - $p ) * ( ( 1.0 / $n1 ) + ( 1.0 / $n2 ) ) );
		if ( $se <= 0.0 ) {
			return array(
				'z'           => 0.0,
				'p_value'     => 1.0,
				'significant' => false,
				'alpha'       => $alpha,
			);
		}
		$z       = ( $p2 - $p1 ) / $se;
		$p_value = 2.0 * ( 1.0 - self::standard_normal_cdf( abs( $z ) ) );
		if ( $p_value < 0.0 ) {
			$p_value = 0.0;
		}
		if ( $p_value > 1.0 ) {
			$p_value = 1.0;
		}

		return array(
			'z'           => $z,
			'p_value'     => $p_value,
			'significant' => ( $p_value < $alpha ),
			'alpha'       => $alpha,
		);
	}

	/**
	 * Standard normal CDF via erf approximation.
	 *
	 * @param float $x Value.
	 * @return float
	 */
	public static function standard_normal_cdf( $x ) {
		$x = (float) $x;
		return 0.5 * ( 1.0 + self::erf( $x / sqrt( 2.0 ) ) );
	}

	/**
	 * Error function approximation (Abramowitz & Stegun 7.1.26).
	 *
	 * @param float $x Value.
	 * @return float
	 */
	public static function erf( $x ) {
		$x = (float) $x;
		$sign = $x < 0 ? -1.0 : 1.0;
		$x    = abs( $x );
		$a1 = 0.254829592;
		$a2 = -0.284496736;
		$a3 = 1.421413741;
		$a4 = -1.453152027;
		$a5 = 1.061405429;
		$p  = 0.3275911;
		$t  = 1.0 / ( 1.0 + $p * $x );
		$y  = 1.0 - ( ( ( ( ( $a5 * $t + $a4 ) * $t ) + $a3 ) * $t + $a2 ) * $t + $a1 ) * $t * exp( -$x * $x );
		return $sign * $y;
	}

	/**
	 * Conversion rate. Zero views is a zero rate.
	 *
	 * @param int $conversions Conversions.
	 * @param int $views       Views.
	 * @return float
	 */
	public static function rate( $conversions, $views ) {
		$views = (int) $views;
		if ( $views <= 0 ) {
			return 0.0;
		}
		return max( 0, (int) $conversions ) / $views;
	}

	/**
	 * Relative uplift of B over A. Null when A has no conversions to compare.
	 *
	 * @param float $rate_a Rate A.
	 * @param float $rate_b Rate B.
	 * @return float|null
	 */
	public static function uplift( $rate_a, $rate_b ) {
		$rate_a = (float) $rate_a;
		if ( $rate_a <= 0.0 ) {
			return null;
		}
		return ( (float) $rate_b - $rate_a ) / $rate_a;
	}

	/**
	 * Verdict from all-time views. The 100-view gate is checked before the p-value.
	 *
	 * @param int   $views_a     Views A.
	 * @param int   $views_b     Views B.
	 * @param float $rate_a      Rate A.
	 * @param float $rate_b      Rate B.
	 * @param bool  $significant True when the two-proportion test is under alpha.
	 * @return array{code:string,label:string}
	 */
	public static function verdict( $views_a, $views_b, $rate_a, $rate_b, $significant ) {
		if ( (int) $views_a < self::MIN_PER_ARM || (int) $views_b < self::MIN_PER_ARM ) {
			return array(
				'code'  => 'insufficient',
				'label' => __( 'Not enough data (<100 per arm)', 'reactwoo-geocore' ),
			);
		}
		if ( ! $significant || (float) $rate_b === (float) $rate_a ) {
			return array(
				'code'  => 'inconclusive',
				'label' => __( 'No clear winner yet', 'reactwoo-geocore' ),
			);
		}
		if ( (float) $rate_b > (float) $rate_a ) {
			return array(
				'code'  => 'b_better',
				'label' => __( 'B is better (95%)', 'reactwoo-geocore' ),
			);
		}
		return array(
			'code'  => 'b_worse',
			'label' => __( 'B is worse (95%)', 'reactwoo-geocore' ),
		);
	}

	/**
	 * All-time totals, the last 30 days, uplift, p-value, and the verdict.
	 *
	 * @param array<int, array<string, mixed>> $rows  Daily rows.
	 * @param string                           $today Y-m-d. Empty uses the site's today.
	 * @return array<string, mixed>
	 */
	public static function report( array $rows, $today = '' ) {
		$totals = self::totals( $rows );
		$rate_a = self::rate( $totals['a']['conversions'], $totals['a']['views'] );
		$rate_b = self::rate( $totals['b']['conversions'], $totals['b']['views'] );
		$sig    = null;
		if ( $totals['a']['views'] > 0 && $totals['b']['views'] > 0 ) {
			$sig = self::two_proportion_test(
				$totals['a']['conversions'],
				$totals['a']['views'],
				$totals['b']['conversions'],
				$totals['b']['views'],
				self::DEFAULT_ALPHA
			);
		}
		$significant = is_array( $sig ) && ! empty( $sig['significant'] );
		$verdict     = self::verdict( $totals['a']['views'], $totals['b']['views'], $rate_a, $rate_b, $significant );

		return array(
			'verdict'       => $verdict['label'],
			'verdict_code'  => $verdict['code'],
			'uplift'        => self::uplift( $rate_a, $rate_b ),
			'significance'  => $sig,
			'arms'          => array(
				'a' => array(
					'views'       => $totals['a']['views'],
					'conversions' => $totals['a']['conversions'],
					'rate'        => $rate_a,
				),
				'b' => array(
					'views'       => $totals['b']['views'],
					'conversions' => $totals['b']['conversions'],
					'rate'        => $rate_b,
				),
			),
			'daily'         => self::daily_series( $rows, $today, self::CHART_DAYS ),
		);
	}

	/**
	 * One stored test, or null when the id is not an A/B test.
	 *
	 * @param int $test_id Test post ID.
	 * @return array<string, mixed>|null
	 */
	public static function for_test( $test_id ) {
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! class_exists( 'RWGC_AB_Tests', false ) ) {
			return null;
		}
		$row = RWGC_AB_Tests::get( $test_id );
		if ( null === $row ) {
			return null;
		}
		$rows   = class_exists( 'RWGC_AB_DB', false ) ? RWGC_AB_DB::rows_for_test( $test_id ) : array();
		$report = self::report( $rows );
		$kept   = '';
		if ( RWGC_AB_Tests::STATUS_ENDED === (string) $row['status'] ) {
			$kept = RWGC_AB_Tests::kept_variant( $test_id );
		}
		$report['id']     = (int) $row['id'];
		$report['title']  = (string) $row['title'];
		$report['status'] = (string) $row['status'];
		$report['kept']   = $kept;
		return $report;
	}

	/**
	 * Every test, newest first, the same order as the Experiences list.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function collection() {
		if ( ! class_exists( 'RWGC_AB_Tests', false ) ) {
			return array();
		}
		$out = array();
		foreach ( RWGC_AB_Tests::query() as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$one = self::for_test( $id );
			if ( null !== $one ) {
				$out[] = $one;
			}
		}
		return $out;
	}

	/**
	 * Sum views and conversions for arms A and B. Other variant ids are ignored.
	 *
	 * @param array<int, array<string, mixed>> $rows Daily rows.
	 * @return array{a:array{views:int,conversions:int},b:array{views:int,conversions:int}}
	 */
	public static function totals( array $rows ) {
		$totals = array(
			'a' => array(
				'views'       => 0,
				'conversions' => 0,
			),
			'b' => array(
				'views'       => 0,
				'conversions' => 0,
			),
		);
		foreach ( self::normal_rows( $rows ) as $row ) {
			$totals[ $row['variant'] ]['views']       += $row['views'];
			$totals[ $row['variant'] ]['conversions'] += $row['conversions'];
		}
		return $totals;
	}

	/**
	 * One point per day for the window ending on $today, including days with no rows.
	 *
	 * Days outside the window still count in totals(). The window is not stored.
	 *
	 * @param array<int, array<string, mixed>> $rows  Daily rows.
	 * @param string                           $today Y-m-d. Empty uses the site's today.
	 * @param int                              $days  Length of the window.
	 * @return array<int, array{day:string,a:array{views:int,conversions:int},b:array{views:int,conversions:int}}>
	 */
	public static function daily_series( array $rows, $today = '', $days = 30 ) {
		$days = (int) $days;
		if ( $days < 1 ) {
			$days = self::CHART_DAYS;
		}
		if ( $days > 366 ) {
			$days = 366;
		}
		$today = self::normal_day( $today );
		if ( '' === $today ) {
			$today = self::today();
		}

		$buckets = array();
		foreach ( self::normal_rows( $rows ) as $row ) {
			if ( '' === $row['day'] ) {
				continue;
			}
			if ( ! isset( $buckets[ $row['day'] ][ $row['variant'] ] ) ) {
				$buckets[ $row['day'] ][ $row['variant'] ] = array(
					'views'       => 0,
					'conversions' => 0,
				);
			}
			$buckets[ $row['day'] ][ $row['variant'] ]['views']       += $row['views'];
			$buckets[ $row['day'] ][ $row['variant'] ]['conversions'] += $row['conversions'];
		}

		$end = DateTimeImmutable::createFromFormat( '!Y-m-d', $today, new DateTimeZone( 'UTC' ) );
		if ( ! $end instanceof DateTimeImmutable ) {
			$end = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		}

		$series = array();
		$empty  = array(
			'views'       => 0,
			'conversions' => 0,
		);
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day      = $end->modify( '-' . $i . ' days' )->format( 'Y-m-d' );
			$series[] = array(
				'day' => $day,
				'a'   => isset( $buckets[ $day ]['a'] ) ? $buckets[ $day ]['a'] : $empty,
				'b'   => isset( $buckets[ $day ]['b'] ) ? $buckets[ $day ]['b'] : $empty,
			);
		}
		return $series;
	}

	/**
	 * @param array<int, mixed> $rows Proposed rows.
	 * @return array<int, array{variant:string,day:string,views:int,conversions:int}>
	 */
	private static function normal_rows( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$variant = sanitize_key( isset( $row['variant'] ) ? (string) $row['variant'] : '' );
			if ( 'a' !== $variant && 'b' !== $variant ) {
				continue;
			}
			$out[] = array(
				'variant'     => $variant,
				'day'         => self::normal_day( isset( $row['day'] ) ? $row['day'] : '' ),
				'views'       => absint( isset( $row['views'] ) ? $row['views'] : 0 ),
				'conversions' => absint( isset( $row['conversions'] ) ? $row['conversions'] : 0 ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $day Proposed day.
	 * @return string Y-m-d or empty.
	 */
	private static function normal_day( $day ) {
		$day = is_scalar( $day ) ? trim( (string) $day ) : '';
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $day, $match ) ) {
			return $match[1];
		}
		return '';
	}

	/**
	 * Site-local calendar day. A test can pin this with rwgc_ab_results_today.
	 *
	 * @return string Y-m-d.
	 */
	private static function today() {
		$filtered = apply_filters( 'rwgc_ab_results_today', '' );
		$day      = self::normal_day( $filtered );
		if ( '' !== $day ) {
			return $day;
		}
		$raw = function_exists( 'current_time' ) ? (string) current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		$day = self::normal_day( $raw );
		if ( '' !== $day ) {
			return $day;
		}
		return gmdate( 'Y-m-d' );
	}
}
