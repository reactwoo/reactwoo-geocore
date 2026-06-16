<?php
/**
 * Capability + Intelligence Centre — Insights dashboard.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers       = isset( $providers ) && is_array( $providers ) ? $providers : array();
$health          = isset( $health ) && is_array( $health ) ? $health : array();
$recommendations = isset( $recommendations ) && is_array( $recommendations ) ? $recommendations : array();
$readiness       = isset( $readiness ) && is_array( $readiness ) ? $readiness : array();
$activity        = isset( $activity ) && is_array( $activity ) ? $activity : array();
$report_links    = isset( $report_links ) && is_array( $report_links ) ? $report_links : array();
$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard">
	<?php
	RWGC_Admin_UI::render_page_header(
		$rwgc_platform_shell
			? __( 'Capability + Intelligence Centre', 'reactwoo-geocore' )
			: __( 'Your Geo capability map', 'reactwoo-geocore' ),
		__( 'See what is active, what is collecting data, and what to improve next.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-insights-hub' ); ?>
	<?php endif; ?>

	<section class="rwgc-insights-health" aria-labelledby="rwgc-insights-health-title">
		<h2 id="rwgc-insights-health-title" class="screen-reader-text"><?php esc_html_e( 'Platform health', 'reactwoo-geocore' ); ?></h2>
		<?php
		RWGC_Admin_UI::render_stat_grid_open();
		foreach ( $health as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			RWGC_Insights_UI::render_metric_card( $card );
		}
		RWGC_Admin_UI::render_stat_grid_close();
		?>
	</section>

	<section class="rwgc-insights-layout" aria-label="<?php esc_attr_e( 'Capability map', 'reactwoo-geocore' ); ?>">
		<div class="rwgc-insights-layout__main">
			<?php RWGC_Admin_UI::render_section_header( __( 'Capability map', 'reactwoo-geocore' ), __( 'Each product shows what it can do on this site and what still needs setup.', 'reactwoo-geocore' ) ); ?>
			<div class="rwgc-insights-satellite-grid" role="list">
				<?php foreach ( $providers as $provider ) : ?>
					<?php
					if ( ! is_array( $provider ) ) {
						continue;
					}
					RWGC_Insights_UI::render_satellite_card( $provider );
					?>
				<?php endforeach; ?>
			</div>
		</div>

		<aside class="rwgc-insights-layout__side">
			<div class="rwgc-card rwgc-insights-panel">
				<?php RWGC_Admin_UI::render_section_header( __( 'Best next actions', 'reactwoo-geocore' ) ); ?>
				<?php if ( empty( $recommendations ) ) : ?>
					<p class="description"><?php esc_html_e( 'Your capability map looks healthy. Review individual cards for optional improvements.', 'reactwoo-geocore' ); ?></p>
				<?php else : ?>
					<div class="rwgc-insights-recommendations">
						<?php foreach ( $recommendations as $rec ) : ?>
							<?php
							if ( ! is_array( $rec ) ) {
								continue;
							}
							RWGC_Insights_UI::render_recommendation_card( $rec );
							?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="rwgc-card rwgc-insights-panel">
				<?php RWGC_Admin_UI::render_section_header( __( 'What this insight is based on', 'reactwoo-geocore' ) ); ?>
				<?php RWGC_Insights_UI::render_setup_checklist( $readiness ); ?>
			</div>
		</aside>
	</section>

	<?php RWGC_Insights_UI::render_recent_activity( $activity ); ?>

	<?php if ( ! empty( $report_links ) ) : ?>
		<section class="rwgc-insights-reports" aria-labelledby="rwgc-insights-reports-title">
			<?php RWGC_Admin_UI::render_section_header( __( 'More insight reports', 'reactwoo-geocore' ), __( 'Open detailed Geo, audience, campaign, and experiment reports.', 'reactwoo-geocore' ) ); ?>
			<?php
			RWGC_Admin_UI::render_section_hub_cards(
				$report_links,
				array(
					'class'       => 'rwgc-insights-reports',
					'empty_title' => '',
					'empty_body'  => '',
				)
			);
			?>
		</section>
	<?php endif; ?>
</div>
