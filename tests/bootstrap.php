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

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args (unused in tests).
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		$filters = isset( $GLOBALS['rwgc_test_filters'] ) && is_array( $GLOBALS['rwgc_test_filters'] )
			? $GLOBALS['rwgc_test_filters']
			: array();
		if ( empty( $filters[ $hook ] ) ) {
			return $value;
		}
		foreach ( $filters[ $hook ] as $callback ) {
			if ( is_callable( $callback ) ) {
				$value = call_user_func( $callback, $value, ...$args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @return true
	 */
	function add_filter( $hook, $callback ) {
		if ( ! isset( $GLOBALS['rwgc_test_filters'] ) || ! is_array( $GLOBALS['rwgc_test_filters'] ) ) {
			$GLOBALS['rwgc_test_filters'] = array();
		}
		if ( ! isset( $GLOBALS['rwgc_test_filters'][ $hook ] ) ) {
			$GLOBALS['rwgc_test_filters'][ $hook ] = array();
		}
		$GLOBALS['rwgc_test_filters'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	/**
	 * @return false
	 */
	function __return_false() {
		return false;
	}
}

if ( ! function_exists( '__return_null' ) ) {
	/**
	 * @return null
	 */
	function __return_null() {
		return null;
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

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * @param string $hook Hook name.
	 * @return int
	 */
	function did_action( $hook ) {
		return 0;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'http://example.test/wp-admin/' . ltrim( (string) $path, '/' );
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

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * @param string $message Message.
	 * @throws RuntimeException When called in tests.
	 * @return void
	 */
	function wp_die( $message = '' ) {
		throw new RuntimeException( (string) $message );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param array $args     Args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * @param int  $length              Length.
	 * @param bool $special_chars       Special chars.
	 * @param bool $extra_special_chars Extra chars.
	 * @return string
	 */
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		unset( $length, $special_chars, $extra_special_chars );
		return 'abcdefgh';
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

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['rwgc_test_is_admin'] );
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * @return bool
	 */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['rwgc_test_doing_ajax'] );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * @return bool
	 */
	function is_singular() {
		return ! isset( $GLOBALS['rwgc_test_is_singular'] ) || (bool) $GLOBALS['rwgc_test_is_singular'];
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * @return int
	 */
	function get_queried_object_id() {
		return isset( $GLOBALS['rwgc_test_queried_object_id'] ) ? (int) $GLOBALS['rwgc_test_queried_object_id'] : 0;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $post_id, $single );
		return isset( $GLOBALS['rwgc_test_post_meta'][ $key ] ) ? $GLOBALS['rwgc_test_post_meta'][ $key ] : '';
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return isset( $GLOBALS['rwgc_test_visitor_country'] ) ? (string) $GLOBALS['rwgc_test_visitor_country'] : '';
	}
}

if ( ! function_exists( 'rwgc_is_builder_edit_request' ) ) {
	/**
	 * @param int|null $post_id Post ID.
	 * @return bool
	 */
	function rwgc_is_builder_edit_request( $post_id = null ) {
		unset( $post_id );
		return ! empty( $GLOBALS['rwgc_test_builder_edit_request'] );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $cap Capability.
	 * @param mixed  ...$args Additional args.
	 * @return bool
	 */
	function current_user_can( $cap, ...$args ) {
		unset( $args );
		$caps = isset( $GLOBALS['rwgc_test_current_user_caps'] ) && is_array( $GLOBALS['rwgc_test_current_user_caps'] )
			? $GLOBALS['rwgc_test_current_user_caps']
			: array();
		return ! empty( $caps[ $cap ] );
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
