<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_provider_rows = isset( $rwgc_provider_rows ) && is_array( $rwgc_provider_rows ) ? $rwgc_provider_rows : array();
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Targeting providers (developer)', 'reactwoo-geocore' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'This is a technical diagnostics screen for targeting integrations. Most users should use the Targeting screen.', 'reactwoo-geocore' ); ?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Provider', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'State', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Detail', 'reactwoo-geocore' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rwgc_provider_rows ) ) : ?>
			<tr><td colspan="3"><?php esc_html_e( 'No providers loaded.', 'reactwoo-geocore' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rwgc_provider_rows as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( isset( $row['label'] ) ? (string) $row['label'] : '' ); ?></strong><br /><code><?php echo esc_html( isset( $row['key'] ) ? (string) $row['key'] : '' ); ?></code></td>
					<td><?php echo esc_html( isset( $row['state'] ) ? (string) $row['state'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $row['detail'] ) ? (string) $row['detail'] : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
