<?php
/**
 * Insights — satellite dashboard (Capability map).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers       = isset( $providers ) && is_array( $providers ) ? $providers : array();
$health          = isset( $health ) && is_array( $health ) ? $health : array();
$recommendations = isset( $recommendations ) && is_array( $recommendations ) ? $recommendations : array();
$ai_suggestion   = class_exists( 'RWGC_Geo_AI_Suggestions', false )
	? RWGC_Geo_AI_Suggestions::get_for_context( 'insights' )
	: null;
$platform_shell  = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard rwgc-insights-dashboard--compact">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Insights', 'reactwoo-geocore' ),
		__( 'What needs attention across your Geo suite?', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( ! $platform_shell && class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-hub' );
	}
	?>

	<?php
	if ( class_exists( 'RWGC_Geo_AI_Suggestions', false ) ) {
		RWGC_Geo_AI_Suggestions::render_inline( $ai_suggestion );
	}
	?>

	<?php if ( ! empty( $health ) ) : ?>
		<section class="rwgc-insights-health rwgc-insights-health--compact" aria-label="<?php esc_attr_e( 'Suite health', 'reactwoo-geocore' ); ?>">
			<?php RWGC_Insights_UI::render_health_chips( $health ); ?>
		</section>
	<?php endif; ?>

	<div class="rwgc-insights-dash-grid" role="list">
		<?php foreach ( $providers as $provider ) : ?>
			<?php
			if ( ! is_array( $provider ) ) {
				continue;
			}
			RWGC_Insights_UI::render_satellite_dashboard_card( $provider );
			?>
		<?php endforeach; ?>
	</div>

	<?php RWGC_Insights_UI::render_top_actions( $recommendations ); ?>
</div>
