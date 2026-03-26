<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle simple actions via query args + nonce.
if ( isset( $_GET['rwgc_action'], $_GET['_wpnonce'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action = sanitize_key( wp_unslash( $_GET['rwgc_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $action, array( 'clear_cache', 'update_db' ), true ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwgc_tools_action_' . $action ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'clear_cache' === $action ) {
			RWGC_Cache::clear_all();
			add_settings_error( 'rwgc_tools', 'rwgc_cache_cleared', __( 'Geo cache cleared.', 'reactwoo-geocore' ), 'updated' );
		} elseif ( 'update_db' === $action ) {
			$result = RWGC_MaxMind::update_database();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_db_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'rwgc_tools', 'rwgc_db_updated', __( 'MaxMind database updated.', 'reactwoo-geocore' ), 'updated' );
			}
		}
	}
}

settings_errors( 'rwgc_tools' );
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Core Tools', 'reactwoo-geocore' ); ?></h1>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-tools' ); ?>

	<h2><?php esc_html_e( 'Database & Cache', 'reactwoo-geocore' ); ?></h2>
	<p>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'update_db' ), 'rwgc_tools_action_update_db' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Update MaxMind Database', 'reactwoo-geocore' ); ?>
		</a>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'clear_cache' ), 'rwgc_tools_action_clear_cache' ) ); ?>" class="button">
			<?php esc_html_e( 'Clear Geo Cache', 'reactwoo-geocore' ); ?>
		</a>
	</p>

	<h3><?php esc_html_e( 'Manual upload (fallback)', 'reactwoo-geocore' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'If automatic download fails on your host, you can upload a GeoLite2-Country.mmdb file exported from MaxMind.', 'reactwoo-geocore' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="rwgc_upload_mmdb" />
		<?php wp_nonce_field( 'rwgc_upload_mmdb' ); ?>
		<p>
			<input type="file" name="rwgc_mmdb" accept=".mmdb" />
			<?php submit_button( __( 'Upload .mmdb file', 'reactwoo-geocore' ), 'secondary', 'submit', false ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'Upload the GeoLite2-Country.mmdb file downloaded from your MaxMind account. ReactWoo Geo Core will store it in a safe location and start using it for lookups.', 'reactwoo-geocore' ); ?>
		</p>
	</form>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'DB path', 'reactwoo-geocore' ); ?></th>
			<td><code><?php echo esc_html( $status['path'] ); ?></code></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'DB exists', 'reactwoo-geocore' ); ?></th>
			<td><?php echo $status['exists'] ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'DB last updated', 'reactwoo-geocore' ); ?></th>
			<td><?php echo $status['last_updated'] ? esc_html( $status['last_updated'] ) : esc_html__( 'Unknown', 'reactwoo-geocore' ); ?></td>
		</tr>
		<?php if ( ! empty( $status['last_error'] ) ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Last DB error', 'reactwoo-geocore' ); ?></th>
			<td><span class="description"><?php echo esc_html( $status['last_error'] ); ?></span></td>
		</tr>
		<?php endif; ?>
	</table>

	<h2><?php esc_html_e( 'Test current lookup', 'reactwoo-geocore' ); ?></h2>
	<?php if ( ! empty( $data ) ) : ?>
		<pre><?php echo esc_html( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	<?php else : ?>
		<p><?php esc_html_e( 'No geo data available yet.', 'reactwoo-geocore' ); ?></p>
	<?php endif; ?>
</div>

