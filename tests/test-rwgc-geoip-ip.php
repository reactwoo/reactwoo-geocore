<?php
/**
 * Visitor IP resolution — ignore client-spoofed forwarding headers.
 *
 * Usage: php tests/test-rwgc-geoip-ip.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		unset( $hook );
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

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll GeoIP IP tests passed.\n";
exit( 0 );
