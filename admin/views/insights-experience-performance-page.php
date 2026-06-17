<?php
/**
 * Insights experience performance entry.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_active    = class_exists( 'RWGC_Admin_UI', false ) && RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' );
$platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard">
	<?php RWGC_Admin_UI::render_page_header( __( 'Experience performance', 'reactwoo-geocore' ), __( 'Experiment outcomes, winners, and conversion reporting.', 'reactwoo-geocore' ) ); ?>
	<?php
	if ( ! $platform_shell && class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-experiments' );
	}
	?>
	<div class="rwgc-card">
		<?php if ( $rwgo_active ) : ?>
			<p class="description"><?php esc_html_e( 'Open Geo Optimise reports for experiment performance. Primary experiment management stays under Experiences.', 'reactwoo-geocore' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports' ) ); ?>">
					<?php esc_html_e( 'Open experiment reports', 'reactwoo-geocore' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-dashboard' ) ); ?>" style="margin-left:8px;">
					<?php esc_html_e( 'Open experiments', 'reactwoo-geocore' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Install Geo Optimise to measure which experience variants perform best.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>
	</div>
</div>
