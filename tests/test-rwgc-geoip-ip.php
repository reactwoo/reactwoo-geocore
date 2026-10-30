<?php
/**
 * Visitor IP resolution — ignore client-spoofed forwarding headers.
 *
 * Usage: php tests/test-rwgc-geoip-ip.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str Value.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
function apply_filters( $hook, $value, ...$args ) {
	unset( $args );
	if ( 'rwgc_quic_cloud_cdn_enabled' === $hook ) {
		return ! empty( $GLOBALS['rwgc_test_quic'] );
	}
	if ( 'rwgc_behind_cloudflare' === $hook && array_key_exists( 'rwgc_test_cloudflare', $GLOBALS ) ) {
		return ! empty( $GLOBALS['rwgc_test_cloudflare'] );
	}
	if ( 'rwgc_trusted_proxy_cidrs' === $hook && isset( $GLOBALS['rwgc_test_trusted_cidrs'] ) && is_array( $GLOBALS['rwgc_test_trusted_cidrs'] ) ) {
		return $GLOBALS['rwgc_test_trusted_cidrs'];
	}
	if ( 'rwgc_is_trusted_proxy' === $hook && array_key_exists( 'rwgc_test_trust_override', $GLOBALS ) ) {
		return $GLOBALS['rwgc_test_trust_override'];
	}
	if ( 'rwgc_cloudflare_ip_cidrs' === $hook && isset( $GLOBALS['rwgc_test_cf_cidrs'] ) && is_array( $GLOBALS['rwgc_test_cf_cidrs'] ) ) {
		return $GLOBALS['rwgc_test_cf_cidrs'];
	}
	if ( 'rwgc_visitor_ip' === $hook && isset( $GLOBALS['rwgc_test_visitor_ip'] ) ) {
		return $GLOBALS['rwgc_test_visitor_ip'];
	}
	return $value;
}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Option name.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] ) && array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ) {
			return $GLOBALS['rwgc_test_options'][ $key ];
		}
		return $default;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-geoip.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_geoip_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK   $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

/**
 * @param array<string, string> $server Server vars.
 * @return string
 */
function rwgc_geoip_resolve( array $server ) {
	$backup           = $_SERVER;
	$_SERVER          = $server;
	$ip               = RWGC_GeoIP::get_current_ip();
	$_SERVER          = $backup;
	return $ip;
}

/**
 * @param array<string, string> $server Server vars.
 * @return string
 */
function rwgc_geoip_country( array $server ) {
	$backup  = $_SERVER;
	$_SERVER = $server;
	$country = RWGC_GeoIP::trusted_cloudflare_country();
	$_SERVER = $backup;
	return $country;
}

$visitor = '203.0.113.10';
$spoof   = '8.8.8.8';
$cf_edge = '162.158.0.1';
$cf_cli  = '198.51.100.20';

rwgc_geoip_assert( 'plain REMOTE_ADDR', $visitor === rwgc_geoip_resolve( array( 'REMOTE_ADDR' => $visitor ) ) );

rwgc_geoip_assert(
	'spoofed X-Forwarded-For ignored on public origin',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'              => $visitor,
			'HTTP_X_FORWARDED_FOR'     => $spoof,
			'HTTP_CLIENT_IP'           => $spoof,
			'HTTP_CF_CONNECTING_IP'    => $spoof,
			'HTTP_X_CLUSTER_CLIENT_IP' => $spoof,
			'HTTP_FORWARDED_FOR'       => $spoof,
			'HTTP_FORWARDED'           => 'for=' . $spoof,
			'HTTP_X_FORWARDED'         => $spoof,
			'HTTP_X_REAL_IP'           => $spoof,
			'HTTP_TRUE_CLIENT_IP'      => $spoof,
		)
	)
);

rwgc_geoip_assert(
	'spoofed CF-Connecting-IP ignored when peer is not Cloudflare',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $visitor,
			'HTTP_CF_CONNECTING_IP' => $spoof,
			'HTTP_CF_RAY'           => 'fake',
		)
	)
);

