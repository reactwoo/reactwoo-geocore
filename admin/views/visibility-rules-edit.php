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
$variant_provenance       = isset( $variant_provenance ) && is_array( $variant_provenance ) ? $variant_provenance : array();
$variant_references       = isset( $variant_references ) && is_array( $variant_references ) ? $variant_references : array();
$variant_sync_url         = isset( $variant_sync_url ) ? (string) $variant_sync_url : '';
$is_variant_rule          = ! empty( $variant_provenance['sourceType'] ) && 'page_variant' === $variant_provenance['sourceType'];
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
		<?php if ( isset( $_GET['rwgc_synced'] ) && is_numeric( $_GET['rwgc_synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of popups updated */
						esc_html__( 'Synced this rule to %d referenced popup(s).', 'reactwoo-geocore' ),
						(int) $_GET['rwgc_synced'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					);
					?>
				</p>
			</div>
		<?php endif; ?>
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

	<?php if ( $is_variant_rule ) : ?>
		<div class="rwgc-card rwgc-card--full" style="margin-top:1.5em;">
			<h2><?php esc_html_e( 'Variant rule application', 'reactwoo-geocore' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'This rule was created for a page variant. It defines who matches; each popup or element must still reference this rule to use it.', 'reactwoo-geocore' ); ?>
			</p>
			<?php
			$src_page = (int) ( $variant_provenance['sourcePageId'] ?? 0 );
			$src_ver  = (string) ( $variant_provenance['sourceVariant'] ?? '' );
			$src_url  = (string) ( $variant_provenance['sourceUrl'] ?? '' );
			?>
			<p>
				<strong><?php esc_html_e( 'Created from:', 'reactwoo-geocore' ); ?></strong>
				<?php
				if ( $src_page > 0 ) {
					echo esc_html( get_the_title( $src_page ) . ( $src_ver ? ' / ' . $src_ver : '' ) );
				} else {
					esc_html_e( 'Page variant', 'reactwoo-geocore' );
				}
				?>
				<?php if ( '' !== $src_url ) : ?>
					<br /><code><?php echo esc_html( wp_parse_url( $src_url, PHP_URL_PATH ) ?: $src_url ); ?></code>
				<?php endif; ?>
			</p>
			<p class="notice notice-warning inline" style="margin:0 0 1em;padding:.65em 1em;">
				<?php esc_html_e( 'If you want popups or replacement content to follow this variant, apply the rule to those surfaces too. Popups do not inherit variant rules automatically.', 'reactwoo-geocore' ); ?>
			</p>
			<?php if ( ! empty( $variant_references ) ) : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Referenced surface', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $variant_references as $ref ) : ?>
							<?php
							$ref_status = (string) ( $ref['status'] ?? '' );
							$status_lbl = 'rule_applied' === $ref_status
								? __( 'Rule applied', 'reactwoo-geocore' )
								: __( 'Rule not applied', 'reactwoo-geocore' );
							?>
							<tr>
								<td>
									<?php
									echo esc_html(
										sprintf(
											'%1$s: %2$s',
											ucfirst( (string) ( $ref['type'] ?? __( 'Surface', 'reactwoo-geocore' ) ) ),
											(string) ( $ref['title'] ?? ( $ref['id'] ?? '' ) )
										)
									);
									?>
								</td>
								<td><?php echo esc_html( $status_lbl ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No popups or surfaces reference this rule yet. Attach it in Elementor popup settings or use Sync below after you configure popups.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $variant_sync_url ) : ?>
				<p style="margin-top:1em;">
					<a class="button button-secondary" href="<?php echo esc_url( $variant_sync_url ); ?>">
						<?php esc_html_e( 'Apply to referenced popups', 'reactwoo-geocore' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
