<?php
/**
 * Visitor IP resolution — ignore client-spoofed forwarding headers.
 *
 * Usage: php tests/test-rwgc-geoip-ip.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

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
	if ( 'rwgc_trusted_proxy_cidrs' === $hook && isset( $GLOBALS['rwgc_test_trusted_cidrs'] ) && is_array( $GLOBALS['rwgc_test_trusted_cidrs'] ) ) {
		return $GLOBALS['rwgc_test_trusted_cidrs'];
	}
	if ( 'rwgc_visitor_ip' === $hook && isset( $GLOBALS['rwgc_test_visitor_ip'] ) ) {
		return $GLOBALS['rwgc_test_visitor_ip'];
	}
	return $value;
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
	'Cloudflare edge uses CF-Connecting-IP',
	$cf_cli === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'           => $cf_edge,
			'HTTP_CF_CONNECTING_IP' => $cf_cli,
			'HTTP_X_FORWARDED_FOR'  => $spoof,
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
	'private origin uses rightmost public XFF (proxy-appended)',
	'198.51.100.7' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR' => $spoof . ', 198.51.100.7',
			'HTTP_CLIENT_IP'       => $spoof,
			'HTTP_CF_CONNECTING_IP'=> $spoof,
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
	'private origin with QUIC on skips the appended PoP',
	$visitor === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => $spoof . ', ' . $visitor . ', ' . $quic_edge,
			'HTTP_CLIENT_IP'       => $spoof,
		)
	)
);
rwgc_geoip_assert(
	'private origin with QUIC on still uses a proxy-appended visitor',
	'198.51.100.7' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR' => $spoof . ', 198.51.100.7',
		)
	)
);
rwgc_geoip_assert(
	'private origin falls back to the PoP when it is the only public hop',
	$quic_edge === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => $quic_edge,
		)
	)
);
$GLOBALS['rwgc_test_quic'] = false;

rwgc_geoip_assert(
	'private origin without QUIC still uses the rightmost public hop',
	$quic_edge === rwgc_geoip_resolve(
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
	'private origin skips a trusted hop appended after the visitor',
	'203.0.113.50' === rwgc_geoip_resolve(
		array(
			'REMOTE_ADDR'          => '10.0.0.2',
			'HTTP_X_FORWARDED_FOR' => $spoof . ', 203.0.113.50, 198.51.100.8',
		)
	)
);
$GLOBALS['rwgc_test_trusted_cidrs'] = null;

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

$settings_page = (string) file_get_contents( dirname( __DIR__ ) . '/admin/views/settings-page.php' );
rwgc_geoip_assert( 'settings screen labels the QUIC.cloud checkbox', false !== strpos( $settings_page, 'My site uses QUIC.cloud CDN' ) );
rwgc_geoip_assert( 'settings screen saves quic_cloud_cdn', false !== strpos( $settings_page, '[quic_cloud_cdn]' ) );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll GeoIP IP tests passed.\n";
exit( 0 );
