<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Core Add-ons', 'reactwoo-geocore' ); ?></h1>
	<p><?php esc_html_e( 'These add-ons extend ReactWoo Geo Core with builder-specific targeting and WHMCS-aware pricing.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-addons' ); ?>

	<div class="rwgc-card rwgc-card--full rwgc-addons-intro">
		<h2><?php esc_html_e( 'Baseline vs Extension', 'reactwoo-geocore' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Free tier: Geo Core performs server-side page routing (1 default + 1 country variant per page).', 'reactwoo-geocore' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Pro tier: GeoElementor provides advanced/multi-variant routing via Groups and extends element-level targeting.', 'reactwoo-geocore' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ); ?>" class="button"><?php esc_html_e( 'See Free Routing', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=geo-elementor' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open GeoElementor', 'reactwoo-geocore' ); ?></a>
		</p>
	</div>

	<div class="rwgc-addons-grid rwgc-addons-grid--page">
		<?php foreach ( $addons as $addon ) : ?>
			<?php
			$title_words  = preg_split( '/\s+/', (string) $addon['title'] );
			$initials     = '';
			foreach ( (array) $title_words as $word ) {
				$word = trim( (string) $word );
				if ( '' === $word ) {
					continue;
				}
				$initials .= strtoupper( substr( $word, 0, 1 ) );
				if ( strlen( $initials ) >= 2 ) {
					break;
				}
			}
			if ( '' === $initials ) {
				$initials = 'RW';
			}
			?>
			<div class="rwgc-addon-card">
				<div class="rwgc-addon-image-wrap">
					<?php if ( ! empty( $addon['image'] ) ) : ?>
						<img src="<?php echo esc_url( $addon['image'] ); ?>" alt="<?php echo esc_attr( $addon['title'] ); ?>" class="rwgc-addon-image" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
					<?php endif; ?>
					<div class="rwgc-addon-image-placeholder" <?php echo ! empty( $addon['image'] ) ? 'style="display:none;"' : ''; ?>>
						<span><?php echo esc_html( $initials ); ?></span>
					</div>
				</div>
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

