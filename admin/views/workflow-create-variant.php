<?php
/**
 * Guided flow — create a geo experience (visitor condition + page version).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $result ) ) {
	$result = null;
}
$prefill_master     = isset( $prefill_master ) ? absint( $prefill_master ) : 0;
$prefill_rule_id    = isset( $prefill_rule_id ) ? absint( $prefill_rule_id ) : 0;
$prefill_condition  = isset( $prefill_condition ) ? sanitize_key( (string) $prefill_condition ) : '';
$library_rules      = class_exists( 'RWGC_Experience_Workflow', false ) ? RWGC_Experience_Workflow::get_library_rule_options() : array();
$content_modes      = class_exists( 'RWGC_Experience_Workflow', false ) ? RWGC_Experience_Workflow::get_content_modes() : array( 'duplicate', 'existing', 'blank' );
$create_rule_url    = class_exists( 'RWGC_Experience_Workflow', false ) ? RWGC_Experience_Workflow::get_create_rule_url() : admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=new' );
$has_geo_ai         = in_array( 'ai_adapt', $content_modes, true );

if ( '' === $prefill_condition ) {
	$prefill_condition = $prefill_rule_id > 0 ? 'saved_rule' : 'countries';
}

$next_steps = array();
if ( is_array( $result ) && ! empty( $result['variant_page_id'] ) && class_exists( 'RWGC_Workflows', false ) ) {
	$next_steps = RWGC_Workflows::get_next_steps( 'variant_created', $result );
	if ( ! empty( $result['ai_handoff_url'] ) ) {
		array_unshift(
			$next_steps,
			array(
				'label' => __( 'Adapt copy with Geo AI', 'reactwoo-geocore' ),
				'url'   => (string) $result['ai_handoff_url'],
				'style' => 'primary',
			)
		);
		if ( ! empty( $next_steps[1]['style'] ) && 'primary' === $next_steps[1]['style'] ) {
			$next_steps[1]['style'] = 'secondary';
		}
	}
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Create Geo Rule', 'reactwoo-geocore' ),
		__( 'Choose who sees a different page version and how that version is created.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-workflow-variant' ); ?>

	<ol class="rwgc-suite-stepper" aria-label="<?php esc_attr_e( 'Experience builder steps', 'reactwoo-geocore' ); ?>">
		<li class="rwgc-suite-stepper__item is-current is-reached"><span class="rwgc-suite-stepper__num">1</span><?php esc_html_e( 'Name', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">2</span><?php esc_html_e( 'Default page', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">3</span><?php esc_html_e( 'Visitor condition', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">4</span><?php esc_html_e( 'Page version', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">5</span><?php esc_html_e( 'Review', 'reactwoo-geocore' ); ?></li>
	</ol>

	<?php if ( isset( $_GET['rwgc_rule_saved'] ) && '1' === (string) wp_unslash( $_GET['rwgc_rule_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="rwgc-suite-notice-ok"><p><?php esc_html_e( 'Targeting rule saved. Select it below under “Use saved rule”.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<?php if ( is_array( $result ) && isset( $result['error'] ) ) : ?>
		<div class="rwgc-suite-notice-err"><p><?php echo esc_html( (string) $result['error'] ); ?></p></div>
	<?php elseif ( is_array( $result ) && ! empty( $result['variant_page_id'] ) ) : ?>
		<div class="rwgc-suite-notice-ok">
			<p><strong><?php esc_html_e( 'Experience saved.', 'reactwoo-geocore' ); ?></strong></p>
			<?php if ( ! empty( $result['linked_existing'] ) ) : ?>
				<p><?php esc_html_e( 'The existing page is now linked as the local version.', 'reactwoo-geocore' ); ?></p>
			<?php elseif ( ! empty( $result['copy_draft_ids'] ) ) : ?>
				<p><?php esc_html_e( 'Geo AI generated copy drafts from your page content and targeting rule. Review them before publishing.', 'reactwoo-geocore' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Review the draft, adjust content, then publish when ready.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $result['copy_error'] ) ) : ?>
				<p class="description"><?php echo esc_html( (string) $result['copy_error'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $next_steps ) ) : ?>
			<div class="rwgc-suite-next-actions">
				<?php foreach ( $next_steps as $step ) : ?>
					<?php
					if ( empty( $step['label'] ) || empty( $step['url'] ) ) {
						continue;
					}
					$is_primary = isset( $step['style'] ) && 'primary' === $step['style'];
					$btn_class  = $is_primary ? 'button button-primary' : 'button';
					?>
					<a class="<?php echo esc_attr( $btn_class ); ?>" href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-suite-workflow-form rwgc-exp-workflow-form" id="rwgc-exp-workflow-form">
		<?php wp_nonce_field( 'rwgc_create_variant_workflow' ); ?>
		<input type="hidden" name="action" value="rwgc_create_variant_workflow" />

		<div class="rwgc-suite-field">
			<label for="rwgc_experience_name"><?php esc_html_e( 'Experience name (optional)', 'reactwoo-geocore' ); ?></label>
			<input type="text" name="rwgc_experience_name" id="rwgc_experience_name" class="regular-text" placeholder="<?php esc_attr_e( 'Example: UK Homepage Hero', 'reactwoo-geocore' ); ?>" />
		</div>

		<div class="rwgc-suite-field">
			<label for="rwgc_master_page_id"><?php esc_html_e( 'Default page', 'reactwoo-geocore' ); ?></label>
			<p class="description"><?php esc_html_e( 'Visitors who do not match your condition see this page.', 'reactwoo-geocore' ); ?></p>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => 'rwgc_master_page_id',
					'id'                => 'rwgc_master_page_id',
					'show_option_none'  => __( '— Select —', 'reactwoo-geocore' ),
					'option_none_value' => '0',
					'selected'          => $prefill_master,
					'class'             => 'widefat',
				)
			);
			?>
		</div>

		<div class="rwgc-suite-field">
			<span class="label"><?php esc_html_e( 'Who should see a different version?', 'reactwoo-geocore' ); ?></span>
			<p class="description"><?php esc_html_e( 'Choose how visitors are matched before routing to a local page version.', 'reactwoo-geocore' ); ?></p>
			<label><input type="radio" name="rwgc_condition_type" value="everyone" <?php checked( $prefill_condition, 'everyone' ); ?> /> <?php esc_html_e( 'Everyone (default page only — link an existing page if needed)', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_condition_type" value="countries" <?php checked( $prefill_condition, 'countries' ); ?> /> <?php esc_html_e( 'Selected countries', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_condition_type" value="saved_rule" <?php checked( $prefill_condition, 'saved_rule' ); ?> /> <?php esc_html_e( 'Use saved targeting rule', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_condition_type" value="create_rule" <?php checked( $prefill_condition, 'create_rule' ); ?> /> <?php esc_html_e( 'Create a new targeting rule…', 'reactwoo-geocore' ); ?></label>
		</div>

		<div class="rwgc-exp-panel rwgc-exp-panel--countries" data-rwgc-exp-panel="countries">
			<div class="rwgc-suite-field">
				<label for="rwgc_countries"><?php esc_html_e( 'Countries', 'reactwoo-geocore' ); ?></label>
				<p class="description"><?php esc_html_e( 'Hold Ctrl (Windows) or Cmd (Mac) to select multiple countries. The first country is used for page routing.', 'reactwoo-geocore' ); ?></p>
				<?php
				if ( class_exists( 'RWGC_Experience_Workflow', false ) ) {
					RWGC_Experience_Workflow::render_country_multi_select( 'rwgc_countries', array(), array( 'id' => 'rwgc_countries' ) );
				}
				?>
			</div>
			<div class="rwgc-suite-field">
				<label><input type="checkbox" name="rwgc_save_countries_as_rule" value="1" /> <?php esc_html_e( 'Also save this country list as a reusable targeting rule', 'reactwoo-geocore' ); ?></label>
			</div>
		</div>

		<div class="rwgc-exp-panel rwgc-exp-panel--saved_rule" data-rwgc-exp-panel="saved_rule">
			<div class="rwgc-suite-field">
				<label for="rwgc_saved_rule_id"><?php esc_html_e( 'Saved rule', 'reactwoo-geocore' ); ?></label>
				<?php if ( empty( $library_rules ) ) : ?>
					<p class="description"><?php esc_html_e( 'No targeting rules in your library yet.', 'reactwoo-geocore' ); ?></p>
					<p><a class="button" href="<?php echo esc_url( $create_rule_url ); ?>"><?php esc_html_e( 'Create targeting rule', 'reactwoo-geocore' ); ?></a></p>
				<?php else : ?>
					<select name="rwgc_saved_rule_id" id="rwgc_saved_rule_id" class="widefat">
						<option value="0"><?php esc_html_e( '— Select rule —', 'reactwoo-geocore' ); ?></option>
						<?php foreach ( $library_rules as $rule_row ) : ?>
							<option value="<?php echo (int) $rule_row['id']; ?>" <?php selected( $prefill_rule_id, (int) $rule_row['id'] ); ?>>
								<?php
								echo esc_html( $rule_row['title'] );
								if ( '' !== $rule_row['summary'] ) {
									echo ' — ' . esc_html( $rule_row['summary'] );
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><a href="<?php echo esc_url( $create_rule_url ); ?>"><?php esc_html_e( 'Create another rule', 'reactwoo-geocore' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="rwgc-exp-panel rwgc-exp-panel--create_rule" data-rwgc-exp-panel="create_rule">
			<p class="description"><?php esc_html_e( 'Open the rule library to build conditions (country, time, campaign, and more). When you save, you will return here to finish the experience.', 'reactwoo-geocore' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $create_rule_url ); ?>"><?php esc_html_e( 'Open rule editor', 'reactwoo-geocore' ); ?></a></p>
		</div>

		<div class="rwgc-suite-field rwgc-exp-content-modes">
			<span class="label"><?php esc_html_e( 'Local page version', 'reactwoo-geocore' ); ?></span>
			<p class="description rwgc-exp-everyone-note"><?php esc_html_e( 'With “Everyone”, only linking an existing page is available — all visitors keep the default page unless you route by country or saved rule.', 'reactwoo-geocore' ); ?></p>
			<label class="rwgc-exp-mode rwgc-exp-mode--duplicate"><input type="radio" name="rwgc_content_mode" value="duplicate" checked="checked" /> <?php esc_html_e( 'Duplicate default page (recommended)', 'reactwoo-geocore' ); ?></label><br />
			<label class="rwgc-exp-mode rwgc-exp-mode--existing"><input type="radio" name="rwgc_content_mode" value="existing" /> <?php esc_html_e( 'Use existing page', 'reactwoo-geocore' ); ?></label><br />
			<label class="rwgc-exp-mode rwgc-exp-mode--blank"><input type="radio" name="rwgc_content_mode" value="blank" /> <?php esc_html_e( 'Start from blank draft', 'reactwoo-geocore' ); ?></label><br />
			<?php if ( $has_geo_ai ) : ?>
			<label class="rwgc-exp-mode rwgc-exp-mode--ai_adapt"><input type="radio" name="rwgc_content_mode" value="ai_adapt" /> <?php esc_html_e( 'Duplicate, then adapt copy with Geo AI', 'reactwoo-geocore' ); ?></label>
			<?php endif; ?>
		</div>

		<div class="rwgc-exp-panel rwgc-exp-panel--existing" data-rwgc-exp-panel="existing_variant">
			<div class="rwgc-suite-field">
				<label for="rwgc_existing_variant_id"><?php esc_html_e( 'Existing page', 'reactwoo-geocore' ); ?></label>
				<select name="rwgc_existing_variant_id" id="rwgc_existing_variant_id" class="widefat">
					<option value="0"><?php esc_html_e( '— Select page —', 'reactwoo-geocore' ); ?></option>
					<?php
					if ( $prefill_master > 0 && class_exists( 'RWGC_Experience_Workflow', false ) ) {
						foreach ( RWGC_Experience_Workflow::get_linkable_variant_pages( $prefill_master ) as $link_page ) {
							printf(
								'<option value="%1$d">%2$s</option>',
								(int) $link_page['id'],
								esc_html( $link_page['title'] )
							);
						}
					}
					?>
				</select>
				<p class="description"><?php esc_html_e( 'Pick the default page first to refresh this list.', 'reactwoo-geocore' ); ?></p>
			</div>
		</div>

		<p class="rwgc-exp-review description">
			<?php esc_html_e( 'When a visitor matches your condition, they are routed to the local page version you choose. Drafts are never auto-published.', 'reactwoo-geocore' ); ?>
		</p>

		<p>
			<button type="submit" class="button button-primary button-hero rwgc-exp-submit"><?php esc_html_e( 'Save experience', 'reactwoo-geocore' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-suite-variants' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'reactwoo-geocore' ); ?></a>
		</p>
	</form>
</div>
