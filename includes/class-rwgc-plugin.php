<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin controller for ReactWoo Geo Core.
 */
class RWGC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var RWGC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return RWGC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot plugin services.
	 *
	 * Safe to call multiple times; will only initialize once.
	 *
	 * @return void
	 */
	public function boot() {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		$this->load_dependencies();
		$this->register_services();

		/**
		 * Fires when ReactWoo Geo Core has loaded.
		 */
		do_action( 'rwgc_loaded' );
	}

	/**
	 * Load required class files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Prefer GeoIP2 / MaxMind DB libraries bundled with Geo Core itself.
		$autoload_candidates = array(
			RWGC_PATH . 'vendor/autoload.php',
			WP_PLUGIN_DIR . '/GeoElementor/vendor/autoload.php',
			WP_PLUGIN_DIR . '/geo-elementor/vendor/autoload.php',
		);

		foreach ( $autoload_candidates as $autoload ) {
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
				break;
			}
		}

		require_once RWGC_PATH . 'includes/class-rwgc-settings.php';
		require_once RWGC_PATH . 'includes/class-rwgc-cache.php';
		require_once RWGC_PATH . 'includes/class-rwgc-maxmind.php';
		require_once RWGC_PATH . 'includes/class-rwgc-geoip.php';
		require_once RWGC_PATH . 'includes/class-rwgc-countries.php';
		require_once RWGC_PATH . 'includes/class-rwgc-api.php';
		require_once RWGC_PATH . 'includes/class-rwgc-preview.php';
		require_once RWGC_PATH . 'includes/class-rwgc-platform-client.php';
		require_once RWGC_PATH . 'includes/class-rwgc-ai-orchestrator.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-ui.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin.php';
		require_once RWGC_PATH . 'includes/class-rwgc-shortcodes.php';
		require_once RWGC_PATH . 'includes/class-rwgc-gutenberg.php';
		require_once RWGC_PATH . 'includes/class-rwgc-elementor.php';
		require_once RWGC_PATH . 'includes/engine/class-rwgc-context.php';
		require_once RWGC_PATH . 'includes/engine/class-rwgc-country-groups.php';
		require_once RWGC_PATH . 'includes/rules/class-rwgc-rule-condition-evaluator.php';
		require_once RWGC_PATH . 'includes/engine/class-rwgc-variant.php';
		require_once RWGC_PATH . 'includes/engine/class-rwgc-page-route-bundle.php';
		require_once RWGC_PATH . 'includes/engine/class-rwgc-fallback-resolver.php';
		require_once RWGC_PATH . 'includes/engine/class-rwgc-page-route-resolver.php';
		require_once RWGC_PATH . 'includes/events/class-rwgc-event.php';
		require_once RWGC_PATH . 'includes/events/class-rwgc-events.php';
		require_once RWGC_PATH . 'includes/rules/class-rwgc-rule.php';
		require_once RWGC_PATH . 'includes/class-rwgc-routing.php';
		require_once RWGC_PATH . 'includes/migration/class-rwgc-legacy-route-mapper.php';
		require_once RWGC_PATH . 'includes/class-rwgc-rest.php';
		require_once RWGC_PATH . 'includes/class-rwgc-upsells.php';
		require_once RWGC_PATH . 'includes/class-rwgc-migration.php';
		require_once RWGC_PATH . 'includes/class-rwgc-compat.php';
	}

	/**
	 * Register core services and hooks.
	 *
	 * @return void
	 */
	private function register_services() {
		// Settings and migration always available.
		RWGC_Settings::init();
		RWGC_Platform_Client::init();
		RWGC_Migration::init();
		RWGC_Country_Groups::init();
		RWGC_Preview::init();

		if ( is_admin() ) {
			RWGC_Admin::init();
		}

		// Frontend + shared.
		RWGC_Shortcodes::init();
		RWGC_Gutenberg::init();
		RWGC_Elementor::init();
		RWGC_Routing::init();
		RWGC_REST::init();
		RWGC_Upsells::init();
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		// Ensure settings exist.
		RWGC_Settings::ensure_defaults();
		// Prepare upload directory and DB path if needed.
		RWGC_MaxMind::ensure_storage_dir();
		if ( false === get_option( 'rwgc_country_groups', false ) ) {
			add_option( 'rwgc_country_groups', array(), '', 'no' );
		}
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Nothing destructive; cache will naturally expire.
	}
}

