<?php
/**
 * Targeting campaigns entry page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php RWGC_Admin_UI::render_page_header( __( 'Campaigns', 'reactwoo-geocore' ), __( 'Map campaign identifiers to targeting and attribution rules.', 'reactwoo-geocore' ) ); ?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Campaign-aware targeting depends on synced provider entities (for example Google Ads campaigns) and attribution fields captured by Geo Core / GeoCore Pro.', 'reactwoo-geocore' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-google' ) ); ?>">
				<?php esc_html_e( 'Open Google integrations', 'reactwoo-geocore' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage-campaign' ) ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'Open campaign insights', 'reactwoo-geocore' ); ?>
			</a>
		</p>
	</div>
</div>
