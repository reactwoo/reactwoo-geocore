<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<h1><?php esc_html_e( 'Geo Core Add-ons', 'reactwoo-geocore' ); ?></h1>
	<p><?php esc_html_e( 'Add-ons extend Geo Core with extra targeting and integrations.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-addons' ); ?>

	<div class="rwgc-addons-page">
		<div class="rwgc-card rwgc-card--full rwgc-addons-intro rwgc-addons-intro--focus">
			<h2><?php esc_html_e( 'Core and extensions', 'reactwoo-geocore' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Geo Core detects the visitor country and applies the visibility rules included with this plugin.', 'reactwoo-geocore' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-visibility-rules' ) ); ?>" class="button"><?php esc_html_e( 'Open visibility rules', 'reactwoo-geocore' ); ?></a>
			</p>
		</div>

		<div class="rwgc-addons-grid rwgc-addons-grid--page">
			<?php foreach ( $rwgc_addons as $rwgc_addon ) : ?>
				<?php
				$rwgc_title_words  = preg_split( '/\s+/', (string) $rwgc_addon['title'] );
				$rwgc_initials     = '';
				foreach ( (array) $rwgc_title_words as $rwgc_word ) {
					$rwgc_word = trim( (string) $rwgc_word );
					if ( '' === $rwgc_word ) {
						continue;
					}
					$rwgc_initials .= strtoupper( substr( $rwgc_word, 0, 1 ) );
					if ( strlen( $rwgc_initials ) >= 2 ) {
						break;
					}
				}
				if ( '' === $rwgc_initials ) {
					$rwgc_initials = 'RW';
				}
				?>
				<div class="rwgc-addon-card">
					<div class="rwgc-addon-image-wrap">
						<?php if ( ! empty( $rwgc_addon['image'] ) ) : ?>
							<img src="<?php echo esc_url( $rwgc_addon['image'] ); ?>" alt="<?php echo esc_attr( $rwgc_addon['title'] ); ?>" class="rwgc-addon-image" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
						<?php endif; ?>
						<div class="rwgc-addon-image-placeholder" <?php echo ! empty( $rwgc_addon['image'] ) ? 'style="display:none;"' : ''; ?>>
							<span><?php echo esc_html( $rwgc_initials ); ?></span>
						</div>
					</div>
					<h2><?php echo esc_html( $rwgc_addon['title'] ); ?></h2>
					<p class="rwgc-addon-summary"><?php echo esc_html( $rwgc_addon['summary'] ); ?></p>
					<p class="rwgc-addon-status">
						<strong><?php esc_html_e( 'Status:', 'reactwoo-geocore' ); ?></strong>
						<?php echo esc_html( $rwgc_addon['status_label'] ); ?>
					</p>
					<?php if ( ! empty( $rwgc_addon['cta_url'] ) ) : ?>
						<p>
							<a href="<?php echo esc_url( $rwgc_addon['cta_url'] ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $rwgc_addon['cta_label'] ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

