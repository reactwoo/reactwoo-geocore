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
	 * Cloudflare IPv4/IPv6 ranges (https://www.cloudflare.com/ips/).
	 * Used so CF-Connecting-IP is trusted only when the TCP peer is Cloudflare.
	 *
	 * @return list<string>
	 */
	public static function cloudflare_ip_cidrs() {
		return array(
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		);
	}

	/**
	 * Get current visitor IP (public address).
	 *
	 * Client-controlled forwarding headers (X-Forwarded-For, CF-Connecting-IP,
	 * Client-IP, …) are not trusted unless the TCP peer is a Cloudflare edge
	 * or a private/reserved reverse-proxy address. Spoofed leftmost XFF values
	 * are ignored; a reverse proxy may contribute only the rightmost public hop.
	 *
	 * @return string
	 */
	public static function get_current_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
		$ip     = self::resolve_client_ip( $remote );

		/**
		 * Filter the resolved visitor IP used for MaxMind lookup and targeting.
		 *
		 * @param string $ip     Resolved IP.
		 * @param string $remote REMOTE_ADDR.
		 */
		$filtered = apply_filters( 'rwgc_visitor_ip', $ip, $remote );
		return is_string( $filtered ) ? $filtered : $ip;
	}

	/**
	 * @param string $remote REMOTE_ADDR.
	 * @return string
	 */
	private static function resolve_client_ip( $remote ) {
		$remote = trim( (string) $remote );
		$cf     = self::first_public_ip( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? (string) $_SERVER['HTTP_CF_CONNECTING_IP'] : '' );

		if ( '' !== $cf && self::is_cloudflare_ip( $remote ) ) {
			return $cf;
		}

		if ( self::is_public_ip( $remote ) ) {
			return $remote;
		}

		// Origin is on a private/reserved address (typical reverse proxy).
		// Do not trust CF-Connecting-IP here — clients can send that header when
		// the TCP peer is not Cloudflare. Use the rightmost public XFF hop.
		$xff = self::rightmost_public_ip( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '' );
		if ( '' !== $xff ) {
			return $xff;
		}

		return $remote;
	}

	/**
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_public_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( '' === $ip ) {
			return false;
		}
		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_cloudflare_ip( $ip ) {
		$ip = trim( (string) $ip );
		if ( '' === $ip || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		foreach ( self::cloudflare_ip_cidrs() as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $header Header value (may be a comma list).
	 * @return string
	 */
	private static function first_public_ip( $header ) {
		$ips = self::public_ips_from_header( $header );
		return isset( $ips[0] ) ? $ips[0] : '';
	}

	/**
	 * Rightmost public hop — the address a reverse proxy typically appends.
	 *
	 * @param string $header Header value.
	 * @return string
	 */
	private static function rightmost_public_ip( $header ) {
		$ips = self::public_ips_from_header( $header );
		if ( empty( $ips ) ) {
			return '';
		}
		return $ips[ count( $ips ) - 1 ];
	}

	/**
	 * @param string $header Header value.
	 * @return list<string>
	 */
	private static function public_ips_from_header( $header ) {
		$out = array();
		foreach ( explode( ',', (string) $header ) as $part ) {
			$ip = trim( $part );
			if ( 0 === stripos( $ip, 'for=' ) ) {
				$ip = trim( substr( $ip, 4 ), " \t\"[]" );
			}
			$ip = trim( $ip, '[]' );
			if ( self::is_public_ip( $ip ) ) {
				$out[] = $ip;
			}
		}
		return $out;
	}

	/**
	 * @param string $ip   Address.
	 * @param string $cidr CIDR.
	 * @return bool
	 */
	public static function ip_in_cidr( $ip, $cidr ) {
		$parts = explode( '/', (string) $cidr, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}
		$subnet = $parts[0];
		$bits   = (int) $parts[1];
		$ip_bin = inet_pton( (string) $ip );
		$sub_bin = inet_pton( $subnet );
		if ( false === $ip_bin || false === $sub_bin || strlen( $ip_bin ) !== strlen( $sub_bin ) ) {
			return false;
		}
		$len      = strlen( $ip_bin );
		$max_bits = 4 === $len ? 32 : 128;
		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}
		$full_bytes = (int) floor( $bits / 8 );
		$remain     = $bits % 8;
		if ( $full_bytes > 0 && substr( $ip_bin, 0, $full_bytes ) !== substr( $sub_bin, 0, $full_bytes ) ) {
			return false;
		}
		if ( $remain > 0 ) {
			$mask = ( 0xFF << ( 8 - $remain ) ) & 0xFF;
			if ( ( ord( $ip_bin[ $full_bytes ] ) & $mask ) !== ( ord( $sub_bin[ $full_bytes ] ) & $mask ) ) {
				return false;
			}
		}
		return true;
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

