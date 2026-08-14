<?php
/**
 * Opt-in timing for ReactWoo-owned Elementor callbacks.
 *
 * Off by default. Enable with one of:
 *
 * - `define( 'RWGC_ELEMENTOR_PROFILE', true );` in wp-config.php
 * - option `rwgc_elementor_profile` = 1
 * - `add_filter( 'rwgc_elementor_profile', '__return_true' );`
 *
 * Only Geo Core's own callbacks are measured. Third-party callbacks are never
 * enumerated, wrapped or removed.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records duration, memory delta, query delta, peak memory and HTTP attempts.
 */
class RWGC_Elementor_Profiler {

	const OPTION_ENABLED = 'rwgc_elementor_profile';
	const OPTION_LAST    = 'rwgc_elementor_profile_last';
	const LOG_PREFIX     = '[RWGC_EL_PROFILE]';
	const MAX_ROWS       = 80;

	/**
	 * @var bool|null
	 */
	private static $enabled = null;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $rows = array();

	/**
	 * Count of HTTP requests attempted since the watcher was installed.
	 *
	 * @var int
	 */
	private static $http_attempts = 0;

	/**
	 * @var bool
	 */
	private static $http_watching = false;

	/**
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * @var float
	 */
	private static $started_at = 0.0;

	/**
	 * @return bool
	 */
	public static function enabled() {
		if ( null !== self::$enabled ) {
			return self::$enabled;
		}

		$enabled = false;
		if ( defined( 'RWGC_ELEMENTOR_PROFILE' ) && RWGC_ELEMENTOR_PROFILE ) {
			$enabled = true;
		} elseif ( function_exists( 'get_option' ) && get_option( self::OPTION_ENABLED, false ) ) {
			$enabled = true;
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter whether Geo Core profiles its own Elementor callbacks.
			 *
			 * @param bool $enabled Default false.
			 */
			$enabled = (bool) apply_filters( 'rwgc_elementor_profile', $enabled );
		}

		self::$enabled = (bool) $enabled;
		return self::$enabled;
	}

	/**
	 * Measure one Geo Core callback.
	 *
	 * @param string               $label Callback label (Class::method).
	 * @param callable             $fn    Work to run.
	 * @param array<string, mixed> $extra Scalar context.
	 * @return mixed Whatever $fn returns.
	 */
	public static function measure( $label, $fn, array $extra = array() ) {
		if ( ! self::enabled() ) {
			return $fn();
		}

		self::start();
		$start_http = self::$http_attempts;
		$start_mem  = memory_get_usage( true );
		$start_q    = function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0;
		$start_t    = microtime( true );

		$result = $fn();

		$row = array(
			'cb'         => (string) $label,
			'ms'         => round( ( microtime( true ) - $start_t ) * 1000, 2 ),
			'mem_delta'  => memory_get_usage( true ) - $start_mem,
			'peak'       => memory_get_peak_usage( true ),
			'q_delta'    => function_exists( 'get_num_queries' ) ? ( (int) get_num_queries() - $start_q ) : 0,
			'http'       => self::$http_attempts - $start_http,
		);
		if ( is_array( $result ) ) {
			$row['rows'] = count( $result );
		}
		foreach ( $extra as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$row[ (string) $key ] = $value;
			}
		}

		self::record( $row );
		return $result;
	}

	/**
	 * Record a point-in-time marker (no wrapped work).
	 *
	 * @param string               $label Marker label.
	 * @param array<string, mixed> $extra Scalar context.
	 * @return void
	 */
	public static function mark( $label, array $extra = array() ) {
		if ( ! self::enabled() ) {
			return;
		}
		self::start();
		$row = array(
			'cb'   => (string) $label,
			'ms'   => round( ( microtime( true ) - self::$started_at ) * 1000, 2 ),
			'mem'  => memory_get_usage( true ),
			'peak' => memory_get_peak_usage( true ),
			'q'    => function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0,
			'http' => self::$http_attempts,
		);
		foreach ( $extra as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$row[ (string) $key ] = $value;
			}
		}
		self::record( $row );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows() {
		return self::$rows;
	}

	/**
	 * Observe outgoing HTTP without altering it.
	 *
	 * @param mixed $preempt Short-circuit value from other filters.
	 * @return mixed Unchanged $preempt.
	 */
	public static function note_http_attempt( $preempt ) {
		++self::$http_attempts;
		return $preempt;
	}

	/**
	 * @return void
	 */
	public static function flush() {
		if ( ! self::enabled() || array() === self::$rows ) {
			return;
		}
		$snapshot = array(
			'ver'        => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '0',
			'request'    => self::request_label(),
			'elapsed_ms' => round( ( microtime( true ) - self::$started_at ) * 1000, 2 ),
			'peak'       => memory_get_peak_usage( true ),
			'http'       => self::$http_attempts,
			'rows'       => self::$rows,
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::LOG_PREFIX . ' ' . wp_json_encode( $snapshot ) );

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_LAST, $snapshot, false );
		}
	}

	/**
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$enabled             = null;
		self::$rows                = array();
		self::$http_attempts       = 0;
		self::$http_watching       = false;
		self::$shutdown_registered = false;
		self::$started_at          = 0.0;
	}

	/**
	 * @param array<string, mixed> $row Measurement.
	 * @return void
	 */
	private static function record( array $row ) {
		self::$rows[] = $row;
		if ( count( self::$rows ) > self::MAX_ROWS ) {
			array_shift( self::$rows );
		}
	}

	/**
	 * @return void
	 */
	private static function start() {
		if ( 0.0 === self::$started_at ) {
			self::$started_at = microtime( true );
		}
		if ( ! self::$http_watching && function_exists( 'add_filter' ) ) {
			self::$http_watching = true;
			add_filter( 'pre_http_request', array( __CLASS__, 'note_http_attempt' ), 0 );
		}
		if ( ! self::$shutdown_registered && function_exists( 'register_shutdown_function' ) ) {
			self::$shutdown_registered = true;
			register_shutdown_function( array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * @return string
	 */
	private static function request_label() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		if ( ! isset( $_REQUEST['actions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $action;
		}
		$raw = wp_unslash( $_REQUEST['actions'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_array( $raw ) ) {
			$raw = wp_json_encode( $raw );
		}
		if ( is_string( $raw ) && preg_match_all( '/"action"\s*:\s*"([a-z0-9_]+)"/i', $raw, $m ) ) {
			return $action . ':' . implode( ',', array_values( array_unique( $m[1] ) ) );
		}
		return $action;
	}
}
