<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI controller for ReactWoo Geo Core.
 */
class RWGC_Admin {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'rwgc_platform_admin_notices', array( __CLASS__, 'maybe_show_admin_notices' ) );
		add_action( 'admin_post_rwgc_upload_mmdb', array( __CLASS__, 'handle_upload_mmdb' ) );
		add_action( 'admin_post_rwgc_save_maxmind_settings', array( __CLASS__, 'handle_save_maxmind_settings' ) );
		add_action( 'add_meta_boxes_page', array( __CLASS__, 'register_page_meta_box' ) );
		add_action( 'save_post_page', array( __CLASS__, 'save_page_meta_box' ) );
	}

	/**
	 * Menu primitive cap (aligned with Geo Elementor: admins + WooCommerce shop managers).
	 *
	 * @return string
	 */
	public static function required_capability() {
		$default_cap = 'manage_options';
		if ( ! current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) ) {
			$default_cap = 'manage_woocommerce';
		}
		$capability = apply_filters( 'rwgc_required_capability', $default_cap );
		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = $default_cap;
		}
		if ( ! current_user_can( $capability ) && current_user_can( 'manage_options' ) ) {
			$capability = 'manage_options';
		}
		return $capability;
	}

	/**
	 * Whether the current user may use Geo Core wp-admin screens.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::required_capability() );
	}

	/**
	 * Register a Core admin screen in the unified ReactWoo Geo app.
	 *
	 * @param array<string, mixed> $args route, menu_slug, label, callback; optional section, order, page_title.
	 * @return string|false
	 */
	private static function register_app_route( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'module'     => 'core',
				'capability' => self::required_capability(),
			)
		);
		if ( empty( $args['page_title'] ) && ! empty( $args['label'] ) ) {
			$args['page_title'] = (string) $args['label'];
		}
		if ( empty( $args['menu_title'] ) && ! empty( $args['label'] ) ) {
			$args['menu_title'] = (string) $args['label'];
		}
		if ( function_exists( 'rw_geo_register_app_route' ) ) {
			return rw_geo_register_app_route( $args );
		}
		if ( function_exists( 'rw_geo_register_admin_submenu' ) ) {
			return rw_geo_register_admin_submenu( $args );
		}
		$parent = class_exists( 'RWGC_Admin_Platform', false ) ? RWGC_Admin_Platform::menu_parent() : 'rwgc-dashboard';
		$slug   = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug || empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}
		return add_submenu_page(
			$parent,
			(string) $args['page_title'],
			(string) $args['menu_title'],
			(string) $args['capability'],
			$slug,
			$args['callback']
		);
	}

	/**
	 * Register top-level menu and submenus.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$cap = self::required_capability();

		$menu_label = class_exists( 'RWGC_Admin_Platform', false )
			? RWGC_Admin_Platform::menu_label()
			: __( 'Geo Core', 'reactwoo-geocore' );

		add_menu_page(
			__( 'ReactWoo Geo', 'reactwoo-geocore' ),
			$menu_label,
			$cap,
			class_exists( 'RWGC_Admin_Platform', false ) ? RWGC_Admin_Platform::menu_parent() : 'rwgc-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-location-alt',
			58
		);

		if ( class_exists( 'RWGC_Suite_Admin', false ) ) {
			self::register_app_route(
				array(
					'section'   => 'overview',
					'route'     => 'setup',
					'menu_slug' => 'rwgc-getting-started',
					'label'     => __( 'Setup wizard', 'reactwoo-geocore' ),
					'order'     => 5,
					'callback'  => array( 'RWGC_Suite_Admin', 'render_getting_started' ),
				)
			);
			self::register_app_route(
				array(
					'section'   => 'overview',
					'route'     => 'suite-home',
					'menu_slug' => 'rwgc-suite-home',
					'label'     => __( 'Suite home', 'reactwoo-geocore' ),
					'order'     => 15,
					'callback'  => array( 'RWGC_Suite_Admin', 'render_suite_home' ),
				)
			);
			self::register_app_route(
				array(
					'section'   => 'targeting',
					'route'     => 'variants',
					'menu_slug' => 'rwgc-suite-variants',
					'label'     => __( 'Variants', 'reactwoo-geocore' ),
					'order'     => 15,
					'callback'  => array( 'RWGC_Suite_Admin', 'render_suite_variants' ),
				)
			);
			self::register_app_route(
				array(
					'section'        => 'targeting',
					'route'          => 'variant-wizard',
					'menu_slug'      => 'rwgc-workflow-variant',
					'label'          => __( 'Create variant', 'reactwoo-geocore' ),
					'order'          => 90,
					'is_section_nav' => false,
					'callback'       => array( 'RWGC_Suite_Admin', 'render_workflow_variant' ),
				)
			);
		}

		self::register_app_route(
			array(
				'section'   => 'overview',
				'route'     => 'dashboard',
				'menu_slug' => 'rwgc-dashboard',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 10,
				'callback'  => array( __CLASS__, 'render_dashboard' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'commerce',
				'route'     => 'commerce-home',
				'menu_slug' => 'rwgc-commerce-hub',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
				'callback'  => array( 'RWGC_Admin_Section_Hubs', 'render_commerce_hub' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'targeting',
				'route'     => 'targeting-home',
				'menu_slug' => 'rwgc-targeting-hub',
				'label'     => __( 'Assistant', 'reactwoo-geocore' ),
				'order'     => 5,
				'callback'  => array( 'RWGC_Admin_Section_Hubs', 'render_targeting_hub' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'targeting',
				'route'     => 'advanced',
				'menu_slug' => 'rwgc-targeting-advanced',
				'label'     => __( 'Advanced', 'reactwoo-geocore' ),
				'order'     => 35,
				'callback'  => array( 'RWGC_Admin_Section_Hubs', 'render_targeting_advanced' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'experiences',
				'route'     => 'experiences-home',
				'menu_slug' => 'rwgc-experiences-hub',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
				'callback'  => array( 'RWGC_Admin_Section_Hubs', 'render_experiences_hub' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'targeting',
				'route'     => 'rules',
				'menu_slug' => 'rwgc-visibility-rules',
				'label'     => __( 'Rules', 'reactwoo-geocore' ),
				'order'     => 10,
				'callback'  => array( 'RWGC_Admin_Visibility_Rules', 'render' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'targeting',
				'route'     => 'geo-conditions',
				'menu_slug' => 'rwgc-target-types',
				'label'     => __( 'Geo conditions', 'reactwoo-geocore' ),
				'order'     => 30,
				'callback'  => array( __CLASS__, 'render_target_types' ),
			)
		);
		self::register_app_route(
			array(
				'section'   => 'targeting',
				'route'     => 'audiences',
				'menu_slug' => 'rwgc-targeting-audiences',
				'label'     => __( 'Audiences', 'reactwoo-geocore' ),
				'order'     => 20,
				'callback'  => array( __CLASS__, 'render_targeting_audiences' ),
			)
		);
		self::register_app_route(
			array(
				'section'   => 'targeting',
				'route'     => 'campaigns',
				'menu_slug' => 'rwgc-targeting-campaigns',
				'label'     => __( 'Campaigns', 'reactwoo-geocore' ),
				'order'     => 25,
				'callback'  => array( __CLASS__, 'render_targeting_campaigns' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'insights-home',
				'menu_slug' => 'rwgc-insights-hub',
				'label'     => __( 'Capability map', 'reactwoo-geocore' ),
				'order'     => 5,
				'callback'  => array( 'RWGC_Admin_Section_Hubs', 'render_insights_hub' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'insights-readiness',
				'menu_slug' => 'rwgc-insights-readiness',
				'label'     => __( 'Setup & readiness', 'reactwoo-geocore' ),
				'order'     => 8,
				'callback'  => array( 'RWGC_Insights', 'render_readiness_page' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'ai-opportunities',
				'menu_slug' => 'rwgc-insights-ai',
				'label'     => __( 'AI opportunities', 'reactwoo-geocore' ),
				'order'     => 10,
				'callback'  => array( 'RWGC_Insights', 'render_ai_opportunities_page' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'provider-detail',
				'menu_slug' => 'rwgc-insights-provider-detail',
				'label'     => __( 'Product details', 'reactwoo-geocore' ),
				'order'     => 99,
				'callback'  => array( 'RWGC_Insights', 'render_provider_detail' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'geo-reports',
				'menu_slug' => 'rwgc-usage',
				'label'     => __( 'Geo insights', 'reactwoo-geocore' ),
				'order'     => 15,
				'callback'  => array( __CLASS__, 'render_usage' ),
			)
		);
		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'audience-insights',
				'menu_slug' => 'rwgc-usage-audience',
				'label'     => __( 'Audience insights', 'reactwoo-geocore' ),
				'order'     => 20,
				'callback'  => array( __CLASS__, 'render_usage' ),
			)
		);
		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'campaign-insights',
				'menu_slug' => 'rwgc-usage-campaign',
				'label'     => __( 'Campaign insights', 'reactwoo-geocore' ),
				'order'     => 25,
				'callback'  => array( __CLASS__, 'render_usage' ),
			)
		);
		self::register_app_route(
			array(
				'section'   => 'insights',
				'route'     => 'experience-performance',
				'menu_slug' => 'rwgc-insights-experiments',
				'label'     => __( 'Experience performance', 'reactwoo-geocore' ),
				'order'     => 30,
				'callback'  => array( __CLASS__, 'render_insights_experience_performance' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'integrations',
				'route'     => 'integrations-home',
				'menu_slug' => 'rwgc-integrations-hub',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
				'callback'  => array( 'RWGC_Admin_Section_Hubs', 'render_integrations_hub' ),
			)
		);
		self::register_app_route(
			array(
				'section'              => 'integrations',
				'integration_category' => 'content_builders',
				'route'                => 'gutenberg',
				'menu_slug'            => 'rwgc-integrations-gutenberg',
				'label'                => __( 'Gutenberg', 'reactwoo-geocore' ),
				'order'                => 20,
				'callback'             => array( __CLASS__, 'render_integrations_gutenberg' ),
			)
		);
		self::register_app_route(
			array(
				'section'              => 'integrations',
				'integration_category' => 'ecommerce',
				'route'                => 'woocommerce',
				'menu_slug'            => 'rwgc-integrations-woocommerce',
				'label'                => __( 'WooCommerce', 'reactwoo-geocore' ),
				'order'                => 10,
				'callback'             => array( __CLASS__, 'render_integrations_woocommerce' ),
			)
		);
		self::register_app_route(
			array(
				'section'              => 'integrations',
				'integration_category' => 'system_services',
				'route'                => 'maxmind',
				'menu_slug'            => 'rwgc-integrations-maxmind',
				'label'                => __( 'MaxMind (GeoLite2)', 'reactwoo-geocore' ),
				'order'                => 5,
				'callback'             => array( __CLASS__, 'render_integrations_maxmind' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'settings',
				'route'     => 'settings-home',
				'menu_slug' => 'rwgc-settings-hub',
				'label'     => __( 'Overview', 'reactwoo-geocore' ),
				'order'     => 5,
				'callback'  => array( 'RWGC_Admin_Section_Hubs', 'render_settings_hub' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'settings',
				'route'     => 'settings',
				'menu_slug' => 'rwgc-settings',
				'label'     => __( 'General', 'reactwoo-geocore' ),
				'order'     => 10,
				'callback'  => array( __CLASS__, 'render_settings' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'settings',
				'route'     => 'tools',
				'menu_slug' => 'rwgc-tools',
				'label'     => __( 'Tools', 'reactwoo-geocore' ),
				'order'     => 20,
				'callback'  => array( __CLASS__, 'render_tools' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'settings',
				'route'     => 'ai-snapshot',
				'menu_slug' => 'rwgc-settings-ai-snapshot',
				'label'     => __( 'AI Data Snapshot', 'reactwoo-geocore' ),
				'order'     => 25,
				'callback'  => array( __CLASS__, 'render_ai_snapshot_preview' ),
			)
		);

		self::register_app_route(
			array(
				'section'   => 'settings',
				'route'     => 'addons',
				'menu_slug' => 'rwgc-addons',
				'label'     => __( 'Add-ons', 'reactwoo-geocore' ),
				'order'     => 30,
				'callback'  => array( __CLASS__, 'render_addons' ),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		$is_hub = class_exists( 'RWGC_Admin_Platform', false ) && RWGC_Admin_Platform::is_hub_screen( $hook );
		if ( ! $is_hub && strpos( $hook, 'rwgc-' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'rwgc-design-system',
			RWGC_URL . 'admin/css/rwgc-design-system.css',
			array(),
			RWGC_VERSION
		);
		wp_enqueue_style(
			'rwgc-admin',
			RWGC_URL . 'admin/css/admin.css',
			array( 'rwgc-design-system' ),
			RWGC_VERSION
		);
		wp_enqueue_style(
			'rwgc-suite',
			RWGC_URL . 'admin/css/rwgc-suite.css',
			array( 'rwgc-design-system', 'rwgc-admin' ),
			RWGC_VERSION
		);
		if ( preg_match( '/(rwgc-insights|rwgc-usage|rwgcm-attribution)/', $hook ) ) {
			wp_enqueue_style(
				'rwgc-insights',
				RWGC_URL . 'admin/css/rwgc-insights.css',
				array( 'rwgc-suite' ),
				RWGC_VERSION
			);
		}
		if ( preg_match( '/(rwgc-targeting|rwgc-suite-variants|rwgc-workflow-variant|rwgc-visibility-rules)/', $hook ) ) {
			wp_enqueue_style(
				'rwgc-platform-ui',
				RWGC_URL . 'admin/css/rwgc-platform-ui.css',
				array( 'rwgc-suite' ),
				RWGC_VERSION
			);
			wp_enqueue_style(
				'rwgc-targeting',
				RWGC_URL . 'admin/css/rwgc-targeting.css',
				array( 'rwgc-platform-ui' ),
				RWGC_VERSION
			);
		}
		if ( preg_match( '/(rwgc-insights|rwgc-usage|rwgcm-attribution|rwgc-experiences)/', $hook ) ) {
			wp_enqueue_style(
				'rwgc-platform-ui',
				RWGC_URL . 'admin/css/rwgc-platform-ui.css',
				array( 'rwgc-suite' ),
				RWGC_VERSION
			);
		}
		if ( false !== strpos( $hook, 'rwgc-targeting-hub' ) ) {
			$pages     = class_exists( 'RWGC_Page_Version', false ) ? RWGC_Page_Version::get_page_choices( 80 ) : array();
			$popups    = array();
			if ( post_type_exists( 'elementor_library' ) ) {
				$popup_posts = get_posts(
					array(
						'post_type'      => 'elementor_library',
						'post_status'    => array( 'publish', 'draft', 'private' ),
						'posts_per_page' => 80,
						'meta_query'     => array(
							array(
								'key'   => '_elementor_template_type',
								'value' => 'popup',
							),
						),
					)
				);
				foreach ( $popup_posts as $popup_post ) {
					if ( ! $popup_post instanceof WP_Post ) {
						continue;
					}
					$popups[] = array(
						'id'    => (int) $popup_post->ID,
						'title' => (string) get_the_title( $popup_post ),
					);
				}
			}
			$countries = array();
			if ( class_exists( 'RWGC_Countries', false ) ) {
				foreach ( RWGC_Countries::get_options() as $code => $name ) {
					$countries[] = array(
						'code' => (string) $code,
						'name' => (string) $name,
					);
				}
			}
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_script(
				'rwgc-targeting-assistant',
				RWGC_URL . 'admin/js/rwgc-targeting-assistant.js',
				array( 'jquery' ),
				RWGC_VERSION,
				true
			);
			$bundle_status = class_exists( 'RWGA_Intelligence_Sync_Service', false )
				? RWGA_Intelligence_Sync_Service::get_status()
				: array();
			wp_localize_script(
				'rwgc-targeting-assistant',
				'rwgcTargetingAssistant',
				array(
					'previewUrl'      => esc_url_raw( rest_url( 'geo-ai/v1/interpret/preview' ) ),
					'interpretUrl'    => esc_url_raw( rest_url( 'geo-ai/v1/interpret' ) ),
					'confirmSplitUrl' => esc_url_raw( rest_url( 'geo-ai/v1/interpret/confirm-split' ) ),
					'confirmInterpretationUrl' => esc_url_raw( rest_url( 'geo-ai/v1/interpret/confirm-interpretation' ) ),
					'executeUrl'      => esc_url_raw( rest_url( 'geo-ai/v1/interpret/execute' ) ),
					'learningEventUrl' => esc_url_raw( rest_url( 'geo-ai/v1/intelligence/command/learning-event' ) ),
					'bundleUrl'       => esc_url_raw( rest_url( 'geo-ai/v1/intelligence/command/bundle' ) ),
					'targetSearchUrl' => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targets/search' ) ),
					'targetCreateUrl' => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targets/create' ) ),
					'restNonce'       => wp_create_nonce( 'wp_rest' ),
					'previewDebounce' => 600,
					'geoAiAvailable'  => did_action( 'rwga_loaded' ) > 0,
					'bundleStatus'    => $bundle_status,
					'capabilities'    => class_exists( 'RWGC_Capability_Registry', false )
						? RWGC_Capability_Registry::export_for_assistant()
						: array(),
					'pages'           => $pages,
					'popups'          => $popups,
					'countries'       => $countries,
					'keywordHints'      => self::get_assistant_keyword_hints(),
					'i18n'              => array(
						'assistantName'   => __( 'Geo Assistant', 'reactwoo-geocore' ),
						'opening'         => __( 'Tell me what you want to target. I can detect countries, devices, pages, variants, weather, campaigns, URLs, popups and product rules.', 'reactwoo-geocore' ),
						'detectedLabel'   => __( 'Detected:', 'reactwoo-geocore' ),
						'livePreview'     => __( 'Likely intent', 'reactwoo-geocore' ),
						'checking'        => __( 'Checking what Geo Core can build…', 'reactwoo-geocore' ),
						'createSetup'     => __( 'Create setup', 'reactwoo-geocore' ),
						'editSetup'       => __( 'Edit setup', 'reactwoo-geocore' ),
						'useSplit'        => __( 'Yes, use this split', 'reactwoo-geocore' ),
						'editSplit'       => __( 'Edit split', 'reactwoo-geocore' ),
						'askAiCheck'      => __( 'Ask AI to check', 'reactwoo-geocore' ),
						'askAi'           => __( 'Ask AI', 'reactwoo-geocore' ),
						'chooseSplit'     => __( 'Choose split', 'reactwoo-geocore' ),
						'editManually'    => __( 'Edit manually', 'reactwoo-geocore' ),
						'thinkYouMean'    => __( 'I think you mean:', 'reactwoo-geocore' ),
						'intelligenceThinks' => __( 'The intelligence layer thinks you mean:', 'reactwoo-geocore' ),
						'whyAsking'       => __( 'Why I’m asking:', 'reactwoo-geocore' ),
						'isInterpretationCorrect' => __( 'Is this interpretation correct?', 'reactwoo-geocore' ),
						'useInterpretation' => __( 'This looks correct', 'reactwoo-geocore' ),
						'chooseLocationAudience' => __( 'Choose location/audience', 'reactwoo-geocore' ),
						'askAiAgain'      => __( 'Ask AI again', 'reactwoo-geocore' ),
						'editInterpretation' => __( 'Edit interpretation', 'reactwoo-geocore' ),
						'applyInterpretation' => __( 'Apply interpretation', 'reactwoo-geocore' ),
						'applyChanges'    => __( 'Apply changes', 'reactwoo-geocore' ),
						'editSetupHint'   => __( 'Adjust detected values before confirming.', 'reactwoo-geocore' ),
						'editInterpretationHint' => __( 'Choose the right location or audience for each action below.', 'reactwoo-geocore' ),
						'editingFor'      => __( 'You are editing:', 'reactwoo-geocore' ),
						'choosingFor'     => __( 'Choosing for:', 'reactwoo-geocore' ),
						'forScope'        => __( 'for', 'reactwoo-geocore' ),
						'removeCondition' => __( 'Remove condition', 'reactwoo-geocore' ),
						'locationLabel'   => __( 'Location', 'reactwoo-geocore' ),
						'audienceLabel'   => __( 'Audience', 'reactwoo-geocore' ),
						'campaignLabel'   => __( 'Campaign', 'reactwoo-geocore' ),
						'actionWord'      => __( 'Action', 'reactwoo-geocore' ),
						'weatherLabel'    => __( 'Weather', 'reactwoo-geocore' ),
						'logicLabel'      => __( 'Logic', 'reactwoo-geocore' ),
						'matchAll'        => __( 'Match all conditions', 'reactwoo-geocore' ),
						'matchAny'        => __( 'Match any condition', 'reactwoo-geocore' ),
						'detectedPrefix'  => __( 'Detected:', 'reactwoo-geocore' ),
						'isCorrect'       => __( 'Is this correct?', 'reactwoo-geocore' ),
						'showDebug'       => __( 'Show debug', 'reactwoo-geocore' ),
						'cancel'          => __( 'Cancel', 'reactwoo-geocore' ),
						'choosePage'      => __( 'Choose a page…', 'reactwoo-geocore' ),
						'pageLabel'       => __( 'Page', 'reactwoo-geocore' ),
						'variantLabel'    => __( 'Variant', 'reactwoo-geocore' ),
						'setupPlan'       => __( 'Targeting plan', 'reactwoo-geocore' ),
						'setupHeading'    => __( 'Setup', 'reactwoo-geocore' ),
						'actionDetected'  => __( 'action detected', 'reactwoo-geocore' ),
						'actionsDetected' => __( 'actions detected', 'reactwoo-geocore' ),
						'fieldNeedsAttention'  => __( 'field needs attention', 'reactwoo-geocore' ),
						'fieldsNeedAttention'  => __( 'fields need attention', 'reactwoo-geocore' ),
						'allResolved'     => __( 'All fields resolved', 'reactwoo-geocore' ),
						'continueAfter'   => __( 'Resolve', 'reactwoo-geocore' ),
						'itemWord'        => __( 'item', 'reactwoo-geocore' ),
						'itemsWord'       => __( 'items', 'reactwoo-geocore' ),
						'cardActionWord'  => __( 'Action', 'reactwoo-geocore' ),
						'cardNeedsResolution' => __( 'Needs resolution', 'reactwoo-geocore' ),
						'cardReady'       => __( 'Ready to create', 'reactwoo-geocore' ),
						'actionReview'    => __( 'Action Review', 'reactwoo-geocore' ),
						'needsWord'       => __( 'Needs', 'reactwoo-geocore' ),
						'fieldTarget'     => __( 'target', 'reactwoo-geocore' ),
						'fieldCampaign'   => __( 'campaign', 'reactwoo-geocore' ),
						'fieldAudience'   => __( 'audience', 'reactwoo-geocore' ),
						'fieldLocation'   => __( 'location', 'reactwoo-geocore' ),
						'includeConditions' => __( 'Conditions', 'reactwoo-geocore' ),
						'logicLabel'      => __( 'Logic', 'reactwoo-geocore' ),
						'matchAll'        => __( 'Match all conditions', 'reactwoo-geocore' ),
						'matchAny'        => __( 'Match any condition', 'reactwoo-geocore' ),
						'statusValid'     => __( 'Valid', 'reactwoo-geocore' ),
						'statusNeedsAttention' => __( 'Needs attention', 'reactwoo-geocore' ),
						'anyAudience'     => __( 'Any audience', 'reactwoo-geocore' ),
						'reviewAction'    => __( 'Review action', 'reactwoo-geocore' ),
						'conditionsDetected' => __( 'Conditions detected', 'reactwoo-geocore' ),
						'needConfirmationFor' => __( 'I need confirmation for:', 'reactwoo-geocore' ),
						'sourceLocal'     => __( 'Local smart action', 'reactwoo-geocore' ),
						'sourceLearned'   => __( 'Learned interpretation', 'reactwoo-geocore' ),
						'sourceAi'        => __( 'AI-assisted interpretation', 'reactwoo-geocore' ),
						'sourceClarify'   => __( 'Needs clarification', 'reactwoo-geocore' ),
						/* translators: %s/{n} is the action number that owns the shared target. */
						'blockedByTarget' => __( 'Blocked by target from Action {n}', 'reactwoo-geocore' ),
						'blockedTargetGeneric' => __( 'Blocked until target is resolved', 'reactwoo-geocore' ),
						'sharedTargetTitle' => __( 'Shared Target', 'reactwoo-geocore' ),
						'usedByActions'   => __( 'This target will be used by:', 'reactwoo-geocore' ),
						'waitingSharedTarget' => __( 'Waiting for shared target', 'reactwoo-geocore' ),
						'resolveSharedTarget' => __( 'Resolve shared target', 'reactwoo-geocore' ),
						'createNActions'  => __( 'Create {n} actions', 'reactwoo-geocore' ),
						'createRule'      => __( 'Create rule', 'reactwoo-geocore' ),
						'editRule'          => __( 'Edit rule', 'reactwoo-geocore' ),
						'resolveItems'      => __( 'Resolve', 'reactwoo-geocore' ),
						'actionReady'       => __( 'action ready', 'reactwoo-geocore' ),
						'actionsReady'      => __( 'actions ready', 'reactwoo-geocore' ),
						'hubNeeds'          => __( 'Needs', 'reactwoo-geocore' ),
						'hubNeedsResolution' => __( 'Needs resolution', 'reactwoo-geocore' ),
						'hubReady'          => __( 'Ready', 'reactwoo-geocore' ),
						'hubRule'           => __( 'Rule', 'reactwoo-geocore' ),
						'hubNeedTarget'     => __( 'Target page', 'reactwoo-geocore' ),
						'hubNeedPopup'      => __( 'Popup target', 'reactwoo-geocore' ),
						'hubNeedAudience'   => __( 'Audience segments', 'reactwoo-geocore' ),
						'hubNeedCampaign'   => __( 'Campaign', 'reactwoo-geocore' ),
						'hubNeedLocation'   => __( 'Location', 'reactwoo-geocore' ),
						'hubNeedTraffic'    => __( 'Google Ads mapping', 'reactwoo-geocore' ),
						'invalidCreateRuleSplit' => __( 'This was split into multiple actions, but it looks like one rule. Ask AI to re-check?', 'reactwoo-geocore' ),
						'possibleMatchesFound' => __( 'Possible matches found', 'reactwoo-geocore' ),
						'resolutionHub'   => __( 'Resolution Hub', 'reactwoo-geocore' ),
						'cardActionRemoved' => __( 'Removed', 'reactwoo-geocore' ),
						'cardRestore'     => __( 'Restore action', 'reactwoo-geocore' ),
						'cardTargetLabel' => __( 'Target', 'reactwoo-geocore' ),
						'cardInclude'     => __( 'Include', 'reactwoo-geocore' ),
						'cardExclude'     => __( 'Exclude', 'reactwoo-geocore' ),
						'cardCountries'   => __( 'Countries', 'reactwoo-geocore' ),
						'cardRegions'     => __( 'Regions', 'reactwoo-geocore' ),
						'cardDevices'     => __( 'Devices', 'reactwoo-geocore' ),
						'cardWeather'     => __( 'Weather', 'reactwoo-geocore' ),
						'cardVisitor'     => __( 'Visitor', 'reactwoo-geocore' ),
						'cardNotFound'    => __( 'Not found', 'reactwoo-geocore' ),
						'cardAmbiguous'   => __( 'Possible matches found', 'reactwoo-geocore' ),
						'cardInheritedUnresolved' => __( 'Inherited target is not defined', 'reactwoo-geocore' ),
						'cardUnverified'  => __( 'Could not be verified automatically', 'reactwoo-geocore' ),
						'cardSyncUnavailable' => __( 'No synced list available yet', 'reactwoo-geocore' ),
						'cardMatched'     => __( 'Matched', 'reactwoo-geocore' ),
						'cardIgnored'     => __( 'Ignored', 'reactwoo-geocore' ),
						'cardSetTo'       => __( 'Set to', 'reactwoo-geocore' ),
						'cardUndo'        => __( 'Undo', 'reactwoo-geocore' ),
						'cardSameAs'      => __( 'Same as', 'reactwoo-geocore' ),
						'cardChooseCampaign' => __( 'Choose campaign', 'reactwoo-geocore' ),
						'cardChooseAudience' => __( 'Choose audience', 'reactwoo-geocore' ),
						'cardChooseTarget' => __( 'Choose page/category', 'reactwoo-geocore' ),
						'cardChoosePopup'  => __( 'Choose popup', 'reactwoo-geocore' ),
						'cardSearchPopups' => __( 'Search popups', 'reactwoo-geocore' ),
						'cardSearchTargets' => __( 'Search', 'reactwoo-geocore' ),
						'cardIgnore'      => __( 'Ignore', 'reactwoo-geocore' ),
						'cardRefresh'     => __( 'Refresh synced', 'reactwoo-geocore' ),
						'cardRemoveAction' => __( 'Remove action', 'reactwoo-geocore' ),
						'cardUse'         => __( 'Use', 'reactwoo-geocore' ),
						'cardPickerPlaceholder' => __( 'Select a page or category…', 'reactwoo-geocore' ),
						'cardPopupPickerPlaceholder' => __( 'Select a popup…', 'reactwoo-geocore' ),
						'cardEnterExact'  => __( 'Enter the exact name to use:', 'reactwoo-geocore' ),
						'cardTypeUpdateCampaign' => __( 'Update campaign targeting', 'reactwoo-geocore' ),
						'cardTypeUpdateOriginal' => __( 'Update targeting', 'reactwoo-geocore' ),
						'cardTypeCreateVariant'  => __( 'Create variant', 'reactwoo-geocore' ),
						'cardTypeUpdateVariant'  => __( 'Update variant', 'reactwoo-geocore' ),
						'cardTypeCreateRule'     => __( 'Create rule', 'reactwoo-geocore' ),
						'cardTypeUpdateRule'     => __( 'Update rule', 'reactwoo-geocore' ),
						'cardTypeHide'    => __( 'Hide', 'reactwoo-geocore' ),
						'cardTypeShow'    => __( 'Show', 'reactwoo-geocore' ),
						'cardTypeTest'    => __( 'Preview / test', 'reactwoo-geocore' ),
						'foundActionsPrefix' => __( 'I found', 'reactwoo-geocore' ),
						'foundRulePrefix'  => __( 'I found', 'reactwoo-geocore' ),
						'ruleWord'         => __( 'rule', 'reactwoo-geocore' ),
						'rulesWord'        => __( 'rules', 'reactwoo-geocore' ),
						'toCreateWord'     => __( 'to create', 'reactwoo-geocore' ),
						'actionWord2'     => __( 'action', 'reactwoo-geocore' ),
						'actionsWord2'    => __( 'actions', 'reactwoo-geocore' ),
						'beforeCreate'    => __( 'before this can be created.', 'reactwoo-geocore' ),
						'allResolvedReady' => __( 'Everything is resolved — you can create the setup.', 'reactwoo-geocore' ),
						'reviewInPanel'   => __( 'Review and resolve each action in the setup panel on the right.', 'reactwoo-geocore' ),
						'statusPending'   => __( 'Pending confirmation', 'reactwoo-geocore' ),
						'statusNeedsConfirmation' => __( 'Needs confirmation', 'reactwoo-geocore' ),
						'statusConfirmed' => __( 'Confirmed', 'reactwoo-geocore' ),
						'statusNeedsResolution' => __( 'Needs resolution', 'reactwoo-geocore' ),
						'statusNeedsMapping'    => __( 'Needs mapping', 'reactwoo-geocore' ),
						'confirmationLabel'     => __( 'Confirmation', 'reactwoo-geocore' ),
						'confirmationDetail'  => __( 'Manual confirmation required before execution.', 'reactwoo-geocore' ),
						'trafficSourceLabel'    => __( 'Google Ads traffic', 'reactwoo-geocore' ),
						'resolveGoogleAds'      => __( 'Resolve Google Ads mapping', 'reactwoo-geocore' ),
						'resolvePopupTarget'    => __( 'Resolve popup target', 'reactwoo-geocore' ),
						'popupTargetStartExplanation' => __( 'I could not find an exact popup called "%s". You can create it now, choose an existing popup, or remove this action.', 'reactwoo-geocore' ),
						'popupCreateNew'        => __( 'Create new popup', 'reactwoo-geocore' ),
						'popupChooseExisting'   => __( 'Choose existing popup', 'reactwoo-geocore' ),
						'popupCreateTitle'      => __( 'Create popup', 'reactwoo-geocore' ),
						'popupNameLabel'        => __( 'Name', 'reactwoo-geocore' ),
						'popupStatusLabel'      => __( 'Status', 'reactwoo-geocore' ),
						'popupStatusDraft'      => __( 'Draft', 'reactwoo-geocore' ),
						'popupAttachAction'     => __( 'Use this popup as the target for Action %d', 'reactwoo-geocore' ),
						'popupCreateButton'     => __( 'Create popup', 'reactwoo-geocore' ),
						'popupChooseTitle'      => __( 'Choose existing popup', 'reactwoo-geocore' ),
						'popupSearchPlaceholder' => __( 'Search popups…', 'reactwoo-geocore' ),
						'popupUseSelected'      => __( 'Use selected popup', 'reactwoo-geocore' ),
						'popupSearchLoading'    => __( 'Searching popups…', 'reactwoo-geocore' ),
						'popupSearchEmpty'      => __( 'No matching popups found.', 'reactwoo-geocore' ),
						'popupCreateFromEmpty'  => __( 'Create new popup: %s', 'reactwoo-geocore' ),
						'popupRemoveConfirmTitle' => __( 'Remove action?', 'reactwoo-geocore' ),
						'popupRemoveConfirmBody' => __( 'This will remove the rule setup for %s.', 'reactwoo-geocore' ),
						'popupCreateFailed'     => __( 'Could not create the popup. Try again or choose an existing popup.', 'reactwoo-geocore' ),
						'popupCreateErrorTitle' => __( 'Could not create popup', 'reactwoo-geocore' ),
						'popupCreateErrorReason' => __( 'Reason:', 'reactwoo-geocore' ),
						'popupTryAgain'         => __( 'Try again', 'reactwoo-geocore' ),
						'popupCreateEndpointMissing' => __( 'Popup create endpoint is not configured on this site.', 'reactwoo-geocore' ),
						'popupCreatedSelected'  => __( 'Popup created and selected as the target.', 'reactwoo-geocore' ),
						'popupOpenEditor'       => __( 'Open popup editor', 'reactwoo-geocore' ),
						'popupContinueSetup'    => __( 'Continue setup', 'reactwoo-geocore' ),
						'popupSearchLabel'      => __( 'Search', 'reactwoo-geocore' ),
						'popupDuplicateTitle'   => __( 'Similar popup found', 'reactwoo-geocore' ),
						'popupDuplicateMessage' => __( 'A similar popup already exists.', 'reactwoo-geocore' ),
						'popupUseExisting'      => __( 'Use existing', 'reactwoo-geocore' ),
						'popupCreateAnyway'     => __( 'Create new anyway', 'reactwoo-geocore' ),
						'popupBack'             => __( 'Back', 'reactwoo-geocore' ),
						'resolvePopup'          => __( 'Resolve popup', 'reactwoo-geocore' ),
						'useCurrentPopup'       => __( 'Use current popup', 'reactwoo-geocore' ),
						'ruleCreated'           => __( 'Rule created.', 'reactwoo-geocore' ),
						'viewRule'              => __( 'View rule', 'reactwoo-geocore' ),
						'openTarget'            => __( 'Open target', 'reactwoo-geocore' ),
						'createAnotherRule'     => __( 'Create another rule', 'reactwoo-geocore' ),
						'createdConditions'     => __( 'Created conditions:', 'reactwoo-geocore' ),
						'actionCreated'         => __( 'action created', 'reactwoo-geocore' ),
						'resolveField'          => __( 'Resolve', 'reactwoo-geocore' ),
						'drawerDetected'        => __( 'Detected', 'reactwoo-geocore' ),
						'drawerWhy'             => __( 'Why this needs resolution', 'reactwoo-geocore' ),
						'drawerRecommended'     => __( 'Recommended', 'reactwoo-geocore' ),
						'drawerApply'           => __( 'Apply mapping', 'reactwoo-geocore' ),
						'popupTargetHint'       => __( 'This looks like a popup target. Choose the matching popup before creating the rule.', 'reactwoo-geocore' ),
						'googleAdsWhy'          => __( 'Geo Core needs to know how to recognise visitors from Google Ads.', 'reactwoo-geocore' ),
						'drawerExplanation'     => __( 'Explanation', 'reactwoo-geocore' ),
						'drawerRecommendedOption' => __( 'Recommended option', 'reactwoo-geocore' ),
						'mappingOptionsLabel'   => __( 'Options', 'reactwoo-geocore' ),
						'recommendedBadge'      => __( 'Recommended', 'reactwoo-geocore' ),
						'googleAdsOptStandard'  => __( 'Standard Google Ads UTM tracking', 'reactwoo-geocore' ),
						'googleAdsOptStandardDesc' => __( 'Matches utm_source=google AND utm_medium=cpc', 'reactwoo-geocore' ),
						'googleAdsShortStandard' => __( 'Standard UTM tracking', 'reactwoo-geocore' ),
						'googleAdsOptSource'    => __( 'Google source only', 'reactwoo-geocore' ),
						'googleAdsOptSourceDesc' => __( 'Matches utm_source=google', 'reactwoo-geocore' ),
						'googleAdsShortSource'  => __( 'Google source only', 'reactwoo-geocore' ),
						'googleAdsOptMedium'    => __( 'Paid click medium only', 'reactwoo-geocore' ),
						'googleAdsOptMediumDesc' => __( 'Matches utm_medium=cpc', 'reactwoo-geocore' ),
						'googleAdsShortMedium'    => __( 'Paid click medium only', 'reactwoo-geocore' ),
						'googleAdsOptGclid'     => __( 'Google click ID', 'reactwoo-geocore' ),
						'googleAdsOptGclidDesc' => __( 'Matches when gclid exists', 'reactwoo-geocore' ),
						'googleAdsShortGclid'   => __( 'Google click ID (gclid)', 'reactwoo-geocore' ),
						'googleAdsOptCustom'    => __( 'Custom mapping', 'reactwoo-geocore' ),
						'googleAdsOptCustomDesc' => __( 'Use this if your Google Ads links use non-standard tracking parameters.', 'reactwoo-geocore' ),
						'googleAdsShortCustom'  => __( 'Custom mapping', 'reactwoo-geocore' ),
						'googleAdsCustomMappingLabel' => __( 'Custom mapping', 'reactwoo-geocore' ),
						'googleAdsCustomMappingPlaceholder' => __( 'Example: utm_source=google-ads, source=adwords, or custom query rule', 'reactwoo-geocore' ),
						'googleAdsAdvancedLabel' => __( 'Advanced', 'reactwoo-geocore' ),
						'alsoDetectedLabel'     => __( 'Also detected', 'reactwoo-geocore' ),
						'alsoValidStatusLabel'  => __( 'Status', 'reactwoo-geocore' ),
						'urlExamplesLabel'      => __( 'Examples matched', 'reactwoo-geocore' ),
						'editUrlMatch'          => __( 'Edit URL match', 'reactwoo-geocore' ),
						'editUrlMatchTitle'     => __( 'Edit URL match', 'reactwoo-geocore' ),
						'urlMatchTypeLabel'     => __( 'Match type', 'reactwoo-geocore' ),
						'urlMatchValueLabel'    => __( 'Detected value', 'reactwoo-geocore' ),
						'urlMatchContains'      => __( 'Contains', 'reactwoo-geocore' ),
						'urlMatchStartsWith'    => __( 'Path starts with', 'reactwoo-geocore' ),
						'urlMatchExact'         => __( 'Exact path', 'reactwoo-geocore' ),
						'urlMatchWildcard'      => __( 'Wildcard', 'reactwoo-geocore' ),
						'urlMatchContainsHint'  => __( 'This matches any URL where %s appears.', 'reactwoo-geocore' ),
						'urlMatchWildcardHint'  => __( 'This matches child paths under the path but may not match parent segments like /shop/winter-sale.', 'reactwoo-geocore' ),
						'urlMatchApply'         => __( 'Apply URL match', 'reactwoo-geocore' ),
						'removeGoogleAdsCondition' => __( 'Remove Google Ads condition', 'reactwoo-geocore' ),
						'googleAdsChildLabel'   => __( 'Google Ads', 'reactwoo-geocore' ),
						'urlChildLabel'         => __( 'URL', 'reactwoo-geocore' ),
						'conditionGroupLabel'   => __( 'Condition group', 'reactwoo-geocore' ),
						'googleAdsNeedsMappingShort' => __( 'Google Ads needs mapping.', 'reactwoo-geocore' ),
						'childStatusValid'      => __( 'valid', 'reactwoo-geocore' ),
						'childNeedsMapping'     => __( 'needs mapping', 'reactwoo-geocore' ),
						'childNeedsAttention'   => __( 'needs attention', 'reactwoo-geocore' ),
						'pageTypeLabel'         => __( 'Page type', 'reactwoo-geocore' ),
						'cardResolveRemaining' => __( 'Some fields still need resolving before this setup can be created.', 'reactwoo-geocore' ),
						'cardPreviewSkipped' => __( 'preview only, nothing created', 'reactwoo-geocore' ),
						'setupConfirmed'  => __( 'Setup confirmed. Continue in the workflow.', 'reactwoo-geocore' ),
						'geoAiRequired'   => __( 'Natural-language commands require ReactWoo Geo AI.', 'reactwoo-geocore' ),
						'lowConfidence'   => __( 'Could not interpret that command.', 'reactwoo-geocore' ),
					),
				)
			);
		}
		if ( preg_match( '/(rwgc-suite-home|rwgc-getting-started|rwgc-workflow-variant|rwgc-suite-variants)/', $hook ) ) {
			wp_enqueue_style(
				'rwgc-suite-shell',
				RWGC_URL . 'admin/css/suite-admin.css',
				array( 'rwgc-suite' ),
				RWGC_VERSION
			);
		}
		if ( false !== strpos( $hook, 'rwgc-workflow-variant' ) ) {
			wp_enqueue_script(
				'rwgc-experience-workflow',
				RWGC_URL . 'admin/js/rwgc-experience-workflow.js',
				array( 'jquery' ),
				RWGC_VERSION,
				true
			);
		}
		wp_enqueue_script(
			'rwgc-admin',
			RWGC_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			RWGC_VERSION,
			true
		);

		// Rule builder + playground: RWGC_Targeting_Rule_Builder_Assets::enqueue_targeting_admin().
	}

	/**
	 * Keyword/capability hint groups for the targeting assistant cloud.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_assistant_keyword_hints() {
		return array(
			array(
				'label' => __( 'Actions', 'reactwoo-geocore' ),
				'items' => array(
					array( 'label' => __( 'create variant', 'reactwoo-geocore' ), 'insert' => __( 'Create a variant of this page for ', 'reactwoo-geocore' ) ),
					array( 'label' => __( 'show', 'reactwoo-geocore' ), 'insert' => __( 'Show this only in ', 'reactwoo-geocore' ) ),
					array( 'label' => __( 'hide', 'reactwoo-geocore' ), 'insert' => __( 'Hide this from ', 'reactwoo-geocore' ) ),
					array( 'label' => __( 'diagnose', 'reactwoo-geocore' ), 'insert' => __( 'Why is this not showing?', 'reactwoo-geocore' ) ),
				),
			),
			array(
				'label' => __( 'Conditions', 'reactwoo-geocore' ),
				'items' => array(
					array( 'label' => __( 'country', 'reactwoo-geocore' ), 'insert' => 'Australia' ),
					array( 'label' => __( 'device', 'reactwoo-geocore' ), 'insert' => 'mobile' ),
					array( 'label' => __( 'weather', 'reactwoo-geocore' ), 'insert' => __( 'when the weather is raining', 'reactwoo-geocore' ) ),
					array( 'label' => __( 'campaign', 'reactwoo-geocore' ), 'insert' => 'utm_source ' ),
				),
			),
			array(
				'label' => __( 'Examples', 'reactwoo-geocore' ),
				'items' => array(
					array( 'label' => 'Australia', 'insert' => 'Australia' ),
					array( 'label' => 'France', 'insert' => 'France' ),
					array( 'label' => 'Germany', 'insert' => 'Germany' ),
					array( 'label' => 'homepage', 'insert' => 'homepage' ),
				),
			),
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! self::can_manage() ) {
			return;
		}
		$settings = RWGC_Settings::get_settings();
		$status   = RWGC_MaxMind::get_status();
		$data     = RWGC_API::get_visitor_data();

		include RWGC_PATH . 'admin/views/dashboard-page.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! self::can_manage() ) {
			return;
		}
		$settings = RWGC_Settings::get_settings();
		include RWGC_PATH . 'admin/views/settings-page.php';
	}

	/**
	 * Render tools page.
	 *
	 * @return void
	 */
	public static function render_tools() {
		if ( ! self::can_manage() ) {
			return;
		}
		$settings = RWGC_Settings::get_settings();
		$status   = RWGC_MaxMind::get_status();
		$data     = RWGC_API::get_visitor_data();
		include RWGC_PATH . 'admin/views/tools-page.php';
	}

	/**
	 * Render usage page.
	 *
	 * @return void
	 */
	public static function render_usage() {
		if ( ! self::can_manage() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : 'rwgc-usage'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mode = 'geo';
		if ( 'rwgc-usage-audience' === $page ) {
			$mode = 'audience';
		} elseif ( 'rwgc-usage-campaign' === $page ) {
			$mode = 'campaign';
		}
		$status                = RWGC_MaxMind::get_status();
		$rwgc_rest_enabled     = (bool) RWGC_Settings::get( 'rest_enabled', 1 );
		$rwgc_location_url     = function_exists( 'rwgc_get_rest_location_url' ) ? rwgc_get_rest_location_url() : '';
		$rwgc_capabilities_url = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';
		$rwgc_insights_mode    = $mode;
		include RWGC_PATH . 'admin/views/usage-page.php';
	}

	/**
	 * Insights > Experience performance entry (Geo Optimise reports when installed).
	 *
	 * @return void
	 */
	public static function render_insights_experience_performance() {
		if ( ! self::can_manage() ) {
			return;
		}
		include RWGC_PATH . 'admin/views/insights-experience-performance-page.php';
	}

	/**
	 * Handle manual .mmdb upload from Integrations → MaxMind.
	 *
	 * @return void
	 */
	public static function handle_upload_mmdb() {
		if ( ! self::can_manage() ) {
			wp_die( -1 );
		}
		check_admin_referer( 'rwgc_upload_mmdb' );

		if ( empty( $_FILES['rwgc_mmdb']['tmp_name'] ) || ! is_uploaded_file( $_FILES['rwgc_mmdb']['tmp_name'] ) ) {
			add_settings_error( 'rwgc_maxmind', 'rwgc_upload_missing', __( 'No file uploaded or upload failed.', 'reactwoo-geocore' ), 'error' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ) );
			exit;
		}

		$file     = $_FILES['rwgc_mmdb'];
		$filename = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'mmdb' !== $ext ) {
			add_settings_error( 'rwgc_maxmind', 'rwgc_upload_ext', __( 'Invalid file type. Please upload a .mmdb MaxMind database file.', 'reactwoo-geocore' ), 'error' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ) );
			exit;
		}

		RWGC_MaxMind::ensure_storage_dir();
		$dest_dir  = RWGC_MaxMind::get_storage_dir();
		$dest_path = trailingslashit( $dest_dir ) . 'GeoLite2-Country.mmdb';

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Detected
			add_settings_error( 'rwgc_maxmind', 'rwgc_upload_move', __( 'Failed to move uploaded file into storage directory.', 'reactwoo-geocore' ), 'error' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ) );
			exit;
		}

		$settings                    = RWGC_Settings::get_settings();
		$settings['db_file_path']    = $dest_path;
		$settings['db_last_updated'] = gmdate( 'c' );
		$settings['db_last_error']   = '';
		RWGC_Settings::update( $settings );

		add_settings_error( 'rwgc_maxmind', 'rwgc_upload_success', __( 'MaxMind database uploaded successfully.', 'reactwoo-geocore' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ) );
		exit;
	}

	/**
	 * Suite targeting: registered target types.
	 *
	 * @return void
	 */
	public static function render_target_types() {
		if ( ! self::can_manage() ) {
			return;
		}
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Geo Core targeting API is not loaded.', 'reactwoo-geocore' ) . '</p></div>';
			return;
		}
		RWGC_Target_Registry::init();
		$rwgc_target_types = function_exists( 'rwgc_get_target_types' ) ? rwgc_get_target_types() : array();
		$rwgc_provider_rows = array();
		$classes            = apply_filters(
			'rwgc_target_provider_classes',
			array(
				'RWGC_Target_Provider_Geo',
				'RWGC_Target_Provider_Language',
				'RWGC_Target_Provider_Time',
				'RWGC_Target_Provider_Device',
				'RWGC_Target_Provider_Weather',
				'RWGC_Target_Provider_Analytics',
				'RWGC_Target_Provider_Commerce',
			)
		);
		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			$obj = new $class();
			if ( ! $obj instanceof RWGC_Target_Provider_Interface ) {
				continue;
			}
			$rwgc_provider_rows[] = array_merge(
				array( 'key' => $obj->get_provider_key() ),
				$obj->get_admin_status()
			);
		}
		$rwgc_pro_enabled          = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
		$rwgc_portable_ctx         = function_exists( 'rwgc_get_portable_targeting_editor_context' ) ? rwgc_get_portable_targeting_editor_context() : array();
		$rwgc_use_platform_shell   = class_exists( 'RWGC_Admin_App_Shell', false ) && RWGC_Admin_App_Shell::should_render();
		include RWGC_PATH . 'admin/views/target-types-page.php';
	}

	/**
	 * Render Targeting > Audiences entry screen.
	 *
	 * @return void
	 */
	public static function render_targeting_audiences() {
		if ( ! self::can_manage() ) {
			return;
		}
		include RWGC_PATH . 'admin/views/targeting-audiences-page.php';
	}

	/**
	 * Render Targeting > Campaigns entry screen.
	 *
	 * @return void
	 */
	public static function render_targeting_campaigns() {
		if ( ! self::can_manage() ) {
			return;
		}
		include RWGC_PATH . 'admin/views/targeting-campaigns-page.php';
	}

	/**
	 * Render Integrations > Gutenberg entry screen.
	 *
	 * @return void
	 */
	public static function render_integrations_gutenberg() {
		if ( ! self::can_manage() ) {
			return;
		}
		include RWGC_PATH . 'admin/views/integrations-gutenberg-page.php';
	}

	/**
	 * Render Integrations > WooCommerce entry screen.
	 *
	 * @return void
	 */
	/**
	 * Integrations → MaxMind (credentials + country database).
	 *
	 * @return void
	 */
	public static function render_integrations_maxmind() {
		if ( ! self::can_manage() ) {
			return;
		}
		$settings = RWGC_Settings::get_settings();
		$status   = RWGC_MaxMind::get_status();
		$data     = RWGC_API::get_visitor_data();
		include RWGC_PATH . 'admin/views/integrations-maxmind-page.php';
	}

	/**
	 * Save MaxMind credentials from the integrations screen (merge with existing settings).
	 *
	 * @return void
	 */
	public static function handle_save_maxmind_settings() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized', 'reactwoo-geocore' ), 403 );
		}
		check_admin_referer( 'rwgc_save_maxmind_settings' );

		$settings = RWGC_Settings::get_settings();
		if ( isset( $_POST['maxmind_account_id'] ) ) {
			$settings['maxmind_account_id'] = sanitize_text_field( wp_unslash( $_POST['maxmind_account_id'] ) );
		}
		if ( isset( $_POST['maxmind_license_key'] ) ) {
			$settings['maxmind_license_key'] = sanitize_text_field( wp_unslash( $_POST['maxmind_license_key'] ) );
		}
		$settings['auto_update_db'] = ! empty( $_POST['auto_update_db'] ) ? 1 : 0;
		RWGC_Settings::update( $settings );

		add_settings_error( 'rwgc_maxmind', 'rwgc_maxmind_saved', __( 'MaxMind credentials saved.', 'reactwoo-geocore' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ) );
		exit;
	}

	public static function render_integrations_woocommerce() {
		if ( ! self::can_manage() ) {
			return;
		}
		include RWGC_PATH . 'admin/views/integrations-woocommerce-page.php';
	}

	/**
	 * Settings → AI Data Snapshot preview (compact site intelligence for Geo AI).
	 *
	 * @return void
	 */
	public static function render_ai_snapshot_preview() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_ai_snapshot    = function_exists( 'rwgc_build_ai_snapshot' ) ? rwgc_build_ai_snapshot() : array();
		$rwgc_ai_sync_status = class_exists( 'RWGC_AI_Snapshot_Sync_Status', false )
			? RWGC_AI_Snapshot_Sync_Status::get_status()
			: array();
		include RWGC_PATH . 'admin/views/ai-snapshot-preview-page.php';
	}

	/**
	 * Suite targeting: simulate context values.
	 *
	 * @return void
	 */
	public static function render_context_preview() {
		if ( ! self::can_manage() ) {
			return;
		}
		$overrides = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview fields.
		if ( ! empty( $_GET['rwgc_preview'] ) && '1' === (string) wp_unslash( $_GET['rwgc_preview'] ) ) {
			$keys = array( 'country', 'language', 'locale', 'device_type', 'time_of_day', 'day_of_week', 'currency', 'weather_facet' );
			foreach ( $keys as $k ) {
				if ( isset( $_GET[ 'rwgc_' . $k ] ) ) {
					$overrides[ $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ 'rwgc_' . $k ] ) );
				}
			}
			if ( isset( $_GET['rwgc_weather_facet'] ) && is_array( $_GET['rwgc_weather_facet'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$parts = array_map(
					static function ( $item ) {
						return sanitize_key( (string) wp_unslash( $item ) );
					},
					wp_unslash( $_GET['rwgc_weather_facet'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				);
				$overrides['weather_facet'] = implode( ',', array_filter( $parts ) );
			}
		}
		$rwgc_preview_snapshot = function_exists( 'rwgc_resolve_preview_context' )
			? rwgc_resolve_preview_context( $overrides )
			: array();
		include RWGC_PATH . 'admin/views/context-preview-page.php';
	}

	/**
	 * Suite targeting: provider status.
	 *
	 * @return void
	 */
	public static function render_target_providers() {
		if ( ! self::can_manage() ) {
			return;
		}
		if ( ! interface_exists( 'RWGC_Target_Provider_Interface', false ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Geo Core targeting API is not loaded.', 'reactwoo-geocore' ) . '</p></div>';
			return;
		}
		RWGC_Target_Registry::init();
		$rwgc_provider_rows = array();
		$classes            = apply_filters(
			'rwgc_target_provider_classes',
			array(
				'RWGC_Target_Provider_Geo',
				'RWGC_Target_Provider_Language',
				'RWGC_Target_Provider_Time',
				'RWGC_Target_Provider_Device',
				'RWGC_Target_Provider_Weather',
				'RWGC_Target_Provider_Analytics',
				'RWGC_Target_Provider_Commerce',
			)
		);
		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			$obj = new $class();
			if ( ! $obj instanceof RWGC_Target_Provider_Interface ) {
				continue;
			}
			$rwgc_provider_rows[] = array_merge(
				array( 'key' => $obj->get_provider_key() ),
				$obj->get_admin_status()
			);
		}
		include RWGC_PATH . 'admin/views/target-providers-page.php';
	}

	/**
	 * Render add-ons page.
	 *
	 * @return void
	 */
	public static function render_addons() {
		if ( ! self::can_manage() ) {
			return;
		}
		$addons = RWGC_Upsells::get_addons();
		include RWGC_PATH . 'admin/views/addons-page.php';
	}

	/**
	 * Render an inner navigation row for Geo Core pages.
	 *
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_inner_nav( $current ) {
		if ( function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell() ) {
			if ( 'rwgc-dashboard' === (string) $current ) {
				self::render_geocore_pro_status_card( (string) $current );
			}
			return;
		}

		$items = array(
			'rwgc-dashboard'      => __( 'Overview', 'reactwoo-geocore' ),
			'rwgc-target-types'   => __( 'Rule builder', 'reactwoo-geocore' ),
			'rwgc-usage'          => __( 'Geo reports', 'reactwoo-geocore' ),
			'rwgc-tools'          => __( 'Tools', 'reactwoo-geocore' ),
			'rwgc-settings'       => __( 'Settings', 'reactwoo-geocore' ),
			'rwgc-addons'         => __( 'Add-ons', 'reactwoo-geocore' ),
		);

		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_inner_nav(
				$items,
				(string) $current,
				array(
					'filter'     => 'rwgc_inner_nav_items',
					'aria_label' => __( 'Geo Core section navigation', 'reactwoo-geocore' ),
				)
			);
		}

		self::render_geocore_pro_status_card( (string) $current );
	}

	/**
	 * GeoCore Pro status and link to license / cloud settings (when Pro is active or relevant).
	 *
	 * @param string $current Current Geo Core admin page slug.
	 * @return void
	 */
	public static function render_geocore_pro_status_card( $current = '' ) {
		if ( ! self::can_manage() ) {
			return;
		}

		/**
		 * Replace the default GeoCore Pro status card HTML (return non-empty string to skip default output).
		 *
		 * @param string $html    Empty string by default; non-empty replaces entire card.
		 * @param string $current Current Geo Core admin page slug.
		 */
		$custom = apply_filters( 'rwgc_geocore_pro_status_card_html', '', $current );
		if ( is_string( $custom ) && '' !== $custom ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filter authors own escaping policy.
			echo $custom;
			return;
		}

		$pro_main_file = trailingslashit( WP_PLUGIN_DIR ) . 'reactwoo-geocore-pro/reactwoo-geocore-pro.php';
		$pro_installed = is_readable( $pro_main_file );
		$pro_on        = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
		$show_upsell   = in_array( $current, array( 'rwgc-dashboard', 'rwgc-settings' ), true );

		if ( $pro_on ) {
			$license_key = (string) get_option( 'rwgcp_license_key', '' );
			$token       = (string) get_option( 'rwgcp_access_token', '' );
			$expires_at  = (int) get_option( 'rwgcp_token_expires_at', 0 );
			$profiles    = get_option( 'rwgcp_profiles_cache', array() );
			$profile_n   = is_array( $profiles ) ? count( $profiles ) : 0;

			$masked = '';
			if ( strlen( $license_key ) > 8 ) {
				$masked = str_repeat( '•', max( 0, strlen( $license_key ) - 4 ) ) . substr( $license_key, -4 );
			} elseif ( '' !== $license_key ) {
				$masked = str_repeat( '•', min( 8, strlen( $license_key ) ) );
			}

			$cloud = ( '' !== $token );
			if ( $cloud && $expires_at > 0 && $expires_at < time() ) {
				$cloud = false;
			}

			$matched_id = '';
			if ( function_exists( 'rwgc_get_context_snapshot' ) ) {
				$snap = rwgc_get_context_snapshot();
				if ( is_array( $snap ) && isset( $snap['matched_profile'] ) && is_array( $snap['matched_profile'] ) && ! empty( $snap['matched_profile']['profile_id'] ) ) {
					$matched_id = (string) $snap['matched_profile']['profile_id'];
				}
			}

			echo '<div class="rwgc-pro-status rwgc-pro-status--active" role="region" aria-label="' . esc_attr__( 'GeoCore Pro status', 'reactwoo-geocore' ) . '">';
			echo '<div class="rwgc-pro-status__head">';
			echo '<strong class="rwgc-pro-status__title">' . esc_html__( 'GeoCore Pro', 'reactwoo-geocore' ) . '</strong>';
			echo '<span class="rwgc-pro-status__badge">' . esc_html__( 'Active', 'reactwoo-geocore' ) . '</span>';
			echo '</div>';
			echo '<ul class="rwgc-pro-status__list">';
			echo '<li>' . esc_html__( 'Unlocks campaign, attribution, and experience profile targeting inside Geo Core.', 'reactwoo-geocore' ) . '</li>';
			echo '<li><strong>' . esc_html__( 'License key', 'reactwoo-geocore' ) . ':</strong> ';
			if ( '' !== $license_key ) {
				echo '<code>' . esc_html( $masked ) . '</code>';
			} else {
				echo esc_html__( 'Not saved yet', 'reactwoo-geocore' );
			}
			echo '</li>';
			echo '<li><strong>' . esc_html__( 'Cloud', 'reactwoo-geocore' ) . ':</strong> ';
			echo $cloud ? esc_html__( 'Connected', 'reactwoo-geocore' ) : esc_html__( 'Not connected — add your licence in GeoCore Pro', 'reactwoo-geocore' );
			echo '</li>';
			echo '<li><strong>' . esc_html__( 'Cached experience profiles', 'reactwoo-geocore' ) . ':</strong> ' . esc_html( (string) (int) $profile_n ) . '</li>';
			if ( '' !== $matched_id ) {
				echo '<li><strong>' . esc_html__( 'Current context profile (admin preview)', 'reactwoo-geocore' ) . ':</strong> <code>' . esc_html( $matched_id ) . '</code></li>';
			}
			echo '</ul>';
			if ( current_user_can( 'manage_options' ) ) {
				$url = admin_url( 'admin.php?page=rwgcp-geocore-pro' );
				echo '<p class="rwgc-pro-status__actions"><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open GeoCore Pro', 'reactwoo-geocore' ) . '</a></p>';
			} else {
				echo '<p class="description">' . esc_html__( 'Ask a site administrator to enter the GeoCore Pro license under Settings → GeoCore Pro.', 'reactwoo-geocore' ) . '</p>';
			}
			echo '</div>';
			return;
		}

		if ( $pro_installed ) {
			echo '<div class="rwgc-pro-status rwgc-pro-status--inactive" role="region" aria-label="' . esc_attr__( 'GeoCore Pro status', 'reactwoo-geocore' ) . '">';
			echo '<p><strong>' . esc_html__( 'GeoCore Pro is installed but not active', 'reactwoo-geocore' ) . '</strong> ';
			echo esc_html__( 'Activate it to enable premium runtime features.', 'reactwoo-geocore' ) . '</p>';
			if ( current_user_can( 'activate_plugins' ) ) {
				$url = admin_url( 'plugins.php' );
				echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Open Plugins', 'reactwoo-geocore' ) . '</a></p>';
			}
			echo '</div>';
			return;
		}

		if ( $show_upsell ) {
			echo '<div class="rwgc-pro-status rwgc-pro-status--upsell" role="region" aria-label="' . esc_attr__( 'GeoCore Pro', 'reactwoo-geocore' ) . '">';
			echo '<p><strong>' . esc_html__( 'GeoCore Pro', 'reactwoo-geocore' ) . '</strong> ';
			echo esc_html__( 'unlocks campaign, attribution, and profile-based targeting.', 'reactwoo-geocore' );
			echo ' ';
			echo esc_html__( 'Install and connect GeoCore Pro to enable advanced targeting signals.', 'reactwoo-geocore' );
			echo '</p>';
			echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ) . '">' . esc_html__( 'Browse add-ons', 'reactwoo-geocore' ) . '</a></p>';
			echo '</div>';
		}
	}

	/**
	 * Show admin notices for missing license/DB/etc.
	 *
	 * @return void
	 */
	public static function maybe_show_admin_notices() {
		if ( ! self::can_manage() ) {
			return;
		}

		if ( class_exists( 'RWGC_Admin_Platform', false ) && RWGC_Admin_Platform::is_hub_screen() ) {
			// Platform shell renders notices in rwgc_platform_admin_notices.
		} elseif ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( ! $screen || strpos( (string) $screen->id, 'rwgc-' ) === false ) {
				return;
			}
		}

		$status   = RWGC_MaxMind::get_status();
		$settings = RWGC_Settings::get_settings();

		$maxmind_url = function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' );

		if ( empty( $settings['maxmind_license_key'] ) ) {
			printf(
				'<div class="notice notice-warning rwgc-notice"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'ReactWoo Geo Core: MaxMind license key is not configured. GeoIP lookups will use fallback values.', 'reactwoo-geocore' ),
				esc_url( $maxmind_url ),
				esc_html__( 'Open MaxMind integration', 'reactwoo-geocore' )
			);
		} elseif ( ! $status['exists'] ) {
			if ( ! empty( $status['last_error'] ) ) {
				printf(
					'<div class="notice notice-warning rwgc-notice"><p>%1$s <a href="%2$s">%3$s</a></p><p><code>%4$s</code></p></div>',
					esc_html__( 'ReactWoo Geo Core: MaxMind database not found. Last error:', 'reactwoo-geocore' ),
					esc_url( $maxmind_url ),
					esc_html__( 'Download or upload the database', 'reactwoo-geocore' ),
					esc_html( $status['last_error'] )
				);
			} else {
				printf(
					'<div class="notice notice-warning rwgc-notice"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
					esc_html__( 'ReactWoo Geo Core: MaxMind country database not found.', 'reactwoo-geocore' ),
					esc_url( $maxmind_url ),
					esc_html__( 'Download or upload the database', 'reactwoo-geocore' )
				);
			}
		} elseif ( $status['is_stale'] ) {
			printf(
				'<div class="notice notice-info rwgc-notice"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'ReactWoo Geo Core: MaxMind database may be stale.', 'reactwoo-geocore' ),
				esc_url( $maxmind_url ),
				esc_html__( 'Refresh the database', 'reactwoo-geocore' )
			);
		}
	}

	/**
	 * Register page-level variant routing meta box.
	 *
	 * @return void
	 */
	public static function register_page_meta_box() {
		add_meta_box(
			'rwgc-page-routing',
			__( 'Geo Variant Routing (Free)', 'reactwoo-geocore' ),
			array( __CLASS__, 'render_page_meta_box' ),
			'page',
			'side',
			'default'
		);
	}

	/**
	 * Render page-level routing controls.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render_page_meta_box( $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$config = RWGC_Routing::get_page_route_config( (int) $post->ID );
		wp_nonce_field( 'rwgc_page_routing_save', 'rwgc_page_routing_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="rwgc_route_enabled" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> />
				<?php esc_html_e( 'Enable geo routing for this page', 'reactwoo-geocore' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Free flow: set one page as Master (default), then create one Variant page per country linked to that Master.', 'reactwoo-geocore' ); ?></p>

		<p><strong><?php esc_html_e( 'Page role', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<select name="rwgc_route_role" id="rwgc_route_role">
				<option value="master" <?php selected( 'master', (string) $config['role'] ); ?>><?php esc_html_e( 'Master (default page)', 'reactwoo-geocore' ); ?></option>
				<option value="variant" <?php selected( 'variant', (string) $config['role'] ); ?>><?php esc_html_e( 'Secondary (country-specific page)', 'reactwoo-geocore' ); ?></option>
			</select>
		</p>

		<p><strong><?php esc_html_e( 'Secondary country', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<label for="rwgc_route_country_iso2" class="screen-reader-text"><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></label>
			<?php
			self::render_country_select(
				'rwgc_route_country_iso2',
				(string) $config['country_iso2'],
				array(
					'id'                => 'rwgc_route_country_iso2',
					'class'             => 'rwgc-select-country widefat',
					'show_option_none'  => __( '-- Select country --', 'reactwoo-geocore' ),
					'option_none_value' => '',
				)
			);
			?>
		</p>
		<p><strong><?php esc_html_e( 'Secondary links to this master page', 'reactwoo-geocore' ); ?></strong></p>
		<p>
			<?php
			wp_dropdown_pages(
				array(
					'name'             => 'rwgc_route_master_page_id',
					'id'               => 'rwgc_route_master_page_id',
					'show_option_none' => __( '-- Select master page --', 'reactwoo-geocore' ),
					'option_none_value'=> '0',
					'selected'         => (int) $config['master_page_id'],
				)
			);
			?>
		</p>
		<p class="description"><?php esc_html_e( 'Tip: leave this page as Master for your default audience. On secondary pages, set role to Secondary and select this master page + a country code.', 'reactwoo-geocore' ); ?></p>
		<p class="description"><?php esc_html_e( 'Need multiple country variants per page? Use GeoElementor advanced routing.', 'reactwoo-geocore' ); ?></p>
		<?php
	}

	/**
	 * Save page-level routing controls.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_page_meta_box( $post_id ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$nonce = isset( $_POST['rwgc_page_routing_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgc_page_routing_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rwgc_page_routing_save' ) ) {
			return;
		}

		$config = array(
			'enabled'         => ! empty( $_POST['rwgc_route_enabled'] ),
			'default_page_id' => isset( $_POST['rwgc_route_default_page_id'] ) ? absint( wp_unslash( $_POST['rwgc_route_default_page_id'] ) ) : 0,
			'country_iso2'    => isset( $_POST['rwgc_route_country_iso2'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgc_route_country_iso2'] ) ) : '',
			'country_page_id' => isset( $_POST['rwgc_route_country_page_id'] ) ? absint( wp_unslash( $_POST['rwgc_route_country_page_id'] ) ) : 0,
			'role'            => isset( $_POST['rwgc_route_role'] ) ? sanitize_key( wp_unslash( $_POST['rwgc_route_role'] ) ) : 'master',
			'master_page_id'  => isset( $_POST['rwgc_route_master_page_id'] ) ? absint( wp_unslash( $_POST['rwgc_route_master_page_id'] ) ) : 0,
		);

		if ( ! empty( $config['enabled'] ) && 'variant' === $config['role'] ) {
			if ( empty( $config['master_page_id'] ) || empty( $config['country_iso2'] ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_variant_missing_fields', __( 'Secondary page requires both a master page and a country code.', 'reactwoo-geocore' ), 'error' );
				$config['enabled'] = false;
			} elseif ( RWGC_Routing::master_has_variant( (int) $config['master_page_id'], (int) $post_id ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_variant_limit_reached', __( 'Free limit reached: this master page already has one variant. Upgrade to GeoElementor for multiple variants.', 'reactwoo-geocore' ), 'error' );
				$config['enabled'] = false;
			} elseif ( RWGC_Routing::is_variant_country_taken( (int) $config['master_page_id'], (string) $config['country_iso2'], (int) $post_id ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_variant_duplicate_country', __( 'That country is already assigned to another variant for this master page.', 'reactwoo-geocore' ), 'error' );
				$config['enabled'] = false;
			}
		}

		RWGC_Routing::save_page_route_config( $post_id, $config );
	}

	/**
	 * Output a prepopulated country &lt;select&gt; (no free-typed ISO2).
	 *
	 * @param string       $name     Input name.
	 * @param string       $selected Current ISO2 (uppercase).
	 * @param array<string, mixed> $args {
	 *   @type string $id               Element id (default: $name).
	 *   @type string $class            CSS classes.
	 *   @type string $show_option_none Label for empty option; empty string to omit.
	 *   @type string $option_none_value Value for empty option.
	 * }
	 * @return void
	 */
	public static function render_country_select( $name, $selected, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'                 => $name,
				'class'              => 'rwgc-select-country regular-text',
				'show_option_none'   => __( '-- Select country --', 'reactwoo-geocore' ),
				'option_none_value'  => '',
			)
		);
		$countries = RWGC_Countries::get_options();
		$selected  = strtoupper( substr( (string) $selected, 0, 2 ) );
		printf(
			'<select name="%1$s" id="%2$s" class="%3$s">',
			esc_attr( $name ),
			esc_attr( $args['id'] ),
			esc_attr( $args['class'] )
		);
		if ( '' !== $args['show_option_none'] ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $args['option_none_value'] ),
				selected( $selected, (string) $args['option_none_value'], false ),
				esc_html( $args['show_option_none'] )
			);
		}
		foreach ( $countries as $code => $label ) {
			$code = strtoupper( (string) $code );
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $code ),
				selected( $selected, $code, false ),
				esc_html( $label . ' (' . $code . ')' )
			);
		}
		echo '</select>';
	}

	/**
	 * Output a prepopulated currency &lt;select&gt; (ISO3).
	 *
	 * @param string               $name     Input name.
	 * @param string               $selected Current ISO3.
	 * @param array<string, mixed> $args     Same shape as {@see render_country_select()}.
	 * @return void
	 */
	public static function render_currency_select( $name, $selected, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'                => $name,
				'class'             => 'rwgc-select-currency regular-text',
				'show_option_none'  => '',
				'option_none_value' => '',
			)
		);
		$currencies = RWGC_Countries::get_currency_options();
		$selected   = strtoupper( substr( (string) $selected, 0, 3 ) );
		printf(
			'<select name="%1$s" id="%2$s" class="%3$s">',
			esc_attr( $name ),
			esc_attr( $args['id'] ),
			esc_attr( $args['class'] )
		);
		if ( '' !== $args['show_option_none'] ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $args['option_none_value'] ),
				selected( $selected, (string) $args['option_none_value'], false ),
				esc_html( $args['show_option_none'] )
			);
		}
		foreach ( $currencies as $code => $label ) {
			$code = strtoupper( substr( (string) $code, 0, 3 ) );
			$lab  = is_string( $label ) ? wp_strip_all_tags( $label ) : $code;
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $code ),
				selected( $selected, $code, false ),
				esc_html( $lab )
			);
		}
		echo '</select>';
	}
}