rwgc_geoip_assert(
	'Cloudflare edge ignores CF-Connecting-IP until Behind Cloudflare is on',
	$cf_edge === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => $cf_cli,
			'HTTP_CF_IPCOUNTRY'     => 'ZA',
			'HTTP_X_FORWARDED_FOR'  => $spoof,
		)
	)
);
rwgc_geoip_assert(
	'Cloudflare edge ignores Forwarded until Behind Cloudflare is on',
	$cf_edge === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'         => $cf_edge,
			'HTTP_FORWARDED'      => 'for=' . $cf_cli,
			'HTTP_X_FORWARDED'    => $cf_cli,
			'HTTP_X_REAL_IP'      => $cf_cli,
			'HTTP_TRUE_CLIENT_IP' => $cf_cli,
		)
	)
);
rwgc_geoip_assert(
	'spoofed CF-IPCountry ignored when Behind Cloudflare is off',
	'' === rwgc_geoip_country(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => $cf_cli,
			'HTTP_CF_IPCOUNTRY'     => 'ZA',
		)
	)
);

rwgc_geoip_assert( 'known Cloudflare IPv4', RWGC_GeoIP::is_cloudflare_ip( $cf_edge ) );
rwgc_geoip_assert( 'visitor IP is not Cloudflare', ! RWGC_GeoIP::is_cloudflare_ip( $visitor ) );
rwgc_geoip_assert( 'cidr v4 match', RWGC_GeoIP::ip_in_cidr( '104.16.0.1', '104.16.0.0/13' ) );
rwgc_geoip_assert( 'cidr v4 miss', ! RWGC_GeoIP::ip_in_cidr( '1.1.1.1', '104.16.0.0/13' ) );
rwgc_geoip_assert( 'cidr v6 match', RWGC_GeoIP::ip_in_cidr( '2606:4700::1', '2606:4700::/32' ) );
rwgc_geoip_assert( 'cidr /17 match', RWGC_GeoIP::ip_in_cidr( '198.41.128.1', '198.41.128.0/17' ) );
rwgc_geoip_assert( 'cidr /17 miss', ! RWGC_GeoIP::ip_in_cidr( '198.41.127.1', '198.41.128.0/17' ) );

rwgc_geoip_assert(
	'private origin ignores spoofed X-Forwarded-For',
	'10.0.0.2' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR'  => '102.132.0.1',
			'HTTP_X_REAL_IP'        => '102.132.0.1',
			'HTTP_TRUE_CLIENT_IP'   => '102.132.0.1',
			'HTTP_CLIENT_IP'        => $spoof,
			'HTTP_CF_CONNECTING_IP' => $spoof,
			'HTTP_CF_IPCOUNTRY'     => 'ZA',
			'HTTP_FORWARDED'        => 'for=102.132.0.1',
			'HTTP_X_FORWARDED'      => '102.132.0.1',
			'HTTP_FORWARDED_FOR'    => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'loopback ignores X-Forwarded-For 102.132.0.1',
	'127.0.0.1' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1',
			'HTTP_X_REAL_IP'       => '102.132.0.1',
			'HTTP_CF_IPCOUNTRY'    => 'ZA',
		)
	)
);
rwgc_geoip_assert(
	'loopback spoof does not yield a Cloudflare country',
	'' === rwgc_geoip_country(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1',
			'HTTP_CF_IPCOUNTRY'    => 'ZA',
		)
	)
);

rwgc_geoip_assert(
	'private origin with no XFF keeps REMOTE_ADDR',
	'10.0.0.2' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_CLIENT_IP'       => $spoof,
			'HTTP_CF_CONNECTING_IP'=> $spoof,
		)
	)
);

$quic_edge = '';
foreach ( RWGC_GeoIP::bundled_quic_cloud_ips() as $candidate ) {
	if ( is_string( $candidate ) && false === strpos( $candidate, ':' ) ) {
		$quic_edge = $candidate;
		break;
	}
}
rwgc_geoip_assert( 'bundled QUIC.cloud list has an IPv4 edge', '' !== $quic_edge );
rwgc_geoip_assert( 'bundled QUIC.cloud list is a real snapshot', count( RWGC_GeoIP::bundled_quic_cloud_ips() ) >= 10 );
rwgc_geoip_assert( 'visitor address is not a QUIC.cloud edge', ! RWGC_GeoIP::is_quic_cloud_ip( $visitor ) );
rwgc_geoip_assert( 'published edge matches the bundled list', RWGC_GeoIP::is_quic_cloud_ip( $quic_edge ) );
$quic_v6 = '';
foreach ( RWGC_GeoIP::bundled_quic_cloud_ips() as $candidate ) {
	if ( is_string( $candidate ) && false !== strpos( $candidate, ':' ) ) {
		$quic_v6 = $candidate;
		break;
	}
}
rwgc_geoip_assert( 'bundled QUIC.cloud list has an IPv6 edge', '' !== $quic_v6 && RWGC_GeoIP::is_quic_cloud_ip( $quic_v6 ) );
rwgc_geoip_assert( 'refresh without HTTP does not replace the list', false === RWGC_GeoIP::refresh_quic_cloud_ips() );
rwgc_geoip_assert( 'bad QUIC payload is ignored', array() === RWGC_GeoIP::parse_quic_cloud_ip_payload( 'nope' ) );
rwgc_geoip_assert(
	'json array payload parses',
	array( '203.0.113.9' ) === RWGC_GeoIP::parse_quic_cloud_ip_payload( array( '203.0.113.9', 'not-an-ip' ) )
);

