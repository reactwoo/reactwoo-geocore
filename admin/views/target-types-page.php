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
		<h2><?php esc_html_e( 'Portable rules (Elementor & Geo Content block)', 'reactwoo-geocore' ); ?></h2>
		<p><?php esc_html_e( 'Multi-condition visibility—including synced Google Analytics audiences when GeoCore Pro is active—uses the portable JSON schema in two places (not the suite page-versions rule builder alone).', 'reactwoo-geocore' ); ?></p>
		<ul class="ul-disc" style="margin-left:1.25em;">
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['elementor'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['elementor'] ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['block'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['block'] ); ?></li>
			<?php endif; ?>
		</ul>
		<?php
		$audiences = isset( $rwgc_portable_ctx['audiences'] ) && is_array( $rwgc_portable_ctx['audiences'] ) ? $rwgc_portable_ctx['audiences'] : array();
		$campaigns = isset( $rwgc_portable_ctx['campaigns'] ) && is_array( $rwgc_portable_ctx['campaigns'] ) ? $rwgc_portable_ctx['campaigns'] : array();
		?>
		<?php if ( ! empty( $audiences ) ) : ?>
			<h3 style="margin-top:1rem;"><?php esc_html_e( 'Synced GA4 audiences (use id in portable JSON)', 'reactwoo-geocore' ); ?></h3>
			<table class="widefat striped" style="max-width:720px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'ID', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Copy', 'reactwoo-geocore' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $audiences as $row ) : ?>
					<?php
					if ( ! is_array( $row ) ) {
						continue;
					}
					$aid = isset( $row['id'] ) ? (string) $row['id'] : '';
					$anm = isset( $row['name'] ) ? (string) $row['name'] : $aid;
					if ( '' === $aid ) {
						continue;
					}
					?>
					<tr>
						<td><?php echo esc_html( $anm ); ?></td>
						<td><code><?php echo esc_html( $aid ); ?></code></td>
						<td><button type="button" class="button button-small rwgc-portable-copy-id" data-copy="<?php echo esc_attr( $aid ); ?>"><?php esc_html_e( 'Copy id', 'reactwoo-geocore' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'Example condition: type "audience", operator "in", value [ "PASTE_ID_HERE" ]. Elementor and the Geo Content block can insert this via quick-insert when you edit JSON there.', 'reactwoo-geocore' ); ?></p>
		<?php elseif ( ! empty( $rwgc_pro_enabled ) ) : ?>
			<p class="description"><?php esc_html_e( 'No audiences in cache yet. Open GeoCore Pro → Integrations → Google Analytics → Sync audiences.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $campaigns ) ) : ?>
			<h3 style="margin-top:1rem;"><?php esc_html_e( 'Synced Google Ads campaigns (token for portable JSON)', 'reactwoo-geocore' ); ?></h3>
			<table class="widefat striped" style="max-width:720px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Copy token', 'reactwoo-geocore' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $campaigns as $row ) : ?>
					<?php
					if ( ! is_array( $row ) ) {
						continue;
					}
					$cid = isset( $row['id'] ) ? (string) $row['id'] : '';
					$cnm = isset( $row['name'] ) ? (string) $row['name'] : $cid;
					$tok = $cnm !== '' ? $cnm : $cid;
					if ( '' === $tok ) {
						continue;
					}
					?>
					<tr>
						<td><?php echo esc_html( $cnm !== '' ? $cnm : $cid ); ?></td>
						<td><button type="button" class="button button-small rwgc-portable-copy-id" data-copy="<?php echo esc_attr( $tok ); ?>"><?php esc_html_e( 'Copy', 'reactwoo-geocore' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Pro Targeting', 'reactwoo-geocore' ); ?></h2>
		<?php if ( $rwgc_pro_enabled ) : ?>
			<p><?php esc_html_e( 'GeoCore Pro is active: synced Google entities appear above and in Elementor / Geo Content quick-insert. Suite page versions and the target catalog below still use the registered target types (e.g. ga_audience) for routing experiments.', 'reactwoo-geocore' ); ?></p>
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
