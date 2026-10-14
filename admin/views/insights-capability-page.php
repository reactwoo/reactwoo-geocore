<?php
/**
 * Insights — satellite dashboard (Capability map).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_providers       = isset( $rwgc_providers ) && is_array( $rwgc_providers ) ? $rwgc_providers : array();
$rwgc_health          = isset( $rwgc_health ) && is_array( $rwgc_health ) ? $rwgc_health : array();
$rwgc_recommendations = isset( $rwgc_recommendations ) && is_array( $rwgc_recommendations ) ? $rwgc_recommendations : array();
$rwgc_ai_suggestion   = class_exists( 'RWGC_Geo_AI_Suggestions', false )
	? RWGC_Geo_AI_Suggestions::get_for_context( 'insights' )
	: null;
$rwgc_platform_shell  = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard rwgc-insights-dashboard--compact">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Insights', 'reactwoo-geocore' ),
		__( 'What needs attention across your Geo suite?', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( ! $rwgc_platform_shell && class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-hub' );
	}
	?>

	<?php
	if ( class_exists( 'RWGC_Geo_AI_Suggestions', false ) ) {
		RWGC_Geo_AI_Suggestions::render_inline( $rwgc_ai_suggestion );
	}
	?>

	<?php if ( ! empty( $rwgc_health ) ) : ?>
		<section class="rwgc-insights-health rwgc-insights-health--compact" aria-label="<?php esc_attr_e( 'Suite health', 'reactwoo-geocore' ); ?>">
			<?php RWGC_Insights_UI::render_health_chips( $rwgc_health ); ?>
		</section>
	<?php endif; ?>

	<div class="rwgc-insights-dash-grid" role="list">
		<?php foreach ( $rwgc_providers as $rwgc_provider ) : ?>
			<?php
			if ( ! is_array( $rwgc_provider ) ) {
				continue;
			}
			RWGC_Insights_UI::render_satellite_dashboard_card( $rwgc_provider );
			?>
		<?php endforeach; ?>
	</div>

	<?php RWGC_Insights_UI::render_top_actions( $rwgc_recommendations ); ?>
</div>
