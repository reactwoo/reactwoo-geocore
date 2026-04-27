<?php
/**
 * Guided flow — create a country-specific page linked to a default page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $result ) ) {
	$result = null;
}
$prefill_master = isset( $prefill_master ) ? absint( $prefill_master ) : 0;

$next_steps = array();
if ( is_array( $result ) && ! empty( $result['variant_page_id'] ) && class_exists( 'RWGC_Workflows', false ) ) {
	$next_steps = RWGC_Workflows::get_next_steps( 'variant_created', $result );
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Create Geo Rule', 'reactwoo-geocore' ),
		__( 'Build a rule in five simple steps.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-workflow-variant' ); ?>

	<ol class="rwgc-suite-stepper" aria-label="<?php esc_attr_e( 'Rule builder steps', 'reactwoo-geocore' ); ?>">
		<li class="rwgc-suite-stepper__item is-current is-reached"><span class="rwgc-suite-stepper__num">1</span><?php esc_html_e( 'Rule name', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">2</span><?php esc_html_e( 'Visitor condition', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">3</span><?php esc_html_e( 'Action', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">4</span><?php esc_html_e( 'Target', 'reactwoo-geocore' ); ?></li>
		<li class="rwgc-suite-stepper__item is-reached"><span class="rwgc-suite-stepper__num">5</span><?php esc_html_e( 'Review', 'reactwoo-geocore' ); ?></li>
	</ol>

	<?php if ( is_array( $result ) && isset( $result['error'] ) ) : ?>
		<div class="rwgc-suite-notice-err"><p><?php echo esc_html( (string) $result['error'] ); ?></p></div>
	<?php elseif ( is_array( $result ) && ! empty( $result['variant_page_id'] ) ) : ?>
		<div class="rwgc-suite-notice-ok">
			<p><strong><?php esc_html_e( 'Local version created.', 'reactwoo-geocore' ); ?></strong></p>
			<p><?php esc_html_e( 'Review the draft, adjust content, then publish when ready.', 'reactwoo-geocore' ); ?></p>
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

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-suite-workflow-form">
		<?php wp_nonce_field( 'rwgc_create_variant_workflow' ); ?>
		<input type="hidden" name="action" value="rwgc_create_variant_workflow" />

		<div class="rwgc-suite-field">
			<label for="rwgc_rule_name"><?php esc_html_e( 'Rule name', 'reactwoo-geocore' ); ?></label>
			<input type="text" id="rwgc_rule_name" class="regular-text" placeholder="<?php esc_attr_e( 'Example: UK Homepage Hero', 'reactwoo-geocore' ); ?>" />
		</div>

		<div class="rwgc-suite-field">
			<label for="rwgc_master_page_id"><?php esc_html_e( 'Step 2: Who should this apply to?', 'reactwoo-geocore' ); ?></label>
			<?php
			wp_dropdown_pages(
				array(
					'name'              => 'rwgc_master_page_id',
					'id'                => 'rwgc_master_page_id',
					'show_option_none'  => __( '— Select —', 'reactwoo-geocore' ),
					'option_none_value' => '0',
					'selected'          => $prefill_master,
				)
			);
			?>
		</div>

		<div class="rwgc-suite-field">
			<label for="rwgc_country_iso2"><?php esc_html_e( 'Country condition', 'reactwoo-geocore' ); ?></label>
			<?php
			RWGC_Admin::render_country_select(
				'rwgc_country_iso2',
				'',
				array(
					'id'               => 'rwgc_country_iso2',
					'class'            => 'rwgc-select-country widefat',
					'show_option_none' => __( '— Select country —', 'reactwoo-geocore' ),
					'option_none_value'=> '',
				)
			);
			?>
		</div>

		<div class="rwgc-suite-field">
			<span class="label"><?php esc_html_e( 'Step 3: Choose action', 'reactwoo-geocore' ); ?></span><br />
			<label><input type="radio" name="rwgc_rule_action" value="route" checked="checked" /> <?php esc_html_e( 'Route to page version', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_rule_action" value="show" /> <?php esc_html_e( 'Show content', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_rule_action" value="hide" /> <?php esc_html_e( 'Hide content', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_rule_action" value="redirect" /> <?php esc_html_e( 'Redirect visitor', 'reactwoo-geocore' ); ?></label>
		</div>

		<div class="rwgc-suite-field">
			<span class="label"><?php esc_html_e( 'Step 4: Choose target', 'reactwoo-geocore' ); ?></span><br />
			<select name="rwgc_rule_target" class="widefat">
				<option value="page_version"><?php esc_html_e( 'Page version', 'reactwoo-geocore' ); ?></option>
				<option value="page"><?php esc_html_e( 'Entire page', 'reactwoo-geocore' ); ?></option>
				<option value="elementor"><?php esc_html_e( 'Elementor section/widget', 'reactwoo-geocore' ); ?></option>
				<option value="gutenberg"><?php esc_html_e( 'Gutenberg block', 'reactwoo-geocore' ); ?></option>
				<option value="popup"><?php esc_html_e( 'Popup', 'reactwoo-geocore' ); ?></option>
				<option value="global"><?php esc_html_e( 'Global element', 'reactwoo-geocore' ); ?></option>
			</select>
		</div>

		<div class="rwgc-suite-field">
			<span class="label"><?php esc_html_e( 'Step 5: Review', 'reactwoo-geocore' ); ?></span>
			<p class="description"><?php esc_html_e( 'When a visitor matches [country], route to [page version].', 'reactwoo-geocore' ); ?></p>
		</div>

		<div class="rwgc-suite-field">
			<span class="label"><?php esc_html_e( 'Starting content', 'reactwoo-geocore' ); ?></span><br />
			<label><input type="radio" name="rwgc_variant_mode" value="duplicate" checked="checked" /> <?php esc_html_e( 'Copy content from the default page (recommended)', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_variant_mode" value="blank" /> <?php esc_html_e( 'Start from a blank draft', 'reactwoo-geocore' ); ?></label>
		</div>

		<p>
			<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save & Activate', 'reactwoo-geocore' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-suite-variants' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'reactwoo-geocore' ); ?></a>
		</p>
	</form>

	<details class="rwgc-tech-ref-details rwgc-suite-workflow-note">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Advanced settings', 'reactwoo-geocore' ); ?></summary>
		<p class="description rwgc-suite-workflow-note__text">
			<?php esc_html_e( 'Priority, fallback behaviour, schedule, admin ignore, and internal notes are available in advanced configuration screens.', 'reactwoo-geocore' ); ?>
		</p>
	</details>
</div>
