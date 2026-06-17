<?php
/**
 * Targeting → Variants (country page variants).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant_rows = class_exists( 'RWGC_Admin_Targeting_Variants', false )
	? RWGC_Admin_Targeting_Variants::get_table_rows()
	: array();
$create_url   = admin_url( 'admin.php?page=rwgc-workflow-variant' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-targeting-variants">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Variants', 'reactwoo-geocore' ),
		__( 'Full-page versions for visitors who match your conditions.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( class_exists( 'RWGC_Admin_Targeting_Nav', false ) ) {
		RWGC_Admin_Targeting_Nav::render_tabs( 'rwgc-suite-variants' );
	}
	?>

	<p class="description rwgc-targeting-variants__help">
		<?php esc_html_e( 'Routing happens automatically. Matching visitors see the mapped page. Everyone else sees the default page.', 'reactwoo-geocore' ); ?>
	</p>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( $create_url ); ?>"><?php esc_html_e( 'Create variant', 'reactwoo-geocore' ); ?></a>
		<a class="button" href="<?php echo esc_url( function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'targeting', 'rwgc-targeting-hub' ) : admin_url( 'admin.php?page=rwgc-targeting-hub' ) ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Open assistant', 'reactwoo-geocore' ); ?></a>
	</p>

	<?php if ( empty( $variant_rows ) ) : ?>
		<div class="rwgc-card">
			<p><?php esc_html_e( 'No country variants yet. Create one to show a different page to visitors in specific countries.', 'reactwoo-geocore' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped rwgc-targeting-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Default page', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Variant page', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Condition', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $variant_rows as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $row['default_page'] ); ?></strong></td>
						<td><?php echo esc_html( (string) $row['variant_page'] ); ?></td>
						<td><?php echo esc_html( (string) $row['type'] ); ?></td>
						<td><?php echo esc_html( (string) $row['condition'] ); ?></td>
						<td><span class="rwgc-status-pill rwgc-status-pill--<?php echo esc_attr( (string) ( $row['status_tone'] ?? 'neutral' ) ); ?>"><?php echo esc_html( (string) $row['status'] ); ?></span></td>
						<td>
							<?php if ( ! empty( $row['edit_url'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( (string) $row['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geocore' ); ?></a>
							<?php endif; ?>
							<?php if ( ! empty( $row['preview_url'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( (string) $row['preview_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview', 'reactwoo-geocore' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
