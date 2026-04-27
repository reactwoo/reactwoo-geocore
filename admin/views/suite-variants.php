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
		__( 'Rules / Page Versions', 'reactwoo-geocore' ),
		__( 'Create and manage rules that show, hide, redirect, or route page versions for the right visitors.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-suite-variants' ); ?>

	<p class="rwgc-suite-shell__intro">
		<?php esc_html_e( 'Use plain-English rules: "When a visitor matches conditions, show an action on a target."', 'reactwoo-geocore' ); ?>
	</p>

	<div class="rwgc-grid">
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Create a geo rule', 'reactwoo-geocore' ); ?></h2>
			<ol class="rwgc-steps">
				<li><?php esc_html_e( 'Name the rule', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Choose visitor conditions', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Choose action: Show / Hide / Redirect / Route', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Choose target', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Review and activate', 'reactwoo-geocore' ); ?></li>
			</ol>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-workflow-variant' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Geo Rule', 'reactwoo-geocore' ); ?></a></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Rule sentence preview', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'When a visitor matches [condition], then [action] [target].', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Example: When a visitor is in the UK, route to the UK homepage version.', 'reactwoo-geocore' ); ?></p>
		</div>
	</div>

	<?php if ( empty( $overview_rows ) ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'No page version rules yet. Create your first rule to route visitors to a page version.', 'reactwoo-geocore' ); ?></p></div>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-workflow-variant' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Geo Rule', 'reactwoo-geocore' ); ?></a>
		</p>
	<?php else : ?>
		<table class="widefat striped rwgc-suite-variants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Page version', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Condition', 'reactwoo-geocore' ); ?></th>
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
							<a class="button button-small" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Edit rule', 'reactwoo-geocore' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-workflow-variant' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Geo Rule', 'reactwoo-geocore' ); ?></a>
	</p>
</div>
