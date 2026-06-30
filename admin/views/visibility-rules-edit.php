<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_new                  = ! empty( $is_new );
$post_id                 = isset( $post_id ) ? (int) $post_id : 0;
$title                   = isset( $title ) ? (string) $title : '';
$status                  = isset( $status ) ? (string) $status : 'draft';
$portable_raw            = isset( $portable_raw ) ? (string) $portable_raw : '';
$list_url                = isset( $list_url ) ? (string) $list_url : admin_url( 'admin.php?page=rwgc-visibility-rules' );
$form_url                = admin_url( 'admin-post.php' );
$editor_presenter        = isset( $editor_presenter ) && is_array( $editor_presenter ) ? $editor_presenter : array();
$variant_provenance      = isset( $variant_provenance ) && is_array( $variant_provenance ) ? $variant_provenance : array();
$variant_references      = isset( $variant_references ) && is_array( $variant_references ) ? $variant_references : array();
$variant_sync_url        = isset( $variant_sync_url ) ? (string) $variant_sync_url : '';
$is_variant_rule         = ! empty( $variant_provenance['sourceType'] ) && 'page_variant' === $variant_provenance['sourceType'];
$target_label            = (string) ( $editor_presenter['target_label'] ?? '' );
$summary_text            = (string) ( $editor_presenter['summary'] ?? '' );
$summary_chips           = isset( $editor_presenter['chips'] ) && is_array( $editor_presenter['chips'] ) ? $editor_presenter['chips'] : array();
$status_label            = (string) ( $editor_presenter['status_label'] ?? ( 'publish' === $status ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ) ) );
$status_slug             = (string) ( $editor_presenter['status_slug'] ?? $status );
$is_valid                = ! empty( $editor_presenter['valid'] );
$warnings                = isset( $editor_presenter['warnings'] ) && is_array( $editor_presenter['warnings'] ) ? $editor_presenter['warnings'] : array();
$delete_url              = $post_id > 0
	? wp_nonce_url(
		admin_url( 'admin-post.php?action=rwgc_delete_visibility_rule&rule_id=' . $post_id ),
		'rwgc_delete_visibility_rule_' . $post_id
	)
	: '';
