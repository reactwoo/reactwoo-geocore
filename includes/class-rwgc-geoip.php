<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geo detection service using MaxMind DB and cache.
 */
class RWGC_GeoIP {

	const QUIC_REFRESH_HOOK = 'rwgc_refresh_quic_cloud_ips';

	const QUIC_IPS_OPTION = 'rwgc_quic_cloud_ips';

	const QUIC_IPS_URL = 'https://www.quic.cloud/ips-all?json';

	/**
	 * @var array<string, bool>|null
	 */
	private static $quic_lookup = null;

	/**
	 * @var array<int, string>|null
	 */
	private static $quic_ips = null;

	/**
	 * Schedule a background refresh of the QUIC.cloud edge list. Never fetches on a visitor render.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::QUIC_REFRESH_HOOK, array( __CLASS__, 'refresh_quic_cloud_ips' ) );
		add_action( 'admin_init', array( __CLASS__, 'sync_quic_cloud_schedule' ) );
		add_action( 'update_option_' . RWGC_Settings::OPTION_KEY, array( __CLASS__, 'sync_quic_cloud_schedule' ) );
	}

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
	 * Client-IP, …) are not trusted unless the TCP peer is a Cloudflare edge,
	 * a private/reserved reverse-proxy address, or a trusted public proxy.
	 * Spoofed leftmost XFF values are ignored. A trusted proxy contributes the
	 * rightmost public hop that is not itself a trusted proxy (QUIC.cloud's
	 * header can end with the PoP address). A private peer uses that same walk:
	 * a local reverse proxy often leaves REMOTE_ADDR on a loopback address while
	 * the QUIC.cloud PoP is still the last public hop.
	 *
	 * Trusted public proxies are off by default. Settings → “My site uses
	 * QUIC.cloud CDN” trusts only QUIC.cloud's published edge list. Developers
	 * can add other CDN ranges with the `rwgc_trusted_proxy_cidrs` filter.
	 * `rwgc_visitor_ip` still runs last.
	 *
	 * @return string
	 */
	public static function get_current_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
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
		$cf     = self::first_public_ip( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? (string) wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) : '' );

		if ( '' !== $cf && self::is_cloudflare_ip( $remote ) ) {
			return $cf;
		}

		if ( self::is_public_ip( $remote ) ) {
			if ( self::peer_is_trusted_proxy( $remote ) ) {
				$from_header = self::visitor_from_trusted_xff( $remote );
				if ( '' !== $from_header ) {
					return $from_header;
				}
			}
			return $remote;
		}

		// Origin is on a private/reserved address (typical reverse proxy).
		// Do not trust CF-Connecting-IP here — clients can send that header when
		// the TCP peer is not Cloudflare. Skip trusted proxy hops in X-Forwarded-For
		// (QUIC.cloud appends its PoP) and use the visitor hop in front of them.
		// If every public hop is a trusted proxy, keep the rightmost public address
		// rather than the private REMOTE_ADDR.
		$from_header = self::visitor_from_trusted_xff( $remote );
		if ( '' !== $from_header ) {
			return $from_header;
		}

		$xff = self::rightmost_public_ip( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) : '' );
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
	 * Whether the QUIC.cloud CDN setting is on.
	 *
	 * @return bool
	 */
	public static function quic_cloud_enabled() {
		$enabled = false;
		if ( class_exists( 'RWGC_Settings', false ) && function_exists( 'get_option' ) ) {
			$enabled = (bool) RWGC_Settings::get( 'quic_cloud_cdn', 0 );
		}
		/**
		 * Override the saved “My site uses QUIC.cloud CDN” checkbox.
		 *
		 * @param bool $enabled Saved setting, default false.
		 */
		return (bool) apply_filters( 'rwgc_quic_cloud_cdn_enabled', $enabled );
	}

	/**
	 * Extra trusted proxy CIDRs or exact IPs for CDNs other than Cloudflare and QUIC.cloud.
	 *
	 * @return list<string>
	 */
	public static function trusted_proxy_cidrs() {
		/**
		 * Trusted reverse-proxy ranges. When REMOTE_ADDR matches, the visitor IP is
		 * the rightmost public X-Forwarded-For hop that is not itself in this list.
		 * Default is empty, so a public peer is never trusted from this filter alone.
		 *
		 * @param list<string> $cidrs CIDRs or exact IPs.
		 */
		$cidrs = apply_filters( 'rwgc_trusted_proxy_cidrs', array() );
		if ( ! is_array( $cidrs ) ) {
			return array();
		}
		$out = array();
		foreach ( $cidrs as $cidr ) {
			if ( ! is_string( $cidr ) ) {
				continue;
			}
			$cidr = trim( $cidr );
			if ( '' !== $cidr ) {
				$out[] = $cidr;
			}
		}
		return $out;
	}

	/**
	 * @param string $remote REMOTE_ADDR.
	 * @return bool
	 */
	private static function peer_is_trusted_proxy( $remote ) {
		if ( self::quic_cloud_enabled() && self::is_quic_cloud_ip( $remote ) ) {
			return true;
		}
		return self::ip_matches_proxy_list( $remote, self::trusted_proxy_cidrs() );
	}

	/**
	 * Rightmost public X-Forwarded-For hop that is not the proxy itself.
	 *
	 * @param string $remote REMOTE_ADDR.
	 * @return string Empty when the header has no usable visitor hop.
	 */
	private static function visitor_from_trusted_xff( $remote ) {
		$header = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) : '';
		$ips    = self::public_ips_from_header( $header );
		for ( $i = count( $ips ) - 1; $i >= 0; $i-- ) {
			$hop = $ips[ $i ];
			if ( self::ips_equal( $hop, $remote ) || self::peer_is_trusted_proxy( $hop ) ) {
				continue;
			}
			return $hop;
		}
		return '';
	}

	/**
	 * @param string            $ip    IP.
	 * @param array<int,string> $cidrs CIDRs or exact IPs.
	 * @return bool
	 */
	private static function ip_matches_proxy_list( $ip, array $cidrs ) {
		foreach ( $cidrs as $cidr ) {
			if ( false === strpos( $cidr, '/' ) ) {
				if ( self::ips_equal( $ip, $cidr ) ) {
					return true;
				}
				continue;
			}
			if ( self::ip_in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $left  IP.
	 * @param string $right IP.
	 * @return bool
	 */
	private static function ips_equal( $left, $right ) {
		$a = self::ip_lookup_key( $left );
		$b = self::ip_lookup_key( $right );
		return '' !== $a && $a === $b;
	}

	/**
	 * Binary key so compressed and expanded IPv6 addresses match.
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	private static function ip_lookup_key( $ip ) {
		$bin = inet_pton( trim( (string) $ip ) );
		return false === $bin ? '' : $bin;
	}

	/**
	 * Whether an address is a published QUIC.cloud edge.
	 *
	 * Uses the last successful refresh when one is stored, otherwise the list
	 * bundled with this plugin. Does not fetch on the request that calls it.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	public static function is_quic_cloud_ip( $ip ) {
		$key = self::ip_lookup_key( $ip );
		if ( '' === $key ) {
			return false;
		}
		$map = self::quic_cloud_lookup();
		return isset( $map[ $key ] );
	}

	/**
	 * @return array<string, bool>
	 */
	private static function quic_cloud_lookup() {
		if ( null !== self::$quic_lookup ) {
			return self::$quic_lookup;
		}
		$map = array();
		foreach ( self::quic_cloud_ips() as $listed ) {
			$key = self::ip_lookup_key( $listed );
			if ( '' !== $key ) {
				$map[ $key ] = true;
			}
		}
		self::$quic_lookup = $map;
		return $map;
	}

	/**
	 * Edge addresses used for the QUIC.cloud setting.
	 *
	 * @return list<string>
	 */
	public static function quic_cloud_ips() {
		if ( null !== self::$quic_ips ) {
			return self::$quic_ips;
		}
		$cached = self::cached_quic_cloud_ips();
		self::$quic_ips = ! empty( $cached ) ? $cached : self::bundled_quic_cloud_ips();
		return self::$quic_ips;
	}

	/**
	 * @return list<string>
	 */
	public static function bundled_quic_cloud_ips() {
		$path = dirname( __FILE__ ) . '/data/quic-cloud-ips.json';
		if ( ! is_readable( $path ) ) {
			return array();
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		return self::parse_quic_cloud_ip_payload( $decoded );
	}

	/**
	 * Accept the published JSON array or the bundled `{ ips: [] }` document.
	 *
	 * @param mixed $decoded Decoded JSON.
	 * @return list<string>
	 */
	public static function parse_quic_cloud_ip_payload( $decoded ) {
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		if ( isset( $decoded['ips'] ) && is_array( $decoded['ips'] ) ) {
			return self::normalize_ip_list( $decoded['ips'] );
		}
		$list = array();
		foreach ( $decoded as $key => $value ) {
			if ( is_int( $key ) && is_string( $value ) ) {
				$list[] = $value;
			}
		}
		return self::normalize_ip_list( $list );
	}

	/**
	 * @param array<mixed> $ips Raw addresses.
	 * @return list<string>
	 */
	private static function normalize_ip_list( array $ips ) {
		$out  = array();
		$seen = array();
		foreach ( $ips as $ip ) {
			if ( ! is_string( $ip ) ) {
				continue;
			}
			$ip = trim( $ip );
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				continue;
			}
			$key = self::ip_lookup_key( $ip );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $ip;
		}
		return $out;
	}

	/**
	 * @return list<string>
	 */
	private static function cached_quic_cloud_ips() {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$stored = get_option( self::QUIC_IPS_OPTION, array() );
		if ( ! is_array( $stored ) || empty( $stored['ips'] ) || ! is_array( $stored['ips'] ) ) {
			return array();
		}
		return self::normalize_ip_list( $stored['ips'] );
	}

	/**
	 * Keep or clear the daily refresh. Runs from admin and after the setting is saved.
	 *
	 * @return void
	 */
	public static function sync_quic_cloud_schedule( $old = null, $new = null ) {
		unset( $old, $new );
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		$scheduled = wp_next_scheduled( self::QUIC_REFRESH_HOOK );
		if ( self::quic_cloud_enabled() ) {
			if ( ! $scheduled ) {
				wp_schedule_event( time() + 120, 'daily', self::QUIC_REFRESH_HOOK );
			}
			return;
		}
		while ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::QUIC_REFRESH_HOOK );
			$scheduled = wp_next_scheduled( self::QUIC_REFRESH_HOOK );
		}
	}

	/**
	 * Replace the cached edge list from QUIC.cloud's published JSON.
	 *
	 * A failed or tiny response leaves the previous cache, and the request path
	 * falls back to the bundled file. This does not run during visitor HTML.
	 *
	 * @return bool
	 */
	public static function refresh_quic_cloud_ips() {
		if ( function_exists( 'wp_doing_cron' ) && ! wp_doing_cron() && function_exists( 'is_admin' ) && ! is_admin() ) {
			return false;
		}
		if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'update_option' ) ) {
			return false;
		}

		$url = self::QUIC_IPS_URL;
		/**
		 * Source for the QUIC.cloud edge list. The published document is a JSON array of IPs.
		 *
		 * @param string $url Default https://www.quic.cloud/ips-all?json.
		 */
		$filtered = apply_filters( 'rwgc_quic_cloud_ip_list_url', $url );
		if ( is_string( $filtered ) && '' !== $filtered ) {
			$url = $filtered;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : 0;
		if ( 200 !== $code ) {
			return false;
		}
		$body = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $response ) : '';
		$ips  = self::parse_quic_cloud_ip_payload( json_decode( $body, true ) );
		// Refuse an empty or truncated body so a bad response cannot wipe a good cache.
		if ( count( $ips ) < 10 ) {
			return false;
		}
		update_option(
			self::QUIC_IPS_OPTION,
			array(
				'ips'     => $ips,
				'fetched' => time(),
				'source'  => $url,
			),
			false
		);
		self::$quic_ips    = null;
		self::$quic_lookup = null;
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

