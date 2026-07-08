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
$orphaned_variant_rules   = isset( $orphaned_variant_rules ) && is_array( $orphaned_variant_rules ) ? $orphaned_variant_rules : array();

/**
 * Render compact condition chips for a portable rule row.
 *
 * @param array<string, array<int, string>> $chips Chip groups from editor presenter.
 * @return void
 */
$rwgc_render_rule_chips = static function ( array $chips ) {
	$printed = false;
	foreach ( array( 'include', 'exclude', 'device', 'page', 'traffic' ) as $group ) {
		$list = isset( $chips[ $group ] ) && is_array( $chips[ $group ] ) ? $chips[ $group ] : array();
		foreach ( $list as $label ) {
			$printed = true;
			$class   = 'rwgc-rule-chip';
			if ( 'include' === $group ) {
				$class .= ' rwgc-rule-chip--include';
			} elseif ( 'exclude' === $group ) {
				$class .= ' rwgc-rule-chip--exclude';
			} elseif ( 'traffic' === $group ) {
				$class .= ' rwgc-rule-chip--traffic';
			}
			echo '<span class="' . esc_attr( $class ) . '">' . esc_html( (string) $label ) . '</span>';
		}
	}
	if ( ! $printed ) {
		echo '<span class="description">' . esc_html__( 'No conditions yet', 'reactwoo-geocore' ) . '</span>';
	}
};
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-rules-page">
	<div class="rwgc-rules-header">
		<div class="rwgc-rules-header__intro">
			<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
				<?php
				RWGC_Admin_UI::render_page_header(
					$rwgc_use_platform_shell
						? __( 'Targeting rules', 'reactwoo-geocore' )
						: __( 'Visibility rules library', 'reactwoo-geocore' ),
					__( 'Reusable portable rules and builder-attached targeting in one place.', 'reactwoo-geocore' ),
					array( 'class' => 'rwgc-suite-page-header rwgc-rules-header__title' )
				);
				?>
			<?php else : ?>
				<header class="rwgc-suite-page-header rwgc-rules-header__title">
					<h1><?php esc_html_e( 'Targeting rules', 'reactwoo-geocore' ); ?></h1>
					<p class="rwgc-suite-page-header__subtitle"><?php esc_html_e( 'Reusable portable rules and builder-attached targeting in one place.', 'reactwoo-geocore' ); ?></p>
				</header>
			<?php endif; ?>
		</div>
		<div class="rwgc-rules-header__actions">
			<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add portable rule', 'reactwoo-geocore' ); ?></a>
			<button type="button" class="rwgc-btn rwgc-btn--secondary" data-rwgc-open-rule-tester><?php esc_html_e( 'Test rule', 'reactwoo-geocore' ); ?></button>
		</div>
	</div>

	<?php if ( ! empty( $orphaned_variant_rules ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %d: orphan count */
					esc_html( _n( '%d orphaned variant rule found.', '%d orphaned variant rules found.', count( $orphaned_variant_rules ), 'reactwoo-geocore' ) ),
					count( $orphaned_variant_rules )
				);
				?>
				<a href="#rwgc-orphan-variant-rules"><?php esc_html_e( 'Review', 'reactwoo-geocore' ); ?></a>
			</p>
		</div>
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

	<section class="rwgc-rules-card" aria-labelledby="rwgc-portable-rules-heading">
		<div class="rwgc-rules-card__head">
			<div>
				<h2 id="rwgc-portable-rules-heading"><?php esc_html_e( 'Portable rule library', 'reactwoo-geocore' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Reusable rule sets you can attach in Elementor, Gutenberg, commerce, and other surfaces. Edit them here — they are not tied to a single page until applied in a builder.', 'reactwoo-geocore' ); ?>
				</p>
			</div>
			<?php if ( ! empty( $rules ) ) : ?>
				<a class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add portable rule', 'reactwoo-geocore' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $rules ) ) : ?>
			<div class="rwgc-rules-table-wrap">
				<table class="rwgc-rules-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Rule', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Applies to / target', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Conditions summary', 'reactwoo-geocore' ); ?></th>
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
							$rule_id      = (int) $rule_post->ID;
							$edit_url     = admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=' . $rule_id );
							$del_url      = wp_nonce_url(
								admin_url( 'admin-post.php?action=rwgc_delete_visibility_rule&rule_id=' . $rule_id ),
								'rwgc_delete_visibility_rule_' . $rule_id
							);
							$dup_url      = wp_nonce_url(
								admin_url( 'admin-post.php?action=rwgc_duplicate_visibility_rule&rule_id=' . $rule_id ),
								'rwgc_duplicate_visibility_rule_' . $rule_id
							);
							$is_published = 'publish' === $rule_post->post_status;
							$status_class = $is_published ? 'rwgc-rule-status-pill--published' : 'rwgc-rule-status-pill--draft';
							$status_label = $is_published
								? __( 'Published', 'reactwoo-geocore' )
								: __( 'Draft', 'reactwoo-geocore' );
							$portable_raw = (string) get_post_meta( $rule_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
							$presenter    = class_exists( 'RWGC_Visibility_Rule_Editor_Presenter', false )
								? RWGC_Visibility_Rule_Editor_Presenter::build( $rule_id, $rule_post->post_title, $rule_post->post_status, $portable_raw )
								: array( 'target_label' => '', 'chips' => array() );
							$target_label = (string) ( $presenter['target_label'] ?? '' );
							$chips        = isset( $presenter['chips'] ) && is_array( $presenter['chips'] ) ? $presenter['chips'] : array();
							?>
							<tr class="rwgc-rule-row">
								<td>
									<span class="rwgc-rule-row__title"><?php echo esc_html( $rule_post->post_title ); ?></span>
									<div class="rwgc-rule-row__meta">
										<span class="rwgc-rule-source-pill rwgc-rule-source-pill--geocore"><?php esc_html_e( 'Geo Core', 'reactwoo-geocore' ); ?></span>
										<span class="rwgc-rule-chip"><?php esc_html_e( 'Portable', 'reactwoo-geocore' ); ?></span>
									</div>
								</td>
								<td>
									<?php if ( '' !== $target_label ) : ?>
										<?php echo esc_html( $target_label ); ?>
									<?php else : ?>
										<span class="rwgc-rule-row__target"><?php esc_html_e( 'Reusable library rule', 'reactwoo-geocore' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<div class="rwgc-rule-row__chips">
										<?php $rwgc_render_rule_chips( $chips ); ?>
									</div>
								</td>
								<td>
									<span class="rwgc-rule-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
								</td>
								<td><?php echo esc_html( get_the_modified_date( '', $rule_post ) ); ?></td>
								<td>
									<div class="rwgc-rule-row__actions">
										<a class="rwgc-btn rwgc-btn--primary rwgc-btn--sm" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit rule', 'reactwoo-geocore' ); ?></a>
										<button type="button" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" data-rwgc-open-rule-tester data-rule-id="<?php echo esc_attr( (string) $rule_id ); ?>"><?php esc_html_e( 'Test', 'reactwoo-geocore' ); ?></button>
										<?php if ( function_exists( 'rwgc_can_link_ux_opportunity_review' ) && rwgc_can_link_ux_opportunity_review() ) : ?>
											<a class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" href="<?php echo esc_url( rwgc_ux_opportunity_review_admin_url( array( 'rule_id' => (string) $rule_id, 'source' => 'rules' ) ) ); ?>"><?php esc_html_e( 'Review this rule with AI', 'reactwoo-geocore' ); ?></a>
										<?php endif; ?>
										<a class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" href="<?php echo esc_url( $dup_url ); ?>"><?php esc_html_e( 'Duplicate', 'reactwoo-geocore' ); ?></a>
										<a class="rwgc-btn rwgc-btn--danger rwgc-btn--sm" href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Move this rule to trash?', 'reactwoo-geocore' ) ); ?>');"><?php esc_html_e( 'Trash', 'reactwoo-geocore' ); ?></a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="rwgc-rules-empty">
				<p class="rwgc-rules-empty__title"><?php esc_html_e( 'No portable rules yet', 'reactwoo-geocore' ); ?></p>
				<p class="rwgc-rules-empty__text"><?php esc_html_e( 'Create a reusable rule set to attach the same conditions across Elementor, blocks, and commerce.', 'reactwoo-geocore' ); ?></p>
				<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Add portable rule', 'reactwoo-geocore' ); ?></a>
			</div>
		<?php endif; ?>
	</section>

	<section class="rwgc-rules-card" aria-labelledby="rwgc-targeting-rules-heading">
		<div class="rwgc-rules-card__head">
			<div>
				<h2 id="rwgc-targeting-rules-heading"><?php esc_html_e( 'Targeting rules', 'reactwoo-geocore' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Rules created inside builders and product screens — attached to a page, popup, element, route, or store context. They are edited where they were created, not in the portable library above.', 'reactwoo-geocore' ); ?>
					<a href="<?php echo esc_url( $rule_builder_url ); ?>"><?php esc_html_e( 'Condition reference', 'reactwoo-geocore' ); ?></a>
				</p>
			</div>
		</div>

		<?php if ( ! empty( $index_rows ) ) : ?>
			<div class="rwgc-rules-table-wrap">
				<table class="rwgc-rules-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Rule', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Applied location', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Targeting summary', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Updated', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $index_rows as $row ) : ?>
							<?php
							$source_key   = isset( $row['source_key'] ) ? sanitize_key( (string) $row['source_key'] ) : '';
							$status_text  = (string) ( $row['status'] ?? '' );
							$is_active    = __( 'Active', 'reactwoo-geocore' ) === $status_text;
							$status_class = $is_active ? 'rwgc-rule-status-pill--active' : 'rwgc-rule-status-pill--draft';
							$source_class = '' !== $source_key ? ' rwgc-rule-source-pill--' . $source_key : '';
							$edit_external = ! empty( $row['edit_url'] ) && 0 === strpos( (string) $row['edit_url'], 'http' ) && false === strpos( (string) $row['edit_url'], admin_url() );
							?>
							<tr class="rwgc-rule-row">
								<td>
									<span class="rwgc-rule-row__title"><?php echo esc_html( (string) ( $row['name'] ?? '' ) ); ?></span>
									<div class="rwgc-rule-row__meta">
										<span class="rwgc-rule-chip"><?php echo esc_html( (string) ( $row['rule_scope'] ?? __( 'Builder', 'reactwoo-geocore' ) ) ); ?></span>
									</div>
								</td>
								<td>
									<?php if ( '' !== $source_key ) : ?>
										<span class="rwgc-rule-source-pill<?php echo esc_attr( $source_class ); ?>"><?php echo esc_html( (string) ( $row['source'] ?? '' ) ); ?></span>
									<?php else : ?>
										<?php echo esc_html( (string) ( $row['source'] ?? '' ) ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) ( $row['location'] ?? '' ) ); ?></td>
								<td>
									<div class="rwgc-rule-row__chips">
										<span class="rwgc-rule-chip"><?php echo esc_html( (string) ( $row['targeting'] ?? '' ) ); ?></span>
									</div>
								</td>
								<td>
									<span class="rwgc-rule-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
								</td>
								<td><?php echo esc_html( (string) ( $row['updated'] ?? '—' ) ); ?></td>
								<td>
									<?php if ( ! empty( $row['edit_url'] ) ) : ?>
										<div class="rwgc-rule-row__actions">
											<a class="rwgc-btn rwgc-btn--primary rwgc-btn--sm" href="<?php echo esc_url( (string) $row['edit_url'] ); ?>"<?php echo $edit_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
												<?php echo esc_html( (string) ( $row['edit_label'] ?? __( 'Open', 'reactwoo-geocore' ) ) ); ?>
											</a>
										</div>
										<?php if ( ! empty( $row['action_note'] ) ) : ?>
											<p class="rwgc-rule-row__note"><?php echo esc_html( (string) $row['action_note'] ); ?></p>
										<?php endif; ?>
									<?php else : ?>
										<span class="description"><?php esc_html_e( 'Edit in the builder where this rule was created.', 'reactwoo-geocore' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="rwgc-rules-empty">
				<p class="rwgc-rules-empty__title"><?php esc_html_e( 'No builder-attached rules yet', 'reactwoo-geocore' ); ?></p>
				<p class="rwgc-rules-empty__text"><?php esc_html_e( 'Create rules in Elementor, the block editor, page routing, or Geo Commerce. They will appear here with a link back to their source builder.', 'reactwoo-geocore' ); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( ! empty( $orphaned_variant_rules ) ) : ?>
		<section class="rwgc-rules-card" id="rwgc-orphan-variant-rules" aria-labelledby="rwgc-orphan-rules-heading">
			<div class="rwgc-rules-card__head">
				<div>
					<h2 id="rwgc-orphan-rules-heading"><?php esc_html_e( 'Variant rule health', 'reactwoo-geocore' ); ?></h2>
					<p class="description"><?php esc_html_e( 'These variant rules are archived or point at a removed page. Front-end evaluation fails closed (variant popups and content stay hidden).', 'reactwoo-geocore' ); ?></p>
				</div>
			</div>
			<div class="rwgc-rules-table-wrap">
				<table class="rwgc-rules-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Rule', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Variant', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Lifecycle', 'reactwoo-geocore' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $orphaned_variant_rules as $orphan ) : ?>
							<tr class="rwgc-rule-row">
								<td><span class="rwgc-rule-row__title"><?php echo esc_html( (string) ( $orphan['title'] ?? '' ) ); ?></span></td>
								<td><?php echo esc_html( (string) ( $orphan['variant'] ?? '' ) ); ?></td>
								<td><span class="rwgc-rule-status-pill rwgc-rule-status-pill--draft"><?php echo esc_html( (string) ( $orphan['lifecycle'] ?? __( 'inactive', 'reactwoo-geocore' ) ) ); ?></span></td>
								<td>
									<?php if ( ! empty( $orphan['edit_url'] ) ) : ?>
										<a class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm" href="<?php echo esc_url( (string) $orphan['edit_url'] ); ?>"><?php esc_html_e( 'Review', 'reactwoo-geocore' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>
</div>
