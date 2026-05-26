<?php
/**
 * Settings section hub.
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
			? __( 'Settings', 'reactwoo-geocore' )
			: __( 'Geo settings', 'reactwoo-geocore' ),
		__( 'General, tools, licences, and per-provider configuration.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-settings-hub' ); ?>
	<?php endif; ?>

	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_section_hub_cards(
			$cards,
			array(
				'empty_title' => __( 'No settings screens', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Core settings will appear here once routes are registered.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
