<?php
/**
 * Targeting campaigns entry page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ads_url    = admin_url( 'admin.php?page=rwgcp-google-ads' );
$has_pro    = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
$ads_count  = $has_pro ? (int) get_option( 'rwgcp_google_ads_campaign_count', 0 ) : 0;
$sync_meta  = class_exists( 'RWGCP_Google_Integration', false ) ? RWGCP_Google_Integration::get_sync_meta() : array();
if ( isset( $sync_meta['ads_count'] ) ) {
	$ads_count = (int) $sync_meta['ads_count'];
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php RWGC_Admin_UI::render_page_header( __( 'Campaigns', 'reactwoo-geocore' ), __( 'Google Ads campaigns, UTM campaigns, and campaign targeting rules.', 'reactwoo-geocore' ) ); ?>
	<div class="rwgc-card">
		<?php if ( $has_pro && $ads_count > 0 ) : ?>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d campaign count */
						_n( '%d Google Ads campaign is available for targeting.', '%d Google Ads campaigns are available for targeting.', $ads_count, 'reactwoo-geocore' ),
						$ads_count
					)
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $ads_url ); ?>"><?php esc_html_e( 'Manage Google Ads campaigns', 'reactwoo-geocore' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage-campaign' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Campaign insights', 'reactwoo-geocore' ); ?></a>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No campaigns have been synced yet. Connect Google Ads or add UTM campaign rules in GeoCore Pro attribution.', 'reactwoo-geocore' ); ?></p>
			<p>
				<?php if ( $has_pro ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( $ads_url ); ?>"><?php esc_html_e( 'Connect Google Ads', 'reactwoo-geocore' ); ?></a>
				<?php else : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>"><?php esc_html_e( 'View GeoCore Pro', 'reactwoo-geocore' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=campaign-attribution' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'UTM campaign rules', 'reactwoo-geocore' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>