rwgc_geoip_assert(
	'setting off ignores XFF from a QUIC.cloud peer',
	$quic_edge === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => $quic_edge,
			'HTTP_X_FORWARDED_FOR' => $spoof . ', ' . $visitor,
			'HTTP_CLIENT_IP'       => $spoof,
		)
	)
);

$GLOBALS['rwgc_test_quic'] = true;
rwgc_geoip_assert(
	'setting on uses the visitor hop from a QUIC.cloud peer',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $quic_edge,
			'HTTP_X_FORWARDED_FOR'  => $spoof . ', ' . $visitor,
			'HTTP_CF_CONNECTING_IP' => $spoof,
			'HTTP_CLIENT_IP'        => $spoof,
		)
	)
);
rwgc_geoip_assert(
	'setting on skips a QUIC.cloud hop appended after the visitor',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => $quic_edge,
			'HTTP_X_FORWARDED_FOR' => $visitor . ', ' . $quic_edge,
		)
	)
);
rwgc_geoip_assert(
	'setting on ignores XFF when the public peer is not QUIC.cloud',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $visitor,
			'HTTP_X_FORWARDED_FOR'  => $spoof,
			'HTTP_CF_CONNECTING_IP' => $spoof,
		)
	)
);
rwgc_geoip_assert(
	'QUIC on does not trust a loopback peer by itself',
	'127.0.0.1' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => $spoof . ', ' . $visitor . ', ' . $quic_edge,
			'HTTP_CLIENT_IP'       => $spoof,
		)
	)
);
rwgc_geoip_assert(
	'QUIC on does not trust a private peer by itself',
	'10.0.0.2' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, 198.51.100.7',
		)
	)
);
$GLOBALS['rwgc_test_trusted_cidrs'] = array( '127.0.0.1', '10.0.0.0/8' );
rwgc_geoip_assert(
	'trusted loopback with QUIC on skips the appended PoP',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, ' . $visitor . ', ' . $quic_edge,
			'HTTP_X_REAL_IP'       => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'trusted private proxy walks from the right and skips itself',
	'198.51.100.7' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, 198.51.100.7, 10.0.0.2',
		)
	)
);
$GLOBALS['rwgc_test_trusted_cidrs'] = null;
$GLOBALS['rwgc_test_quic'] = false;

rwgc_geoip_assert(
	'private origin without a trusted proxy ignores the rightmost public hop',
	'127.0.0.1' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => $visitor . ', ' . $quic_edge,
		)
	)
);

