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
	$rwgc_insights_sub   = __( 'How synced audiences and profile matches perform over time.', 'reactwoo-geocore' );
} elseif ( 'campaign' === $rwgc_insights_mode ) {
	$rwgc_insights_title = __( 'Campaign insights', 'reactwoo-geocore' );
	$rwgc_insights_sub   = __( 'Campaign performance, attribution, and targeting linked to ads or UTM data.', 'reactwoo-geocore' );
}

$has_pro     = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
$ads_count   = 0;
$sync_meta   = array();
if ( $has_pro && class_exists( 'RWGCP_Google_Integration', false ) ) {
	$sync_meta = RWGCP_Google_Integration::get_sync_meta();
	$ads_count = isset( $sync_meta['ads_count'] ) ? (int) $sync_meta['ads_count'] : 0;
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
	<?php
	$rwgc_insights_nav_slug = 'rwgc-usage';
	if ( 'audience' === $rwgc_insights_mode ) {
		$rwgc_insights_nav_slug = 'rwgc-usage-audience';
	} elseif ( 'campaign' === $rwgc_insights_mode ) {
		$rwgc_insights_nav_slug = 'rwgc-usage-campaign';
	}
	if ( class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( $rwgc_insights_nav_slug );
	} elseif ( ! $rwgc_platform_shell ) {
		RWGC_Admin::render_inner_nav( $rwgc_insights_nav_slug );
	}
	?>

	<?php if ( 'campaign' === $rwgc_insights_mode ) : ?>
		<div class="rwgc-card rwgc-card--full">
			<?php if ( $has_pro && $ads_count > 0 ) : ?>
				<h2><?php esc_html_e( 'Campaign performance', 'reactwoo-geocore' ); ?></h2>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d synced campaign count */
							_n( '%d Google Ads campaign is synced for targeting and reporting.', '%d Google Ads campaigns are synced for targeting and reporting.', $ads_count, 'reactwoo-geocore' ),
							$ads_count
						)
					);
					?>
				</p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-google-ads' ) ); ?>"><?php esc_html_e( 'Manage Google Ads campaigns', 'reactwoo-geocore' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-targeting-campaigns' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Campaign targeting rules', 'reactwoo-geocore' ); ?></a>
				</p>
				<p class="description"><?php esc_html_e( 'Detailed campaign charts and attribution breakdowns will appear here as reporting expands.', 'reactwoo-geocore' ); ?></p>
			<?php else : ?>
				<h2><?php esc_html_e( 'No campaign data yet', 'reactwoo-geocore' ); ?></h2>
				<p><?php esc_html_e( 'Campaign insights need synced Google Ads campaigns or UTM campaign rules. Connect advertising integrations to see performance and attribution here.', 'reactwoo-geocore' ); ?></p>
				<p>
					<?php if ( $has_pro ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-google-ads' ) ); ?>"><?php esc_html_e( 'Connect Google Ads', 'reactwoo-geocore' ); ?></a>
					<?php else : ?>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>"><?php esc_html_e( 'View GeoCore Pro', 'reactwoo-geocore' ); ?></a>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=campaign-attribution' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'UTM campaign rules', 'reactwoo-geocore' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

	<?php elseif ( 'audience' === $rwgc_insights_mode ) : ?>
		<div class="rwgc-card rwgc-card--full">
			<h2><?php esc_html_e( 'Audience performance', 'reactwoo-geocore' ); ?></h2>
			<?php if ( $has_pro ) : ?>
				<p class="description"><?php esc_html_e( 'Review synced GA4 audiences and experience profile matches. Audience-level charts will expand in a future release.', 'reactwoo-geocore' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-google-analytics' ) ); ?>"><?php esc_html_e( 'View synced audiences', 'reactwoo-geocore' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-targeting-audiences' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Audience targeting', 'reactwoo-geocore' ); ?></a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Audience insights require GeoCore Pro for GA4 audience sync and experience profiles.', 'reactwoo-geocore' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>"><?php esc_html_e( 'View GeoCore Pro', 'reactwoo-geocore' ); ?></a></p>
			<?php endif; ?>
		</div>

	<?php else : ?>
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

		<?php if ( $has_pro ) : ?>
			<div class="rwgc-card rwgc-card--full">
				<h2><?php esc_html_e( 'Attribution signals', 'reactwoo-geocore' ); ?></h2>
				<p class="description"><?php esc_html_e( 'UTM and campaign attribution are managed in GeoCore Pro.', 'reactwoo-geocore' ); ?></p>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=campaign-attribution' ) ); ?>"><?php esc_html_e( 'Open attribution', 'reactwoo-geocore' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage-campaign' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Campaign insights', 'reactwoo-geocore' ); ?></a>
				</p>
			</div>
		<?php endif; ?>
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
