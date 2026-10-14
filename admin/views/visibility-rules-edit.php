<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_is_new                  = ! empty( $rwgc_is_new );
$rwgc_post_id                 = isset( $rwgc_post_id ) ? (int) $rwgc_post_id : 0;
$rwgc_title                   = isset( $rwgc_title ) ? (string) $rwgc_title : '';
$rwgc_status                  = isset( $rwgc_status ) ? (string) $rwgc_status : 'draft';
$rwgc_portable_raw            = isset( $rwgc_portable_raw ) ? (string) $rwgc_portable_raw : '';
$rwgc_list_url                = isset( $rwgc_list_url ) ? (string) $rwgc_list_url : admin_url( 'admin.php?page=rwgc-visibility-rules' );
$rwgc_form_url                = admin_url( 'admin-post.php' );
$rwgc_editor_presenter        = isset( $rwgc_editor_presenter ) && is_array( $rwgc_editor_presenter ) ? $rwgc_editor_presenter : array();
$rwgc_variant_provenance      = isset( $rwgc_variant_provenance ) && is_array( $rwgc_variant_provenance ) ? $rwgc_variant_provenance : array();
$rwgc_variant_references      = isset( $rwgc_variant_references ) && is_array( $rwgc_variant_references ) ? $rwgc_variant_references : array();
$rwgc_variant_sync_url        = isset( $rwgc_variant_sync_url ) ? (string) $rwgc_variant_sync_url : '';
$rwgc_is_variant_rule         = ! empty( $rwgc_variant_provenance['sourceType'] ) && 'page_variant' === $rwgc_variant_provenance['sourceType'];
$rwgc_target_label            = (string) ( $rwgc_editor_presenter['target_label'] ?? '' );
$rwgc_summary_text            = (string) ( $rwgc_editor_presenter['summary'] ?? '' );
$rwgc_summary_chips           = isset( $rwgc_editor_presenter['chips'] ) && is_array( $rwgc_editor_presenter['chips'] ) ? $rwgc_editor_presenter['chips'] : array();
$rwgc_status_label            = (string) ( $rwgc_editor_presenter['status_label'] ?? ( 'publish' === $rwgc_status ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ) ) );
$rwgc_status_slug             = (string) ( $rwgc_editor_presenter['status_slug'] ?? $rwgc_status );
$rwgc_is_valid                = ! empty( $rwgc_editor_presenter['valid'] );
$rwgc_warnings                = isset( $rwgc_editor_presenter['warnings'] ) && is_array( $rwgc_editor_presenter['warnings'] ) ? $rwgc_editor_presenter['warnings'] : array();
$rwgc_rule_set_decoded        = '' !== trim( $rwgc_portable_raw ) ? json_decode( $rwgc_portable_raw, true ) : null;
$rwgc_scope_summary           = class_exists( 'RWGC_Rule_Context_Compatibility', false ) && is_array( $rwgc_rule_set_decoded )
	? RWGC_Rule_Context_Compatibility::scope_summary( $rwgc_rule_set_decoded )
	: __( 'Any content', 'reactwoo-geocore' );
$rwgc_scope_has_page_constraint = class_exists( 'RWGC_Rule_Context_Compatibility', false ) && is_array( $rwgc_rule_set_decoded )
	&& 'Any content' !== $rwgc_scope_summary
	&& false === strpos( $rwgc_scope_summary, 'URL contains' );
$rwgc_delete_url              = $rwgc_post_id > 0
	? wp_nonce_url(
		admin_url( 'admin-post.php?action=rwgc_delete_visibility_rule&rule_id=' . $rwgc_post_id ),
		'rwgc_delete_visibility_rule_' . $rwgc_post_id
	)
	: '';