$GLOBALS['rwgc_test_trusted_cidrs'] = array( '198.51.100.0/24' );
rwgc_geoip_assert(
	'trusted proxy filter uses the visitor hop',
	'203.0.113.50' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '198.51.100.8',
			'HTTP_X_FORWARDED_FOR' => $spoof . ', 203.0.113.50',
		)
	)
);
rwgc_geoip_assert(
	'trusted proxy filter ignores a peer outside the range',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => $visitor,
			'HTTP_X_FORWARDED_FOR' => $spoof,
		)
	)
);
rwgc_geoip_assert(
	'unlisted private origin ignores a trusted hop in X-Forwarded-For',
	'10.0.0.2' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, 203.0.113.50, 198.51.100.8',
		)
	)
);
rwgc_geoip_assert(
	'trusted proxy skips its own hop and ignores the left-most spoof',
	'203.0.113.50' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => '198.51.100.8',
			'HTTP_X_FORWARDED_FOR'  => '102.132.0.1, 203.0.113.50, 198.51.100.9',
			'HTTP_X_REAL_IP'        => '102.132.0.1',
			'HTTP_TRUE_CLIENT_IP'   => '102.132.0.1',
			'HTTP_CF_CONNECTING_IP' => '102.132.0.1',
			'HTTP_CF_IPCOUNTRY'     => 'ZA',
		)
	)
);
rwgc_geoip_assert(
	'trusted proxy uses X-Real-IP only when X-Forwarded-For is absent',
	'203.0.113.60' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'    => '198.51.100.8',
			'HTTP_X_REAL_IP' => '203.0.113.60',
		)
	)
);
rwgc_geoip_assert(
	'trusted proxy ignores a comma list in X-Real-IP',
	'198.51.100.8' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'    => '198.51.100.8',
			'HTTP_X_REAL_IP' => '102.132.0.1, 203.0.113.60',
		)
	)
);
rwgc_geoip_assert(
	'trusted proxy uses True-Client-IP when no other forwarding header is set',
	'203.0.113.61' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'         => '198.51.100.8',
			'HTTP_TRUE_CLIENT_IP' => '203.0.113.61',
		)
	)
);
rwgc_geoip_assert(
	'Forwarded cannot replace a proxy-set X-Real-IP',
	'203.0.113.60' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'      => '198.51.100.8',
			'HTTP_X_REAL_IP'   => '203.0.113.60',
			'HTTP_FORWARDED'   => 'for=102.132.0.1',
			'HTTP_X_FORWARDED' => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'X-Forwarded cannot replace a proxy-set True-Client-IP',
	'203.0.113.61' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'         => '198.51.100.8',
			'HTTP_TRUE_CLIENT_IP' => '203.0.113.61',
			'HTTP_X_FORWARDED'    => '102.132.0.1',
			'HTTP_FORWARDED'      => 'for=102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'Forwarded is used when the proxy did not set X-Forwarded-For or X-Real-IP',
	'203.0.113.70' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'    => '198.51.100.8',
			'HTTP_FORWARDED' => 'for=102.132.0.1, for=203.0.113.70',
		)
	)
);
rwgc_geoip_assert(
	'Forwarded parameters do not replace a proxy-set X-Real-IP',
	'203.0.113.60' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'    => '198.51.100.8',
			'HTTP_X_REAL_IP' => '203.0.113.60',
			'HTTP_FORWARDED' => 'proto=https;for="102.132.0.1";by=198.51.100.8',
		)
	)
);
rwgc_geoip_assert(
	'a comma-list X-Real-IP is not replaced by Forwarded',
	'198.51.100.8' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'      => '198.51.100.8',
			'HTTP_X_REAL_IP'   => '203.0.113.60, 102.132.0.1',
			'HTTP_FORWARDED'   => 'for=102.132.0.1',
			'HTTP_X_FORWARDED' => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'X-Forwarded cannot replace an X-Real-IP that is the proxy itself',
	'198.51.100.8' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'      => '198.51.100.8',
			'HTTP_X_REAL_IP'   => '198.51.100.8',
			'HTTP_X_FORWARDED' => '102.132.0.1',
			'HTTP_FORWARDED'   => 'for=102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'Forwarded cannot replace a private True-Client-IP',
	'198.51.100.8' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'         => '198.51.100.8',
			'HTTP_TRUE_CLIENT_IP' => '10.1.2.3',
			'HTTP_FORWARDED'      => 'for=102.132.0.1;proto=https',
			'HTTP_X_FORWARDED'    => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'X-Forwarded-For still wins over a client Forwarded header',
	'203.0.113.50' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '198.51.100.8',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, 203.0.113.50',
			'HTTP_FORWARDED'       => 'for=102.132.0.1',
			'HTTP_X_FORWARDED'     => '102.132.0.1',
			'HTTP_X_REAL_IP'       => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'X-Forwarded is used when no other forwarding header is set',
	'203.0.113.80' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'      => '198.51.100.8',
			'HTTP_X_FORWARDED' => '102.132.0.1, 203.0.113.80',
		)
	)
);
rwgc_geoip_assert(
	'Forwarded with parameters is used when no other forwarding header is set',
	'203.0.113.70' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'    => '198.51.100.8',
			'HTTP_FORWARDED' => 'for=102.132.0.1;proto=http, for="203.0.113.70";proto=https',
		)
	)
);
rwgc_geoip_assert(
	'Client-IP, Forwarded-For, and X-Cluster-Client-IP are not visitor addresses',
	'198.51.100.8' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'              => '198.51.100.8',
			'HTTP_CLIENT_IP'           => '102.132.0.1',
			'HTTP_FORWARDED_FOR'       => '102.132.0.1',
			'HTTP_X_CLUSTER_CLIENT_IP' => '102.132.0.1',
		)
	)
);
$GLOBALS['rwgc_test_trusted_cidrs'] = null;

