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
		 *
		 * Target registry init translates provider labels, so defer it to `init`
		 * (WP 6.7 warns when translation functions run before `init`).
		 */
		add_action( 'init', array( 'RWGC_Target_Registry', 'init' ), 0 );

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
		require_once RWGC_PATH . 'includes/class-rwgc-satellite-updater.php';
		require_once RWGC_PATH . 'includes/class-rwgc-ai-orchestrator.php';
		require_once RWGC_PATH . 'includes/ai/class-rwgc-ai-snapshot-schema.php';
		require_once RWGC_PATH . 'includes/ai/class-rwgc-ai-snapshot-sync-status.php';
		require_once RWGC_PATH . 'includes/ai/class-rwgc-ai-snapshot-builder.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-platform.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-route-registry.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-app-shell.php';
		require_once RWGC_PATH . 'includes/class-rwgc-platform-sync-status.php';
		require_once RWGC_PATH . 'includes/class-rwgc-platform-integrations.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-section-hubs.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-settings-nav.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-ui.php';
		require_once RWGC_PATH . 'includes/class-rwgc-insights.php';
		require_once RWGC_PATH . 'includes/class-rwgc-insights-ui.php';
		require_once RWGC_PATH . 'includes/class-rwgc-insights-nav.php';
		require_once RWGC_PATH . 'includes/class-rwgc-capability-registry.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-targeting-nav.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-targeting-variants.php';
		require_once RWGC_PATH . 'includes/class-rwgc-module-registry.php';
		require_once RWGC_PATH . 'includes/class-rwgc-onboarding.php';
		require_once RWGC_PATH . 'includes/class-rwgc-workflows.php';
		require_once RWGC_PATH . 'includes/class-rwgc-variant-manager.php';
		require_once RWGC_PATH . 'includes/class-rwgc-experience-workflow.php';
		require_once RWGC_PATH . 'includes/class-rwgc-suite-admin.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin.php';
		require_once RWGC_PATH . 'includes/class-rwgc-shortcodes.php';
		require_once RWGC_PATH . 'includes/class-rwgc-gutenberg.php';
		require_once RWGC_PATH . 'includes/class-rwgc-targeting-rule-builder-assets.php';
		require_once RWGC_PATH . 'includes/class-rwgc-visibility-rule-cpt.php';
		require_once RWGC_PATH . 'includes/class-rwgc-visibility-rule-repository.php';
		require_once RWGC_PATH . 'includes/class-rwgc-visibility-rule-copy-context.php';
		require_once RWGC_PATH . 'includes/class-rwgc-admin-visibility-rules.php';
		require_once RWGC_PATH . 'includes/class-rwgc-elementor.php';
		require_once RWGC_PATH . 'includes/integrations/class-rwgc-integrations-loader.php';
		require_once RWGC_PATH . 'includes/context/class-rwgc-context-attribution.php';
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

		require_once RWGC_PATH . 'includes/targeting/interface-rwgc-target-provider.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-context-snapshot.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-target-operators.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-targeting-rule-set-schema.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-rule-evaluator.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-rule-registry.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-surface-settings.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-targeting-surface-evaluator.php';
		require_once RWGC_PATH . 'includes/compat/class-rwgc-legacy-geo-rule-cpt.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-context-snapshot-formatter.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-targeting-rule-set-evaluator.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-target-availability.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-target-simulator.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-target-registry.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-page-version.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-page-version-routing.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-variant-rule-applications.php';
		require_once RWGC_PATH . 'includes/targeting/class-rwgc-context-resolver.php';
		require_once RWGC_PATH . 'includes/targeting/providers/class-rwgc-target-provider-geo.php';
		require_once RWGC_PATH . 'includes/targeting/providers/class-rwgc-target-provider-language.php';
		require_once RWGC_PATH . 'includes/targeting/providers/class-rwgc-target-provider-time.php';
		require_once RWGC_PATH . 'includes/targeting/providers/class-rwgc-target-provider-device.php';
		require_once RWGC_PATH . 'includes/targeting/providers/class-rwgc-target-provider-weather.php';
		require_once RWGC_PATH . 'includes/targeting/providers/class-rwgc-target-provider-analytics.php';
		require_once RWGC_PATH . 'includes/targeting/providers/class-rwgc-target-provider-commerce.php';
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
		RWGC_Visibility_Rule_CPT::init();
		RWGC_Variant_Rule_Applications::init();
		RWGC_Legacy_Geo_Rule_CPT::init();

		if ( is_admin() ) {
			RWGC_Admin_Visibility_Rules::init();
			RWGC_Admin_Platform::init();
			RWGC_Admin_Route_Registry::init();
			RWGC_Admin_Settings_Nav::init();
			require_once RWGC_PATH . 'includes/class-rwgc-admin-integrations-nav.php';
			RWGC_Admin_Integrations_Nav::init();
			require_once RWGC_PATH . 'includes/class-rwgc-admin-experiences-nav.php';
			RWGC_Admin_Experiences_Nav::init();
			require_once RWGC_PATH . 'includes/class-rwgc-admin-targeting-rules-index.php';
			RWGC_Admin_App_Shell::init();
			RWGC_Platform_Sync_Status::init();
			RWGC_Platform_Integrations::init();
			RWGC_Suite_Admin::init();
			RWGC_Insights::init();
			RWGC_Insights_Nav::init();
			RWGC_Admin_Targeting_Nav::init();
			RWGC_Admin::init();
		}

		// Frontend + shared.
		RWGC_Shortcodes::init();
		RWGC_Gutenberg::init();
		RWGC_Targeting_Rule_Builder_Assets::init();
		RWGC_Elementor::init();
		RWGC_Integrations_Loader::init();
		RWGC_Routing::init();
		RWGC_Page_Version_Routing::init();
		add_filter( 'rwgc_portable_targeting_editor_context', array( 'RWGC_Page_Version_Routing', 'filter_editor_context' ), 20 );
		RWGC_REST::init();
		RWGC_Upsells::init();

		add_action( 'init', array( __CLASS__, 'register_satellite_updater' ), 1 );
	}

	/**
	 * Register update checker after textdomain load (init priority 0).
	 *
	 * @return void
	 */
	public static function register_satellite_updater() {
		if ( ! class_exists( 'RWGC_Satellite_Updater', false ) ) {
			return;
		}
		RWGC_Satellite_Updater::register(
			array(
				'basename'            => plugin_basename( RWGC_FILE ),
				'version'             => RWGC_VERSION,
				'catalog_slug'        => 'reactwoo-geocore',
				'attach_bearer_token' => false,
				'name'                => __( 'ReactWoo Geo Core', 'reactwoo-geocore' ),
				'description'         => __( 'Free geolocation engine: MaxMind country detection, routing, REST, block.', 'reactwoo-geocore' ),
			)
		);
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once RWGC_PATH . 'includes/class-rwgc-onboarding.php';
		// Ensure settings exist.
		RWGC_Settings::ensure_defaults();
		// Prepare upload directory and DB path if needed.
		RWGC_MaxMind::ensure_storage_dir();
		if ( false === get_option( 'rwgc_country_groups', false ) ) {
			add_option( 'rwgc_country_groups', array(), '', 'no' );
		}
		RWGC_Onboarding::flag_activation_redirect();
		if ( class_exists( 'RWGC_Page_Version_Routing', false ) ) {
			RWGC_Page_Version_Routing::activation_flush();
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

