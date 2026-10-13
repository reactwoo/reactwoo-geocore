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

$main      = (string) file_get_contents( $root . '/reactwoo-geocore.php' );
$plugin    = (string) file_get_contents( $root . '/includes/class-rwgc-plugin.php' );
$functions = (string) file_get_contents( $root . '/includes/functions-rwgc.php' );
$caps      = (string) file_get_contents( $root . '/includes/platform/class-rwgc-platform-capabilities-bootstrap.php' );
$admin     = (string) file_get_contents( $root . '/includes/class-rwgc-admin.php' );
$elementor = (string) file_get_contents( $root . '/includes/class-rwgc-elementor.php' );
$rest      = (string) file_get_contents( $root . '/includes/class-rwgc-rest.php' );

$moved = array(
	'includes/class-rwgc-routing.php',
	'includes/class-rwgc-variant-manager.php',
	'includes/targeting/class-rwgc-page-version.php',
	'includes/targeting/class-rwgc-page-version-routing.php',
	'includes/engine/class-rwgc-variant.php',
	'includes/engine/class-rwgc-page-route-resolver.php',
	'includes/migration/class-rwgc-legacy-route-mapper.php',
	'admin/views/workflow-create-variant.php',
	'admin/views/targeting-variants-page.php',
);

foreach ( $moved as $relative ) {
	rwgc_variant_assert( 'core zip does not contain ' . $relative, ! is_file( $root . '/' . $relative ) );
}

rwgc_variant_assert( 'page variants default off', false !== strpos( $functions, 'function rwgc_page_variants_available()' ) );
rwgc_variant_assert( 'country visibility is a core action', false !== strpos( $caps, "'geo.country_visibility'" ) );
rwgc_variant_assert( 'core does not register page.version_url', false === strpos( $caps, "'page.version_url'" ) );
rwgc_variant_assert( 'core does not boot page-version rewrites', false === strpos( $main, 'RWGC_Page_Version_Routing' ) );
rwgc_variant_assert( 'core does not init the page router', false === strpos( $plugin, 'RWGC_Routing::init()' ) );
rwgc_variant_assert( 'admin has no page-version meta box', false === strpos( $admin, 'function render_page_meta_box' ) );
rwgc_variant_assert( 'admin no longer tells people to upgrade to GeoElementor for variants', false === strpos( $admin, 'Upgrade to GeoElementor' ) );
rwgc_variant_assert( 'elementor has no page-version controls', false === strpos( $elementor, 'rwgc_route_enabled' ) );
rwgc_variant_assert( 'elementor leaves a hook for Pro', false !== strpos( $elementor, 'rwgc_elementor_page_version_controls' ) );
rwgc_variant_assert( 'capabilities payload reports page_variants', false !== strpos( $rest, "'page_variants'" ) );

if ( $failed > 0 ) {
	fwrite( STDERR, $failed . " assertion(s) failed\n" );
	exit( 1 );
}

echo "page-variant ownership checks passed\n";
exit( 0 );
