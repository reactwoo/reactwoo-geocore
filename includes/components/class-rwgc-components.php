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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
	}

	/**
	 * Admin stylesheet only on Geo Core screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_styles( $hook_suffix ) {
		$hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';
		$screen_id   = '';
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && isset( $screen->id ) ) {
				$screen_id = (string) $screen->id;
			}
		}
		if ( false === strpos( $hook_suffix, 'rwgc' ) && false === strpos( $screen_id, 'rwgc' ) ) {
			return;
		}
		self::enqueue_stylesheet();
	}

	/**
	 * Front stylesheet only when this request renders a Geo Core block, shortcode, or widget.
	 *
	 * @return void
	 */
	public static function enqueue_front_styles() {
		if ( is_admin() || ! self::front_request_needs_styles() ) {
			return;
		}
		self::enqueue_stylesheet();
	}

	/**
	 * Register and enqueue the component stylesheet once.
	 *
	 * @return void
	 */
	public static function enqueue_stylesheet() {
		if ( ! defined( 'RWGC_PATH' ) || ! defined( 'RWGC_URL' ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}
		$path = RWGC_PATH . 'assets/css/rw-components.css';
		if ( ! file_exists( $path ) ) {
			return;
		}
		if ( ! wp_style_is( 'rw-components', 'registered' ) ) {
			wp_register_style(
				'rw-components',
				RWGC_URL . 'assets/css/rw-components.css',
				array(),
				defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '1.0.0'
			);
		}
		if ( ! wp_style_is( 'rw-components', 'enqueued' ) ) {
			wp_enqueue_style( 'rw-components' );
			self::apply_brand_tokens();
		}
	}

	/**
	 * Whether the main query contains a Geo Core block, shortcode, or widget.
	 *
	 * @return bool
	 */
	private static function front_request_needs_styles() {
		$posts = array();
		if ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof WP_Query && is_array( $GLOBALS['wp_query']->posts ) ) {
			$posts = $GLOBALS['wp_query']->posts;
		}
		$needles = array(
			'reactwoo-geocore/geo-content',
			'reactwoo/experience-slot',
			'[rwgc_country',
			'[rwgc_country_code',
			'[rwgc_currency',
			'[rwgc_city',
			'[rwgc_region',
			'[rwgc_if',
			'rwgc_use_experience_slot',
		);
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$content = (string) $post->post_content;
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $content, $needle ) ) {
					return true;
				}
			}
		}
		if ( function_exists( 'is_active_widget' ) ) {
			$widgets = array( 'rwgc_geo_content', 'rwgc_experience_slot' );
			foreach ( $widgets as $widget_id ) {
				if ( is_active_widget( false, false, $widget_id ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Apply confirmed Brand Profile tokens from the locally cached Cloud manifest.
	 * Never fetches Cloud on the visitor path.
	 *
	 * @return void
	 */
	public static function apply_brand_tokens() {
		if ( ! class_exists( 'RWGC_Cloud_Manifest_Store', false ) ) {
			return;
		}
		$raw = RWGC_Cloud_Manifest_Store::current_raw();
		if ( ! is_array( $raw ) || empty( $raw['brand_profile'] ) || ! is_array( $raw['brand_profile'] ) ) {
			return;
		}
		$profile = $raw['brand_profile'];
		if ( empty( $profile['confirmed'] ) ) {
			return;
		}
		$vars = isset( $profile['css_variables'] ) && is_array( $profile['css_variables'] ) ? $profile['css_variables'] : array();
		if ( empty( $vars ) ) {
			return;
		}
		$css = ':root{';
		foreach ( $vars as $name => $value ) {
			$key = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $name ) );
			$val = preg_replace( '/[^a-zA-Z0-9#,.%() "\'\-]/', '', (string) $value );
			if ( '' === $key || '' === $val || 0 !== strpos( $key, '--rw-' ) ) {
				continue;
			}
			$css .= $key . ':' . $val . ';';
		}
		$css .= '}';
		wp_add_inline_style( 'rw-components', $css );
	}
}
