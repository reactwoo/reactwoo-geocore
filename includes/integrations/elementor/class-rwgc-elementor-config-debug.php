<?php
/**
 * Opt-in Elementor widgets-config timing logs.
 *
 * Enable only when debugging Elements panel / get_widgets_config failures:
 * define( 'RW_ELEMENTOR_CONFIG_DEBUG', true ); in wp-config.php
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

	/**
	 * @var array<string, int>
	 */
	private static $counts = array();

	/**
	 * @return bool
	 */
	public static function enabled() {
		return defined( 'RW_ELEMENTOR_CONFIG_DEBUG' ) && RW_ELEMENTOR_CONFIG_DEBUG;
	}

	/**
	 * @param string               $callback Callback/class::method label.
	 * @param array<string, mixed> $extra    Safe scalar extras (stack name, option counts).
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
			'cb'         => $callback,
			'n'          => self::$counts[ $callback ],
			't'          => round( microtime( true ), 4 ),
			'mem'        => memory_get_usage( true ),
			'peak'       => memory_get_peak_usage( true ),
			'queries'    => function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0,
		);

		foreach ( $extra as $key => $value ) {
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}
			$payload[ (string) $key ] = $value;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[RW_ELEMENTOR_CONFIG_DEBUG] ' . wp_json_encode( $payload ) );
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
		if ( ! self::enabled() ) {
			return $fn();
		}

		$start_q = function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0;
		$start_t = microtime( true );
		$result  = $fn();
		$extra['elapsed_ms'] = (int) round( ( microtime( true ) - $start_t ) * 1000 );
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
}
