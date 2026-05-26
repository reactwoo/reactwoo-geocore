<?php
/**
 * Insights section hub.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = isset( $cards ) && is_array( $cards ) ? $cards : array();
$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		$rwgc_platform_shell
			? __( 'Insights', 'reactwoo-geocore' )
			: __( 'Geo insights', 'reactwoo-geocore' ),
		__( 'Reports and recommendations from Geo Core and installed capability providers.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-insights-hub' ); ?>
	<?php else : ?>
		<p class="description rwgc-insights-hub__intro">
			<?php esc_html_e( 'Open usage reports, AI analyses, and optimisation outcomes from one place.', 'reactwoo-geocore' ); ?>
		</p>
	<?php endif; ?>

	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_section_hub_cards(
			$cards,
			array(
				'empty_title' => __( 'No insight reports yet', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Install Geo AI or Geo Optimise to add analysis and experiment reporting tabs here.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
