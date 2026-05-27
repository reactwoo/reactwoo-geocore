<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
$rwgc_insights_mode  = isset( $rwgc_insights_mode ) ? sanitize_key( (string) $rwgc_insights_mode ) : 'geo';
$rwgc_insights_title = __( 'Geo insights', 'reactwoo-geocore' );
$rwgc_insights_sub   = __( 'Rule matches, routing activity, and developer reference for geo on this site.', 'reactwoo-geocore' );
if ( 'audience' === $rwgc_insights_mode ) {
	$rwgc_insights_title = __( 'Audience insights', 'reactwoo-geocore' );
	$rwgc_insights_sub   = __( 'Audience-oriented performance signals from Geo Core and connected providers.', 'reactwoo-geocore' );
} elseif ( 'campaign' === $rwgc_insights_mode ) {
	$rwgc_insights_title = __( 'Campaign insights', 'reactwoo-geocore' );
	$rwgc_insights_sub   = __( 'Campaign attribution and performance signals for geo-targeted experiences.', 'reactwoo-geocore' );
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	if ( $rwgc_platform_shell && class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_page_header( $rwgc_insights_title, $rwgc_insights_sub );
	} else {
		?>
		<h1><?php esc_html_e( 'Reports', 'reactwoo-geocore' ); ?></h1>
		<p class="description"><?php esc_html_e( 'See simple proof that your geo rules and page versions are being used.', 'reactwoo-geocore' ); ?></p>
		<?php
	}
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-usage' ); ?>
	<?php endif; ?>

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
			<p><a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=integrations' ) ); ?>"><?php esc_html_e( 'Open Integrations', 'reactwoo-geocore' ); ?></a></p>
		</div>
	<?php endif; ?>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Developer details', 'reactwoo-geocore' ); ?></summary>
		<div class="rwgc-card rwgc-card--full rwgc-tech-ref-details__inner">
			<p class="description"><?php esc_html_e( 'Technical event and endpoint details have moved here to keep the main Reports screen simple.', 'reactwoo-geocore' ); ?></p>
			<?php if ( ! empty( $rwgc_rest_enabled ) && ! empty( $rwgc_capabilities_url ) ) : ?>
				<?php if ( ! empty( $rwgc_location_url ) ) : ?>
					<p><code><?php echo esc_html( $rwgc_location_url ); ?></code></p>
				<?php endif; ?>
				<p><code><?php echo esc_html( $rwgc_capabilities_url ); ?></code></p>
			<?php endif; ?>
		</div>
	</details>
</div>
