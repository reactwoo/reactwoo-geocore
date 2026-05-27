<?php
/**
 * Admin UI for the visibility rules library (Targeting section).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List + edit screens for {@see RWGC_Visibility_Rule_CPT::POST_TYPE}.
 */
class RWGC_Admin_Visibility_Rules {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_rwgc_save_visibility_rule', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_rwgc_delete_visibility_rule', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! RWGC_Admin::can_manage() ) {
			return;
		}
		if ( ! post_type_exists( RWGC_Visibility_Rule_CPT::POST_TYPE ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Visibility rules are not registered.', 'reactwoo-geocore' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit = isset( $_GET['rwgc_edit'] ) ? wp_unslash( $_GET['rwgc_edit'] ) : '';

		if ( 'new' === $edit ) {
			self::render_edit( null );
			return;
		}
		if ( is_numeric( $edit ) && (int) $edit > 0 ) {
			$post = RWGC_Visibility_Rule_Repository::get_post( (int) $edit );
			if ( ! $post ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_error=notfound' ) );
				exit;
			}
			self::render_edit( $post );
			return;
		}

		self::render_list();
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		if ( ! RWGC_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geocore' ) );
		}
		check_admin_referer( 'rwgc_save_visibility_rule' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$post_id = isset( $_POST['rwgc_rule_id'] ) ? absint( wp_unslash( $_POST['rwgc_rule_id'] ) ) : 0;
		$title   = isset( $_POST['rwgc_rule_title'] ) ? wp_unslash( (string) $_POST['rwgc_rule_title'] ) : '';
		$status  = isset( $_POST['rwgc_rule_status'] ) ? wp_unslash( (string) $_POST['rwgc_rule_status'] ) : 'draft';
		$json    = isset( $_POST['rwgc_portable_targeting'] ) ? wp_unslash( (string) $_POST['rwgc_portable_targeting'] ) : '';
		// phpcs:enable

		$new_id = RWGC_Visibility_Rule_Repository::save( $title, $status, $json, $post_id );
		if ( $new_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_error=save' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=' . $new_id . '&updated=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_delete() {
		if ( ! RWGC_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geocore' ) );
		}
		$post_id = isset( $_GET['rule_id'] ) ? absint( wp_unslash( $_GET['rule_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $post_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgc-visibility-rules' ) );
			exit;
		}
		check_admin_referer( 'rwgc_delete_visibility_rule_' . $post_id );
		RWGC_Visibility_Rule_Repository::delete( $post_id );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-visibility-rules&deleted=1' ) );
		exit;
	}

	/**
	 * @param WP_Post|null $post Post or null for new.
	 * @return void
	 */
	private static function render_edit( $post ) {
		$is_new = ! ( $post instanceof WP_Post );
		$portable_raw  = '';
		$title         = '';
		$status        = 'draft';
		$post_id       = 0;
		if ( $post instanceof WP_Post ) {
			$post_id      = (int) $post->ID;
			$title        = $post->post_title;
			$status       = $post->post_status;
			$portable_raw = (string) get_post_meta( $post_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
			if ( '' !== trim( $portable_raw ) ) {
				$decoded = json_decode( $portable_raw, true );
				if ( is_array( $decoded ) ) {
					$portable_raw = wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
				}
			}
		}
		$list_url = admin_url( 'admin.php?page=rwgc-visibility-rules' );
		include RWGC_PATH . 'admin/views/visibility-rules-edit.php';
	}

	/**
	 * @return void
	 */
	private static function render_list() {
		$rules = RWGC_Visibility_Rule_Repository::query();
		include RWGC_PATH . 'admin/views/visibility-rules-list.php';
	}
}
