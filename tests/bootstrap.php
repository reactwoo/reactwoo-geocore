<?php
/**
 * PHPUnit bootstrap: minimal WordPress stubs for engine classes (no full WP load).
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

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

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $maybeint Value.
	 * @return int
	 */
	function absint( $maybeint ) {
		return (int) abs( (float) $maybeint );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param mixed $args     Arguments.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['rwgc_test_options'] ?? array() )
			? $GLOBALS['rwgc_test_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return bool
	 */
	function update_option( $option, $value ) {
		if ( ! isset( $GLOBALS['rwgc_test_options'] ) || ! is_array( $GLOBALS['rwgc_test_options'] ) ) {
			$GLOBALS['rwgc_test_options'] = array();
		}
		$GLOBALS['rwgc_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args (unused in tests).
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
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

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Arguments.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	/**
	 * @param string $content Content.
	 * @return string
	 */
	function do_shortcode( $content ) {
		return $content;
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return isset( $GLOBALS['rwgc_test_visitor_country'] ) ? (string) $GLOBALS['rwgc_test_visitor_country'] : 'US';
	}
}

if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
	/**
	 * @param string $mode  Visibility mode.
	 * @param bool   $match Whether the rule matched.
	 * @return bool
	 */
	function rwgc_visibility_mode_allows_render( $mode, $match ) {
		return 'hide_if' === sanitize_key( (string) $mode ) ? ! (bool) $match : (bool) $match;
	}
}

$base = dirname( __DIR__ ) . '/includes/';
require_once $base . 'context/class-rwgc-context-attribution.php';
require_once $base . 'engine/class-rwgc-context.php';
require_once $base . 'rules/class-rwgc-rule-condition-evaluator.php';
require_once $base . 'engine/class-rwgc-variant.php';
require_once $base . 'engine/class-rwgc-page-route-bundle.php';
require_once $base . 'engine/class-rwgc-fallback-resolver.php';
require_once $base . 'engine/class-rwgc-page-route-resolver.php';
require_once $base . 'events/class-rwgc-event.php';
require_once $base . 'events/class-rwgc-events.php';
