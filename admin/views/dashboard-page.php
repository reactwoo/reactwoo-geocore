<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'ReactWoo Geo Core', 'reactwoo-geocore' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Central geolocation dashboard for setup, status, and integrations. Geo Core is free (WordPress.org): core detection, routing, shortcodes, block, and the public REST location endpoint do not require a ReactWoo product license.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-dashboard' ); ?>

	<div class="rwui-hero">
		<h2><?php esc_html_e( 'Free Baseline Routing + Shared Geo Engine', 'reactwoo-geocore' ); ?></h2>
		<p><?php esc_html_e( 'Geo Core owns geo detection, MaxMind lifecycle, and free page-level routing. GeoElementor extends this for advanced multi-variant behavior.', 'reactwoo-geocore' ); ?></p>
		<div class="rwui-badges">
			<span class="rwui-badge"><?php esc_html_e( 'Geo Core: engine + baseline', 'reactwoo-geocore' ); ?></span>
			<span class="rwui-badge rwui-badge--accent"><?php esc_html_e( 'Free limit: 1 fallback + 1 mapping', 'reactwoo-geocore' ); ?></span>
		</div>
		<div class="rwui-cta-row">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open Core Settings', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=geo-elementor-variants' ) ); ?>" class="button"><?php esc_html_e( 'Open Pro Variant Groups', 'reactwoo-geocore' ); ?></a>
		</div>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Feature Split', 'reactwoo-geocore' ); ?></h2>
		<table class="rwui-matrix">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Capability', 'reactwoo-geocore' ); ?></th>
					<th><?php esc_html_e( 'Geo Core (Free baseline)', 'reactwoo-geocore' ); ?></th>
					<th><?php esc_html_e( 'GeoElementor (Pro extension)', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Shared geo engine and MaxMind management', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'Yes', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'Uses Geo Core', 'reactwoo-geocore' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Page-level server-side routing', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'Yes (1 default + 1 additional country)', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'Extends for advanced variants', 'reactwoo-geocore' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Variant groups / advanced mappings', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'No', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'Yes', 'reactwoo-geocore' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="rwgc-grid">
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Getting Started', 'reactwoo-geocore' ); ?></h2>
			<ol class="rwgc-steps">
				<li><?php esc_html_e( 'Configure MaxMind (GeoLite2) credentials in Settings.', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Download/update database from Tools.', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Configure page-level variant routing in any page editor (Geo Variant Routing box).', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Verify lookup and then use shortcodes/PHP/block.', 'reactwoo-geocore' ); ?></li>
			</ol>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Setup Now', 'reactwoo-geocore' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ); ?>" class="button"><?php esc_html_e( 'View Usage Guide', 'reactwoo-geocore' ); ?></a>
			</p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Geo enabled', 'reactwoo-geocore' ); ?>:</strong> <?php echo RWGC_Settings::get( 'enabled', 1 ) ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'MaxMind license key (GeoLite2)', 'reactwoo-geocore' ); ?>:</strong> <?php echo ! empty( $settings['maxmind_license_key'] ) ? esc_html__( 'Configured', 'reactwoo-geocore' ) : esc_html__( 'Not set', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'DB path', 'reactwoo-geocore' ); ?>:</strong> <code><?php echo esc_html( $status['path'] ); ?></code></li>
				<li><strong><?php esc_html_e( 'DB exists', 'reactwoo-geocore' ); ?>:</strong> <?php echo $status['exists'] ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'DB last updated', 'reactwoo-geocore' ); ?>:</strong> <?php echo $status['last_updated'] ? esc_html( $status['last_updated'] ) : esc_html__( 'Unknown', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'DB stale', 'reactwoo-geocore' ); ?>:</strong> <?php echo $status['is_stale'] ? esc_html__( 'Possibly', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
			</ul>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Current visitor', 'reactwoo-geocore' ); ?></h2>
			<?php if ( ! empty( $data ) ) : ?>
				<ul>
					<li><strong><?php esc_html_e( 'IP', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['ip'] ); ?></li>
					<li><strong><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['country_code'] . ' – ' . $data['country_name'] ); ?></li>
					<li><strong><?php esc_html_e( 'Region', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['region'] ); ?></li>
					<li><strong><?php esc_html_e( 'City', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['city'] ); ?></li>
					<li><strong><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['currency'] ); ?></li>
					<li><strong><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['source'] ); ?><?php echo ! empty( $data['cached'] ) ? ' (' . esc_html__( 'cached', 'reactwoo-geocore' ) . ')' : ''; ?></li>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No geo data available yet.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Preview & conditional content', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Administrators can simulate another country on the front end by adding a query argument (same override used for shortcodes and blocks):', 'reactwoo-geocore' ); ?></p>
			<p><code>?rwgc_preview_country=GB</code></p>
			<p class="description"><?php esc_html_e( 'Use the admin bar notice to clear preview mode. Filter rwgc_can_preview_geo can grant access to other roles.', 'reactwoo-geocore' ); ?></p>
			<p><strong><?php esc_html_e( 'Shortcode examples', 'reactwoo-geocore' ); ?>:</strong></p>
			<ul class="rwgc-docs-list">
				<li><code>[rwgc_if country="GB,US"]…[/rwgc_if]</code></li>
				<li><code>[rwgc_if exclude="US,CA"]…[/rwgc_if]</code></li>
				<li><code>[rwgc_if country="DE" exclude="BE"]…[/rwgc_if]</code></li>
				<li><code>[rwgc_if groups="your_group_slug"]…[/rwgc_if]</code></li>
			</ul>
			<p class="description"><?php esc_html_e( 'See the Usage screen for the full list.', 'reactwoo-geocore' ); ?></p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Free Variant Routing', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'Geo Core includes server-side page routing with a strict free limit of 1 default fallback + 1 country variant per page.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Edit a page and use the "Geo Variant Routing (Free)" panel to map fallback and one additional country target.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Need multiple countries or advanced rule logic? Upgrade with GeoElementor.', 'reactwoo-geocore' ); ?></p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Quick actions', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'Use the Tools tab to update the MaxMind database, clear cache, and test your setup.', 'reactwoo-geocore' ); ?></p>
			<p><?php esc_html_e( 'Use the Usage tab for shortcode, PHP, REST, Gutenberg, and free page-level routing examples.', 'reactwoo-geocore' ); ?></p>
			<p><?php esc_html_e( 'Use the Add-ons tab to discover GeoElementor and WHMCS Bridge integrations.', 'reactwoo-geocore' ); ?></p>
		</div>
	</div>
</div>

