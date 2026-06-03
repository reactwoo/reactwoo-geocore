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
			__( 'Reusable portable rules and builder-attached targeting in one place.', 'reactwoo-geocore' )
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

	<?php if ( ! empty( $rules ) ) : ?>
		<div class="rwgc-card rwgc-card--full" style="margin-bottom:1.5em;">
			<h2><?php esc_html_e( 'Portable rule library', 'reactwoo-geocore' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Reusable rule sets you can attach in Elementor, Gutenberg, commerce, and other surfaces. Edit them here — they are not tied to a single page until applied in a builder.', 'reactwoo-geocore' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add portable rule', 'reactwoo-geocore' ); ?></a>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Scope', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Conditions', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Updated', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
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
						$conditions   = class_exists( 'RWGC_Admin_Targeting_Rules_Index', false )
							? RWGC_Admin_Targeting_Rules_Index::summarize_portable_meta( (int) $rule_post->ID, RWGC_Visibility_Rule_CPT::META_PORTABLE )
							: '—';
						?>
						<tr>
							<td><strong><?php echo esc_html( $rule_post->post_title ); ?></strong></td>
							<td><span class="rwgc-rules-scope rwgc-rules-scope--portable"><?php esc_html_e( 'Portable', 'reactwoo-geocore' ); ?></span></td>
							<td><span class="rwgc-rules-source rwgc-rules-source--geocore"><?php esc_html_e( 'Geo Core', 'reactwoo-geocore' ); ?></span></td>
							<td><?php echo esc_html( $conditions ); ?></td>
							<td><?php echo esc_html( $status_label ); ?></td>
							<td><?php echo esc_html( get_the_modified_date( '', $rule_post ) ); ?></td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit rule', 'reactwoo-geocore' ); ?></a>
								<a href="<?php echo esc_url( $del_url ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Move this rule to trash?', 'reactwoo-geocore' ) ); ?>');"><?php esc_html_e( 'Trash', 'reactwoo-geocore' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<div class="rwgc-card rwgc-card--full" style="margin-bottom:1.5em;">
			<h2><?php esc_html_e( 'Portable rule library', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'No portable rules yet. Create one to reuse the same conditions across Elementor, blocks, and commerce.', 'reactwoo-geocore' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add portable rule', 'reactwoo-geocore' ); ?></a></p>
		</div>
	<?php endif; ?>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Targeting rules', 'reactwoo-geocore' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Rules created inside builders and product screens — attached to a page, popup, element, route, or store context. They are edited where they were created, not in the portable library above.', 'reactwoo-geocore' ); ?>
			<a href="<?php echo esc_url( $rule_builder_url ); ?>"><?php esc_html_e( 'Condition reference', 'reactwoo-geocore' ); ?></a>
		</p>

		<?php if ( ! empty( $index_rows ) ) : ?>
			<table class="widefat striped rwgc-rules-index-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Rule', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Scope', 'reactwoo-geocore' ); ?></th>
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
						<?php
						$source_key = isset( $row['source_key'] ) ? sanitize_key( (string) $row['source_key'] ) : '';
						$scope      = isset( $row['rule_scope'] ) ? (string) $row['rule_scope'] : __( 'Builder', 'reactwoo-geocore' );
						?>
						<tr>
							<td><strong><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></strong></td>
							<td><span class="rwgc-rules-scope rwgc-rules-scope--builder"><?php echo esc_html( $scope ); ?></span></td>
							<td>
								<?php if ( '' !== $source_key ) : ?>
									<span class="rwgc-rules-source rwgc-rules-source--<?php echo esc_attr( $source_key ); ?>"><?php echo esc_html( (string) ( $row['source'] ?? '' ) ); ?></span>
								<?php else : ?>
									<?php echo esc_html( (string) ( $row['source'] ?? '' ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) ( $row['location'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['targeting'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['status'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['updated'] ?? '—' ) ); ?></td>
							<td>
								<?php if ( ! empty( $row['edit_url'] ) ) : ?>
									<a class="button button-small" href="<?php echo esc_url( (string) $row['edit_url'] ); ?>"<?php echo ( ! empty( $row['edit_url'] ) && 0 === strpos( (string) $row['edit_url'], 'http' ) && false === strpos( (string) $row['edit_url'], admin_url() ) ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
										<?php echo esc_html( (string) ( $row['edit_label'] ?? __( 'Open', 'reactwoo-geocore' ) ) ); ?>
									</a>
									<?php if ( ! empty( $row['action_note'] ) ) : ?>
										<p class="description" style="margin:.35em 0 0;max-width:16rem;"><?php echo esc_html( (string) $row['action_note'] ); ?></p>
									<?php endif; ?>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'Edit in the builder where this rule was created.', 'reactwoo-geocore' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'No builder-attached targeting rules found yet. Create rules in Elementor, the block editor, page routing, or Geo Commerce.', 'reactwoo-geocore' ); ?></p></div>
		<?php endif; ?>
	</div>
</div>
<style>
	.rwgc-rules-scope{display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600;line-height:1.4}
	.rwgc-rules-scope--portable{background:#e8f4fd;color:#135e96}
	.rwgc-rules-scope--builder{background:#f3f0ff;color:#53389e}
	.rwgc-rules-source{display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:500;line-height:1.4;background:#f0f0f1;color:#1d2327}
	.rwgc-rules-source--elementor{background:#fef3f2;color:#9b1c1c}
	.rwgc-rules-source--gutenberg{background:#ecfdf3;color:#027a48}
	.rwgc-rules-source--geocore{background:#eff8ff;color:#175cd3}
	.rwgc-rules-source--commerce{background:#fff6ed;color:#b93815}
</style>
