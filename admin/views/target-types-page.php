<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_target_types = isset( $rwgc_target_types ) && is_array( $rwgc_target_types ) ? $rwgc_target_types : array();
$rwgc_provider_rows = isset( $rwgc_provider_rows ) && is_array( $rwgc_provider_rows ) ? $rwgc_provider_rows : array();
$rwgc_pro_enabled   = ! empty( $rwgc_pro_enabled );
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

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Pro Targeting', 'reactwoo-geocore' ); ?></h2>
		<?php if ( $rwgc_pro_enabled ) : ?>
			<p><?php esc_html_e( 'Active: campaign, attribution, and experience profile conditions are available in the rule builder.', 'reactwoo-geocore' ); ?></p>
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
