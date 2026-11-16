<?php
/**
 * Reproduce the classic-theme cookie bug with PHP output buffering off.
 *
 * Usage: php -d output_buffering=0 tests/AB/rwgc-ab-unbuffered-cookie.php leak|buffer
 *
 * `leak` echoes a theme header before assignment. headers_sent() is true and
 * setcookie() does not store rwgc_ab.
 * `buffer` opens the template buffer first, then echoes the header. The cookie
 * is sent and the next request keeps the same arm. The header HTML is flushed.
 *
 * @package ReactWoo_Geo_Core
 */

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "cli only\n" );
	exit( 1 );
}

$root = dirname( __DIR__, 2 );
require $root . '/tests/bootstrap.php';
require $root . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
require $root . '/includes/ab/class-rwgc-ab-assignment.php';

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * @param string $scheme Scheme.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		unset( $scheme );
		return 'rwgc-ab-test-salt';
	}
}

$mode = isset( $argv[1] ) ? (string) $argv[1] : '';
$ob   = strtolower( (string) ini_get( 'output_buffering' ) );
if ( ! in_array( $ob, array( '0', 'off', '' ), true ) ) {
	fwrite( STDERR, "output_buffering={$ob}\n" );
	exit( 1 );
}
if ( 0 !== ob_get_level() ) {
	fwrite( STDERR, 'ob level ' . ob_get_level() . "\n" );
	exit( 1 );
}

$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
$GLOBALS['rwgc_test_is_admin'] = false;
$GLOBALS['rwgc_test_filters']['rwgc_ab_running_test_ids'] = static function () {
	return array( 42 );
};

if ( 'leak' === $mode ) {
	echo '<header>theme</header>';
	$headers = headers_sent() ? '1' : '0';
	RWGC_AB_Assignment::variant( 42 );
	$sent = RWGC_AB_Assignment::cookie_was_sent() ? '1' : '0';
	fwrite( STDERR, "LEAK headers={$headers} cookie_sent={$sent}\n" );
	exit( ( '1' === $headers && '0' === $sent ) ? 0 : 1 );
}

if ( 'buffer' !== $mode ) {
	fwrite( STDERR, "mode\n" );
	exit( 1 );
}

if ( headers_sent() || ! RWGC_AB_Assignment::should_buffer_template() ) {
	fwrite( STDERR, "buffer was not eligible\n" );
	exit( 1 );
}

RWGC_AB_Assignment::buffer_template();
if ( 1 !== ob_get_level() ) {
	fwrite( STDERR, 'buffer level ' . ob_get_level() . "\n" );
	exit( 1 );
}

echo '<header>theme</header>';
if ( headers_sent() ) {
	fwrite( STDERR, "header escaped the buffer\n" );
	exit( 1 );
}

$arm = RWGC_AB_Assignment::variant( 42 );
if ( ! in_array( $arm, array( 'a', 'b' ), true ) || ! RWGC_AB_Assignment::cookie_was_sent() ) {
	fwrite( STDERR, "cookie not sent arm={$arm}\n" );
	exit( 1 );
}
if ( ! isset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] ) ) {
	fwrite( STDERR, "cookie missing\n" );
	exit( 1 );
}
$cookie = (string) $_COOKIE[ RWGC_AB_Assignment::COOKIE ];
$held   = ob_get_contents();
RWGC_AB_Assignment::end_template_buffer();
if ( ! is_string( $held ) || false === strpos( $held, '<header>theme</header>' ) ) {
	fwrite( STDERR, "lost output\n" );
	exit( 1 );
}
if ( 0 !== ob_get_level() ) {
	fwrite( STDERR, "buffer left open\n" );
	exit( 1 );
}

RWGC_AB_Assignment::reset_request_state();
$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = $cookie;
$again = RWGC_AB_Assignment::variant( 42 );
if ( $again !== $arm ) {
	fwrite( STDERR, "arm flipped {$arm} -> {$again}\n" );
	exit( 1 );
}

echo "OK arm={$arm}\n";
exit( 0 );
