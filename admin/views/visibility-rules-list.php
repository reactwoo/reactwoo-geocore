<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rules                    = isset( $rules ) && is_array( $rules ) ? $rules : array();
$index_rows               = class_exists( 'RWGC_Admin_Targeting_Rules_Index', false )
	? RWGC_Admin_Targeting_Rules_Index::get_rows()
	: array();
$rwgc_use_platform_shell  = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
$new_url                  = admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=new' );
$rule_builder_url         = admin_url( 'admin.php?page=rwgc-target-types' );
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			$rwgc_use_platform_shell
				? __( 'Targeting rules', 'reactwoo-geocore' )
				: __( 'Visibility rules library', 'reactwoo-geocore' ),
			__( 'All geo targeting rules across Geo Core, Elementor, routing, and commerce in one place.', 'reactwoo-geocore' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Targeting rules', 'reactwoo-geocore' ); ?></h1>
	<?php endif; ?>

	<?php if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule saved.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule moved to trash.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgc_error'] ) && 'notfound' === $_GET['rwgc_error'] ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Rule not found.', 'reactwoo-geocore' ); ?></p></div>
	<?php elseif ( ! empty( $_GET['rwgc_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Something went wrong.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'Portable library rules are reusable templates. Elementor, routing, and commerce rules are managed in their source screens.', 'reactwoo-geocore' ); ?>
		<a href="<?php echo esc_url( $rule_builder_url ); ?>"><?php esc_html_e( 'Condition reference', 'reactwoo-geocore' ); ?></a>
	</p>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add portable rule', 'reactwoo-geocore' ); ?></a>
	</p>

	<?php if ( ! empty( $index_rows ) ) : ?>
		<h2><?php esc_html_e( 'All targeting rules', 'reactwoo-geocore' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Rule', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Applied location', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Targeting', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Updated', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $index_rows as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></strong></td>
						<td><?php echo esc_html( (string) ( $row['source'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['location'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['targeting'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['updated'] ?? '—' ) ); ?></td>
						<td>
							<?php if ( ! empty( $row['edit_url'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( (string) $row['edit_url'] ); ?>"><?php echo esc_html( (string) ( $row['edit_label'] ?? __( 'Edit', 'reactwoo-geocore' ) ) ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'No targeting rules found yet. Add a portable rule or create rules in Elementor, page routing, or Geo Commerce.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $rules ) ) : ?>
		<h2><?php esc_html_e( 'Portable rule library', 'reactwoo-geocore' ); ?></h2>
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
			</tbody>
		</table>
	<?php endif; ?>
</div>
