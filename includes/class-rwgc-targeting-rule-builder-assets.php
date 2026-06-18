<?php
/**
 * Shared “Who should see this?” rule builder (Elementor, block editor, wp-admin surfaces).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and enqueues {@see RWGC_Targeting_Rule_Builder_Assets::SCRIPT_HANDLE} where portable rules are edited.
 */
class RWGC_Targeting_Rule_Builder_Assets {

	const SCRIPT_HANDLE = 'rwgc-rule-builder';
	const STYLE_HANDLE  = 'rwgc-rule-builder';

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_scripts' ), 6 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( __CLASS__, 'enqueue_elementor' ), 6 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor' ), 4 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_targeting_admin' ), 15 );
	}

	/**
	 * Register script/style once.
	 *
	 * @return void
	 */
	public static function register_scripts() {
		if ( wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			return;
		}
		wp_register_style(
			self::STYLE_HANDLE,
			RWGC_URL . 'assets/css/rwgc-rule-builder.css',
			array(),
			RWGC_VERSION
		);
		wp_register_script(
			self::SCRIPT_HANDLE,
			RWGC_URL . 'assets/js/rwgc-rule-builder.js',
			array( 'jquery' ),
			RWGC_VERSION,
			true
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'rwgcRuleBuilderI18n',
			self::get_js_strings()
		);
	}

	/**
	 * Fresh editor context (weather facets, Pro sync lists) when the script is enqueued.
	 *
	 * @return void
	 */
	public static function localize_editor_context() {
		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'registered' ) ) {
			return;
		}
		$ctx = function_exists( 'rwgc_get_portable_targeting_editor_context' ) ? rwgc_get_portable_targeting_editor_context() : array();
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'rwgcRuleBuilderContext',
			is_array( $ctx ) ? $ctx : array()
		);
	}

	/**
	 * Localized UI strings (merged in JS with editor context).
	 *
	 * @return array<string, string>
	 */
	public static function get_js_strings() {
		return array(
			'whoHeading'           => __( 'Who should see this?', 'reactwoo-geocore' ),
			'matchAll'             => __( 'Match all conditions', 'reactwoo-geocore' ),
			'matchAny'             => __( 'Match any condition', 'reactwoo-geocore' ),
			'matchConditionsLabel' => __( 'Conditions', 'reactwoo-geocore' ),
			'addCondition'         => __( 'Add condition', 'reactwoo-geocore' ),
			'remove'               => __( 'Remove', 'reactwoo-geocore' ),
			'duplicate'            => __( 'Duplicate', 'reactwoo-geocore' ),
			'clearRule'            => __( 'Clear rule', 'reactwoo-geocore' ),
			'fieldLabel'           => __( 'When', 'reactwoo-geocore' ),
			'operatorLabel'        => __( 'Match style', 'reactwoo-geocore' ),
			'valueLabel'           => __( 'Selection', 'reactwoo-geocore' ),
			'opIs'                 => __( 'is', 'reactwoo-geocore' ),
			'opIsNot'              => __( 'is not', 'reactwoo-geocore' ),
			'opIncludesAny'        => __( 'includes any of', 'reactwoo-geocore' ),
			'opExcludes'           => __( 'excludes', 'reactwoo-geocore' ),
			'opEmpty'              => __( 'is empty', 'reactwoo-geocore' ),
			'opNotEmpty'           => __( 'is not empty', 'reactwoo-geocore' ),
			'fieldCountry'         => __( 'Visitor country', 'reactwoo-geocore' ),
			'fieldGa4Audience'     => __( 'GA4 audience', 'reactwoo-geocore' ),
			'fieldAdsCampaign'     => __( 'Google Ads campaign', 'reactwoo-geocore' ),
			'fieldUtmCampaign'     => __( 'UTM campaign', 'reactwoo-geocore' ),
			'fieldUtmSource'       => __( 'UTM source', 'reactwoo-geocore' ),
			'fieldUtmMedium'       => __( 'UTM medium', 'reactwoo-geocore' ),
			'fieldDevice'          => __( 'Device type', 'reactwoo-geocore' ),
			'fieldLoggedIn'        => __( 'Logged-in status', 'reactwoo-geocore' ),
			'fieldWeatherFacet'    => __( 'Shopping weather', 'reactwoo-geocore' ),
			'pickWeatherFacets'    => __( 'Choose weather types', 'reactwoo-geocore' ),
			'noWeatherTitle'       => __( 'Weather provider not configured.', 'reactwoo-geocore' ),
			'connectWeather'       => __( 'Connect weather', 'reactwoo-geocore' ),
			'pickCountries'        => __( 'Choose countries', 'reactwoo-geocore' ),
			'pickAudiences'        => __( 'Choose an audience', 'reactwoo-geocore' ),
			'pickCampaigns'        => __( 'Choose campaigns', 'reactwoo-geocore' ),
			'searchPlaceholder'    => __( 'Search…', 'reactwoo-geocore' ),
			'sourceGa4'            => __( 'GA4', 'reactwoo-geocore' ),
			'sourceGoogleAds'      => __( 'Google Ads', 'reactwoo-geocore' ),
			'summaryReady'         => __( 'This rule is ready.', 'reactwoo-geocore' ),
			'summaryIncomplete'    => __( 'Complete the highlighted fields to activate this rule.', 'reactwoo-geocore' ),
			'summaryMultiRules'    => __( 'This document has multiple rule groups. The builder edits the first group; use advanced view for the rest.', 'reactwoo-geocore' ),
			'unsupportedCard'      => __( 'Unsupported condition', 'reactwoo-geocore' ),
			'advancedToggle'       => __( 'Advanced: view or edit stored data', 'reactwoo-geocore' ),
			'advancedWarning'      => __( 'Only edit this if you understand how saved visibility data is structured.', 'reactwoo-geocore' ),
			'jsonInvalid'          => __( 'That data is not valid and cannot be saved. Fix the advanced view or discard changes.', 'reactwoo-geocore' ),
			'proNotice'            => __( 'GA4 audience and Google Ads campaign choices need GeoCore Pro and a completed account sync.', 'reactwoo-geocore' ),
			'noAudiencesTitle'     => __( 'No GA4 audiences found yet.', 'reactwoo-geocore' ),
			'noCampaignsTitle'     => __( 'No Google Ads campaigns found yet.', 'reactwoo-geocore' ),
			'connectGa4'           => __( 'Connect GA4', 'reactwoo-geocore' ),
			'syncAudiences'        => __( 'Sync audiences', 'reactwoo-geocore' ),
			'learnAudiences'       => __( 'Learn how audience targeting works', 'reactwoo-geocore' ),
			'connectAds'           => __( 'Connect Google Ads', 'reactwoo-geocore' ),
			'syncCampaigns'        => __( 'Sync campaigns', 'reactwoo-geocore' ),
			'loggedInYes'          => __( 'Logged in', 'reactwoo-geocore' ),
			'loggedInNo'           => __( 'Logged out', 'reactwoo-geocore' ),
			'booleanHint'          => __( 'Applies to WordPress login state.', 'reactwoo-geocore' ),
			'visibilityModeLabel'  => __( 'Visibility mode', 'reactwoo-geocore' ),
			'visibilityShow'       => __( 'Show only when rules match', 'reactwoo-geocore' ),
			'visibilityHide'       => __( 'Hide when rules match', 'reactwoo-geocore' ),
			'selectedLabel'        => __( 'Selected', 'reactwoo-geocore' ),
			'playgroundIntro'      => __( 'Try the same rule builder used in Elementor and the Geo Content block. Changes here are for practice only until you paste them into a page, block, or geo rule.', 'reactwoo-geocore' ),
			'syncedCount'          => __( '%1$d synced', 'reactwoo-geocore' ),
			'enableAdvancedHint'   => __( 'Turn on “Use visibility rule builder” above to edit multi-condition rules.', 'reactwoo-geocore' ),
			'noConditionsYet'      => __( 'Add at least one condition to define who should see this content.', 'reactwoo-geocore' ),
			'summaryPrefixShow'    => __( 'This content will only be visible to visitors who match these targeting rules:', 'reactwoo-geocore' ),
			'summaryPrefixHide'    => __( 'This content will be hidden from visitors who match these targeting rules:', 'reactwoo-geocore' ),
			'fieldPageVersion'     => __( 'Page Version URL', 'reactwoo-geocore' ),
			'pageVersionNameLabel' => __( 'Version name', 'reactwoo-geocore' ),
			'pageVersionPageLabel' => __( 'Page', 'reactwoo-geocore' ),
			'pageVersionPlaceholder' => __( 'campaign_name', 'reactwoo-geocore' ),
			'pageVersionHelper'    => __( 'Visitors using this URL will see the targeted version of this page.', 'reactwoo-geocore' ),
			'pageVersionPattern'   => __( 'Page Version URL:', 'reactwoo-geocore' ),
			'pageVersionSummary'   => __( 'This content will show only on %s.', 'reactwoo-geocore' ),
			'pageVersionFullUrl'   => __( 'Full URL to test or share', 'reactwoo-geocore' ),
			'pageVersionCopy'      => __( 'Copy URL', 'reactwoo-geocore' ),
			'pageVersionCopied'    => __( 'Copied', 'reactwoo-geocore' ),
			'pageVersionOpen'      => __( 'Open in new tab', 'reactwoo-geocore' ),
			'pageVersionInvalid'   => __( 'Enter only the version name, such as campaign_name.', 'reactwoo-geocore' ),
			'pageVersionChars'     => __( 'Use letters, numbers, hyphens, and underscores only.', 'reactwoo-geocore' ),
			'pageVersionLength'    => __( 'Keep the version name under 80 characters.', 'reactwoo-geocore' ),
			'pageVersionPickPage'  => __( 'Choose a page first, then enter the version name.', 'reactwoo-geocore' ),
			'libraryLabel'         => __( 'Apply saved visibility rule', 'reactwoo-geocore' ),
			'libraryNone'          => __( '— Choose from library —', 'reactwoo-geocore' ),
			'libraryHelp'          => __( 'Portable library rules only (Targeting → Visibility rules). You can still edit conditions after applying.', 'reactwoo-geocore' ),
			'advancedTargetingNotice' => __( 'Additional condition types require GeoCore Pro. Country and shopping weather (when configured) are always available.', 'reactwoo-geocore' ),
		);
	}

	/**
	 * Geo Core → Targeting admin playground.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_targeting_admin( $hook ) {
		$is_playground = false !== strpos( $hook, 'rwgc-target-types' );
		$is_library    = false !== strpos( $hook, 'rwgc-visibility-rules' );
		if ( ! $is_playground && ! $is_library ) {
			return;
		}
		self::enqueue_admin();
		if ( $is_playground ) {
			wp_add_inline_script( self::SCRIPT_HANDLE, self::get_mount_playground_inline(), 'after' );
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_edit'] ) ) {
			wp_add_inline_script( self::SCRIPT_HANDLE, self::get_mount_inline( '#rwgc_portable_targeting', "'show_if'", 'allowAllConditionTypes:true' ), 'after' );
		}
	}

	/**
	 * Mount rule builder on a textarea selector (admin / third-party).
	 *
	 * @param string $selector   CSS selector for textarea.
	 * @param string $get_mode_js Optional JS expression returning show_if|hide_if (default show_if).
	 * @return string Inline script.
	 */
	public static function get_mount_inline( $selector, $get_mode_js = "'show_if'", $extra_options_js = '' ) {
		$selector = esc_js( (string) $selector );
		$get_mode = $get_mode_js ? $get_mode_js : "'show_if'";
		$extra    = $extra_options_js ? ',' . $extra_options_js : '';
		return "(function(){function rwgcAssistantPrefill(t){try{var raw=sessionStorage.getItem('rwgc_targeting_assistant_portable');if(!raw||!t){return;}var data=JSON.parse(raw);if(!data||!data.portable_rule_set){return;}t.value=JSON.stringify(data.portable_rule_set,null,2);sessionStorage.removeItem('rwgc_targeting_assistant_portable');}catch(e){}}function rwgcRbTryMount(){var t=document.querySelector('{$selector}');if(!t||!window.ReactWooRuleBuilder||t.getAttribute('data-rwgc-rb-mounted')){return;}rwgcAssistantPrefill(t);window.ReactWooRuleBuilder.mount({textarea:t,getMode:function(){return {$get_mode};}{$extra}});}if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',rwgcRbTryMount);}else{rwgcRbTryMount();}})();";
	}

	/**
	 * @return string
	 */
	public static function get_mount_playground_inline() {
		return self::get_mount_inline( '#rwgc-targeting-playground-json', "'show_if'", 'showVisibilityMode:true,isPlayground:true,allowAllConditionTypes:true' );
	}

	/**
	 * @return void
	 */
	public static function enqueue_elementor() {
		self::register_scripts();
		self::localize_editor_context();
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * @return void
	 */
	public static function enqueue_block_editor() {
		self::register_scripts();
		self::localize_editor_context();
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Geo Elementor and other plugins can call this on their admin screens.
	 *
	 * @return void
	 */
	public static function enqueue_admin() {
		self::register_scripts();
		self::localize_editor_context();
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Ensure the block editor script depends on the rule builder (metadata may register the handle first).
	 *
	 * @return void
	 */
	public static function patch_block_editor_script_deps() {
		global $wp_scripts;
		if ( ! ( $wp_scripts instanceof WP_Scripts ) ) {
			return;
		}
		if ( ! isset( $wp_scripts->registered['rwgc-geo-content-editor'] ) ) {
			return;
		}
		$dep = self::SCRIPT_HANDLE;
		if ( ! in_array( $dep, $wp_scripts->registered['rwgc-geo-content-editor']->deps, true ) ) {
			$wp_scripts->registered['rwgc-geo-content-editor']->deps[] = $dep;
		}
	}
}
