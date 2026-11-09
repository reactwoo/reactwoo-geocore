<?php
/**
 * A/B tests list (Experiences).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_ab_rows   = isset( $rwgc_ab_rows ) && is_array( $rwgc_ab_rows ) ? $rwgc_ab_rows : array();
$rwgc_ab_notice = isset( $rwgc_ab_notice ) ? sanitize_key( (string) $rwgc_ab_notice ) : '';
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'A/B tests', 'reactwoo-geocore' ),
			__( 'Element tests stored on this site. End a test to stop it, or delete it.', 'reactwoo-geocore' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'A/B tests', 'reactwoo-geocore' ); ?></h1>
	<?php endif; ?>

	<?php if ( 'ended' === $rwgc_ab_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test ended.', 'reactwoo-geocore' ); ?></p></div>
	<?php elseif ( 'deleted' === $rwgc_ab_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test deleted.', 'reactwoo-geocore' ); ?></p></div>
	<?php elseif ( 'notfound' === $rwgc_ab_notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Test not found.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Test', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rwgc_ab_rows ) ) : ?>
				<tr>
					<td colspan="4"><?php esc_html_e( 'No A/B tests yet.', 'reactwoo-geocore' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $rwgc_ab_rows as $rwgc_ab_row ) : ?>
					<?php
					if ( ! is_array( $rwgc_ab_row ) ) {
						continue;
					}
					$rwgc_ab_id     = isset( $rwgc_ab_row['id'] ) ? absint( $rwgc_ab_row['id'] ) : 0;
					$rwgc_ab_title  = isset( $rwgc_ab_row['title'] ) ? (string) $rwgc_ab_row['title'] : '';
					$rwgc_ab_status = isset( $rwgc_ab_row['status'] ) ? (string) $rwgc_ab_row['status'] : 'draft';
					$rwgc_ab_config = isset( $rwgc_ab_row['config'] ) && is_array( $rwgc_ab_row['config'] ) ? $rwgc_ab_row['config'] : array();
					$rwgc_ab_source = class_exists( 'RWGC_AB_Admin', false ) ? RWGC_AB_Admin::source_link( $rwgc_ab_config ) : array( 'url' => '', 'label' => '' );
					$rwgc_ab_adapter = isset( $rwgc_ab_config['adapter'] ) ? (string) $rwgc_ab_config['adapter'] : '';
					$rwgc_ab_tone   = 'neutral';
					if ( 'publish' === $rwgc_ab_status ) {
						$rwgc_ab_tone = 'success';
					} elseif ( class_exists( 'RWGC_AB_Tests', false ) && RWGC_AB_Tests::STATUS_ENDED === $rwgc_ab_status ) {
						$rwgc_ab_tone = 'warning';
					}
					$rwgc_ab_label = class_exists( 'RWGC_AB_Tests', false ) ? RWGC_AB_Tests::status_label( $rwgc_ab_status ) : $rwgc_ab_status;
					$rwgc_ab_end   = wp_nonce_url(
						admin_url( 'admin-post.php?action=rwgc_ab_end_test&test_id=' . $rwgc_ab_id ),
						'rwgc_ab_end_test_' . $rwgc_ab_id
					);
					$rwgc_ab_delete = wp_nonce_url(
						admin_url( 'admin-post.php?action=rwgc_ab_delete_test&test_id=' . $rwgc_ab_id ),
						'rwgc_ab_delete_test_' . $rwgc_ab_id
					);
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $rwgc_ab_title ); ?></strong>
							<div class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: test ID, 2: adapter slug */
										__( 'ID %1$d · %2$s', 'reactwoo-geocore' ),
										$rwgc_ab_id,
										$rwgc_ab_adapter
									)
								);
								?>
							</div>
						</td>
						<td>
							<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
								<?php RWGC_Admin_UI::render_pill( $rwgc_ab_label, $rwgc_ab_tone ); ?>
							<?php else : ?>
								<?php echo esc_html( $rwgc_ab_label ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( '' !== $rwgc_ab_source['label'] && '' !== $rwgc_ab_source['url'] ) : ?>
								<a href="<?php echo esc_url( $rwgc_ab_source['url'] ); ?>"><?php echo esc_html( $rwgc_ab_source['label'] ); ?></a>
							<?php elseif ( '' !== $rwgc_ab_source['label'] ) : ?>
								<?php echo esc_html( $rwgc_ab_source['label'] ); ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'Not linked', 'reactwoo-geocore' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( class_exists( 'RWGC_AB_Tests', false ) && RWGC_AB_Tests::STATUS_ENDED !== $rwgc_ab_status ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( $rwgc_ab_end ); ?>"><?php esc_html_e( 'End', 'reactwoo-geocore' ); ?></a>
							<?php endif; ?>
							<a class="button-link-delete" style="margin-left:8px;" href="<?php echo esc_url( $rwgc_ab_delete ); ?>" onclick="return confirm( <?php echo esc_attr( wp_json_encode( __( 'Delete this A/B test?', 'reactwoo-geocore' ) ) ); ?> );"><?php esc_html_e( 'Delete', 'reactwoo-geocore' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
