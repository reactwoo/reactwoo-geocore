<?php
/**
 * Experiences → Variants (experiment / A-B variants only).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant_rows = array();

if ( class_exists( 'RWGO_Experiment_Repository', false ) ) {
	foreach ( RWGO_Experiment_Repository::query_experiments() as $experiment_post ) {
		if ( ! $experiment_post instanceof WP_Post ) {
			continue;
		}
		$config   = RWGO_Experiment_Repository::get_config( (int) $experiment_post->ID );
		$variants = isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array();
		$status   = isset( $config['status'] ) ? (string) $config['status'] : 'draft';

		foreach ( $variants as $variant ) {
			if ( ! is_array( $variant ) ) {
				continue;
			}
			$label = isset( $variant['label'] ) ? (string) $variant['label'] : __( 'Variant', 'reactwoo-geocore' );
			$key   = isset( $variant['key'] ) ? (string) $variant['key'] : '';
			$page_id = isset( $variant['page_id'] ) ? (int) $variant['page_id'] : 0;
			$page_title = '';
			if ( $page_id > 0 ) {
				$p = get_post( $page_id );
				if ( $p ) {
					$page_title = $p->post_title;
				}
			}

			$variant_rows[] = array(
				'experiment' => $experiment_post->post_title,
				'variant'    => $label . ( '' !== $key ? ' (' . $key . ')' : '' ),
				'content'    => $page_title ? $page_title : __( 'Linked page', 'reactwoo-geocore' ),
				'status'     => $status ? ucfirst( $status ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'    => get_the_modified_date( '', $experiment_post ),
				'edit_url'   => admin_url( 'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . (int) $experiment_post->ID ),
			);
		}
	}
}

/**
 * @param array<int, array<string, mixed>> $variant_rows Variant rows.
 */
$variant_rows = apply_filters( 'rwgc_experience_variant_rows', $variant_rows );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
	RWGC_Admin_UI::render_page_header(
		__( 'Variants', 'reactwoo-geocore' ),
		__( 'Experiment and A/B test variants created through Geo Optimise.', 'reactwoo-geocore' )
	);
	?>

	<p class="rwgc-suite-shell__intro">
		<?php esc_html_e( 'Variants listed here are tied to experiments — not Elementor visibility rules or portable targeting libraries.', 'reactwoo-geocore' ); ?>
	</p>

	<?php if ( empty( $variant_rows ) ) : ?>
		<div class="rwgc-card">
			<p><?php esc_html_e( 'No experiment variants have been created yet.', 'reactwoo-geocore' ); ?></p>
			<p>
				<?php if ( class_exists( 'RWGO_Admin', false ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create experiment', 'reactwoo-geocore' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-help' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Learn about variants', 'reactwoo-geocore' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<table class="widefat striped rwgc-suite-variants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Experiment', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Variant', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Content', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Updated', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $variant_rows as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $row['experiment'] ); ?></strong></td>
						<td><?php echo esc_html( (string) $row['variant'] ); ?></td>
						<td><?php echo esc_html( (string) $row['content'] ); ?></td>
						<td><?php echo esc_html( (string) $row['status'] ); ?></td>
						<td><?php echo esc_html( (string) $row['updated'] ); ?></td>
						<td>
							<?php if ( ! empty( $row['edit_url'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( (string) $row['edit_url'] ); ?>"><?php esc_html_e( 'Edit experiment', 'reactwoo-geocore' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
