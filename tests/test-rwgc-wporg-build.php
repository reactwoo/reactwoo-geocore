<?php
/**
 * WordPress.org build guards (source-level).
 *
 * Usage: php tests/test-rwgc-wporg-build.php
 */

$root = dirname( __DIR__ );
$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok    Result.
 * @return void
 */
function rwgc_wporg_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "ok  - $label\n";
		return;
	}
	echo "FAIL - $label\n";
	++$failed;
}

$main = (string) file_get_contents( $root . '/reactwoo-geocore.php' );
$plugin = (string) file_get_contents( $root . '/includes/class-rwgc-plugin.php' );
$functions = (string) file_get_contents( $root . '/includes/functions-rwgc.php' );
$telemetry = (string) file_get_contents( $root . '/includes/cloud/class-rwgc-cloud-telemetry.php' );
$schema = (string) file_get_contents( $root . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php' );
$readme = (string) file_get_contents( $root . '/readme.txt' );
$distignore = (string) file_get_contents( $root . '/.distignore' );
$package = (string) file_get_contents( $root . '/scripts/package_zip.py' );

rwgc_wporg_assert( 'header Requires at least', false !== strpos( $main, 'Requires at least: 6.2' ) );
rwgc_wporg_assert( 'header Requires PHP 8.1', false !== strpos( $main, 'Requires PHP: 8.1' ) );
rwgc_wporg_assert( 'default distribution is reactwoo', false !== strpos( $main, "define( 'RWGC_DISTRIBUTION', 'reactwoo' );" ) );
rwgc_wporg_assert( 'helper detects wporg distribution', false !== strpos( $functions, 'function rwgc_is_wordpress_org_distribution()' ) );
rwgc_wporg_assert( 'plugin skips updater on wporg', false !== strpos( $plugin, 'rwgc_is_wordpress_org_distribution()' ) );
rwgc_wporg_assert( 'updater file still in the tree for the ReactWoo channel', is_file( $root . '/includes/class-rwgc-satellite-updater.php' ) );
rwgc_wporg_assert( 'telemetry option defaults through get_option false', false !== strpos( $telemetry, "get_option( self::OPT_IN_OPTION, false )" ) );
rwgc_wporg_assert( 'telemetry filter no longer defaults to true', false === strpos( $telemetry, "apply_filters( 'rwgc_cloud_telemetry_allowed', true )" ) );

if ( preg_match( '/const FREE_CONDITION_TYPES = array\((.*?)\);/s', $schema, $free_match )
	&& preg_match( '/const PRO_CONDITION_TYPES = array\((.*?)\);/s', $schema, $pro_match ) ) {
	$free = $free_match[1];
	$pro  = $pro_match[1];
	foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'campaign' ) as $type ) {
		rwgc_wporg_assert( $type . ' is a free condition', false !== strpos( $free, "'" . $type . "'" ) );
		rwgc_wporg_assert( $type . ' is not pro-gated', false === strpos( $pro, "'" . $type . "'" ) );
	}
	rwgc_wporg_assert( 'audience stays a pro add-on condition', false !== strpos( $pro, "'audience'" ) );
	rwgc_wporg_assert( 'weather stays a pro add-on condition', false !== strpos( $pro, "'weather_facet'" ) );
} else {
	rwgc_wporg_assert( 'condition type lists found', false );
}

rwgc_wporg_assert( 'readme external services', false !== strpos( $readme, '== External services ==' ) );
rwgc_wporg_assert( 'readme tested up to 7.1', false !== strpos( $readme, 'Tested up to: 7.1' ) );
rwgc_wporg_assert( 'readme stable tag matches header', false !== strpos( $readme, 'Stable tag: 1.9.0' ) && false !== strpos( $main, 'Version: 1.9.0' ) );
rwgc_wporg_assert( 'version constant is 1.9.0', false !== strpos( $main, "define( 'RWGC_VERSION', '1.9.0' );" ) );
rwgc_wporg_assert( 'readme upgrade notice', false !== strpos( $readme, '== Upgrade Notice ==' ) && false !== strpos( $readme, 'Requires PHP 8.1' ) );
rwgc_wporg_assert( 'updater stub exists', is_file( $root . '/includes/class-rwgc-satellite-updater-stub.php' ) );
rwgc_wporg_assert( 'wporg build loads the updater stub', false !== strpos( $plugin, 'class-rwgc-satellite-updater-stub.php' ) );
rwgc_wporg_assert( 'distignore drops directory assets', false !== strpos( $distignore, '.wordpress-org' ) );
rwgc_wporg_assert( 'readme requires PHP 8.1', false !== strpos( $readme, 'Requires PHP: 8.1' ) );
rwgc_wporg_assert( 'readme discloses MaxMind', false !== strpos( $readme, 'download.maxmind.com' ) );
rwgc_wporg_assert( 'readme discloses QUIC.cloud', false !== strpos( $readme, 'quic.cloud' ) );
rwgc_wporg_assert( 'readme discloses api.reactwoo.com', false !== strpos( $readme, 'api.reactwoo.com' ) );
rwgc_wporg_assert( 'readme discloses decision.reactwoo.com', false !== strpos( $readme, 'decision.reactwoo.com' ) );
rwgc_wporg_assert( 'distignore drops the updater', false !== strpos( $distignore, 'includes/class-rwgc-satellite-updater.php' ) );
rwgc_wporg_assert( 'distignore drops docs', (bool) preg_match( '/^docs$/m', $distignore ) );
rwgc_wporg_assert( 'distignore drops vendor dev-bin', false !== strpos( $distignore, 'vendor/**/dev-bin' ) );
rwgc_wporg_assert( 'distignore drops shell scripts', (bool) preg_match( '/^\*\.sh$/m', $distignore ) );
rwgc_wporg_assert( 'packager has a wporg target', false !== strpos( $package, 'wporg' ) );
rwgc_wporg_assert( 'packager refuses prohibited archive names', false !== strpos( $package, 'def wporg_prohibited_names' ) );

$short = '';
if ( preg_match( '/^License URI:.*\R\R(.+)\R/m', $readme, $short_match ) ) {
	$short = trim( $short_match[1] );
}
rwgc_wporg_assert( 'short description within 150 characters (' . strlen( $short ) . ')', strlen( $short ) > 0 && strlen( $short ) <= 150 );
$block_json = (string) file_get_contents( $root . '/blocks/geo-content/block.json' );
$block_asset = (string) file_get_contents( $root . '/blocks/geo-content/index.asset.php' );
rwgc_wporg_assert( 'geo content editor script is a file', false !== strpos( $block_json, '"editorScript": "file:./index.js"' ) );
rwgc_wporg_assert( 'geo content editor handle is registered from the asset file', false !== strpos( $block_asset, "'rwgc-geo-content-editor'" ) );
$admin = (string) file_get_contents( $root . '/includes/class-rwgc-admin.php' );
rwgc_wporg_assert( 'license-key warning is not the only MaxMind notice', false === strpos( $admin, 'MaxMind license key is not configured' ) );
rwgc_wporg_assert( 'missing database notice uses the notice code', false !== strpos( $admin, 'admin_notice_code' ) );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}

echo "\nAll WordPress.org build guards passed.\n";
exit( 0 );
