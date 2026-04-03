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
		__( 'Create a country-specific page version', 'reactwoo-geocore' ),
		__( 'Choose a default page and a country. We create a draft page, link it as your local version, and turn on routing — the same outcome as the page editor meta box, without hunting for it.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-workflow-variant' ); ?>

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
			<label for="rwgc_master_page_id"><?php esc_html_e( 'Default page (shown to most visitors)', 'reactwoo-geocore' ); ?></label>
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
			<label for="rwgc_country_iso2"><?php esc_html_e( 'Country for this local version', 'reactwoo-geocore' ); ?></label>
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
			<span class="label"><?php esc_html_e( 'Starting content', 'reactwoo-geocore' ); ?></span><br />
			<label><input type="radio" name="rwgc_variant_mode" value="duplicate" checked="checked" />
				<?php esc_html_e( 'Copy content from the default page (recommended)', 'reactwoo-geocore' ); ?></label><br />
			<label><input type="radio" name="rwgc_variant_mode" value="blank" />
				<?php esc_html_e( 'Start from a blank draft', 'reactwoo-geocore' ); ?></label>
		</div>

		<p>
			<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Create local version', 'reactwoo-geocore' ); ?></button>
		</p>
	</form>

	<details class="rwgc-tech-ref-details rwgc-suite-workflow-note">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Technical note', 'reactwoo-geocore' ); ?></summary>
		<p class="description rwgc-suite-workflow-note__text">
			<?php esc_html_e( 'Free Geo Core allows one country-specific page per default page. GeoElementor unlocks multiple variants and advanced rules.', 'reactwoo-geocore' ); ?>
		</p>
	</details>
</div>
