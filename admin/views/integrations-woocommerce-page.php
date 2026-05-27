<?php
/**
 * Integrations WooCommerce entry page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$woocommerce_active = class_exists( 'WooCommerce' ) || function_exists( 'WC' );
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php RWGC_Admin_UI::render_page_header( __( 'WooCommerce', 'reactwoo-geocore' ), __( 'Connection and dependency status for Geo Commerce features.', 'reactwoo-geocore' ) ); ?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Commerce outcomes (pricing, offers, overlays, availability) are managed in the Commerce section. This integration screen confirms WooCommerce dependency health.', 'reactwoo-geocore' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'WooCommerce status:', 'reactwoo-geocore' ); ?></strong>
			<?php echo $woocommerce_active ? esc_html__( 'Connected', 'reactwoo-geocore' ) : esc_html__( 'Not detected', 'reactwoo-geocore' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-commerce-hub' ) ); ?>">
				<?php esc_html_e( 'Open commerce outcomes', 'reactwoo-geocore' ); ?>
			</a>
		</p>
	</div>
</div>
