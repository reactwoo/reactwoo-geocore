<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geo detection service using MaxMind DB and cache.
 */
class RWGC_GeoIP {

	/**
	 * Resolve current visitor data.
	 *
	 * @return array
	 */
	public static function resolve_visitor() {
		$ip = self::get_current_ip();

		// Try cache first.
		$cached = $ip ? RWGC_Cache::get( $ip ) : null;
		if ( is_array( $cached ) && ! empty( $cached ) ) {
			$cached['cached'] = true;
			/**
			 * Filter final geo data.
			 */
			return apply_filters( 'rwgc_geo_data', $cached );
		}

		$data = self::lookup_ip( $ip );
		$data['cached'] = false;

		if ( $ip ) {
			RWGC_Cache::set( $ip, $data );
		}

		/**
		 * Fires when geo has been resolved for a visitor.
		 *
		 * @param array $data Geo data.
		 */
		do_action( 'rwgc_geo_resolved', $data );

		/**
		 * Filter final geo data.
		 *
		 * @param array $data Geo data.
		 */
		return apply_filters( 'rwgc_geo_data', $data );
	}

	/**
	 * Get current visitor IP (public address).
	 *
	 * @return string
	 */
	public static function get_current_ip() {
		$keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				foreach ( explode( ',', (string) $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
						return $ip;
					}
				}
			}
		}
		return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	}

	/**
	 * Lookup IP using MaxMind DB with fallback.
	 *
	 * @param string $ip IP address.
	 * @return array
	 */
	public static function lookup_ip( $ip ) {
		$defaults = self::empty_payload();
		$defaults['ip'] = $ip;

		// Allow early bail if disabled.
		if ( ! RWGC_Settings::get( 'enabled', 1 ) ) {
			$defaults['source'] = 'disabled';
			return $defaults;
		}

		// Fallback country/currency from settings.
		$fallback_country  = strtoupper( (string) apply_filters( 'rwgc_fallback_country', RWGC_Settings::get( 'fallback_country', 'US' ) ) );
		$fallback_currency = strtoupper( (string) apply_filters( 'rwgc_fallback_currency', RWGC_Settings::get( 'fallback_currency', 'USD' ) ) );

		// Prefer the explicit DB path here so Tools status and runtime lookups
		// always agree on the same file. Allow a filter so we can log/debug.
		$db_path = RWGC_MaxMind::get_db_path();
		$db_path = apply_filters( 'rwgc_db_path', $db_path );
		if ( ! $db_path || ! file_exists( $db_path ) ) {
			if ( RWGC_Settings::get( 'debug_mode', 0 ) && function_exists( 'error_log' ) ) {
				error_log( 'RWGC GeoIP: DB path missing or unreadable: ' . var_export( $db_path, true ) );
			}
			$defaults['country_code'] = $fallback_country;
			$defaults['country_name'] = self::get_country_name( $fallback_country );
			$defaults['currency']     = $fallback_currency;
			$defaults['source']       = 'fallback_db_missing';
			return $defaults;
		}

		// Try to load GeoIP2 reader from common locations (Geo Core vendor or GeoElementor).
		if ( ! class_exists( '\GeoIp2\Database\Reader' ) ) {
			$autoload_paths = array(
				RWGC_PATH . 'vendor/autoload.php',
				WP_PLUGIN_DIR . '/GeoElementor/vendor/autoload.php',
				WP_PLUGIN_DIR . '/geo-elementor/vendor/autoload.php',
			);
			foreach ( $autoload_paths as $autoload ) {
				if ( file_exists( $autoload ) ) {
					require_once $autoload;
					if ( class_exists( '\GeoIp2\Database\Reader' ) ) {
						break;
					}
				}
			}
		}

		// Require GeoIP2 reader; if still unavailable, fallback.
		if ( ! class_exists( '\GeoIp2\Database\Reader' ) ) {
			$defaults['country_code'] = $fallback_country;
			$defaults['country_name'] = self::get_country_name( $fallback_country );
			$defaults['currency']     = $fallback_currency;
			$defaults['source']       = 'fallback_no_reader';
			return $defaults;
		}

		if ( ! $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$defaults['country_code'] = $fallback_country;
			$defaults['country_name'] = self::get_country_name( $fallback_country );
			$defaults['currency']     = $fallback_currency;
			$defaults['source']       = 'fallback_invalid_ip';
			return $defaults;
		}

		try {
			$reader  = new \GeoIp2\Database\Reader( $db_path );
			$record  = $reader->country( $ip );
			$country = strtoupper( (string) $record->country->isoCode );
			$name    = (string) $record->country->name;
			$reader->close();

			if ( ! $country ) {
				$country = $fallback_country;
			}
			if ( ! $name ) {
				$name = self::get_country_name( $country );
			}

			$map      = RWGC_API::get_country_currency_map();
			$currency = isset( $map[ $country ] ) ? $map[ $country ] : $fallback_currency;

			return array(
				'ip'           => $ip,
				'country_code' => $country,
				'country_name' => $name,
				'region'       => '',
				'city'         => '',
				'currency'     => strtoupper( $currency ),
				'source'       => 'maxmind',
				'cached'       => false,
			);
		} catch ( \Throwable $e ) {
			if ( RWGC_Settings::get( 'debug_mode', 0 ) && function_exists( 'error_log' ) ) {
				error_log( 'RWGC GeoIP error: ' . $e->getMessage() );
			}
			$defaults['country_code'] = $fallback_country;
			$defaults['country_name'] = self::get_country_name( $fallback_country );
			$defaults['currency']     = $fallback_currency;
			$defaults['source']       = 'fallback_error';
			return $defaults;
		}
	}

	/**
	 * Empty payload template.
	 *
	 * @return array
	 */
	private static function empty_payload() {
		return array(
			'ip'           => '',
			'country_code' => '',
			'country_name' => '',
			'region'       => '',
			'city'         => '',
			'currency'     => '',
			'source'       => 'unknown',
			'cached'       => false,
		);
	}

	/**
	 * Get country name for a code (basic lookup).
	 *
	 * @param string $country_code ISO2.
	 * @return string
	 */
	public static function get_country_name( $country_code ) {
		$code = strtoupper( (string) $country_code );
		// Very small built-in list; developers can filter or extend.
		$names = array(
			'US' => __( 'United States', 'reactwoo-geocore' ),
			'GB' => __( 'United Kingdom', 'reactwoo-geocore' ),
			'ZA' => __( 'South Africa', 'reactwoo-geocore' ),
			'CA' => __( 'Canada', 'reactwoo-geocore' ),
			'AU' => __( 'Australia', 'reactwoo-geocore' ),
			'DE' => __( 'Germany', 'reactwoo-geocore' ),
		);
		/**
		 * Filter built-in country names.
		 *
		 * @param array $names Map of ISO2 => name.
		 */
		$names = apply_filters( 'rwgc_country_names', $names );
		return isset( $names[ $code ] ) ? $names[ $code ] : $code;
	}
}