$GLOBALS['rwgc_test_cloudflare'] = true;
rwgc_geoip_assert(
	'Behind Cloudflare uses CF-Connecting-IP and ignores a spoofed X-Forwarded-For',
	$cf_cli === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => $cf_cli,
			'HTTP_X_FORWARDED_FOR'  => '102.132.0.1',
			'HTTP_X_REAL_IP'        => '102.132.0.1',
			'HTTP_TRUE_CLIENT_IP'   => '102.132.0.1',
			'HTTP_FORWARDED'        => 'for=102.132.0.1',
			'HTTP_X_FORWARDED'      => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'Behind Cloudflare does not fall through from a bad CF-Connecting-IP to Forwarded',
	$cf_edge === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
			'HTTP_FORWARDED'        => 'for=102.132.0.1',
			'HTTP_X_FORWARDED'      => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'Behind Cloudflare still walks X-Forwarded-For when CF-Connecting-IP is not a public address',
	$cf_cli === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => 'not-an-ip',
			'HTTP_X_FORWARDED_FOR'  => '102.132.0.1, ' . $cf_cli,
			'HTTP_FORWARDED'        => 'for=102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'Behind Cloudflare trusts CF-IPCountry from a Cloudflare peer',
	'ZA' === rwgc_geoip_country(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => $cf_cli,
			'HTTP_CF_IPCOUNTRY'     => 'ZA',
		)
	)
);
rwgc_geoip_assert(
	'Behind Cloudflare ignores CF-IPCountry when the peer is not Cloudflare',
	'' === rwgc_geoip_country(
		array(
			'REMOTE_ADDR'       => $visitor,
			'HTTP_CF_IPCOUNTRY' => 'ZA',
		)
	)
);
rwgc_geoip_assert(
	'Behind Cloudflare ignores CF-Connecting-IP from a non-Cloudflare peer',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $visitor,
			'HTTP_CF_CONNECTING_IP' => '102.132.0.1',
			'HTTP_CF_IPCOUNTRY'     => 'ZA',
			'HTTP_X_FORWARDED_FOR'  => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'Behind Cloudflare ignores CF-IPCountry XX',
	'' === rwgc_geoip_country(
		array(
			'REMOTE_ADDR'       => $cf_edge,
			'HTTP_CF_IPCOUNTRY' => 'XX',
		)
	)
);
rwgc_geoip_assert(
	'Behind Cloudflare skips a Cloudflare hop and does not take the left-most spoof',
	$cf_cli === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => $cf_edge,
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, ' . $cf_cli . ', ' . $cf_edge,
		)
	)
);
$GLOBALS['rwgc_test_cf_cidrs'] = array( '203.0.113.0/24' );
rwgc_geoip_assert( 'Cloudflare range filter can add a range', RWGC_GeoIP::is_cloudflare_ip( '203.0.113.9' ) );
rwgc_geoip_assert( 'Cloudflare range filter replaces the bundled list', ! RWGC_GeoIP::is_cloudflare_ip( $cf_edge ) );
rwgc_geoip_assert(
	'filtered Cloudflare range trusts CF-Connecting-IP for that peer only',
	'198.51.100.40' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => '203.0.113.9',
			'HTTP_CF_CONNECTING_IP' => '198.51.100.40',
			'HTTP_X_FORWARDED_FOR'  => '102.132.0.1',
		)
	)
);
$GLOBALS['rwgc_test_cloudflare'] = false;
rwgc_geoip_assert(
	'turning Behind Cloudflare off stops trusting the filtered range',
	'203.0.113.9' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => '203.0.113.9',
			'HTTP_CF_CONNECTING_IP' => '198.51.100.40',
			'HTTP_CF_IPCOUNTRY'     => 'ZA',
		)
	)
);
rwgc_geoip_assert(
	'filtered range does not supply a country while Behind Cloudflare is off',
	'' === rwgc_geoip_country(
		array(
			'REMOTE_ADDR'       => '203.0.113.9',
			'HTTP_CF_IPCOUNTRY' => 'ZA',
		)
	)
);
$GLOBALS['rwgc_test_cf_cidrs'] = null;

