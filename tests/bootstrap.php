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

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		$caps = isset( $GLOBALS['rwgc_test_user_caps'] ) && is_array( $GLOBALS['rwgc_test_user_caps'] )
			? $GLOBALS['rwgc_test_user_caps']
			: array();
		return ! empty( $caps[ (string) $capability ] );
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * @param string   $page_title Page title.
	 * @param string   $menu_title Menu title.
	 * @param string   $capability Capability.
	 * @param string   $menu_slug Menu slug.
	 * @param callable $callback Callback.
	 * @param string   $icon_url Icon URL.
	 * @param int|null $position Position.
	 * @return string
	 */
	function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
		$GLOBALS['rwgc_test_menu_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position' );
		return 'toplevel_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * @param string|null $parent_slug Parent slug.
	 * @param string      $page_title Page title.
	 * @param string      $menu_title Menu title.
	 * @param string      $capability Capability.
	 * @param string      $menu_slug Menu slug.
	 * @param callable    $callback Callback.
	 * @param int|null    $position Position.
	 * @return string
	 */
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		$GLOBALS['rwgc_test_submenu_pages'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );
		return ( null === $parent_slug ? 'admin_page_' : $parent_slug . '_page_' ) . $menu_slug;
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
