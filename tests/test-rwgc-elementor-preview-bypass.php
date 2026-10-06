<?php
/**
 * Regression: an unauthenticated ?elementor-preview request must not bypass
 * Elementor document geo rules (ported from PR #16, elementor-preview hunk).
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', __DIR__ );

$fails = 0;

/**
 * @param bool   $cond Assertion.
 * @param string $msg  Description.
 * @return void
 */
function rwgc_assert( $cond, $msg ) {
	global $fails;
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		++$fails;
		return;
	}
	fwrite( STDOUT, "OK: {$msg}\n" );
}

$GLOBALS['rwgc_test_logged_in'] = false;

function is_user_logged_in() {
	return ! empty( $GLOBALS['rwgc_test_logged_in'] );
}
function apply_filters( $hook, $value, ...$args ) {
	unset( $hook, $args );
	return $value;
}
function add_action( ...$args ) {
	unset( $args );
}
function add_filter( ...$args ) {
	unset( $args );
}

$src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgc-elementor.php' );
$at  = strpos( $src, 'function filter_document_content' );
rwgc_assert( false !== $at, 'filter_document_content is present' );
$end  = strpos( $src, "\n\t}\n", $at );
$body = substr( $src, $at, false === $end ? 4000 : $end - $at );
rwgc_assert( false === strpos( $body, "isset( \$_GET['elementor-preview'] )" ), 'document filter no longer trusts a bare ?elementor-preview' );
rwgc_assert( false !== strpos( $body, 'rwgc_is_builder_edit_request()' ), 'document filter uses the capability-checked builder bypass' );

require_once dirname( __DIR__ ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-routing.php';

$_GET['elementor-preview'] = '123';
rwgc_assert( false === rwgc_is_builder_edit_request(), 'logged-out visitor with ?elementor-preview is not a builder request' );

exit( $fails > 0 ? 1 : 0 );
