<?php
/**
 * Provider capability detail — progressive disclosure.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$provider = isset( $provider ) && is_array( $provider ) ? $provider : null;
$back_url = class_exists( 'RWGC_Insights_Nav', false )
	? RWGC_Insights_Nav::get_url( 'rwgc-insights-hub' )
	: admin_url( 'admin.php?page=rwgc-insights-hub' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard">
	<?php
	if ( is_array( $provider ) && ! empty( $provider['label'] ) ) {
		RWGC_Admin_UI::render_page_header(
			/* translators: %s: product name */
			sprintf( __( '%s — capability details', 'reactwoo-geocore' ), (string) $provider['label'] ),
			__( 'Full feature list, metrics, and setup guidance for this product.', 'reactwoo-geocore' )
		);
	} else {
		RWGC_Admin_UI::render_page_header(
			__( 'Product not found', 'reactwoo-geocore' ),
			__( 'Return to the capability map and choose a product.', 'reactwoo-geocore' )
		);
	}
	?>
	<?php
	if ( class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-hub' );
	}
	?>

	<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to capability map', 'reactwoo-geocore' ); ?></a></p>

	<?php if ( is_array( $provider ) ) : ?>
		<?php RWGC_Insights_UI::render_satellite_card( $provider ); ?>
	<?php endif; ?>
</div>
