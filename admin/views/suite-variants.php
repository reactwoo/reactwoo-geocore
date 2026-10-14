<?php
/**
 * Experiences → Variants (experiment / A-B variants only).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_variant_rows = array();

if ( class_exists( 'RWGO_Experiment_Repository', false ) ) {
	foreach ( RWGO_Experiment_Repository::query_experiments() as $rwgc_experiment_post ) {
		if ( ! $rwgc_experiment_post instanceof WP_Post ) {
			continue;
		}
		$rwgc_config   = RWGO_Experiment_Repository::get_config( (int) $rwgc_experiment_post->ID );
		$rwgc_variants = isset( $rwgc_config['variants'] ) && is_array( $rwgc_config['variants'] ) ? $rwgc_config['variants'] : array();
		$rwgc_status   = isset( $rwgc_config['status'] ) ? (string) $rwgc_config['status'] : 'draft';

		foreach ( $rwgc_variants as $rwgc_variant ) {
			if ( ! is_array( $rwgc_variant ) ) {
				continue;
			}
			$rwgc_label = isset( $rwgc_variant['label'] ) ? (string) $rwgc_variant['label'] : __( 'Variant', 'reactwoo-geocore' );
			$rwgc_key   = isset( $rwgc_variant['key'] ) ? (string) $rwgc_variant['key'] : '';
			$rwgc_page_id = isset( $rwgc_variant['page_id'] ) ? (int) $rwgc_variant['page_id'] : 0;
			$rwgc_page_title = '';
			if ( $rwgc_page_id > 0 ) {
				$rwgc_p = get_post( $rwgc_page_id );
				if ( $rwgc_p ) {
					$rwgc_page_title = $rwgc_p->post_title;
				}
			}

			$rwgc_variant_rows[] = array(
				'experiment' => $rwgc_experiment_post->post_title,
				'variant'    => $rwgc_label . ( '' !== $rwgc_key ? ' (' . $rwgc_key . ')' : '' ),
				'content'    => $rwgc_page_title ? $rwgc_page_title : __( 'Linked page', 'reactwoo-geocore' ),
				'status'     => $rwgc_status ? ucfirst( $rwgc_status ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'    => get_the_modified_date( '', $rwgc_experiment_post ),
				'edit_url'   => admin_url( 'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . (int) $rwgc_experiment_post->ID ),
			);
		}
	}
}

/**
 * @param array<int, array<string, mixed>> $variant_rows Variant rows.
 */
$rwgc_variant_rows = apply_filters( 'rwgc_experience_variant_rows', $rwgc_variant_rows );
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

	<?php if ( empty( $rwgc_variant_rows ) ) : ?>
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
				<?php foreach ( $rwgc_variant_rows as $rwgc_row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $rwgc_row['experiment'] ); ?></strong></td>
						<td><?php echo esc_html( (string) $rwgc_row['variant'] ); ?></td>
						<td><?php echo esc_html( (string) $rwgc_row['content'] ); ?></td>
						<td><?php echo esc_html( (string) $rwgc_row['status'] ); ?></td>
						<td><?php echo esc_html( (string) $rwgc_row['updated'] ); ?></td>
						<td>
							<?php if ( ! empty( $rwgc_row['edit_url'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( (string) $rwgc_row['edit_url'] ); ?>"><?php esc_html_e( 'Edit experiment', 'reactwoo-geocore' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
