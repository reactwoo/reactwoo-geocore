<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_target_types = isset( $rwgc_target_types ) && is_array( $rwgc_target_types ) ? $rwgc_target_types : array();
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Target types', 'reactwoo-geocore' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Suite-wide targeting keys registered by Geo Core and providers. Satellites (Geo Commerce, Geo Optimise, etc.) consume these definitions for conditions — they do not own the vocabulary.', 'reactwoo-geocore' ); ?>
	</p>

	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Key', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Label', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Group', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Provider', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Mode', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Availability', 'reactwoo-geocore' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $rwgc_target_types ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No target types registered.', 'reactwoo-geocore' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $rwgc_target_types as $def ) : ?>
				<?php
				if ( ! is_array( $def ) ) {
					continue;
				}
				$key   = isset( $def['key'] ) ? (string) $def['key'] : '';
				$avail = class_exists( 'RWGC_Target_Availability', false ) ? RWGC_Target_Availability::describe( $def ) : array( 'label' => '—' );
				?>
				<tr>
					<td><code><?php echo esc_html( $key ); ?></code></td>
					<td><?php echo esc_html( isset( $def['label'] ) ? (string) $def['label'] : $key ); ?></td>
					<td><?php echo esc_html( isset( $def['group'] ) ? (string) $def['group'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $def['provider'] ) ? (string) $def['provider'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $def['value_mode'] ) ? (string) $def['value_mode'] : '' ); ?></td>
					<td><?php echo esc_html( isset( $avail['label'] ) ? $avail['label'] : '' ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>
