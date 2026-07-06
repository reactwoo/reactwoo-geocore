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

	const STYLE_HANDLE       = 'rwgc-rule-tester';
	const RULES_STYLE_HANDLE = 'rwgc-rules-page';
	const SCRIPT_HANDLE      = 'rwgc-visibility-rule-tester';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 25 );
		add_action( 'admin_footer', array( __CLASS__, 'render_modal_shell' ) );
	}

	/**
	 * Whether the current admin request is the visibility rules list or editor.
	 *
	 * @param string $hook Optional admin_enqueue_scripts hook suffix.
	 * @return bool
	 */
	public static function is_visibility_rules_screen( $hook = '' ) {
		$hook = (string) $hook;
		if ( '' !== $hook && false !== strpos( $hook, 'rwgc-visibility-rules' ) ) {
			return true;
		}

		$page = '';
		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( 'rwgc-visibility-rules' === $page ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && isset( $screen->id ) && false !== strpos( (string) $screen->id, 'rwgc-visibility-rules' ) ) {
			return true;
		}

		/**
		 * Allow satellites or shell integrations to force rules/tester assets on a screen.
		 *
		 * @param bool   $match Default false when page/hook/screen did not match.
		 * @param string $hook  Admin hook suffix.
		 */
		return (bool) apply_filters( 'rwgc_visibility_rules_tester_enqueue', false, $hook );
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( ! self::is_visibility_rules_screen( $hook ) ) {
			return;
		}

		self::ensure_suite_styles();

		wp_register_style(
			self::RULES_STYLE_HANDLE,
			RWGC_URL . 'admin/css/rwgc-rules-page.css',
			array( 'rwgc-suite' ),
			RWGC_VERSION
		);
		wp_register_style(
			self::STYLE_HANDLE,
			RWGC_URL . 'admin/css/rwgc-rule-tester.css',
			array( 'rwgc-suite', self::RULES_STYLE_HANDLE ),
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

		if ( wp_style_is( 'rwgc-platform-ui', 'registered' ) ) {
			wp_enqueue_style( 'rwgc-platform-ui' );
		}
		if ( wp_style_is( 'rwgc-targeting', 'registered' ) ) {
			wp_enqueue_style( 'rwgc-targeting' );
		}

		$current_rule_id = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_edit'] ) && is_numeric( $_GET['rwgc_edit'] ) ) {
			$current_rule_id = absint( $_GET['rwgc_edit'] );
		}

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'rwgcRuleTester',
			array(
				'restUrl'            => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/preview-rule' ) ),
				'assignmentRestUrl'  => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/preview-assignment' ) ),
				'assignmentsUrl'     => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/rule-tester/assignments' ) ),
				'compatibilityUrl'   => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/rule-compatibility-check' ) ),
				'previewUrl'         => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/rule-tester/preview-url' ) ),
				'ruleUrl'            => esc_url_raw( rest_url( 'reactwoo-geocore/v1/targeting/rule-tester/rule/' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'currentRuleId'  => $current_rule_id,
				'useEditorDraft' => isset( $_GET['rwgc_edit'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'bootstrap'      => class_exists( 'RWGC_Visibility_Rule_Tester', false )
					? RWGC_Visibility_Rule_Tester::bootstrap_config()
					: array(),
				'labels'         => array(
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
					'selectedContent'       => __( 'Selected content', 'reactwoo-geocore' ),
					'simulatedPageType'     => __( 'Simulated page type', 'reactwoo-geocore' ),
					'ruleEvaluationTitle'   => __( 'Rule evaluation', 'reactwoo-geocore' ),
					'appliedTargetsTitle'   => __( 'Applied targets on selected content', 'reactwoo-geocore' ),
					'noAppliedTargets'      => __( 'This rule was evaluated successfully, but it is not applied to any detected section, product, block, popup, or element on this selected content.', 'reactwoo-geocore' ),
					'targetColumn'          => __( 'Target', 'reactwoo-geocore' ),
					'targetTypeColumn'      => __( 'Type', 'reactwoo-geocore' ),
					'sourceColumn'          => __( 'Source', 'reactwoo-geocore' ),
					'modeColumn'            => __( 'Mode', 'reactwoo-geocore' ),
					'outcomeColumn'         => __( 'Outcome', 'reactwoo-geocore' ),
					'previewTitle'          => __( 'Preview', 'reactwoo-geocore' ),
					'openPreview'           => __( 'Open simulated preview', 'reactwoo-geocore' ),
					'copyPreview'           => __( 'Copy preview link', 'reactwoo-geocore' ),
					'summaryTitle'          => __( 'Summary', 'reactwoo-geocore' ),
					'pageMatchLabel'        => __( 'Page match', 'reactwoo-geocore' ),
					'appliedTargetsFound'   => __( 'Applied targets found', 'reactwoo-geocore' ),
					'renderedImpactsFound'  => __( 'Rendered product impacts', 'reactwoo-geocore' ),
					'visibleOutcomes'       => __( 'Visible outcomes', 'reactwoo-geocore' ),
					'hiddenOutcomes'        => __( 'Hidden outcomes', 'reactwoo-geocore' ),
					'whyNoPageMatchTitle'   => __( 'Why the page/context did not match', 'reactwoo-geocore' ),
					'pageEvalTitle'         => __( 'Page/context evaluation', 'reactwoo-geocore' ),
					'targetDetectionTitle'  => __( 'Applied target detection', 'reactwoo-geocore' ),
					'targetOutcomesTitle'   => __( 'Target outcomes', 'reactwoo-geocore' ),
					'directAssignmentsTitle'=> __( 'Direct assignments on selected content', 'reactwoo-geocore' ),
					'renderedImpactsTitle'  => __( 'Rendered product impacts', 'reactwoo-geocore' ),
					'productColumn'         => __( 'Product', 'reactwoo-geocore' ),
					'ruleColumn'            => __( 'Rule', 'reactwoo-geocore' ),
					'noRenderedImpacts'     => __( 'No rendered product impacts were detected for this rule on the selected content.', 'reactwoo-geocore' ),
					'testType'              => __( 'Test type', 'reactwoo-geocore' ),
					'testModeRule'          => __( 'Visibility rule', 'reactwoo-geocore' ),
					'testModeApplied'       => __( 'Applied target / element', 'reactwoo-geocore' ),
					'stepAssignment'        => __( 'Applied target', 'reactwoo-geocore' ),
					'selectAssignment'      => __( 'Elementor assignment', 'reactwoo-geocore' ),
					'assignmentPlaceholder' => __( 'Choose a section, container, or popup assignment', 'reactwoo-geocore' ),
					'assignmentEmpty'       => __( 'No visibility assignments were found on this content.', 'reactwoo-geocore' ),
					'assignmentHelp'        => __( 'Select an Elementor element that has a saved visibility rule applied.', 'reactwoo-geocore' ),
					'compatibilityWarning'  => __( 'Compatibility warning', 'reactwoo-geocore' ),
					'ruleMatchLabel'        => __( 'Rule match', 'reactwoo-geocore' ),
					'elementOutcomeLabel'   => __( 'Element outcome', 'reactwoo-geocore' ),
					'visibleTitle'          => __( 'VISIBLE', 'reactwoo-geocore' ),
					'hiddenTitle'           => __( 'HIDDEN', 'reactwoo-geocore' ),
					'appliedModeLabel'      => __( 'Applied mode', 'reactwoo-geocore' ),
					'loadingAssignments'    => __( 'Loading assignments…', 'reactwoo-geocore' ),
				),
			)
		);
	}

	/**
	 * Register/enqueue suite styles when another admin callback has not already done so.
	 *
	 * @return void
	 */
	private static function ensure_suite_styles() {
		$chain = array(
			'rwgc-design-system' => array(),
			'rwgc-admin'         => array( 'rwgc-design-system' ),
			'rwgc-suite'         => array( 'rwgc-design-system', 'rwgc-admin' ),
		);

		foreach ( $chain as $handle => $deps ) {
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				$file = 'rwgc-design-system.css';
				if ( 'rwgc-admin' === $handle ) {
					$file = 'admin.css';
				} elseif ( 'rwgc-suite' === $handle ) {
					$file = 'rwgc-suite.css';
				}
				wp_register_style(
					$handle,
					RWGC_URL . 'admin/css/' . $file,
					$deps,
					RWGC_VERSION
				);
			}
			if ( ! wp_style_is( $handle, 'enqueued' ) ) {
				wp_enqueue_style( $handle );
			}
		}
	}

	/**
	 * @return void
	 */
	public static function render_modal_shell() {
		if ( ! self::is_visibility_rules_screen() ) {
			return;
		}
		include RWGC_PATH . 'admin/views/visibility-rule-tester-modal.php';
	}
}
