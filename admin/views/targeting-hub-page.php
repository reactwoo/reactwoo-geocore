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
		__( 'Define who qualifies for a version, offer, or message.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-targeting-hub' ); ?>
	<?php else : ?>
		<p class="description rwgc-targeting-hub__intro">
			<?php esc_html_e( 'Create and manage eligibility rules, campaign conditions, and reusable geo conditions.', 'reactwoo-geocore' ); ?>
		</p>
	<?php endif; ?>

	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_section_hub_cards(
			$cards,
			array(
				'empty_title' => __( 'No targeting screens yet', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Targeting routes are not registered yet.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
