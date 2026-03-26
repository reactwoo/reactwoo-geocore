<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Core Add-ons', 'reactwoo-geocore' ); ?></h1>
	<p><?php esc_html_e( 'These add-ons extend ReactWoo Geo Core with builder-specific targeting and WHMCS-aware pricing.', 'reactwoo-geocore' ); ?></p>

	<div class="rwgc-addons-grid">
		<?php foreach ( $addons as $addon ) : ?>
			<div class="rwgc-addon-card">
				<?php if ( ! empty( $addon['image'] ) ) : ?>
					<img src="<?php echo esc_url( $addon['image'] ); ?>" alt="<?php echo esc_attr( $addon['title'] ); ?>" class="rwgc-addon-image" />
				<?php endif; ?>
				<h2><?php echo esc_html( $addon['title'] ); ?></h2>
				<p class="rwgc-addon-summary"><?php echo esc_html( $addon['summary'] ); ?></p>
				<p class="rwgc-addon-status">
					<strong><?php esc_html_e( 'Status:', 'reactwoo-geocore' ); ?></strong>
					<?php echo esc_html( $addon['status_label'] ); ?>
				</p>
				<?php if ( ! empty( $addon['cta_url'] ) ) : ?>
					<p>
						<a href="<?php echo esc_url( $addon['cta_url'] ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $addon['cta_label'] ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>