$GLOBALS['rwgc_test_trust_override'] = true;
rwgc_geoip_assert(
	'rwgc_is_trusted_proxy can trust a peer that is not in the proxy list',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '198.51.100.8',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, ' . $visitor,
		)
	)
);
$GLOBALS['rwgc_test_trust_override'] = false;
$GLOBALS['rwgc_test_trusted_cidrs'] = array( '198.51.100.0/24' );
rwgc_geoip_assert(
	'rwgc_is_trusted_proxy can refuse a peer that is in the proxy list',
	'198.51.100.8' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '198.51.100.8',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1, ' . $visitor,
		)
	)
);
$GLOBALS['rwgc_test_trusted_cidrs'] = null;
unset( $GLOBALS['rwgc_test_trust_override'] );

$GLOBALS['rwgc_test_visitor_ip'] = '198.51.100.77';
rwgc_geoip_assert(
	'rwgc_visitor_ip filter still wins',
	'198.51.100.77' === rwgc_geoip_resolve( array( 'REMOTE_ADDR' => $visitor ) )
);
unset( $GLOBALS['rwgc_test_visitor_ip'] );

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str Value.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * @param string $text          Text.
	 * @param bool   $remove_breaks Collapse breaks.
	 * @return string
	 */
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = strip_tags( (string) $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( (string) $text );
	}
}
require_once dirname( __DIR__ ) . '/includes/class-rwgc-settings.php';
$quic_defaults = RWGC_Settings::get_defaults();
rwgc_geoip_assert( 'quic setting defaults off', isset( $quic_defaults['quic_cloud_cdn'] ) && 0 === (int) $quic_defaults['quic_cloud_cdn'] );
$quic_on = RWGC_Settings::sanitize_settings(
	array(
		'enabled'        => '1',
		'quic_cloud_cdn' => '1',
	)
);
rwgc_geoip_assert( 'quic setting sanitizes on', 1 === (int) $quic_on['quic_cloud_cdn'] );
$quic_again = RWGC_Settings::sanitize_settings( $quic_on );
rwgc_geoip_assert( 'quic setting persists through sanitize', 1 === (int) $quic_again['quic_cloud_cdn'] );
$quic_off = RWGC_Settings::sanitize_settings( array( 'enabled' => '1' ) );
rwgc_geoip_assert( 'unchecked quic setting sanitizes off', 0 === (int) $quic_off['quic_cloud_cdn'] );
rwgc_geoip_assert( 'cloudflare setting defaults off', isset( $quic_defaults['behind_cloudflare'] ) && 0 === (int) $quic_defaults['behind_cloudflare'] );
rwgc_geoip_assert( 'trusted proxies default empty', isset( $quic_defaults['trusted_proxies'] ) && '' === $quic_defaults['trusted_proxies'] );
$proxy_saved = RWGC_Settings::sanitize_settings(
	array(
		'enabled'           => '1',
		'behind_cloudflare' => '1',
		'trusted_proxies'   => "127.0.0.1\n10.0.0.0/8\nnot-an-ip\n198.51.100.8",
	)
);
rwgc_geoip_assert( 'cloudflare setting sanitizes on', 1 === (int) $proxy_saved['behind_cloudflare'] );
rwgc_geoip_assert(
	'trusted proxies keep IP and CIDR lines',
	"127.0.0.1\n10.0.0.0/8\n198.51.100.8" === $proxy_saved['trusted_proxies']
);
$proxy_off = RWGC_Settings::sanitize_settings(
	array(
		'enabled'           => '1',
		'behind_cloudflare' => '0',
	)
);
rwgc_geoip_assert( 'unchecked cloudflare setting sanitizes off', 0 === (int) $proxy_off['behind_cloudflare'] );
$proxy_partial = RWGC_Settings::sanitize_settings( array( 'enabled' => '1' ) );
rwgc_geoip_assert( 'partial save does not invent an explicit cloudflare off', ! array_key_exists( 'behind_cloudflare', $proxy_partial ) );

