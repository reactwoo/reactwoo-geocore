<?php
/**
 * Elementor widgets-config debug trail (LiteSpeed 503).
 *
 * Records a compact last-run snapshot + response headers on elementor_ajax
 * only. Never boots on heartbeat, editor HTML, or frontend (1.8.139 wrote
 * debug.log + update_option on every request and exhausted LiteSpeed workers).
 *
 * Verbose error_log / per-widget lines when enabled:
 *
 * - define( 'RW_ELEMENTOR_CONFIG_DEBUG', true ); in wp-config.php
 * - or option `rwgc_elementor_widget_load_debug` = 1 (default on for ajax)
 *
 * Does not log rule JSON, credentials, licence tokens, or customer PII.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared debug logger for Elementor control-registration hot paths.
 */
class RWGC_Elementor_Config_Debug {

	const OPTION_ENABLED = 'rwgc_elementor_widget_load_debug';
	const OPTION_LAST    = 'rwgc_elementor_widget_load_last';
	const LOG_PREFIX     = '[RWGC_EL_WIDGETS]';

	/**
	 * @var array<string, int>
	 */
	private static $counts = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $checkpoints = array();

	/**
	 * @var array<string, mixed>
	 */
	private static $summary = array();

	/**
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Verbose per-widget / error_log lines. Only meaningful on elementor_ajax.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( ! self::is_elementor_ajax_request() ) {
			return false;
		}
		if ( defined( 'RW_ELEMENTOR_CONFIG_DEBUG' ) && RW_ELEMENTOR_CONFIG_DEBUG ) {
			return true;
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgc_elementor_widget_load_debug', null );
			if ( null !== $filtered ) {
				return (bool) $filtered;
			}
		}
		if ( function_exists( 'get_option' ) ) {
			return (bool) get_option( self::OPTION_ENABLED, true );
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public static function is_elementor_ajax_request() {
		if ( class_exists( 'RWGC_Elementor_Ajax', false ) ) {
			return RWGC_Elementor_Ajax::is_elementor_ajax();
		}
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return false;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( 'elementor_ajax' === $action );
	}

	/**
	 * @return void
	 */
	public static function boot() {
		if ( self::$shutdown_registered ) {
			return;
		}
		if ( ! self::is_elementor_ajax_request() ) {
			return;
		}
		self::$shutdown_registered = true;
		self::$summary             = array(
			'ver'        => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '0',
			'started_at' => round( microtime( true ), 4 ),
			'heavy'      => ( class_exists( 'RWGC_Elementor_Ajax', false ) && RWGC_Elementor_Ajax::is_heavy_elementor_ajax() ) ? 1 : 0,
			'action'     => self::request_action_names(),
		);
		register_shutdown_function( array( __CLASS__, 'flush_shutdown' ) );
		self::checkpoint( 'boot', array() );
	}

