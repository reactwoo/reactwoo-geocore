<?php
/**
 * Integrations Gutenberg entry page.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php RWGC_Admin_UI::render_page_header( __( 'Gutenberg', 'reactwoo-geocore' ), __( 'Block editor integration for Geo Content and targeting-aware blocks.', 'reactwoo-geocore' ) ); ?>
	<div class="rwgc-card">
		<p class="description">
			<?php esc_html_e( 'Geo Core registers Gutenberg integration for Geo Content and portable targeting context in the editor.', 'reactwoo-geocore' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">
				<?php esc_html_e( 'Create content in block editor', 'reactwoo-geocore' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-target-types' ) ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'View targeting conditions', 'reactwoo-geocore' ); ?>
			</a>
		</p>
	</div>
</div>
