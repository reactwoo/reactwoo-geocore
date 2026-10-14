<?php
/**
 * Targeting audiences entry page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_ga_url       = admin_url( 'admin.php?page=rwgcp-google-analytics' );
$rwgc_profiles_url = admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=profiles' );
$rwgc_has_pro      = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php RWGC_Admin_UI::render_page_header( __( 'Audiences', 'reactwoo-geocore' ), __( 'GA4 audiences, synced profiles, and audience conditions for targeting.', 'reactwoo-geocore' ) ); ?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Synced GA4 audiences and experience profiles used by visibility rules.', 'reactwoo-geocore' ); ?>
		</p>
		<?php if ( $rwgc_has_pro ) : ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $rwgc_ga_url ); ?>"><?php esc_html_e( 'Sync GA4 audiences', 'reactwoo-geocore' ); ?></a>
				<a class="button" href="<?php echo esc_url( $rwgc_profiles_url ); ?>" style="margin-left:8px;"><?php esc_html_e( 'View synced audiences', 'reactwoo-geocore' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=new' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Create audience condition', 'reactwoo-geocore' ); ?></a>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-targeting-hub' ) ); ?>"><?php esc_html_e( 'Review audience rules', 'reactwoo-geocore' ); ?></a>
			</p>
		<?php endif; ?>
	</div>
</div>
