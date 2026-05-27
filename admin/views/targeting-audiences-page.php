<?php
/**
 * Targeting audiences entry page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php RWGC_Admin_UI::render_page_header( __( 'Audiences', 'reactwoo-geocore' ), __( 'Define reusable audience segments for eligibility and reporting.', 'reactwoo-geocore' ) ); ?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Audience targeting is powered by GeoCore Pro profile, attribution, and analytics conditions. Manage audience definitions from GeoCore Pro and reuse them across Experiences and Commerce.', 'reactwoo-geocore' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=profiles' ) ); ?>">
				<?php esc_html_e( 'Open audience profiles', 'reactwoo-geocore' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-target-types' ) ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'View condition catalog', 'reactwoo-geocore' ); ?>
			</a>
		</p>
	</div>
</div>
