<?php
/**
 * Targeting section hub.
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
			? __( 'Targeting', 'reactwoo-geocore' )
			: __( 'Experience targeting', 'reactwoo-geocore' ),
		__( 'Rules, page versions, Elementor experiences, and experiments share one visibility engine.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-targeting-hub' ); ?>
	<?php else : ?>
		<p class="description rwgc-targeting-hub__intro">
			<?php esc_html_e( 'Choose an experience surface below — rules, page versions, Elementor, and experiments share the same visibility engine and rule builder.', 'reactwoo-geocore' ); ?>
		</p>
	<?php endif; ?>

	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_section_hub_cards(
			$cards,
			array(
				'empty_title' => __( 'No targeting screens yet', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Install Geo Elementor or Geo Optimise to add rules, variants, and experiments.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
