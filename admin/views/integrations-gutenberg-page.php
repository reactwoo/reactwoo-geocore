<?php
/**
 * Integrations Gutenberg entry page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$has_block = class_exists( 'RWGC_Gutenberg', false );
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php RWGC_Admin_UI::render_page_header( __( 'Gutenberg', 'reactwoo-geocore' ), __( 'Block editor integration for Geo Content and targeting-aware blocks.', 'reactwoo-geocore' ) ); ?>
	<div class="rwgc-card">
		<?php if ( $has_block ) : ?>
			<p class="description">
				<?php esc_html_e( 'Geo Core registers the Geo Content block and portable targeting context in the block editor. Create posts or pages and add geo-aware blocks from the inserter.', 'reactwoo-geocore' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">
					<?php esc_html_e( 'Create content in block editor', 'reactwoo-geocore' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-target-types' ) ); ?>" style="margin-left:8px;">
					<?php esc_html_e( 'View targeting conditions', 'reactwoo-geocore' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'No Gutenberg rules have been created yet.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Gutenberg integration is available when the block editor is enabled. Add Geo Content blocks to posts or pages to target visitors by geo.', 'reactwoo-geocore' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">
					<?php esc_html_e( 'Open block editor', 'reactwoo-geocore' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
</div>
