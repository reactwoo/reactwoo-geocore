<?php
/**
 * Assets for the visibility rule tester modal.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues modal tester script/style on visibility rules admin screens.
 */
class RWGC_Visibility_Rule_Tester_Assets {

	const STYLE_HANDLE      = 'rwgc-rule-tester';
	const RULES_STYLE_HANDLE = 'rwgc-rules-page';
	const SCRIPT_HANDLE     = 'rwgc-visibility-rule-tester';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_modal_shell' ) );
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( $hook, 'rwgc-visibility-rules' ) ) {
			return;
		}

		wp_register_style(
			self::RULES_STYLE_HANDLE,
			RWGC_URL . 'admin/css/rwgc-rules-page.css',
			array( 'rwgc-suite' ),
			RWGC_VERSION
		);
		wp_register_style(
			self::STYLE_HANDLE,
			RWGC_URL . 'admin/css/rwgc-rule-tester.css',
			array( 'rwgc-suite' ),
			RWGC_VERSION
		);
		wp_register_script(
			self::SCRIPT_HANDLE,
			RWGC_URL . 'admin/js/rwgc-visibility-rule-tester.js',
			array(),
			RWGC_VERSION,
			true
		);

		wp_enqueue_style( self::RULES_STYLE_HANDLE );
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );

		$current_rule_id = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_edit'] ) && is_numeric( $_GET['rwgc_edit'] ) ) {
			$current_rule_id = absint( $_GET['rwgc_edit'] );
		}

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'rwgcRuleTester',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/preview-rule' ) ),
				'ruleUrl'         => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/rule-tester/rule/' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'currentRuleId'   => $current_rule_id,
				'useEditorDraft'  => isset( $_GET['rwgc_edit'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'bootstrap'       => class_exists( 'RWGC_Visibility_Rule_Tester', false )
					? RWGC_Visibility_Rule_Tester::bootstrap_config()
					: array(),
				'labels'          => array(
					'title'                 => __( 'Test visibility rule', 'reactwoo-geocore' ),
					'subtitle'              => __( 'Choose a rule, choose where to test it, then simulate a visitor.', 'reactwoo-geocore' ),
					'stepRule'              => __( 'Rule', 'reactwoo-geocore' ),
					'stepContent'           => __( 'Content', 'reactwoo-geocore' ),
					'stepVisitor'           => __( 'Visitor context', 'reactwoo-geocore' ),
					'stepTraffic'           => __( 'Traffic context', 'reactwoo-geocore' ),
					'selectRule'            => __( 'Visibility rule', 'reactwoo-geocore' ),
					'selectRulePlaceholder' => __( 'Choose a visibility rule', 'reactwoo-geocore' ),
					'selectContent'         => __( 'Content', 'reactwoo-geocore' ),
					'contentManual'         => __( 'Manual URL / path', 'reactwoo-geocore' ),
					'contentNone'           => __( '— No content selected —', 'reactwoo-geocore' ),
					'contentPage'           => __( 'Pages', 'reactwoo-geocore' ),
					'contentPost'           => __( 'Posts', 'reactwoo-geocore' ),
					'contentProduct'        => __( 'Products', 'reactwoo-geocore' ),
					'ruleConditions'        => __( 'Conditions', 'reactwoo-geocore' ),
					'presets'               => __( 'Quick presets', 'reactwoo-geocore' ),
					'trafficPresets'        => __( 'Traffic presets', 'reactwoo-geocore' ),
					'presetGoogleAds'       => __( 'Google Ads standard UTM', 'reactwoo-geocore' ),
					'presetWinterSale'      => __( 'Winter sale URL', 'reactwoo-geocore' ),
					'presetNoCampaign'      => __( 'No campaign', 'reactwoo-geocore' ),
					'runTest'               => __( 'Run test', 'reactwoo-geocore' ),
					'reset'                 => __( 'Reset', 'reactwoo-geocore' ),
					'close'                 => __( 'Close', 'reactwoo-geocore' ),
					'testing'               => __( 'Testing…', 'reactwoo-geocore' ),
					'matchTitle'            => __( 'MATCH', 'reactwoo-geocore' ),
					'noMatchTitle'          => __( 'NO MATCH', 'reactwoo-geocore' ),
					'incompleteTitle'       => __( 'CANNOT TEST', 'reactwoo-geocore' ),
					'errorTitle'            => __( 'Test failed', 'reactwoo-geocore' ),
					'country'               => __( 'Country', 'reactwoo-geocore' ),
					'countryPlaceholder'    => __( 'Choose a country', 'reactwoo-geocore' ),
					'countryHelper'         => __( 'Based on this rule\'s allowed countries.', 'reactwoo-geocore' ),
					'device'                => __( 'Device', 'reactwoo-geocore' ),
					'pageType'              => __( 'Page type', 'reactwoo-geocore' ),
					'urlPath'               => __( 'URL / path', 'reactwoo-geocore' ),
					'utmSource'             => __( 'UTM source', 'reactwoo-geocore' ),
					'utmMedium'             => __( 'UTM medium', 'reactwoo-geocore' ),
					'gclid'                 => __( 'gclid present', 'reactwoo-geocore' ),
					'contentHelp'           => __( 'Choose where this rule would apply. Page type and URL are filled from the selection when possible.', 'reactwoo-geocore' ),
					'noConditions'          => __( 'Add conditions to this rule to see a summary here.', 'reactwoo-geocore' ),
					'missingContext'        => __( 'Fill in the required visitor context fields before running a test.', 'reactwoo-geocore' ),
					'resultPlaceholder'     => __( 'Select a rule and visitor context to test whether this rule would match.', 'reactwoo-geocore' ),
					'searchPlaceholder'     => __( 'Search…', 'reactwoo-geocore' ),
					'detectedPageType'      => __( 'Page type', 'reactwoo-geocore' ),
					'detectedUrl'           => __( 'URL', 'reactwoo-geocore' ),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render_modal_shell() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'rwgc-visibility-rules' ) ) {
			return;
		}
		include RWGC_PATH . 'admin/views/visibility-rule-tester-modal.php';
	}
}