$rwgc_logic_preview         = isset( $rwgc_editor_presenter['logic_preview'] ) && is_array( $rwgc_editor_presenter['logic_preview'] ) ? $rwgc_editor_presenter['logic_preview'] : array();
$rwgc_logic_intro           = (string) ( $rwgc_logic_preview['intro'] ?? '' );
$rwgc_logic_lines           = isset( $rwgc_logic_preview['lines'] ) && is_array( $rwgc_logic_preview['lines'] ) ? $rwgc_logic_preview['lines'] : array();
$rwgc_save_label            = $rwgc_is_new ? __( 'Create rule', 'reactwoo-geocore' ) : __( 'Save rule', 'reactwoo-geocore' );
$rwgc_page_title            = $rwgc_is_new ? __( 'Add visibility rule', 'reactwoo-geocore' ) : __( 'Edit visibility rule', 'reactwoo-geocore' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-rule-editor">
	<div class="rwgc-rule-editor__header">
		<div class="rwgc-rule-editor__header-copy">
			<h1><?php echo esc_html( $rwgc_page_title ); ?></h1>
			<p class="rwgc-rule-editor__subheader">
				<?php esc_html_e( 'Build reusable GeoCore rules for pages, popups, products, Elementor, blocks, and commerce.', 'reactwoo-geocore' ); ?>
			</p>
		</div>
		<div class="rwgc-rule-editor__top-actions">
			<a class="button" href="<?php echo esc_url( $rwgc_list_url ); ?>">&larr; <?php esc_html_e( 'Back to rules', 'reactwoo-geocore' ); ?></a>
			<button type="submit" class="button button-primary" form="rwgc-visibility-rule-form"><?php echo esc_html( $rwgc_save_label ); ?></button>
			<a class="button" href="#rwgc-rule-logic-preview"><?php esc_html_e( 'Preview logic', 'reactwoo-geocore' ); ?></a>
			<button type="button" class="button" data-rwgc-open-rule-tester><?php esc_html_e( 'Test rule', 'reactwoo-geocore' ); ?></button>
			<?php if ( $rwgc_post_id > 0 && function_exists( 'rwgc_can_link_ux_opportunity_review' ) && rwgc_can_link_ux_opportunity_review() ) : ?>
				<a class="button" href="<?php echo esc_url( rwgc_ux_opportunity_review_admin_url( array( 'rule_id' => (string) $rwgc_post_id, 'source' => 'rules' ) ) ); ?>"><?php esc_html_e( 'Review this rule with AI', 'reactwoo-geocore' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( isset( $_GET['updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['updated'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rule saved.', 'reactwoo-geocore' ); ?></p></div>
		<?php if ( isset( $_GET['rwgc_synced'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of popups updated */
						esc_html__( 'Synced this rule to %d referenced popup(s).', 'reactwoo-geocore' ),
						absint( wp_unslash( $_GET['rwgc_synced'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<form id="rwgc-visibility-rule-form" method="post" action="<?php echo esc_url( $rwgc_form_url ); ?>" <?php echo '' !== $rwgc_target_label ? 'data-rwgc-target-label="' . esc_attr( $rwgc_target_label ) . '"' : ''; ?>>
		<?php wp_nonce_field( 'rwgc_save_visibility_rule' ); ?>
		<input type="hidden" name="action" value="rwgc_save_visibility_rule" />
		<input type="hidden" name="rwgc_rule_id" value="<?php echo esc_attr( (string) $rwgc_post_id ); ?>" />
		<?php if ( ! empty( $rwgc_return_url ) ) : ?>
			<input type="hidden" name="rwgc_return" value="<?php echo esc_attr( (string) $rwgc_return_url ); ?>" />
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
								<td><input name="rwgc_rule_title" id="rwgc_rule_title" type="text" class="regular-text" required value="<?php echo esc_attr( $rwgc_title ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="rwgc_rule_status"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></label></th>
								<td>
									<select name="rwgc_rule_status" id="rwgc_rule_status">
										<option value="publish" <?php selected( $rwgc_status, 'publish' ); ?>><?php esc_html_e( 'Active', 'reactwoo-geocore' ); ?></option>
										<option value="draft" <?php selected( $rwgc_status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'reactwoo-geocore' ); ?></option>
									</select>
								</td>
							</tr>
							<?php if ( '' !== $rwgc_target_label ) : ?>
								<tr>
									<th scope="row"><?php esc_html_e( 'Applies to', 'reactwoo-geocore' ); ?></th>
									<td><span class="rwgc-condition-chip"><?php echo esc_html( $rwgc_target_label ); ?></span></td>
								</tr>
							<?php endif; ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Rule scope', 'reactwoo-geocore' ); ?></th>
								<td>
									<span class="rwgc-condition-chip"><?php echo esc_html( $rwgc_scope_summary ); ?></span>
									<?php if ( $rwgc_scope_has_page_constraint ) : ?>
										<p class="description" style="margin:8px 0 0;">
											<?php esc_html_e( 'This rule should only be applied to matching content contexts unless you intentionally use it as a non-match rule.', 'reactwoo-geocore' ); ?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<div class="rwgc-rule-card" id="rwgc-rule-conditions">
					<div class="rwgc-rule-card__header">
						<h2><?php esc_html_e( 'Who should see this?', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-rule-card__body">
						<div class="rwgc-rb-mount-wrap">
							<textarea name="rwgc_portable_targeting" id="rwgc_portable_targeting" rows="4" class="large-text code"><?php echo esc_textarea( $rwgc_portable_raw ); ?></textarea>
						</div>
						<p class="description"><?php esc_html_e( 'Match all condition groups at the top level. Nested groups show OR logic inside a traffic trigger.', 'reactwoo-geocore' ); ?></p>
					</div>
				</div>

				<div class="rwgc-rule-card" id="rwgc-rule-logic-preview">
					<div class="rwgc-rule-card__header">
						<h2><?php esc_html_e( 'Logic preview', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-rule-card__body rwgc-logic-preview" id="rwgc-rule-logic-preview-body">
						<?php if ( '' !== $rwgc_logic_intro ) : ?>
							<p class="rwgc-logic-preview__intro"><?php echo esc_html( $rwgc_logic_intro ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $rwgc_logic_lines ) ) : ?>
							<ol class="rwgc-logic-preview__list">
								<?php foreach ( $rwgc_logic_lines as $rwgc_line ) : ?>
									<li>
										<span><?php echo esc_html( (string) ( $rwgc_line['text'] ?? '' ) ); ?></span>
										<?php if ( ! empty( $rwgc_line['children'] ) && is_array( $rwgc_line['children'] ) ) : ?>
											<ul class="rwgc-logic-preview__sublist">
												<?php foreach ( $rwgc_line['children'] as $rwgc_child ) : ?>
													<li><?php echo esc_html( (string) $rwgc_child ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ol>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Add conditions to generate a logic preview.', 'reactwoo-geocore' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<details class="rwgc-rule-card">
					<summary><?php esc_html_e( 'Advanced: stored rule data', 'reactwoo-geocore' ); ?></summary>
					<div class="rwgc-rule-card__body">
						<p class="description"><?php esc_html_e( 'Use this only for debugging or copying portable rule JSON.', 'reactwoo-geocore' ); ?></p>
						<textarea id="rwgc_portable_targeting_advanced" rows="12" class="large-text code" readonly><?php echo esc_textarea( $rwgc_portable_raw ); ?></textarea>
					</div>
				</details>
			</div>

			<aside class="rwgc-rule-editor__sidebar rwgc-rule-editor__sidebar--sticky">
				<div class="rwgc-summary-card" id="rwgc-rule-summary-card">
					<div class="rwgc-summary-card__header">
						<h2><?php esc_html_e( 'Rule summary', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-summary-card__body">
						<p class="rwgc-summary-card__text"><?php echo esc_html( $rwgc_summary_text ); ?></p>
						<?php if ( '' !== $rwgc_target_label ) : ?>
							<div class="rwgc-summary-card__section">
								<strong><?php esc_html_e( 'Target', 'reactwoo-geocore' ); ?></strong>
								<span class="rwgc-summary-chip"><?php echo esc_html( $rwgc_target_label ); ?></span>
							</div>
						<?php endif; ?>
						<?php foreach ( array( 'include' => __( 'Include', 'reactwoo-geocore' ), 'exclude' => __( 'Exclude', 'reactwoo-geocore' ), 'device' => __( 'Device', 'reactwoo-geocore' ), 'page' => __( 'Page', 'reactwoo-geocore' ), 'traffic' => __( 'Traffic', 'reactwoo-geocore' ) ) as $rwgc_chip_key => $rwgc_chip_label ) : ?>
							<?php if ( ! empty( $rwgc_summary_chips[ $rwgc_chip_key ] ) ) : ?>
								<div class="rwgc-summary-card__section">
									<strong><?php echo esc_html( $rwgc_chip_label ); ?></strong>
									<?php
									$rwgc_chip_items = (array) $rwgc_summary_chips[ $rwgc_chip_key ];
									foreach ( $rwgc_chip_items as $rwgc_chip_index => $rwgc_chip ) :
										if ( 'traffic' === $rwgc_chip_key && $rwgc_chip_index > 0 ) :
											?>
											<span class="rwgc-summary-chip rwgc-summary-chip--or"><?php esc_html_e( 'OR', 'reactwoo-geocore' ); ?></span>
											<?php
										endif;
										?>
										<span class="rwgc-summary-chip"><?php echo esc_html( (string) $rwgc_chip ); ?></span>
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
							<span class="rwgc-status-pill rwgc-status-pill--<?php echo esc_attr( 'publish' === $rwgc_status_slug ? 'publish' : 'draft' ); ?>">
								<?php echo esc_html( $rwgc_status_label ); ?>
							</span>
						</p>
						<p>
							<?php if ( $rwgc_is_valid ) : ?>
								<span class="rwgc-status-pill rwgc-status-pill--publish"><?php esc_html_e( 'Valid', 'reactwoo-geocore' ); ?></span>
							<?php else : ?>
								<span class="rwgc-status-pill rwgc-status-pill--warn"><?php esc_html_e( 'Needs review', 'reactwoo-geocore' ); ?></span>
							<?php endif; ?>
						</p>
						<?php foreach ( $rwgc_warnings as $rwgc_warning ) : ?>
							<p class="description"><?php echo esc_html( (string) $rwgc_warning ); ?></p>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="rwgc-rule-card">
					<div class="rwgc-rule-card__header">
						<h2><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></h2>
					</div>
					<div class="rwgc-rule-card__body rwgc-rule-actions">
						<?php submit_button( $rwgc_save_label, 'primary', 'submit', false ); ?>
						<button type="button" class="button" data-rwgc-open-rule-tester><?php esc_html_e( 'Test rule', 'reactwoo-geocore' ); ?></button>
						<?php if ( $rwgc_post_id > 0 ) : ?>
							<button type="button" class="button" id="rwgc-duplicate-visibility-rule"><?php esc_html_e( 'Duplicate rule', 'reactwoo-geocore' ); ?></button>
						<?php endif; ?>
						<?php if ( '' !== $rwgc_delete_url ) : ?>
							<a class="button button-link-delete" href="<?php echo esc_url( $rwgc_delete_url ); ?>" data-rwgc-confirm="<?php echo esc_attr( __( 'Delete this rule permanently?', 'reactwoo-geocore' ) ); ?>">
								<?php esc_html_e( 'Delete rule', 'reactwoo-geocore' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</aside>
		</div>

		<p class="rwgc-rule-editor__footer-save">
			<?php submit_button( $rwgc_save_label, 'primary', 'submit', false ); ?>
		</p>
	</form>

	<?php if ( $rwgc_is_variant_rule ) : ?>
		<div class="rwgc-rule-card rwgc-card--full" style="margin-top:1.5em;">
			<div class="rwgc-rule-card__header">
				<h2><?php esc_html_e( 'Variant rule application', 'reactwoo-geocore' ); ?></h2>
			</div>
			<div class="rwgc-rule-card__body">
				<p class="description">
					<?php esc_html_e( 'This rule was created for a page variant. It defines who matches; each popup or element must still reference this rule to use it.', 'reactwoo-geocore' ); ?>
				</p>
				<?php
				$rwgc_src_page = (int) ( $rwgc_variant_provenance['sourcePageId'] ?? 0 );
				$rwgc_src_ver  = (string) ( $rwgc_variant_provenance['sourceVariant'] ?? '' );
				$rwgc_src_url  = (string) ( $rwgc_variant_provenance['sourceUrl'] ?? '' );
				?>
				<p>
					<strong><?php esc_html_e( 'Created from:', 'reactwoo-geocore' ); ?></strong>
					<?php
					if ( $rwgc_src_page > 0 ) {
						echo esc_html( get_the_title( $rwgc_src_page ) . ( $rwgc_src_ver ? ' / ' . $rwgc_src_ver : '' ) );
					} else {
						esc_html_e( 'Page variant', 'reactwoo-geocore' );
					}
					?>
					<?php if ( '' !== $rwgc_src_url ) : ?>
						<br /><code><?php echo esc_html( wp_parse_url( $rwgc_src_url, PHP_URL_PATH ) ?: $rwgc_src_url ); ?></code>
					<?php endif; ?>
				</p>
				<p class="notice notice-warning inline" style="margin:0 0 1em;padding:.65em 1em;">
					<?php esc_html_e( 'If you want popups or replacement content to follow this variant, apply the rule to those surfaces too. Popups do not inherit variant rules automatically.', 'reactwoo-geocore' ); ?>
				</p>
				<?php if ( ! empty( $rwgc_variant_references ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Referenced surface', 'reactwoo-geocore' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rwgc_variant_references as $rwgc_ref ) : ?>
								<?php
								$rwgc_ref_status = (string) ( $rwgc_ref['status'] ?? '' );
								$rwgc_status_lbl = 'rule_applied' === $rwgc_ref_status
									? __( 'Rule applied', 'reactwoo-geocore' )
									: __( 'Rule not applied', 'reactwoo-geocore' );
								?>
								<tr>
									<td>
										<?php
										echo esc_html(
											sprintf(
												'%1$s: %2$s',
												ucfirst( (string) ( $rwgc_ref['type'] ?? __( 'Surface', 'reactwoo-geocore' ) ) ),
												(string) ( $rwgc_ref['title'] ?? ( $rwgc_ref['id'] ?? '' ) )
											)
										);
										?>
									</td>
									<td><?php echo esc_html( $rwgc_status_lbl ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No popups or surfaces reference this rule yet. Attach it in Elementor popup settings or use Sync below after you configure popups.', 'reactwoo-geocore' ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $rwgc_variant_sync_url ) : ?>
					<p style="margin-top:1em;">
						<a class="button button-secondary" href="<?php echo esc_url( $rwgc_variant_sync_url ); ?>">
							<?php esc_html_e( 'Apply to referenced popups', 'reactwoo-geocore' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
