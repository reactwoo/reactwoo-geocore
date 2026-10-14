<?php
/**
 * Setup & readiness — Insights tab.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_readiness = isset( $rwgc_readiness ) && is_array( $rwgc_readiness ) ? $rwgc_readiness : array();
$rwgc_providers = isset( $rwgc_providers ) && is_array( $rwgc_providers ) ? $rwgc_providers : array();
$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-insights-dashboard">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Setup & readiness', 'reactwoo-geocore' ),
		__( 'What data and configuration your insights depend on.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( ! $rwgc_platform_shell && class_exists( 'RWGC_Insights_Nav', false ) ) {
		RWGC_Insights_Nav::render( 'rwgc-insights-readiness' );
	}
	?>

	<div class="rwgc-insights-readiness-grid">
		<div class="rwgc-card rwgc-insights-panel">
			<?php RWGC_Admin_UI::render_section_header( __( 'Data readiness checklist', 'reactwoo-geocore' ) ); ?>
			<?php RWGC_Insights_UI::render_setup_checklist( $rwgc_readiness ); ?>
		</div>

		<div class="rwgc-insights-readiness-providers">
			<?php RWGC_Admin_UI::render_section_header( __( 'Product setup gaps', 'reactwoo-geocore' ), __( 'Missing configuration reported by each product.', 'reactwoo-geocore' ) ); ?>
			<?php
			$rwgc_has_gaps = false;
			foreach ( $rwgc_providers as $rwgc_provider ) :
				if ( ! is_array( $rwgc_provider ) || empty( $rwgc_provider['missing_setup'] ) ) {
					continue;
				}
				$rwgc_has_gaps = true;
				?>
				<div class="rwgc-card rwgc-insights-panel">
					<h3 class="rwgc-insights-section-title"><?php echo esc_html( (string) $rwgc_provider['label'] ); ?></h3>
					<ul class="rwgc-insights-missing-list">
						<?php foreach ( $rwgc_provider['missing_setup'] as $rwgc_missing ) : ?>
							<li><?php echo esc_html( (string) $rwgc_missing ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( class_exists( 'RWGC_Insights', false ) && ! empty( $rwgc_provider['id'] ) ) : ?>
						<p>
							<a href="<?php echo esc_url( RWGC_Insights::get_provider_details_url( (string) $rwgc_provider['id'] ) ); ?>">
								<?php esc_html_e( 'View product details', 'reactwoo-geocore' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<?php if ( ! $rwgc_has_gaps ) : ?>
				<p class="description"><?php esc_html_e( 'No outstanding setup gaps were reported.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
