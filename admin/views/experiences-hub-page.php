<?php
/**
 * Experiences section hub.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = isset( $cards ) && is_array( $cards ) ? $cards : array();
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Experiences', 'reactwoo-geocore' ),
		__( 'Create and test what visitors see.', 'reactwoo-geocore' )
	);
	?>

	<p class="description rwgc-targeting-hub__intro">
		<?php esc_html_e( 'Define variants, dynamic content, geo content, and experiments from one place.', 'reactwoo-geocore' ); ?>
	</p>

	<?php
	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_section_hub_cards(
			$cards,
			array(
				'empty_title' => __( 'No experience screens yet', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Install Geo Elementor or Geo Optimise to unlock variants, dynamic content, and experiments.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
