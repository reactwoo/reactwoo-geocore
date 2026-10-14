<?php
/**
 * Experiences section hub.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_cards           = isset( $rwgc_cards ) && is_array( $rwgc_cards ) ? $rwgc_cards : array();
$rwgc_optimise_active = class_exists( 'RWGO_Plugin', false );
$rwgc_ai_suggestion   = class_exists( 'RWGC_Geo_AI_Suggestions', false )
	? RWGC_Geo_AI_Suggestions::get_for_context( 'experiences' )
	: null;
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Experiences', 'reactwoo-geocore' ),
		__( 'Test versions, measure goals, and pick winners.', 'reactwoo-geocore' )
	);
	?>

	<?php
	if ( class_exists( 'RWGC_Geo_AI_Suggestions', false ) ) {
		RWGC_Geo_AI_Suggestions::render_inline( $rwgc_ai_suggestion );
	}
	?>

	<?php if ( $rwgc_optimise_active ) : ?>
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_hub_cards(
				$rwgc_cards,
				array(
					'empty_title' => __( 'Open an experience screen', 'reactwoo-geocore' ),
					'empty_body'  => __( 'Create experiments, goals, and reports from Geo Optimise.', 'reactwoo-geocore' ),
				)
			);
		}
		?>
	<?php endif; ?>
</div>
