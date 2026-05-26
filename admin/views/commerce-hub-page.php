<?php
/**
 * Commerce section hub.
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
			? __( 'Commerce', 'reactwoo-geocore' )
			: __( 'Geo commerce', 'reactwoo-geocore' ),
		__( 'Regional pricing, cart fees, product overlays, and WooCommerce geo rules.', 'reactwoo-geocore' )
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
			$cards,
			array(
				'empty_title' => __( 'Geo Commerce not active', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Install and activate ReactWoo Geo Commerce to manage WooCommerce geo pricing and fees.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
