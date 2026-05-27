<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rules                    = isset( $rules ) && is_array( $rules ) ? $rules : array();
$rwgc_use_platform_shell  = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
$new_url                  = admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=new' );
$rule_builder_url         = admin_url( 'admin.php?page=rwgc-target-types' );
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			$rwgc_use_platform_shell
				? __( 'Visibility rules', 'reactwoo-geocore' )
				: __( 'Visibility rules library', 'reactwoo-geocore' ),
			__( 'Save reusable portable rule sets, then copy JSON into Elementor, blocks, geo rules, or commerce pricing.', 'reactwoo-geocore' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Visibility rules library', 'reactwoo-geocore' ); ?></h1>
	<?php endif; ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule saved.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule moved to trash.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgc_error'] ) && 'notfound' === $_GET['rwgc_error'] ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Rule not found.', 'reactwoo-geocore' ); ?></p></div>
	<?php elseif ( isset( $_GET['rwgc_error'] ) && 'invalid_rules' === $_GET['rwgc_error'] ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The visibility rules JSON could not be saved because it no longer contains any valid rules.', 'reactwoo-geocore' ); ?></p></div>
	<?php elseif ( ! empty( $_GET['rwgc_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Something went wrong.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'These entries are a library only — they do not apply to the site until you paste the rule into a page, block, Elementor control, or commerce rule.', 'reactwoo-geocore' ); ?>
		<a href="<?php echo esc_url( $rule_builder_url ); ?>"><?php esc_html_e( 'Rule builder reference', 'reactwoo-geocore' ); ?></a>
	</p>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add visibility rule', 'reactwoo-geocore' ); ?></a>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Title', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Updated', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Manage', 'reactwoo-geocore' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rules ) ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No saved visibility rules yet.', 'reactwoo-geocore' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rules as $rule_post ) : ?>
				<?php
				if ( ! $rule_post instanceof WP_Post ) {
					continue;
				}
				$edit_url = admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=' . (int) $rule_post->ID );
				$del_url  = wp_nonce_url(
					admin_url( 'admin-post.php?action=rwgc_delete_visibility_rule&rule_id=' . (int) $rule_post->ID ),
					'rwgc_delete_visibility_rule_' . (int) $rule_post->ID
				);
				$status_label = 'publish' === $rule_post->post_status
					? __( 'Published', 'reactwoo-geocore' )
					: __( 'Draft', 'reactwoo-geocore' );
				?>
				<tr>
					<td><strong><?php echo esc_html( $rule_post->post_title ); ?></strong></td>
					<td><?php echo esc_html( $status_label ); ?></td>
					<td><?php echo esc_html( get_the_modified_date( '', $rule_post ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geocore' ); ?></a>
						|
						<a href="<?php echo esc_url( $del_url ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Move this rule to trash?', 'reactwoo-geocore' ) ); ?>');"><?php esc_html_e( 'Trash', 'reactwoo-geocore' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
