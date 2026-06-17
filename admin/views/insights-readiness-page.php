<?php
/**
 * Setup & readiness — Insights tab.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$readiness = isset( $readiness ) && is_array( $readiness ) ? $readiness : array();
$providers = isset( $providers ) && is_array( $providers ) ? $providers : array();
$platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Setup & readiness', 'reactwoo-geocore' ),
		__( 'What data and configuration your insights depend on.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( ! $platform_shell && class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-readiness' );
	}
	?>

	<div class="rwgc-insights-readiness-grid">
		<div class="rwgc-card rwgc-insights-panel">
			<?php RWGC_Admin_UI::render_section_header( __( 'Data readiness checklist', 'reactwoo-geocore' ) ); ?>
			<?php RWGC_Insights_UI::render_setup_checklist( $readiness ); ?>
		</div>

		<div class="rwgc-insights-readiness-providers">
			<?php RWGC_Admin_UI::render_section_header( __( 'Product setup gaps', 'reactwoo-geocore' ), __( 'Missing configuration reported by each product.', 'reactwoo-geocore' ) ); ?>
			<?php
			$has_gaps = false;
			foreach ( $providers as $provider ) :
				if ( ! is_array( $provider ) || empty( $provider['missing_setup'] ) ) {
					continue;
				}
				$has_gaps = true;
				?>
				<div class="rwgc-card rwgc-insights-panel">
					<h3 class="rwgc-insights-section-title"><?php echo esc_html( (string) $provider['label'] ); ?></h3>
					<ul class="rwgc-insights-missing-list">
						<?php foreach ( $provider['missing_setup'] as $missing ) : ?>
							<li><?php echo esc_html( (string) $missing ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( class_exists( 'RWGC_Insights', false ) && ! empty( $provider['id'] ) ) : ?>
						<p>
							<a href="<?php echo esc_url( RWGC_Insights::get_provider_details_url( (string) $provider['id'] ) ); ?>">
								<?php esc_html_e( 'View product details', 'reactwoo-geocore' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<?php if ( ! $has_gaps ) : ?>
				<p class="description"><?php esc_html_e( 'No outstanding setup gaps were reported.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
