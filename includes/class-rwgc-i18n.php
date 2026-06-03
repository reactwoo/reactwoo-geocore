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
	 * Schedule textdomain load at init priority 0 (WordPress 6.7+ safe).
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

		add_action(
			'init',
			static function () use ( $plugin_file, $text_domain ) {
				self::load_textdomain( $plugin_file, $text_domain );
			},
			0
		);
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
