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
		add_action( 'admin_notices', array( __CLASS__, 'render_proxy_admin_notice' ) );
		add_action( 'rwgc_platform_admin_notices', array( __CLASS__, 'render_proxy_admin_notice' ) );
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
			$cached = self::apply_cloudflare_country( $cached );
			/**
			 * Filter final geo data.
			 */
			return apply_filters( 'rwgc_geo_data', $cached );
		}

		$data = self::lookup_ip( $ip );
		$data['cached'] = false;
		$data = self::apply_cloudflare_country( $data );

		// A missing database is a site problem, not a visitor answer. Do not keep it for the cache TTL.
		if ( $ip && self::should_cache_lookup( $data ) ) {
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
	 * Cloudflare IPv4/IPv6 ranges (https://www.cloudflare.com/ips-v4 and ips-v6).
	 *
	 * Bundled so visitor rendering never fetches them. CF-Connecting-IP and
	 * CF-IPCountry are used only when Behind Cloudflare is on and REMOTE_ADDR
	 * is inside this list.
	 *
	 * @return list<string>
	 */
	public static function cloudflare_ip_cidrs() {
		$cidrs = array(
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
		/**
		 * Replace or extend the bundled Cloudflare ranges.
		 *
		 * @param list<string> $cidrs CIDRs shipped with this version.
		 */
		$filtered = apply_filters( 'rwgc_cloudflare_ip_cidrs', $cidrs );
		if ( ! is_array( $filtered ) ) {
			return $cidrs;
		}
		$out = array();
		foreach ( $filtered as $cidr ) {
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
	 * Get current visitor IP.
	 *
	 * Only REMOTE_ADDR is used unless that address is a trusted proxy. A trusted
	 * proxy is an entry under Settings → Trusted proxies, a QUIC.cloud edge while
	 * that option is on, or a published Cloudflare range while Behind Cloudflare
	 * is on. Private and loopback addresses are not trusted on their own.
	 *
	 * X-Forwarded-For, Forwarded, X-Real-IP, True-Client-IP, Client-IP,
	 * CF-Connecting-IP, and CF-IPCountry are ignored for every other peer.
	 * Behind a trusted proxy, X-Forwarded-For is walked from the right and
	 * trusted hops are skipped. The left-most value is never taken because it
	 * is first. X-Real-IP and True-Client-IP are used only when that walk has
	 * no public hop, and they win over Forwarded and X-Forwarded. A client can
	 * send those last two headers even when the proxy overwrote X-Real-IP.
	 * CF-Connecting-IP is used only when Behind Cloudflare is on and
	 * REMOTE_ADDR is inside the bundled Cloudflare ranges.
	 *
	 * `rwgc_is_trusted_proxy` can force the trust decision. `rwgc_visitor_ip`
	 * still runs last.
	 *
	 * @return string
	 */
	public static function get_current_ip() {
		$remote = self::server_header( 'REMOTE_ADDR' );
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
		if ( ! self::is_trusted_proxy( $remote ) ) {
			return $remote;
		}

		if ( self::cloudflare_headers_trusted( $remote ) ) {
			$cf = self::first_public_ip( self::server_header( 'HTTP_CF_CONNECTING_IP' ) );
			if ( '' !== $cf && ! self::is_trusted_hop( $cf ) ) {
				return $cf;
			}
		}

		$from_header = self::visitor_from_trusted_xff( $remote );
		if ( '' !== $from_header ) {
			return $from_header;
		}

		foreach ( array( 'HTTP_X_REAL_IP', 'HTTP_TRUE_CLIENT_IP' ) as $key ) {
			$single = self::single_forwarded_ip( self::server_header( $key ) );
			if ( '' !== $single && ! self::ips_equal( $single, $remote ) && ! self::is_trusted_hop( $single ) ) {
				return $single;
			}
		}

		// A proxy that only sets X-Real-IP still forwards Forwarded unless it
		// clears the header. Reading it before X-Real-IP let the client choose
		// the country. Use it only when the proxy supplied neither X-Forwarded-For
		// nor a single-value client header.
		foreach ( array( 'HTTP_FORWARDED', 'HTTP_X_FORWARDED' ) as $key ) {
			$from_forwarded = self::visitor_from_forwarding_header( self::server_header( $key ), $remote );
			if ( '' !== $from_forwarded ) {
				return $from_forwarded;
			}
		}

		return $remote;
	}

	/**
	 * @param string $key $_SERVER key.
	 * @return string
	 */
	private static function server_header( $key ) {
		if ( ! isset( $_SERVER[ $key ] ) ) {
			return '';
		}
		$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * One public IP. A comma-separated list is ignored.
	 *
	 * @param string $header Header value.
	 * @return string
	 */
	private static function single_forwarded_ip( $header ) {
		$header = trim( (string) $header );
		if ( '' === $header || false !== strpos( $header, ',' ) ) {
			return '';
		}
		return self::first_public_ip( $header );
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
	 * Behind Cloudflare: on, off, or legacy.
	 *
	 * Legacy is an upgraded site whose saved settings predate the checkbox.
	 * Those sites keep the previous Cloudflare behaviour until an admin saves.
	 *
	 * @return string on|off|legacy
	 */
	public static function cloudflare_mode() {
		if ( ! function_exists( 'get_option' ) || ! class_exists( 'RWGC_Settings', false ) ) {
			return 'off';
		}
		$stored = get_option( RWGC_Settings::OPTION_KEY, array() );
		if ( ! is_array( $stored ) || array() === $stored ) {
			return 'off';
		}
		if ( ! array_key_exists( 'behind_cloudflare', $stored ) ) {
			return 'legacy';
		}
		return ! empty( $stored['behind_cloudflare'] ) ? 'on' : 'off';
	}

	/**
	 * Whether a Cloudflare peer may supply CF-Connecting-IP and CF-IPCountry.
	 *
	 * @return bool
	 */
	public static function cloudflare_trust_active() {
		$active = in_array( self::cloudflare_mode(), array( 'on', 'legacy' ), true );
		/**
		 * Override the Behind Cloudflare checkbox.
		 *
		 * @param bool $active Saved choice, or legacy trust before that choice is saved.
		 */
		return (bool) apply_filters( 'rwgc_behind_cloudflare', $active );
	}

	/**
	 * ISO country from CF-IPCountry when the TCP peer is Cloudflare and the mode is on.
	 *
	 * XX (unknown) and any value that is not two letters are ignored.
	 *
	 * @return string
	 */
	public static function trusted_cloudflare_country() {
		$remote = self::server_header( 'REMOTE_ADDR' );
		if ( ! self::cloudflare_headers_trusted( $remote ) ) {
			return '';
		}
		$code = strtoupper( trim( self::server_header( 'HTTP_CF_IPCOUNTRY' ) ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $code ) || 'XX' === $code ) {
			return '';
		}
		return $code;
	}

	/**
	 * Apply a trusted CF-IPCountry onto a lookup payload.
	 *
	 * @param array<string, mixed> $data Geo payload.
	 * @return array<string, mixed>
	 */
	public static function apply_cloudflare_country( array $data ) {
		$code = self::trusted_cloudflare_country();
		if ( '' === $code ) {
			return $data;
		}
		$data['country_code'] = $code;
		$data['country_name'] = self::get_country_name( $code );
		$data['source']       = 'cloudflare';
		if ( class_exists( 'RWGC_API', false ) ) {
			$map = RWGC_API::get_country_currency_map();
			if ( isset( $map[ $code ] ) ) {
				$data['currency'] = strtoupper( (string) $map[ $code ] );
			}
		}
		return $data;
	}

	/**
	 * Keep valid IP and CIDR tokens from the Trusted proxies field.
	 *
	 * @param mixed $raw Textarea, CSV, or list.
	 * @return list<string>
	 */
	public static function parse_trusted_proxy_list( $raw ) {
		$tokens = self::proxy_list_tokens( $raw );
		$out    = array();
		$seen   = array();
		foreach ( $tokens as $token ) {
			if ( isset( $seen[ $token ] ) || ! self::is_valid_proxy_token( $token ) ) {
				continue;
			}
			$seen[ $token ] = true;
			$out[]          = $token;
		}
		return $out;
	}

	/**
	 * Tokens that are not an IP address or a CIDR range.
	 *
	 * @param mixed $raw Textarea, CSV, or list.
	 * @return list<string>
	 */
	public static function invalid_trusted_proxy_tokens( $raw ) {
		$bad = array();
		foreach ( self::proxy_list_tokens( $raw ) as $token ) {
			if ( ! self::is_valid_proxy_token( $token ) ) {
				$bad[] = $token;
			}
		}
		return $bad;
	}

	/**
	 * @param mixed $raw Textarea, CSV, or list.
	 * @return list<string>
	 */
	private static function proxy_list_tokens( $raw ) {
		$parts = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $item ) {
				if ( is_string( $item ) ) {
					$parts[] = $item;
				}
			}
		} else {
			$split = preg_split( '/[\s,;]+/', (string) $raw );
			$parts = is_array( $split ) ? $split : array();
		}
		$tokens = array();
		foreach ( $parts as $part ) {
			$token = trim( (string) $part );
			if ( '' !== $token ) {
				$tokens[] = $token;
			}
		}
		return $tokens;
	}

	/**
	 * @param string $token IP or CIDR.
	 * @return bool
	 */
	public static function is_valid_proxy_token( $token ) {
		$token = trim( (string) $token );
		if ( '' === $token ) {
			return false;
		}
		if ( false === strpos( $token, '/' ) ) {
			return (bool) filter_var( $token, FILTER_VALIDATE_IP );
		}
		$parts = explode( '/', $token, 2 );
		if ( 2 !== count( $parts ) || ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
			return false;
		}
		if ( ! preg_match( '/^\d+$/', $parts[1] ) ) {
			return false;
		}
		$bits = (int) $parts[1];
		if ( $bits < 1 ) {
			return false;
		}
		$max = false !== strpos( $parts[0], ':' ) ? 128 : 32;
		return $bits <= $max;
	}

	/**
	 * Admin notice when Cloudflare trust is still the upgraded default, when
	 * Cloudflare is detected while the option is off, or when a private peer
	 * is sending a forwarding header that Geo Core now ignores.
	 *
	 * @return void
	 */
	public static function render_proxy_admin_notice() {
		static $shown = false;
		if ( $shown ) {
			return;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}
		if ( class_exists( 'RWGC_Admin', false ) ) {
			if ( ! RWGC_Admin::can_manage() ) {
				return;
			}
		} elseif ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$shown = true;

		$remote = self::server_header( 'REMOTE_ADDR' );
		$url    = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=rwgc-settings' ) : '';
		$mode   = self::cloudflare_mode();

		if ( 'legacy' === $mode ) {
			self::render_notice_paragraph(
				__( 'ReactWoo Geo Core is still reading the visitor IP from Cloudflare, because this site was updated from a version that did that whenever the connection came from Cloudflare. Behind Cloudflare stays on until you save Geo Core settings. Confirm it there, or turn it off if this site is not using Cloudflare.', 'reactwoo-geocore' ),
				$url,
				__( 'Open Geo Core settings', 'reactwoo-geocore' )
			);
		} elseif ( 'off' === $mode && ! self::cloudflare_trust_active() && self::is_cloudflare_ip( $remote ) && self::request_has_cloudflare_visitor_header() ) {
			self::render_notice_paragraph(
				__( 'This request reached WordPress from a Cloudflare address, and Behind Cloudflare is turned off. Country, prices, and payment methods will follow the Cloudflare edge instead of the visitor. Turn on Behind Cloudflare in Geo Core settings.', 'reactwoo-geocore' ),
				$url,
				__( 'Open Geo Core settings', 'reactwoo-geocore' )
			);
		}

		if ( '' !== $remote && ! self::is_public_ip( $remote ) && ! self::is_trusted_proxy( $remote ) && self::request_has_forwarding_header() ) {
			self::render_notice_paragraph(
				sprintf(
					/* translators: %s: private address PHP sees for this request. */
					__( 'PHP sees the private address %s together with a forwarding header such as X-Forwarded-For. Geo Core ignores that header, so a visitor cannot choose a country. If a reverse proxy on this server sets the header, add that address under Trusted proxies in Geo Core settings.', 'reactwoo-geocore' ),
					$remote
				),
				$url,
				__( 'Open Geo Core settings', 'reactwoo-geocore' )
			);
		}
	}

	/**
	 * @param string $message    Notice text.
	 * @param string $url        Settings URL.
	 * @param string $link_label Link text.
	 * @return void
	 */
	private static function render_notice_paragraph( $message, $url, $link_label ) {
		echo '<div class="notice notice-warning rwgc-notice"><p>';
		echo esc_html( $message );
		if ( is_string( $url ) && '' !== $url ) {
			echo ' <a href="' . esc_url( $url ) . '">' . esc_html( $link_label ) . '</a>';
		}
		echo '</p></div>';
	}

	/**
	 * @return bool
	 */
	private static function request_has_cloudflare_visitor_header() {
		return '' !== self::server_header( 'HTTP_CF_CONNECTING_IP' )
			|| '' !== self::server_header( 'HTTP_CF_IPCOUNTRY' )
			|| '' !== self::server_header( 'HTTP_CF_RAY' );
	}

	/**
	 * @return bool
	 */
	private static function request_has_forwarding_header() {
		foreach ( array(
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'HTTP_TRUE_CLIENT_IP',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CF_IPCOUNTRY',
			'HTTP_CLIENT_IP',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
		) as $key ) {
			if ( '' !== self::server_header( $key ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Trusted proxy CIDRs or exact IPs from the setting, then the filter.
	 *
	 * @return list<string>
	 */
	public static function trusted_proxy_cidrs() {
		$configured = self::configured_trusted_proxy_cidrs();
		/**
		 * Trusted reverse-proxy ranges. The list starts as Settings → Trusted proxies.
		 * When REMOTE_ADDR matches, the visitor IP is the rightmost public
		 * X-Forwarded-For hop that is not itself in this list.
		 *
		 * @param list<string> $cidrs CIDRs or exact IPs from the setting.
		 */
		$cidrs = apply_filters( 'rwgc_trusted_proxy_cidrs', $configured );
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
	 * Saved trusted proxies, before the rwgc_trusted_proxy_cidrs filter.
	 *
	 * @return list<string>
	 */
	public static function configured_trusted_proxy_cidrs() {
		if ( ! class_exists( 'RWGC_Settings', false ) || ! function_exists( 'get_option' ) ) {
			return array();
		}
		return self::parse_trusted_proxy_list( (string) RWGC_Settings::get( 'trusted_proxies', '' ) );
	}

	/**
	 * Whether this TCP peer may supply the visitor IP through forwarding headers.
	 *
	 * @param string $remote REMOTE_ADDR.
	 * @return bool
	 */
	public static function is_trusted_proxy( $remote ) {
		$remote  = trim( (string) $remote );
		$trusted = self::is_trusted_hop( $remote );
		/**
		 * Override the trusted-proxy decision for this connection.
		 *
		 * Return true to honour forwarding headers from this peer, or false to
		 * keep REMOTE_ADDR. CF-Connecting-IP and CF-IPCountry still require the
		 * peer to be inside Cloudflare's ranges while Behind Cloudflare is on.
		 *
		 * @param bool   $trusted Result of the saved proxies, QUIC.cloud, and Cloudflare checks.
		 * @param string $remote  REMOTE_ADDR.
		 */
		return (bool) apply_filters( 'rwgc_is_trusted_proxy', $trusted, $remote );
	}

	/**
	 * A hop that should be skipped while walking X-Forwarded-For from the right.
	 *
	 * Does not apply rwgc_is_trusted_proxy. That filter decides the TCP peer only.
	 *
	 * @param string $ip IP.
	 * @return bool
	 */
	private static function is_trusted_hop( $ip ) {
		if ( self::quic_cloud_enabled() && self::is_quic_cloud_ip( $ip ) ) {
			return true;
		}
		if ( self::ip_matches_proxy_list( $ip, self::trusted_proxy_cidrs() ) ) {
			return true;
		}
		if ( self::cloudflare_trust_active() && self::is_cloudflare_ip( $ip ) ) {
			return true;
		}
		return false;
	}

	/**
	 * CF-Connecting-IP and CF-IPCountry apply only for a Cloudflare peer while the mode is on.
	 *
	 * @param string $remote REMOTE_ADDR.
	 * @return bool
	 */
	private static function cloudflare_headers_trusted( $remote ) {
		return self::cloudflare_trust_active()
			&& self::is_cloudflare_ip( $remote )
			&& self::is_trusted_proxy( $remote );
	}

	/**
	 * Rightmost public X-Forwarded-For hop that is not itself a trusted proxy.
	 *
	 * When every public hop is a trusted proxy, the rightmost hop is kept.
	 * The left-most address is never chosen merely because it is first.
	 * Forwarded and X-Forwarded are not read here; a client can send those
	 * while the proxy overwrites X-Real-IP.
	 *
	 * @param string $remote REMOTE_ADDR.
	 * @return string Empty when the header has no public hop.
	 */
	private static function visitor_from_trusted_xff( $remote ) {
		return self::visitor_from_forwarding_header( self::server_header( 'HTTP_X_FORWARDED_FOR' ), $remote );
	}

	/**
	 * Rightmost public hop in one forwarding header that is not a trusted proxy.
	 *
	 * @param string $header Header value.
	 * @param string $remote REMOTE_ADDR.
	 * @return string Empty when the header has no public hop.
	 */
	private static function visitor_from_forwarding_header( $header, $remote ) {
		$ips   = self::public_ips_from_header( $header );
		$count = count( $ips );
		if ( 0 === $count ) {
			return '';
		}
		for ( $i = $count - 1; $i >= 0; $i-- ) {
			$hop = $ips[ $i ];
			if ( self::ips_equal( $hop, $remote ) || self::is_trusted_hop( $hop ) ) {
				continue;
			}
			return $hop;
		}
		return $ips[ $count - 1 ];
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
	 * Whether a lookup result may be stored in the per-IP cache.
	 *
	 * @param mixed $data Lookup payload.
	 * @return bool
	 */
	public static function should_cache_lookup( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}
		$source = isset( $data['source'] ) ? (string) $data['source'] : '';
		return ! in_array( $source, array( 'fallback_db_missing', 'fallback_no_reader' ), true );
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
			$defaults['country_code'] = $fallback_country;
			$defaults['country_name'] = self::get_country_name( $fallback_country );
			$defaults['currency']     = $fallback_currency;
			$defaults['source']       = 'fallback_db_missing';
			return $defaults;
		}

		if ( ! class_exists( '\GeoIp2\Database\Reader' ) ) {
			$autoload_paths = array(
				RWGC_PATH . 'vendor/autoload.php',
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
			// GeoIP2 3.x exposes these as public readonly properties (isoCode and name are ?string).
			$reader  = new \GeoIp2\Database\Reader( $db_path );
			$record  = $reader->country( $ip );
			$iso     = $record->country->isoCode;
			$label   = $record->country->name;
			$reader->close();
			$country = is_string( $iso ) ? strtoupper( $iso ) : '';
			$name    = is_string( $label ) ? $label : '';

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

