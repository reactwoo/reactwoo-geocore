<?php
/**
 * Textdomain loading for ReactWoo Geo Core and shared suite pattern.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin text domains on {@see init} before translated strings run.
 */
class RWGC_I18n {

	/**
	 * @var array<string, bool>
	 */
	private static $bootstrapped = array();

	/**
	 * @var array<string, string> text_domain => plugin __FILE__
	 */
	private static $queue = array();

	/**
	 * @var bool
	 */
	private static $init_hook_added = false;

	/**
	 * @var bool
	 */
	private static $plugins_loaded_hook_added = false;

	/**
	 * Queue textdomain load before any init priority-0 translation calls (e.g. Elementor).
	 *
	 * @param string $plugin_file Main plugin file path (__FILE__).
	 * @param string $text_domain Text domain slug.
	 * @return void
	 */
	public static function bootstrap( $plugin_file, $text_domain ) {
		$text_domain = sanitize_key( (string) $text_domain );
		if ( '' === $text_domain || isset( self::$bootstrapped[ $text_domain ] ) ) {
			return;
		}
		self::$bootstrapped[ $text_domain ] = true;
		self::$queue[ $text_domain ]       = (string) $plugin_file;

		if ( ! self::$plugins_loaded_hook_added ) {
			self::$plugins_loaded_hook_added = true;
			add_action( 'plugins_loaded', array( __CLASS__, 'load_all_bootstrapped' ), 0 );
		}

		if ( ! self::$init_hook_added ) {
			self::$init_hook_added = true;
			add_action( 'init', array( __CLASS__, 'load_all_bootstrapped' ), 1 );
		}

		if ( did_action( 'plugins_loaded' ) ) {
			self::load_textdomain( $plugin_file, $text_domain );
		}
	}

	/**
	 * Load every queued suite textdomain in one early init pass.
	 *
	 * @return void
	 */
	public static function load_all_bootstrapped() {
		foreach ( self::$queue as $text_domain => $plugin_file ) {
			self::load_textdomain( $plugin_file, $text_domain );
		}
	}

	/**
	 * @param string $plugin_file Main plugin file path.
	 * @param string $text_domain Text domain slug.
	 * @return void
	 */
	public static function load_textdomain( $plugin_file, $text_domain ) {
		$text_domain = sanitize_key( (string) $text_domain );
		if ( '' === $text_domain || ! is_readable( $plugin_file ) ) {
			return;
		}
		load_plugin_textdomain( $text_domain, false, dirname( plugin_basename( $plugin_file ) ) . '/languages' );
	}
}
