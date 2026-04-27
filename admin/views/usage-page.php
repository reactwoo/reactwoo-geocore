<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Reports', 'reactwoo-geocore' ); ?></h1>
	<p class="description"><?php esc_html_e( 'See simple proof that your geo rules and page versions are being used.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-usage' ); ?>

	<div class="rwgc-grid">
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Rule matches', 'reactwoo-geocore' ); ?></h2>
			<p><strong>0</strong></p>
			<p class="description"><?php esc_html_e( 'Times Geo Core matched visitor conditions.', 'reactwoo-geocore' ); ?></p>
		</div>
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Top countries', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Coming soon in lightweight reporting.', 'reactwoo-geocore' ); ?></p>
		</div>
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Top rules', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Coming soon in lightweight reporting.', 'reactwoo-geocore' ); ?></p>
		</div>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Recent matches', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Recent visitor matches will appear here as this site records rule activity.', 'reactwoo-geocore' ); ?></p>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Page version routes', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Track how often visitors are routed to a page version from your rules.', 'reactwoo-geocore' ); ?></p>
	</div>

	<?php if ( function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled() ) : ?>
		<div class="rwgc-card rwgc-card--full">
			<h2><?php esc_html_e( 'GeoCore Pro reports', 'reactwoo-geocore' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Top campaigns', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Top sources', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Top mediums', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Profile matches', 'reactwoo-geocore' ); ?></li>
			</ul>
		</div>
	<?php endif; ?>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Developer details', 'reactwoo-geocore' ); ?></summary>
		<div class="rwgc-card rwgc-card--full rwgc-tech-ref-details__inner">
			<p class="description"><?php esc_html_e( 'Technical event and endpoint details have moved here to keep the main Reports screen simple.', 'reactwoo-geocore' ); ?></p>
		</div>
	</details>
</div>
