<?php
/**
 * Capability Map — compact Insights overview.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers       = isset( $providers ) && is_array( $providers ) ? $providers : array();
$health          = isset( $health ) && is_array( $health ) ? $health : array();
$recommendations = isset( $recommendations ) && is_array( $recommendations ) ? $recommendations : array();
$activity        = isset( $activity ) && is_array( $activity ) ? $activity : array();
$view_all_url    = class_exists( 'RWGC_Insights_Nav', false )
	? RWGC_Insights_Nav::get_url( 'rwgc-insights-ai' )
	: admin_url( 'admin.php?page=rwgc-insights-ai' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard rwgc-insights-dashboard--compact">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Capability map', 'reactwoo-geocore' ),
		__( 'What is active on this site and what to improve next.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-hub' );
	}
	?>

	<section class="rwgc-insights-health rwgc-insights-health--compact" aria-labelledby="rwgc-insights-health-title">
		<h2 id="rwgc-insights-health-title" class="screen-reader-text"><?php esc_html_e( 'Platform health', 'reactwoo-geocore' ); ?></h2>
		<?php RWGC_Insights_UI::render_health_chips( $health ); ?>
	</section>

	<section class="rwgc-insights-products" aria-labelledby="rwgc-insights-products-title">
		<?php RWGC_Admin_UI::render_section_header( __( 'Products on this site', 'reactwoo-geocore' ), __( 'Short status for each Geo product. Open details for full capability lists.', 'reactwoo-geocore' ) ); ?>
		<div class="rwgc-insights-product-grid" role="list">
			<?php foreach ( $providers as $provider ) : ?>
				<?php
				if ( ! is_array( $provider ) ) {
					continue;
				}
				RWGC_Insights_UI::render_compact_product_card( $provider );
				?>
			<?php endforeach; ?>
		</div>
	</section>

	<?php RWGC_Insights_UI::render_opportunities_preview( $recommendations, $view_all_url ); ?>

	<?php if ( ! empty( $activity ) ) : ?>
		<?php RWGC_Insights_UI::render_recent_activity( $activity ); ?>
	<?php endif; ?>
</div>
