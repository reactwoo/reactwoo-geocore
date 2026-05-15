<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_target_types = isset( $rwgc_target_types ) && is_array( $rwgc_target_types ) ? $rwgc_target_types : array();
$rwgc_provider_rows = isset( $rwgc_provider_rows ) && is_array( $rwgc_provider_rows ) ? $rwgc_provider_rows : array();
$rwgc_pro_enabled   = ! empty( $rwgc_pro_enabled );
$rwgc_portable_ctx  = isset( $rwgc_portable_ctx ) && is_array( $rwgc_portable_ctx ) ? $rwgc_portable_ctx : array(
	'pro'         => false,
	'audiences'   => array(),
	'campaigns'   => array(),
	'ui_surfaces' => array(),
);
$audiences          = isset( $rwgc_portable_ctx['audiences'] ) && is_array( $rwgc_portable_ctx['audiences'] ) ? $rwgc_portable_ctx['audiences'] : array();
$campaigns          = isset( $rwgc_portable_ctx['campaigns'] ) && is_array( $rwgc_portable_ctx['campaigns'] ) ? $rwgc_portable_ctx['campaigns'] : array();
$help               = isset( $rwgc_portable_ctx['help_urls'] ) && is_array( $rwgc_portable_ctx['help_urls'] ) ? $rwgc_portable_ctx['help_urls'] : array();
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Targeting', 'reactwoo-geocore' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Choose the kinds of visitor conditions your rules can use.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-target-types' ); ?>

	<div class="rwgc-grid">
		<div class="rwgc-card"><h2><?php esc_html_e( 'Location', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Country and country groups.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Browser language and locale.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Device', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Desktop, mobile, or tablet.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Time', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Day and time windows.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Commerce', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Commerce-related targeting signals.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Analytics', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Optional analytics signals.', 'reactwoo-geocore' ); ?></p></div>
	</div>

	<div class="rwgc-card rwgc-card--full rwgc-rb-playground-card">
		<h2><?php esc_html_e( 'Visibility rule builder', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Build who should see content using the same controls as Elementor, the Geo Content block, and Geo Elementor geo rules. Synced GA4 audiences and Google Ads campaigns appear by name when GeoCore Pro has finished a sync.', 'reactwoo-geocore' ); ?></p>

		<div class="rwgc-rb-sync-status">
			<div class="rwgc-rb-sync-status__item">
				<strong><?php esc_html_e( 'GA4 audiences', 'reactwoo-geocore' ); ?></strong>
				<span class="rwgc-rb__pill rwgc-rb__pill--ga4"><?php esc_html_e( 'GA4', 'reactwoo-geocore' ); ?></span>
				<p class="description">
					<?php
					if ( count( $audiences ) > 0 ) {
						echo esc_html( sprintf( _n( '%d audience available', '%d audiences available', count( $audiences ), 'reactwoo-geocore' ), count( $audiences ) ) );
					} elseif ( ! empty( $rwgc_pro_enabled ) ) {
						esc_html_e( 'No audiences synced yet.', 'reactwoo-geocore' );
					} else {
						esc_html_e( 'Requires GeoCore Pro.', 'reactwoo-geocore' );
					}
					?>
				</p>
			</div>
			<div class="rwgc-rb-sync-status__item">
				<strong><?php esc_html_e( 'Google Ads campaigns', 'reactwoo-geocore' ); ?></strong>
				<span class="rwgc-rb__pill rwgc-rb__pill--ads"><?php esc_html_e( 'Google Ads', 'reactwoo-geocore' ); ?></span>
				<p class="description">
					<?php
					if ( count( $campaigns ) > 0 ) {
						echo esc_html( sprintf( _n( '%d campaign available', '%d campaigns available', count( $campaigns ), 'reactwoo-geocore' ), count( $campaigns ) ) );
					} elseif ( ! empty( $rwgc_pro_enabled ) ) {
						esc_html_e( 'No campaigns synced yet.', 'reactwoo-geocore' );
					} else {
						esc_html_e( 'Requires GeoCore Pro.', 'reactwoo-geocore' );
					}
					?>
				</p>
			</div>
		</div>

		<?php if ( ! empty( $rwgc_pro_enabled ) && ( empty( $audiences ) || empty( $campaigns ) ) && ! empty( $help['integrations_ga'] ) ) : ?>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( (string) $help['integrations_ga'] ); ?>"><?php esc_html_e( 'Open GeoCore Pro integrations', 'reactwoo-geocore' ); ?></a>
			</p>
		<?php endif; ?>

		<p class="description"><?php esc_html_e( 'Where to use saved rules:', 'reactwoo-geocore' ); ?></p>
		<ul class="ul-disc" style="margin-left:1.25em;">
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['elementor'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['elementor'] ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['block'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['block'] ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['geo_rule'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['geo_rule'] ); ?></li>
			<?php endif; ?>
		</ul>

		<div class="rwgc-rb-playground-wrap">
			<textarea id="rwgc-targeting-playground-json" name="rwgc_targeting_playground_json" rows="4" class="large-text" aria-hidden="true"></textarea>
		</div>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Pro Targeting', 'reactwoo-geocore' ); ?></h2>
		<?php if ( $rwgc_pro_enabled ) : ?>
			<p><?php esc_html_e( 'GeoCore Pro is active: synced Google lists feed the rule builder on this page and in Elementor, blocks, and geo rules.', 'reactwoo-geocore' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Unlock Google Ads/CPC, UTM attribution persistence, and experience profiles.', 'reactwoo-geocore' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>"><?php esc_html_e( 'Unlock with GeoCore Pro', 'reactwoo-geocore' ); ?></a></p>
		<?php endif; ?>
	</div>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Advanced: Provider status and target catalog', 'reactwoo-geocore' ); ?></summary>
		<div class="rwgc-tech-ref-details__inner">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Provider', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'State', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'reactwoo-geocore' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rwgc_provider_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $row['label'] ) ? (string) $row['label'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['state'] ) ? (string) $row['state'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $row['detail'] ) ? (string) $row['detail'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php echo esc_html( sprintf( __( 'Registered targeting entries: %d', 'reactwoo-geocore' ), count( $rwgc_target_types ) ) ); ?></p>
		</div>
	</details>
</div>