$save_label              = $is_new ? __( 'Create rule', 'reactwoo-geocore' ) : __( 'Save rule', 'reactwoo-geocore' );
$page_title              = $is_new ? __( 'Add visibility rule', 'reactwoo-geocore' ) : __( 'Edit visibility rule', 'reactwoo-geocore' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-rule-editor">
	<div class="rwgc-rule-editor__header">
		<div class="rwgc-rule-editor__header-copy">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<p class="rwgc-rule-editor__subheader">
				<?php esc_html_e( 'Build reusable GeoCore rules for pages, popups, products, Elementor, blocks, and commerce.', 'reactwoo-geocore' ); ?>
			</p>
		</div>
		<div class="rwgc-rule-editor__top-actions">
			<a class="button" href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to rules', 'reactwoo-geocore' ); ?></a>
			<button type="submit" class="button button-primary" form="rwgc-visibility-rule-form"><?php echo esc_html( $save_label ); ?></button>
			<a class="button" href="#rwgc-rule-summary-card"><?php esc_html_e( 'Preview logic', 'reactwoo-geocore' ); ?></a>
		</div>
	</div>

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

	<form id="rwgc-visibility-rule-form" method="post" action="<?php echo esc_url( $form_url ); ?>">
		<?php wp_nonce_field( 'rwgc_save_visibility_rule' ); ?>
		<input type="hidden" name="action" value="rwgc_save_visibility_rule" />
		<input type="hidden" name="rwgc_rule_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
		<?php if ( ! empty( $return_url ) ) : ?>
			<input type="hidden" name="rwgc_return" value="<?php echo esc_attr( (string) $return_url ); ?>" />
		<?php endif; ?>

		<div class="rwgc-rule-editor__layout">
			<div class="rwgc-rule-editor__main">
				<div class="rwgc-rule-card">
					<div class="rwgc-rule-card__header">
						<h2><?php esc_html_e( 'Rule details', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-rule-card__body">
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="rwgc_rule_title"><?php esc_html_e( 'Rule name', 'reactwoo-geocore' ); ?></label></th>
								<td><input name="rwgc_rule_title" id="rwgc_rule_title" type="text" class="regular-text" required value="<?php echo esc_attr( $title ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="rwgc_rule_status"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></label></th>
								<td>
									<select name="rwgc_rule_status" id="rwgc_rule_status">
										<option value="publish" <?php selected( $status, 'publish' ); ?>><?php esc_html_e( 'Active', 'reactwoo-geocore' ); ?></option>
										<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'reactwoo-geocore' ); ?></option>
									</select>
								</td>
							</tr>
							<?php if ( '' !== $target_label ) : ?>
								<tr>
									<th scope="row"><?php esc_html_e( 'Applies to', 'reactwoo-geocore' ); ?></th>
									<td><span class="rwgc-condition-chip"><?php echo esc_html( $target_label ); ?></span></td>
								</tr>
							<?php endif; ?>
						</table>
					</div>
				</div>

				<div class="rwgc-rule-card" id="rwgc-rule-conditions">
					<div class="rwgc-rule-card__header">
						<h2><?php esc_html_e( 'Who should see this?', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-rule-card__body">
						<div class="rwgc-rb-mount-wrap">
							<textarea name="rwgc_portable_targeting" id="rwgc_portable_targeting" rows="4" class="large-text code"><?php echo esc_textarea( $portable_raw ); ?></textarea>
						</div>
						<p class="description"><?php esc_html_e( 'Match all condition groups at the top level. Nested groups show OR logic inside a traffic trigger.', 'reactwoo-geocore' ); ?></p>
					</div>
				</div>

				<details class="rwgc-rule-card">
					<summary><?php esc_html_e( 'Advanced: stored rule data', 'reactwoo-geocore' ); ?></summary>
					<div class="rwgc-rule-card__body">
						<p class="description"><?php esc_html_e( 'Use this only for debugging or copying portable rule JSON.', 'reactwoo-geocore' ); ?></p>
						<textarea id="rwgc_portable_targeting_advanced" rows="12" class="large-text code" readonly><?php echo esc_textarea( $portable_raw ); ?></textarea>
					</div>
				</details>
			</div>

			<aside class="rwgc-rule-editor__sidebar rwgc-rule-editor__sidebar--sticky">
				<div class="rwgc-summary-card" id="rwgc-rule-summary-card">
					<div class="rwgc-summary-card__header">
						<h2><?php esc_html_e( 'Rule summary', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-summary-card__body">
						<p class="rwgc-summary-card__text"><?php echo esc_html( $summary_text ); ?></p>
						<?php if ( '' !== $target_label ) : ?>
							<div class="rwgc-summary-card__section">
								<strong><?php esc_html_e( 'Target', 'reactwoo-geocore' ); ?></strong>
								<span class="rwgc-summary-chip"><?php echo esc_html( $target_label ); ?></span>
							</div>
						<?php endif; ?>
						<?php foreach ( array( 'include' => __( 'Include', 'reactwoo-geocore' ), 'exclude' => __( 'Exclude', 'reactwoo-geocore' ), 'device' => __( 'Device', 'reactwoo-geocore' ), 'page' => __( 'Page', 'reactwoo-geocore' ), 'traffic' => __( 'Traffic', 'reactwoo-geocore' ) ) as $chip_key => $chip_label ) : ?>
							<?php if ( ! empty( $summary_chips[ $chip_key ] ) ) : ?>
								<div class="rwgc-summary-card__section">
									<strong><?php echo esc_html( $chip_label ); ?></strong>
									<?php
									$chip_items = (array) $summary_chips[ $chip_key ];
									foreach ( $chip_items as $chip_index => $chip ) :
										if ( 'traffic' === $chip_key && $chip_index > 0 ) :
											?>
											<span class="rwgc-summary-chip rwgc-summary-chip--or"><?php esc_html_e( 'OR', 'reactwoo-geocore' ); ?></span>
											<?php
										endif;
										?>
										<span class="rwgc-summary-chip"><?php echo esc_html( (string) $chip ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="rwgc-rule-card">
					<div class="rwgc-rule-card__header">
						<h2><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-rule-card__body">
						<p>
							<span class="rwgc-status-pill rwgc-status-pill--<?php echo esc_attr( 'publish' === $status_slug ? 'publish' : 'draft' ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						</p>
						<p>
							<?php if ( $is_valid ) : ?>
								<span class="rwgc-status-pill rwgc-status-pill--publish"><?php esc_html_e( 'Valid', 'reactwoo-geocore' ); ?></span>
							<?php else : ?>
								<span class="rwgc-status-pill rwgc-status-pill--warn"><?php esc_html_e( 'Needs review', 'reactwoo-geocore' ); ?></span>
							<?php endif; ?>
						</p>
						<?php foreach ( $warnings as $warning ) : ?>
							<p class="description"><?php echo esc_html( (string) $warning ); ?></p>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="rwgc-rule-card">
					<div class="rwgc-rule-card__header">
						<h2><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-rule-card__body rwgc-rule-actions">
						<?php submit_button( $save_label, 'primary', 'submit', false ); ?>
						<?php if ( $post_id > 0 ) : ?>
							<button type="button" class="button" id="rwgc-duplicate-visibility-rule"><?php esc_html_e( 'Duplicate rule', 'reactwoo-geocore' ); ?></button>
						<?php endif; ?>
						<?php if ( '' !== $delete_url ) : ?>
							<a class="button button-link-delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule permanently?', 'reactwoo-geocore' ) ); ?>');">
								<?php esc_html_e( 'Delete rule', 'reactwoo-geocore' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</aside>
		</div>

		<p class="rwgc-rule-editor__footer-save">
			<?php submit_button( $save_label, 'primary', 'submit', false ); ?>
		</p>
	</form>

	<?php if ( $post_id > 0 ) : ?>
		<script>
		(function () {
			var dup = document.getElementById('rwgc-duplicate-visibility-rule');
			if (!dup) { return; }
			dup.addEventListener('click', function () {
				var idInput = document.querySelector('#rwgc-visibility-rule-form [name="rwgc_rule_id"]');
				var titleInput = document.getElementById('rwgc_rule_title');
				if (idInput) { idInput.value = '0'; }
				if (titleInput && titleInput.value.indexOf('(copy)') === -1) {
					titleInput.value = titleInput.value.trim() + ' (copy)';
				}
				document.getElementById('rwgc-visibility-rule-form').submit();
			});
		}());
		</script>
	<?php endif; ?>

	<?php if ( $is_variant_rule ) : ?>
		<div class="rwgc-rule-card rwgc-card--full" style="margin-top:1.5em;">
			<div class="rwgc-rule-card__header">
				<h2><?php esc_html_e( 'Variant rule application', 'reactwoo-geocore' ); ?></h2>
			</div>
			<div class="rwgc-rule-card__body">
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
		</div>
	<?php endif; ?>
</div>
