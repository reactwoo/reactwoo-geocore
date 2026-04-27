<?php
/**
 * Geo Suite — admin shell: Suite Home, Getting Started wizard, guided workflows.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers suite pages and handles workflow POST actions.
 */
class RWGC_Suite_Admin {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( 'RWGC_Onboarding', 'maybe_redirect_after_activation' ) );
		add_action( 'admin_post_rwgc_save_wizard', array( __CLASS__, 'handle_save_wizard' ) );
		add_action( 'admin_post_rwgc_create_variant_workflow', array( __CLASS__, 'handle_create_variant' ) );
		add_action( 'admin_post_rwgc_dismiss_welcome', array( __CLASS__, 'handle_dismiss_welcome' ) );
		add_filter( 'rwgc_inner_nav_items', array( __CLASS__, 'filter_inner_nav_items' ), 5, 2 );
	}

	/**
	 * Insert Suite Home and Getting Started before legacy Dashboard entry.
	 *
	 * @param array<string, string> $items   Slug => label.
	 * @param string                $current Current page slug.
	 * @return array<string, string>
	 */
	public static function filter_inner_nav_items( $items, $current ) {
		unset( $current );
		if ( ! is_array( $items ) ) {
			$items = array();
		}
		// Keep inner navigation focused on the primary Geo Core workflow.
		return $items;
	}

	/**
	 * Wizard display step 1–3 (migrates legacy `wizard_step` 0).
	 *
	 * @param array<string, mixed> $state Onboarding state.
	 * @return int
	 */
	private static function normalize_wizard_step( array $state ) {
		$ws = isset( $state['wizard_step'] ) ? (int) $state['wizard_step'] : 1;
		if ( $ws < 1 ) {
			$ws = ! empty( $state['goal'] ) ? 2 : 1;
		}
		// Legacy: goal was saved when wizard_step stayed at 1.
		if ( 1 === $ws && ! empty( $state['goal'] ) ) {
			$ws = 2;
		}
		return min( 3, max( 1, $ws ) );
	}

	/**
	 * @return void
	 */
	public static function render_suite_home() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state     = RWGC_Onboarding::get_state();
		$activity  = RWGC_Onboarding::get_activity();
		$launchers = RWGC_Workflows::get_launchers();
		$goal      = isset( $state['goal'] ) ? (string) $state['goal'] : '';
		$readiness = RWGC_Module_Registry::get_readiness_rows( $goal );
		include RWGC_PATH . 'admin/views/suite-home.php';
	}

	/**
	 * @return void
	 */
	public static function render_getting_started() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state         = RWGC_Onboarding::get_state();
		$goal          = isset( $state['goal'] ) ? (string) $state['goal'] : '';
		$wizard_step   = self::normalize_wizard_step( $state );
		$visitor_data  = class_exists( 'RWGC_API', false ) ? RWGC_API::get_visitor_data() : array();
		$readiness     = RWGC_Module_Registry::get_readiness_rows( $goal );
		$launchers     = RWGC_Workflows::order_launchers_for_goal( RWGC_Workflows::get_launchers(), $goal );
		$guidance      = RWGC_Workflows::get_goal_guidance( $goal );
		include RWGC_PATH . 'admin/views/getting-started.php';
	}

	/**
	 * @return void
	 */
	public static function render_suite_variants() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$overview_rows = class_exists( 'RWGC_Variant_Manager', false )
			? RWGC_Variant_Manager::get_routing_overview_rows()
			: array();
		include RWGC_PATH . 'admin/views/suite-variants.php';
	}

	/**
	 * @return void
	 */
	public static function render_workflow_variant() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage pages.', 'reactwoo-geocore' ) );
		}
		$result = get_transient( 'rwgc_variant_workflow_result_' . get_current_user_id() );
		if ( false !== $result ) {
			delete_transient( 'rwgc_variant_workflow_result_' . get_current_user_id() );
		} else {
			$result = null;
		}
		$prefill_master = isset( $_GET['rwgc_master_page_id'] ) ? absint( wp_unslash( $_GET['rwgc_master_page_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		include RWGC_PATH . 'admin/views/workflow-create-variant.php';
	}

	/**
	 * @return void
	 */
	public static function handle_save_wizard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geocore' ) );
		}
		check_admin_referer( 'rwgc_save_wizard' );

		$action = isset( $_POST['rwgc_wizard_action'] ) ? sanitize_key( wp_unslash( $_POST['rwgc_wizard_action'] ) ) : 'goal';
		$prev   = RWGC_Onboarding::get_state();
		$goal   = isset( $_POST['rwgc_wizard_goal'] ) ? sanitize_key( wp_unslash( $_POST['rwgc_wizard_goal'] ) ) : ( isset( $prev['goal'] ) ? (string) $prev['goal'] : '' );

		$data = isset( $_POST['rwgc_wizard'] ) && is_array( $_POST['rwgc_wizard'] ) ? map_deep( wp_unslash( $_POST['rwgc_wizard'] ), 'sanitize_text_field' ) : array();

		if ( 'advance_env' === $action ) {
			RWGC_Onboarding::update_state(
				array(
					'wizard_step' => 3,
					'wizard_data' => $data,
				)
			);
		} elseif ( 'complete' === $action ) {
			RWGC_Onboarding::update_state(
				array(
					'wizard_completed' => true,
					'wizard_data'      => $data,
				)
			);
		} else {
			// goal (default): save goal and move to environment step.
			RWGC_Onboarding::update_state(
				array(
					'goal'        => $goal,
					'wizard_step' => 2,
					'wizard_data' => $data,
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-getting-started&rwgc_saved=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_create_variant() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geocore' ) );
		}
		check_admin_referer( 'rwgc_create_variant_workflow' );

		$master = isset( $_POST['rwgc_master_page_id'] ) ? absint( wp_unslash( $_POST['rwgc_master_page_id'] ) ) : 0;
		$iso2   = isset( $_POST['rwgc_country_iso2'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgc_country_iso2'] ) ) : '';
		$mode   = isset( $_POST['rwgc_variant_mode'] ) ? sanitize_key( wp_unslash( $_POST['rwgc_variant_mode'] ) ) : 'duplicate';

		$res = RWGC_Variant_Manager::create_country_variant( $master, $iso2, $mode );

		if ( is_wp_error( $res ) ) {
			set_transient(
				'rwgc_variant_workflow_result_' . get_current_user_id(),
				array(
					'error' => $res->get_error_message(),
				),
				120
			);
		} else {
			set_transient(
				'rwgc_variant_workflow_result_' . get_current_user_id(),
				$res,
				120
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_done=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_dismiss_welcome() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geocore' ) );
		}
		check_admin_referer( 'rwgc_dismiss_welcome' );
		RWGC_Onboarding::update_state( array( 'dismissed_welcome' => true ) );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-suite-home' ) );
		exit;
	}
}
