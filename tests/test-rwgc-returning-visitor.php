<?php
/**
 * CLI tests for returning / new visitor attribution.
 *
 * Run: php tests/test-rwgc-returning-visitor.php
 *
 * @package ReactWoo_Geo_Core
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
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Value.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/includes/context/class-rwgc-context-attribution.php';

/**
 * @param string $msg Message.
 * @return void
 */
function rwgc_rv_fail( $msg ) {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
}

$_GET    = array();
$_COOKIE = array();
RWGC_Context_Attribution::reset();
$_GET['utm_source'] = 'google';
$first              = RWGC_Context_Attribution::resolve();
if ( ! empty( $first['returning_visitor'] ) ) {
	rwgc_rv_fail( 'First visit with UTM must not be returning.' );
}

$again = RWGC_Context_Attribution::resolve();
if ( ! empty( $again['returning_visitor'] ) ) {
	rwgc_rv_fail( 'Memoized second resolve in the same request must stay new.' );
}

$_GET    = array();
$_COOKIE = array( 'rwgc_returning' => '1700000000' );
RWGC_Context_Attribution::reset();
$returning = RWGC_Context_Attribution::resolve();
if ( empty( $returning['returning_visitor'] ) ) {
	rwgc_rv_fail( 'Prior rwgc_returning cookie must classify as returning.' );
}

$_COOKIE = array(
	'rwgc_ft' => rawurlencode(
		wp_json_encode(
			array(
				'source'   => 'newsletter',
				'medium'   => 'email',
				'campaign' => 'spring',
				'content'  => '',
				'term'     => '',
				'gclid'    => '',
			)
		)
	),
);
RWGC_Context_Attribution::reset();
$from_ft = RWGC_Context_Attribution::resolve();
if ( empty( $from_ft['returning_visitor'] ) ) {
	rwgc_rv_fail( 'Prior first-touch cookie must classify as returning.' );
}

fwrite( STDOUT, "OK: returning visitor attribution CLI tests passed.\n" );
exit( 0 );
