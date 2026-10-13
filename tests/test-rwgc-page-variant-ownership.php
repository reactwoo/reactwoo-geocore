<?php
/**
 * Page-variant ownership guards (source-level).
 *
 * Usage: php tests/test-rwgc-page-variant-ownership.php
 */

$root   = dirname( __DIR__ );
$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok    Result.
 * @return void
 */
function rwgc_variant_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "ok  - $label\n";
		return;
	}
	echo "FAIL - $label\n";
	++$failed;
}

$main       = (string) file_get_contents( $root . '/reactwoo-geocore.php' );
$plugin     = (string) file_get_contents( $root . '/includes/class-rwgc-plugin.php' );
$functions  = (string) file_get_contents( $root . '/includes/functions-rwgc.php' );
$caps       = (string) file_get_contents( $root . '/includes/platform/class-rwgc-platform-capabilities-bootstrap.php' );
$routing    = (string) file_get_contents( $root . '/includes/class-rwgc-routing.php' );
$admin      = (string) file_get_contents( $root . '/includes/class-rwgc-admin.php' );
$manager    = (string) file_get_contents( $root . '/includes/class-rwgc-variant-manager.php' );
$elementor  = (string) file_get_contents( $root . '/includes/class-rwgc-elementor.php' );
$rest       = (string) file_get_contents( $root . '/includes/class-rwgc-rest.php' );

rwgc_variant_assert( 'page variants default off', false !== strpos( $functions, 'function rwgc_page_variants_available()' ) );
rwgc_variant_assert( 'country visibility is a core action', false !== strpos( $caps, "'geo.country_visibility'" ) );
rwgc_variant_assert( 'core does not register page.version_url', false === strpos( $caps, "'page.version_url'" ) );
rwgc_variant_assert( 'wporg skips early page-version bootstrap', false !== strpos( $main, "'wporg' !== RWGC_DISTRIBUTION" ) );
rwgc_variant_assert( 'wporg skips routing init', false !== strpos( $plugin, 'rwgc_is_wordpress_org_distribution()' ) );
rwgc_variant_assert( 'save path does not apply a one-variant cap', false === strpos( $routing, 'master_has_variant( $out[' ) );
rwgc_variant_assert( 'admin no longer tells people to upgrade to GeoElementor for variants', false === strpos( $admin, 'Upgrade to GeoElementor' ) );
rwgc_variant_assert( 'variant manager has no GeoElementor upgrade', false === strpos( $manager, 'GeoElementor' ) );
rwgc_variant_assert( 'elementor has no one-variant limit note', false === strpos( $elementor, 'Free limit:' ) );
rwgc_variant_assert( 'capabilities payload reports page_variants', false !== strpos( $rest, "'page_variants'" ) );

if ( $failed > 0 ) {
	fwrite( STDERR, $failed . " assertion(s) failed\n" );
	exit( 1 );
}

echo "page-variant ownership checks passed\n";
exit( 0 );
