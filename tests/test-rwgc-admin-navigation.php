<?php
/**
 * CLI regression tests for admin shell navigation helpers (minimal WP stubs).
 *
 * Run: php tests/test-rwgc-admin-navigation.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_filters'] = array();

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return is_string( $key ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) : '';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['rwgc_test_filters'][ $hook ][ (int) $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => (int) $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		if ( empty( $GLOBALS['rwgc_test_filters'][ $hook ] ) ) {
			return $value;
		}
		ksort( $GLOBALS['rwgc_test_filters'][ $hook ] );
		foreach ( $GLOBALS['rwgc_test_filters'][ $hook ] as $callbacks ) {
			foreach ( $callbacks as $row ) {
				$accepted = max( 1, (int) $row['accepted_args'] );
				$params   = array_merge( array( $value ), array_slice( $args, 0, $accepted - 1 ) );
				$value    = call_user_func_array( $row['callback'], $params );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'remove_submenu_page' ) ) {
	function remove_submenu_page( $menu_slug, $submenu_slug ) {
		if ( empty( $GLOBALS['submenu'][ $menu_slug ] ) || ! is_array( $GLOBALS['submenu'][ $menu_slug ] ) ) {
			return false;
		}
		foreach ( $GLOBALS['submenu'][ $menu_slug ] as $index => $entry ) {
			if ( is_array( $entry ) && isset( $entry[2] ) && (string) $entry[2] === (string) $submenu_slug ) {
				unset( $GLOBALS['submenu'][ $menu_slug ][ $index ] );
				return $entry;
			}
		}
		return false;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-admin-route-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-admin-app-shell.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-admin-platform.php';

/**
 * @param string $msg Message.
 * @return void
 */
function rwgc_test_fail( $msg ) {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
}

/**
 * @param array<int, array<int, mixed>> $entries Submenu entries.
 * @param string                        $slug    Menu slug.
 * @return bool
 */
function rwgc_test_submenu_has_slug( array $entries, $slug ) {
	foreach ( $entries as $entry ) {
		if ( is_array( $entry ) && isset( $entry[2] ) && (string) $entry[2] === (string) $slug ) {
			return true;
		}
	}
	return false;
}

RWGC_Admin_Route_Registry::register_default_sections();
RWGC_Admin_Route_Registry::register_core_routes();

// Simulate runtime admin_menu registration where onboarding can sort ahead of the dashboard.
RWGC_Admin_Route_Registry::register_route(
	array(
		'menu_slug' => 'rwgc-getting-started',
		'section'   => 'overview',
		'route'     => 'setup',
		'label'     => 'Setup wizard',
		'order'     => 5,
	)
);

$overview_url = RWGC_Admin_Route_Registry::get_url( 'overview' );
if ( 'https://example.test/wp-admin/admin.php?page=rwgc-dashboard' !== $overview_url ) {
	rwgc_test_fail( 'Overview section URL should resolve to the dashboard, not the first ordered onboarding tab.' );
}

add_filter(
	'rwgc_app_shell_render',
	static function () {
		return false;
	}
);

if ( RWGC_Admin_App_Shell::is_enabled_for_page( 'rwgc-dashboard' ) ) {
	rwgc_test_fail( 'Shell should be disabled when rwgc_app_shell_render returns false.' );
}

$parent = RWGC_Admin_Platform::menu_parent();
$GLOBALS['submenu'] = array(
	$parent => array(
		array( 'Overview', 'manage_options', 'rwgc-dashboard' ),
		array( 'Setup wizard', 'manage_options', 'rwgc-getting-started' ),
		array( 'Settings', 'manage_options', 'rwgc-settings' ),
	),
);

RWGC_Admin_Platform::collapse_hub_submenu();

if ( ! rwgc_test_submenu_has_slug( $GLOBALS['submenu'][ $parent ], 'rwgc-settings' ) ) {
	rwgc_test_fail( 'Collapsed sidebar should keep normal submenu links when the app shell is disabled.' );
}

fwrite( STDOUT, "OK: RWGC admin navigation CLI tests passed.\n" );
exit( 0 );
