<?php
/**
 * Experiences section hub.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards           = isset( $cards ) && is_array( $cards ) ? $cards : array();
$optimise_active = class_exists( 'RWGO_Plugin', false );
$exp_status      = class_exists( 'RWGC_Capability_Registry', false )
	? RWGC_Capability_Registry::get_status( 'experiences' )
	: array( 'state' => $optimise_active ? 'available' : 'not_installed' );
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Experiences', 'reactwoo-geocore' ),
		__( 'Test versions, measure goals, and pick winners.', 'reactwoo-geocore' )
	);
	?>

	<?php if ( ! $optimise_active ) : ?>
		<div class="rwgc-card rwgc-experiences-locked">
			<h2><?php esc_html_e( 'Experiences require Geo Optimise', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Use Experiences to split traffic, measure conversions, and choose winning versions.', 'reactwoo-geocore' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>"><?php esc_html_e( 'Install Geo Optimise', 'reactwoo-geocore' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Learn more', 'reactwoo-geocore' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_hub_cards(
				$cards,
				array(
					'empty_title' => __( 'Open an experience screen', 'reactwoo-geocore' ),
					'empty_body'  => __( 'Create experiments, goals, and reports from Geo Optimise.', 'reactwoo-geocore' ),
				)
			);
		}
		?>
	<?php endif; ?>
</div>
