<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_new                   = ! empty( $is_new );
$post_id                  = isset( $post_id ) ? (int) $post_id : 0;
$title                    = isset( $title ) ? (string) $title : '';
$status                   = isset( $status ) ? (string) $status : 'draft';
$portable_raw             = isset( $portable_raw ) ? (string) $portable_raw : '';
$list_url                 = isset( $list_url ) ? (string) $list_url : admin_url( 'admin.php?page=rwgc-visibility-rules' );
$form_url                 = admin_url( 'admin-post.php' );
$rwgc_use_platform_shell  = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			$is_new ? __( 'Add visibility rule', 'reactwoo-geocore' ) : __( 'Edit visibility rule', 'reactwoo-geocore' ),
			__( 'Build with the same portable schema used across Elementor, blocks, and commerce. Copy the saved JSON into any surface that accepts visibility rules.', 'reactwoo-geocore' )
		);
		?>
	<?php else : ?>
		<h1><?php echo $is_new ? esc_html__( 'Add visibility rule', 'reactwoo-geocore' ) : esc_html__( 'Edit visibility rule', 'reactwoo-geocore' ); ?></h1>
	<?php endif; ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule saved.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgc_error'] ) && 'invalid_rules' === $_GET['rwgc_error'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The visibility rules JSON could not be saved because it no longer contains any valid rules.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to library', 'reactwoo-geocore' ); ?></a></p>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<?php wp_nonce_field( 'rwgc_save_visibility_rule' ); ?>
		<input type="hidden" name="action" value="rwgc_save_visibility_rule" />
		<input type="hidden" name="rwgc_rule_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwgc_rule_title"><?php esc_html_e( 'Title', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_rule_title" id="rwgc_rule_title" type="text" class="regular-text" required value="<?php echo esc_attr( $title ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
				<td>
					<select name="rwgc_rule_status" id="rwgc_rule_status">
						<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Published', 'reactwoo-geocore' ); ?></option>
						<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'reactwoo-geocore' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="rwgc_portable_targeting"><?php esc_html_e( 'Visibility rules', 'reactwoo-geocore' ); ?></label>
				</th>
				<td>
					<div class="rwgc-rb-mount-wrap">
						<textarea name="rwgc_portable_targeting" id="rwgc_portable_targeting" rows="4" class="large-text code"><?php echo esc_textarea( $portable_raw ); ?></textarea>
					</div>
					<p class="description"><?php esc_html_e( 'Use Advanced in the builder only when you need raw JSON. This library does not auto-apply rules on the front end.', 'reactwoo-geocore' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_new ? __( 'Create rule', 'reactwoo-geocore' ) : __( 'Save rule', 'reactwoo-geocore' ) ); ?>
	</form>
</div>
