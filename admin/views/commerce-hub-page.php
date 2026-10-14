<?php
/**
 * Commerce section hub.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_cards = isset( $rwgc_cards ) && is_array( $rwgc_cards ) ? $rwgc_cards : array();
$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		$rwgc_platform_shell
			? __( 'Commerce', 'reactwoo-geocore' )
			: __( 'Geo commerce', 'reactwoo-geocore' ),
		__( 'Adjust pricing, offers and product experiences.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-commerce-hub' ); ?>
	<?php else : ?>
		<p class="description rwgc-commerce-hub__intro">
			<?php esc_html_e( 'Visitor conditions use the same Geo Core rule engine as Targeting — eligibility is evaluated in Core; Commerce applies store outcomes.', 'reactwoo-geocore' ); ?>
		</p>
	<?php endif; ?>

	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_section_hub_cards(
			$rwgc_cards,
			array(
				'empty_title' => __( 'No commerce screens', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Commerce screens appear here when that plugin is active.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