unset( $GLOBALS['rwgc_test_cloudflare'] );
$GLOBALS['rwgc_test_options'] = array(
	'rwgc_settings' => array(
		'enabled' => 1,
	),
);
rwgc_geoip_assert( 'missing cloudflare key is legacy trust', 'legacy' === RWGC_GeoIP::cloudflare_mode() );
rwgc_geoip_assert( 'legacy trust is active before the setting is saved', RWGC_GeoIP::cloudflare_trust_active() );
$legacy_merged = RWGC_Settings::get_settings();
rwgc_geoip_assert( 'merged settings keep legacy cloudflare unset', ! array_key_exists( 'behind_cloudflare', $legacy_merged ) );
$legacy_resaved = RWGC_Settings::sanitize_settings( $legacy_merged );
rwgc_geoip_assert( 'resaving other settings keeps legacy cloudflare unset', ! array_key_exists( 'behind_cloudflare', $legacy_resaved ) );
rwgc_geoip_assert(
	'legacy trust uses CF-Connecting-IP from a Cloudflare peer',
	$cf_cli === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => $cf_cli,
			'HTTP_X_FORWARDED_FOR'  => '102.132.0.1',
			'HTTP_FORWARDED'        => 'for=102.132.0.1',
			'HTTP_X_FORWARDED'      => '102.132.0.1',
		)
	)
);
rwgc_geoip_assert(
	'legacy trust reads CF-IPCountry',
	'ZA' === rwgc_geoip_country(
		array(
			'REMOTE_ADDR'       => $cf_edge,
			'HTTP_CF_IPCOUNTRY' => 'ZA',
		)
	)
);
rwgc_geoip_assert(
	'legacy trust still ignores a spoof from a private address',
	'127.0.0.1' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '102.132.0.1',
			'HTTP_CF_IPCOUNTRY'    => 'ZA',
		)
	)
);
$GLOBALS['rwgc_test_options']['rwgc_settings']['behind_cloudflare'] = 0;
rwgc_geoip_assert( 'saved cloudflare off is explicit', 'off' === RWGC_GeoIP::cloudflare_mode() );
rwgc_geoip_assert( 'explicit off is not active', ! RWGC_GeoIP::cloudflare_trust_active() );
$GLOBALS['rwgc_test_options']['rwgc_settings']['behind_cloudflare'] = 1;
rwgc_geoip_assert( 'saved cloudflare on is explicit', 'on' === RWGC_GeoIP::cloudflare_mode() );
$GLOBALS['rwgc_test_options'] = array( 'rwgc_settings' => array() );
rwgc_geoip_assert( 'empty settings are not legacy', 'off' === RWGC_GeoIP::cloudflare_mode() );
unset( $GLOBALS['rwgc_test_options'] );

$settings_page = (string) file_get_contents( dirname( __DIR__ ) . '/admin/views/settings-page.php' );
$settings_css  = (string) file_get_contents( dirname( __DIR__ ) . '/admin/css/admin.css' );
rwgc_geoip_assert( 'settings screen labels the QUIC.cloud checkbox', false !== strpos( $settings_page, 'My site uses QUIC.cloud CDN' ) );
rwgc_geoip_assert( 'settings screen saves quic_cloud_cdn', false !== strpos( $settings_page, '[quic_cloud_cdn]' ) );
rwgc_geoip_assert( 'settings screen labels Behind Cloudflare', false !== strpos( $settings_page, 'Behind Cloudflare' ) );
rwgc_geoip_assert( 'settings screen saves behind_cloudflare', false !== strpos( $settings_page, '[behind_cloudflare]' ) );
rwgc_geoip_assert( 'settings screen posts an explicit cloudflare off', false !== strpos( $settings_page, 'type="hidden"' ) && false !== strpos( $settings_page, '[behind_cloudflare]" value="0"' ) );
rwgc_geoip_assert( 'settings screen saves trusted_proxies', false !== strpos( $settings_page, '[trusted_proxies]' ) );
rwgc_geoip_assert( 'settings screen uses the mobile settings class', false !== strpos( $settings_page, 'rwgc-settings-screen' ) );
rwgc_geoip_assert( 'settings stylesheet stacks the form on a phone', false !== strpos( $settings_css, '.rwgc-settings-screen' ) && false !== strpos( $settings_css, 'max-width: 782px' ) );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll GeoIP IP tests passed.\n";
exit( 0 );
