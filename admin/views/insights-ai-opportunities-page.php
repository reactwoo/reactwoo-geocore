<?php
/**
 * AI UX Reviewer — Insights entry (Geo Core shell).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
$geo_ai_active  = function_exists( 'rwgc_is_geo_ai_active' ) && rwgc_is_geo_ai_active();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard rwgc-insights-ai-reviewer">
	<?php
	if ( ! $platform_shell && class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-ai' );
	}
	?>

	<?php if ( $geo_ai_active && class_exists( 'RWGA_UX_Reviewer_UI', false ) ) : ?>
		<?php
		$uid = get_current_user_id();
		$cards = array();
		if ( $uid > 0 ) {
			$cached = get_transient( 'rwga_ux_review_' . $uid );
			if ( is_array( $cached ) ) {
				$cards = $cached;
			}
		}
		RWGA_UX_Reviewer_UI::render_workspace(
			array(
				'display_mode'     => ! empty( $cards ) ? 'result' : 'fresh',
				'source'          => 'insights',
				'page_id'         => isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'product_id'      => isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'variant_page_id' => isset( $_GET['variant_page_id'] ) ? (int) $_GET['variant_page_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'rule_id'         => isset( $_GET['rule_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rule_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'engine_source'   => isset( $_GET['rwga_engine'] ) ? sanitize_key( wp_unslash( (string) $_GET['rwga_engine'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'action_count'    => isset( $_GET['rwga_actions'] ) ? (int) $_GET['rwga_actions'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'capabilities'    => function_exists( 'rwgc_get_suite_capability_map' ) ? rwgc_get_suite_capability_map() : array(),
				'cards'           => $cards,
				'show_inner_nav'  => false,
				'embed'           => true,
				'wrap_class'      => 'rwga-ux-reviewer--insights-embed',
			)
		);
		?>
	<?php else : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'AI UX Reviewer', 'reactwoo-geocore' ),
			__( 'Review targeted pages, variants, products, and rules for UX and conversion opportunities.', 'reactwoo-geocore' )
		);
		?>
		<div class="rwgc-card rwgc-insights-panel">
			<h2><?php esc_html_e( 'Geo Optimise required', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Activate ReactWoo Geo Optimise to run AI UX reviews, see priority findings, scores, and capability-aware recommendations across your Geo suite.', 'reactwoo-geocore' ); ?></p>
		</div>
	<?php endif; ?>
</div>
