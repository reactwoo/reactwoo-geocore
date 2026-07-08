<?php
/**
 * AI opportunities — Insights tab.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$recommendations = isset( $recommendations ) && is_array( $recommendations ) ? $recommendations : array();
$ai_provider     = isset( $ai_provider ) && is_array( $ai_provider ) ? $ai_provider : null;
$platform_shell  = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'AI opportunities', 'reactwoo-geocore' ),
		__( 'Site intelligence, sync, and AI-assisted improvements.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( ! $platform_shell && class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-ai' );
	}
	?>

	<?php if ( is_array( $ai_provider ) ) : ?>
		<div class="rwgc-card rwgc-insights-panel" style="margin-bottom:1rem;">
			<?php RWGC_Insights_UI::render_compact_product_card( $ai_provider ); ?>
		</div>
	<?php else : ?>
		<div class="rwgc-card rwgc-insights-panel" style="margin-bottom:1rem;">
			<p class="description"><?php esc_html_e( 'Install Geo AI to unlock site intelligence snapshots and AI-assisted recommendations.', 'reactwoo-geocore' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( function_exists( 'rwgc_is_geo_ai_active' ) && rwgc_is_geo_ai_active() && function_exists( 'rwgc_ux_opportunity_review_admin_url' ) ) : ?>
		<?php
		$suite_map       = function_exists( 'rwgc_get_suite_capability_map' ) ? rwgc_get_suite_capability_map() : array();
		$geo_ai_licensed = ! empty( $suite_map['geo_ai_licensed'] );
		$remote_ai       = ! empty( $suite_map['remote_ai_available'] );
		?>
		<div class="rwgc-card rwgc-insights-panel" style="margin-bottom:1rem;">
			<?php RWGC_Admin_UI::render_section_header( __( 'AI UX Review', 'reactwoo-geocore' ) ); ?>
			<p class="description"><?php esc_html_e( 'Use Geo AI to find UX and conversion opportunities across targeted pages, variants, and products.', 'reactwoo-geocore' ); ?></p>
			<ul class="rwga-capability-list" style="display:flex;flex-wrap:wrap;gap:8px;list-style:none;margin:0 0 12px;padding:0;">
				<li><span class="rwgc-geo-badge rwgc-geo-badge--success"><?php esc_html_e( 'Local deterministic review available', 'reactwoo-geocore' ); ?></span></li>
				<li>
					<span class="rwgc-geo-badge rwgc-geo-badge--<?php echo $remote_ai ? 'success' : 'locked'; ?>">
						<?php echo $remote_ai ? esc_html__( 'Remote Geo AI connected', 'reactwoo-geocore' ) : esc_html__( 'Remote Geo AI requires valid licence', 'reactwoo-geocore' ); ?>
					</span>
				</li>
				<?php if ( ! $geo_ai_licensed ) : ?>
					<li><span class="rwgc-geo-badge rwgc-geo-badge--locked"><?php esc_html_e( 'Licence required to run reviews', 'reactwoo-geocore' ); ?></span></li>
				<?php endif; ?>
			</ul>
			<p>
				<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( rwgc_ux_opportunity_review_admin_url( array( 'source' => 'insights' ) ) ); ?>">
					<?php esc_html_e( 'Open UX Review', 'reactwoo-geocore' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<div class="rwgc-card rwgc-insights-panel">
		<?php RWGC_Admin_UI::render_section_header( __( 'Recommended actions', 'reactwoo-geocore' ) ); ?>
		<?php if ( empty( $recommendations ) ) : ?>
			<p class="description"><?php esc_html_e( 'No AI-related opportunities right now. Run a site intelligence sync when Geo AI is configured.', 'reactwoo-geocore' ); ?></p>
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
</div>