	/**
	 * @param string               $name  Checkpoint id.
	 * @param array<string, mixed> $extra Safe scalars.
	 * @return void
	 */
	public static function checkpoint( $name, array $extra = array() ) {
		$row = array(
			'cp'   => (string) $name,
			'ms'   => self::elapsed_ms(),
			'mem'  => memory_get_usage( true ),
			'peak' => memory_get_peak_usage( true ),
		);
		if ( function_exists( 'get_num_queries' ) ) {
			$row['q'] = (int) get_num_queries();
		}
		foreach ( $extra as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$row[ (string) $key ] = $value;
			}
		}
		self::$checkpoints[] = $row;
		self::log( (string) $name, $extra );
	}

	/**
	 * @param string               $callback Callback/class::method label.
	 * @param array<string, mixed> $extra    Safe scalar extras.
	 * @return void
	 */
	public static function log( $callback, array $extra = array() ) {
		if ( ! self::enabled() ) {
			return;
		}

		$callback = (string) $callback;
		if ( ! isset( self::$counts[ $callback ] ) ) {
			self::$counts[ $callback ] = 0;
		}
		++self::$counts[ $callback ];

		$payload = array(
			'cb'   => $callback,
			'n'    => self::$counts[ $callback ],
			'ms'   => self::elapsed_ms(),
			'mem'  => memory_get_usage( true ),
			'peak' => memory_get_peak_usage( true ),
		);
		if ( function_exists( 'get_num_queries' ) ) {
			$payload['q'] = (int) get_num_queries();
		}

		foreach ( $extra as $key => $value ) {
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}
			$payload[ (string) $key ] = $value;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( self::LOG_PREFIX . ' ' . wp_json_encode( $payload ) );
	}

	/**
	 * Time a callable and log elapsed ms.
	 *
	 * @param string               $callback Label.
	 * @param callable             $fn       Work to measure.
	 * @param array<string, mixed> $extra    Safe extras.
	 * @return mixed
	 */
	public static function time( $callback, $fn, array $extra = array() ) {
		$start_q = function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0;
		$start_t = microtime( true );
		$result  = $fn();
		$extra['elapsed_ms']    = (int) round( ( microtime( true ) - $start_t ) * 1000 );
		$extra['delta_queries'] = function_exists( 'get_num_queries' )
			? max( 0, (int) get_num_queries() - $start_q )
			: 0;

		if ( is_array( $result ) ) {
			$extra['rows'] = count( $result );
		} elseif ( is_countable( $result ) ) {
			$extra['rows'] = count( $result );
		}

		self::log( $callback, $extra );
		return $result;
	}

	/**
	 * True for ReactWoo / WHMCS / Social / Reviews widget classes.
	 *
	 * @param string $class Class name.
	 * @return bool
	 */
	public static function is_our_entry( $class ) {
		$class = (string) $class;
		$needles = array(
			'ReactWoo\\',
			'RWGC_',
			'RW_Elementor_',
			'RW_WHMCS',
			'RWSC_',
			'GRP_',
		);
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $class, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $key   Summary key.
	 * @param mixed  $value Scalar or short string.
	 * @return void
	 */
	public static function set_summary( $key, $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			self::$summary[ (string) $key ] = $value;
		}
	}

	/**
	 * Compact headers for the Network tab (works if the request survives).
	 *
	 * @param string $note slim|error|…
	 * @return void
	 */
	public static function send_headers( $note ) {
		if ( headers_sent() ) {
			return;
		}
		$ver = defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '0';
		header( 'X-RWGC-Widgets-Config: ' . $ver . '; ' . $note );
		$parts = array(
			'heavy=' . ( isset( self::$summary['heavy'] ) ? (int) self::$summary['heavy'] : 0 ),
			'kept=' . ( isset( self::$summary['kept'] ) ? (int) self::$summary['kept'] : 0 ),
			'skip=' . ( isset( self::$summary['skipped'] ) ? (int) self::$summary['skipped'] : 0 ),
			'ours=' . ( isset( self::$summary['ours'] ) ? (int) self::$summary['ours'] : 0 ),
			'unhook=' . ( isset( self::$summary['unhooked'] ) ? (int) self::$summary['unhooked'] : 0 ),
			'ms=' . self::elapsed_ms(),
		);
		if ( ! empty( self::$summary['left'] ) ) {
			$parts[] = 'left=' . preg_replace( '/[^A-Za-z0-9_,\\\\]/', '', (string) self::$summary['left'] );
		}
		if ( ! empty( self::$summary['slowest'] ) ) {
			$parts[] = 'slow=' . preg_replace( '/[^A-Za-z0-9_\-:,]/', '', (string) self::$summary['slowest'] );
		}
		header( 'X-RWGC-El-Debug: ' . implode( ';', $parts ) );
	}

	/**
	 * Persist snapshot even when LiteSpeed kills the request mid-flight.
	 *
	 * @return void
	 */
	public static function flush_shutdown() {
		$last_err = function_exists( 'error_get_last' ) ? error_get_last() : null;
		$fatal    = '';
		if ( is_array( $last_err ) && isset( $last_err['type'], $last_err['message'] ) ) {
			$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
			if ( in_array( (int) $last_err['type'], $fatal_types, true ) ) {
				$fatal = substr( (string) $last_err['message'], 0, 180 );
			}
		}

		$snapshot = array(
			'summary'     => self::$summary,
			'checkpoints' => array_slice( self::$checkpoints, -20 ),
			'elapsed_ms'  => self::elapsed_ms(),
			'peak'        => memory_get_peak_usage( true ),
			'fatal'       => $fatal,
			'flushed_at'  => gmdate( 'c' ),
		);

		if ( ! self::is_elementor_ajax_request() ) {
			return;
		}

		if ( self::enabled() ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( self::LOG_PREFIX . ' shutdown ' . wp_json_encode( $snapshot['summary'] + array( 'elapsed_ms' => $snapshot['elapsed_ms'], 'fatal' => $fatal ) ) );
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_LAST, $snapshot, false );
		}
	}

	/**
	 * @return int
	 */
	private static function elapsed_ms() {
		$start = isset( self::$summary['started_at'] ) ? (float) self::$summary['started_at'] : microtime( true );
		return (int) round( ( microtime( true ) - $start ) * 1000 );
	}

	/**
	 * @return string
	 */
	private static function request_action_names() {
		if ( ! isset( $_REQUEST['actions'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $action;
		}
		$raw = wp_unslash( $_REQUEST['actions'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_array( $raw ) ) {
			$raw = wp_json_encode( $raw );
		}
		if ( ! is_string( $raw ) ) {
			return '';
		}
		if ( preg_match_all( '/"action"\s*:\s*"([a-z0-9_]+)"/i', $raw, $m ) ) {
			return implode( ',', array_values( array_unique( $m[1] ) ) );
		}
		return substr( $raw, 0, 80 );
	}
}
