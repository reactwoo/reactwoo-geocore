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

	<?php if ( is_array( $ai_provider ) && 'missing' !== ( $ai_provider['status'] ?? '' ) && function_exists( 'rwgc_ux_opportunity_review_admin_url' ) ) : ?>
		<div class="rwgc-card rwgc-insights-panel" style="margin-bottom:1rem;">
			<?php RWGC_Admin_UI::render_section_header( __( 'UX opportunity review', 'reactwoo-geocore' ) ); ?>
			<p class="description"><?php esc_html_e( 'Run a capability-aware UX review for a page, product, or variant. Recommendations respect which Geo suite products are installed and licensed.', 'reactwoo-geocore' ); ?></p>
			<p>
				<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( rwgc_ux_opportunity_review_admin_url( array( 'source' => 'insights' ) ) ); ?>">
					<?php esc_html_e( 'Open UX opportunity review', 'reactwoo-geocore' ); ?>
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
