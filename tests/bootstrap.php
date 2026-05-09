<?php
/**
 * PHPUnit bootstrap: minimal WordPress stubs for engine classes (no full WP load).
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

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $maybeint Value.
	 * @return int
	 */
	function absint( $maybeint ) {
		return (int) abs( (float) $maybeint );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return bool
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['wp_filter'][ $hook ][ (int) $priority ][] = array(
			'function'      => $callback,
			'accepted_args' => (int) $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return bool
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @return true
	 */
	function remove_all_filters( $hook ) {
		unset( $GLOBALS['wp_filter'][ $hook ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		if ( empty( $GLOBALS['wp_filter'][ $hook ] ) || ! is_array( $GLOBALS['wp_filter'][ $hook ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['wp_filter'][ $hook ] );
		foreach ( $GLOBALS['wp_filter'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$accepted = isset( $callback['accepted_args'] ) ? (int) $callback['accepted_args'] : 1;
				$params   = array_slice( array_merge( array( $value ), $args ), 0, max( 1, $accepted ) );
				$value    = call_user_func_array( $callback['function'], $params );
			}
		}
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
		if ( empty( $GLOBALS['wp_filter'][ $hook ] ) || ! is_array( $GLOBALS['wp_filter'][ $hook ] ) ) {
			return;
		}
		ksort( $GLOBALS['wp_filter'][ $hook ] );
		foreach ( $GLOBALS['wp_filter'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$accepted = isset( $callback['accepted_args'] ) ? (int) $callback['accepted_args'] : count( $args );
				call_user_func_array( $callback['function'], array_slice( $args, 0, max( 0, $accepted ) ) );
			}
		}
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
require_once $base . 'targeting/interface-rwgc-target-provider.php';
require_once $base . 'targeting/class-rwgc-context-snapshot.php';
require_once $base . 'targeting/class-rwgc-target-registry.php';
require_once $base . 'targeting/class-rwgc-context-resolver.php';
require_once $base . 'targeting/providers/class-rwgc-target-provider-analytics.php';
