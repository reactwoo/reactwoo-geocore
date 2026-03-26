<?php
/**
 * Plugin Name: ReactWoo Geo Core
 * Description: Shared geolocation engine for ReactWoo plugins and WordPress sites. Provides MaxMind-based country detection, cache, shortcodes, REST API, and a Gutenberg block.
 * Version: 0.1.0
 * Author: ReactWoo
 * License: GPL-2.0-or-later
 * Text Domain: reactwoo-geocore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core constants.
if ( ! defined( 'RWGC_VERSION' ) ) {
	define( 'RWGC_VERSION', '0.1.0' );
}
if ( ! defined( 'RWGC_FILE' ) ) {
	define( 'RWGC_FILE', __FILE__ );
}
if ( ! defined( 'RWGC_PATH' ) ) {
	define( 'RWGC_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RWGC_URL' ) ) {
	define( 'RWGC_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'RWGC_BASENAME' ) ) {
	define( 'RWGC_BASENAME', plugin_basename( __FILE__ ) );
}

// Autoload core includes.
// Settings are needed during activation, so load them before the main plugin class.
require_once RWGC_PATH . 'includes/class-rwgc-settings.php';
require_once RWGC_PATH . 'includes/class-rwgc-maxmind.php';
require_once RWGC_PATH . 'includes/class-rwgc-plugin.php';
require_once RWGC_PATH . 'includes/functions-rwgc.php';

/**
 * Bootstrap the plugin.
 */
function rwgc_boot() {
	$plugin = \RWGC_Plugin::instance();
	$plugin->boot();
}

add_action( 'plugins_loaded', 'rwgc_boot', 5 );

register_activation_hook( __FILE__, array( '\RWGC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\RWGC_Plugin', 'deactivate' ) );

