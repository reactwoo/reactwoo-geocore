<?php
/**
 * Page versions — master / local version relationships (Geo Core free routing).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$overview_rows = isset( $overview_rows ) && is_array( $overview_rows ) ? $overview_rows : array();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Geo Suite — Page versions', 'reactwoo-geocore' ),
		__( 'Default pages and their country-specific versions (free Geo Core routing). Power users can still edit routing in the page sidebar.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-suite-variants' ); ?>

	<p class="rwgc-suite-shell__intro">
		<?php esc_html_e( 'Each row is a “default” page with geo routing on. When a local version exists, visitors from that country see it instead.', 'reactwoo-geocore' ); ?>
	</p>

	<?php if ( empty( $overview_rows ) ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'No pages use Geo variant routing yet. Create a default page + local version from the guided workflow.', 'reactwoo-geocore' ); ?></p></div>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-workflow-variant' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create page version', 'reactwoo-geocore' ); ?></a>
		</p>
	<?php else : ?>
		<table class="widefat striped rwgc-suite-variants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Default page', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Local version', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $overview_rows as $row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $row['master_title'] ); ?></strong>
							<?php if ( ! empty( $row['edit_master'] ) ) : ?>
								<br /><a href="<?php echo esc_url( $row['edit_master'] ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geocore' ); ?></a>
								<?php if ( ! empty( $row['view_master'] ) ) : ?>
									&nbsp;|&nbsp;<a href="<?php echo esc_url( $row['view_master'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'reactwoo-geocore' ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $row['variant'] ) && is_array( $row['variant'] ) ) : ?>
								<?php echo esc_html( $row['variant']['variant_title'] ); ?>
								<?php if ( ! empty( $row['variant']['edit_variant'] ) ) : ?>
									<br /><a href="<?php echo esc_url( $row['variant']['edit_variant'] ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geocore' ); ?></a>
								<?php endif; ?>
							<?php else : ?>
								<span class="description"><?php esc_html_e( 'None yet', 'reactwoo-geocore' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( ! empty( $row['variant']['country_iso2'] ) ) {
								echo esc_html( (string) $row['variant']['country_iso2'] );
							} else {
								echo '—';
							}
							?>
						</td>
						<td>
							<?php
							$add_url = admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_master_page_id=' . (int) $row['master_id'] );
							?>
							<a class="button button-small" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add / manage version', 'reactwoo-geocore' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-workflow-variant' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create page version', 'reactwoo-geocore' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-getting-started' ) ); ?>" class="button"><?php esc_html_e( 'Getting Started', 'reactwoo-geocore' ); ?></a>
	</p>
</div>
