<?php
/**
 * Loader for ReactWoo Component System (WP8).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires component classes once and boots the library.
 */
final class RWGC_Components {

	/** @var bool */
	private static $loaded = false;

	/**
	 * @return void
	 */
	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;
		$dir          = dirname( __FILE__ ) . '/';
		require_once $dir . 'class-rwgc-component-definition.php';
		require_once $dir . 'interface-rwgc-component-renderer.php';
		require_once $dir . 'class-rwgc-component-registry.php';
		require_once $dir . 'class-rwgc-php-html-component-renderer.php';
		require_once $dir . 'class-rwgc-component-library.php';
		require_once $dir . 'functions-reactwoo-components.php';
	}

	/**
	 * @return void
	 */
	public static function init() {
		self::load();
		RWGC_Component_Library::boot();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front_styles' ), 20 );
	}

	/**
	 * Base rw- token stylesheet (lightweight; no Shadow DOM).
	 *
	 * @return void
	 */
	public static function enqueue_front_styles() {
		$path = RWGC_PATH . 'assets/css/rw-components.css';
		if ( ! file_exists( $path ) ) {
			return;
		}
		wp_register_style(
			'rw-components',
			RWGC_URL . 'assets/css/rw-components.css',
			array(),
			defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '1.0.0'
		);
		wp_enqueue_style( 'rw-components' );
	}
}
