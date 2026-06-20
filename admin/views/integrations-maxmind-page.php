<?php
/**
 * Integrations → MaxMind (GeoLite2) credentials and country database.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$option_key   = RWGC_Settings::OPTION_KEY;
$settings     = isset( $settings ) && is_array( $settings ) ? $settings : RWGC_Settings::get_settings();
$status       = isset( $status ) && is_array( $status ) ? $status : RWGC_MaxMind::get_status();
$data         = isset( $data ) && is_array( $data ) ? $data : ( class_exists( 'RWGC_API', false ) ? RWGC_API::get_visitor_data() : array() );
$page_url     = function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' );
$settings_url = admin_url( 'admin.php?page=rwgc-settings' );
$can_manage   = class_exists( 'RWGC_Admin', false ) ? RWGC_Admin::can_manage() : current_user_can( 'manage_options' );

// Handle maintenance actions (update DB, clear cache).
if ( isset( $_GET['rwgc_action'], $_GET['_wpnonce'] ) && $can_manage ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action = sanitize_key( wp_unslash( $_GET['rwgc_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $action, array( 'clear_cache', 'update_db' ), true ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwgc_maxmind_action_' . $action ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'clear_cache' === $action && class_exists( 'RWGC_Cache', false ) ) {
			RWGC_Cache::clear_all();
			add_settings_error( 'rwgc_maxmind', 'rwgc_cache_cleared', __( 'Geo cache cleared.', 'reactwoo-geocore' ), 'updated' );
		} elseif ( 'update_db' === $action ) {
			$result = RWGC_MaxMind::update_database();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'rwgc_maxmind', 'rwgc_db_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'rwgc_maxmind', 'rwgc_db_updated', __( 'MaxMind country database updated.', 'reactwoo-geocore' ), 'updated' );
			}
			$status = RWGC_MaxMind::get_status();
		}
	}
}

settings_errors( 'rwgc_maxmind' );
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'MaxMind (GeoLite2)', 'reactwoo-geocore' ),
		__( 'Connect your MaxMind account, refresh the country database, and verify visitor detection for targeting.', 'reactwoo-geocore' )
	);
	?>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Account credentials', 'reactwoo-geocore' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'These are your MaxMind (third-party) GeoLite2 credentials — not a ReactWoo product license.', 'reactwoo-geocore' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwgc_save_maxmind_settings" />
			<?php wp_nonce_field( 'rwgc_save_maxmind_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwgc_maxmind_account_id"><?php esc_html_e( 'Account ID', 'reactwoo-geocore' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="rwgc_maxmind_account_id" name="maxmind_account_id" value="<?php echo esc_attr( isset( $settings['maxmind_account_id'] ) ? (string) $settings['maxmind_account_id'] : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwgc_maxmind_license_key"><?php esc_html_e( 'License key', 'reactwoo-geocore' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="rwgc_maxmind_license_key" name="maxmind_license_key" value="<?php echo esc_attr( isset( $settings['maxmind_license_key'] ) ? (string) $settings['maxmind_license_key'] : '' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Create a free GeoLite2 license at maxmind.com (Account → Manage License Keys).', 'reactwoo-geocore' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic updates', 'reactwoo-geocore' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_update_db" value="1" <?php checked( ! empty( $settings['auto_update_db'] ) ); ?> />
							<?php esc_html_e( 'Download or refresh the database automatically when possible.', 'reactwoo-geocore' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save credentials', 'reactwoo-geocore' ) ); ?>
		</form>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Country database', 'reactwoo-geocore' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'update_db', $page_url ), 'rwgc_maxmind_action_update_db' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Download / refresh database', 'reactwoo-geocore' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'clear_cache', $page_url ), 'rwgc_maxmind_action_clear_cache' ) ); ?>" class="button">
				<?php esc_html_e( 'Clear geo cache', 'reactwoo-geocore' ); ?>
			</a>
		</p>

		<h3><?php esc_html_e( 'Manual upload (fallback)', 'reactwoo-geocore' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'If automatic download fails on your host, upload a GeoLite2-Country.mmdb file from your MaxMind account.', 'reactwoo-geocore' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="rwgc_upload_mmdb" />
			<?php wp_nonce_field( 'rwgc_upload_mmdb' ); ?>
			<p>
				<input type="file" name="rwgc_mmdb" accept=".mmdb" />
				<?php submit_button( __( 'Upload .mmdb file', 'reactwoo-geocore' ), 'secondary', 'submit', false ); ?>
			</p>
		</form>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Database path', 'reactwoo-geocore' ); ?></th>
				<td><code><?php echo esc_html( isset( $status['path'] ) ? (string) $status['path'] : '' ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Database on disk', 'reactwoo-geocore' ); ?></th>
				<td><?php echo ! empty( $status['exists'] ) ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last updated', 'reactwoo-geocore' ); ?></th>
				<td><?php echo ! empty( $status['last_updated'] ) ? esc_html( (string) $status['last_updated'] ) : esc_html__( 'Unknown', 'reactwoo-geocore' ); ?></td>
			</tr>
			<?php if ( ! empty( $status['last_error'] ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last error', 'reactwoo-geocore' ); ?></th>
				<td><span class="description"><?php echo esc_html( (string) $status['last_error'] ); ?></span></td>
			</tr>
			<?php endif; ?>
		</table>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Test visitor detection', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Uses the same APIs as the front end. Confirm country before building targeting rules.', 'reactwoo-geocore' ); ?></p>
		<?php if ( ! empty( $data ) ) : ?>
			<p><strong><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $data['country_code'] ) ? (string) $data['country_code'] : '' ); ?></p>
			<p><strong><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $data['language'] ) ? (string) $data['language'] : '' ); ?></p>
			<p><strong><?php esc_html_e( 'Device', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $data['device_type'] ) ? (string) $data['device_type'] : '' ); ?></p>
			<details>
				<summary><?php esc_html_e( 'Developer details', 'reactwoo-geocore' ); ?></summary>
				<pre><?php echo esc_html( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
			</details>
		<?php else : ?>
			<p><?php esc_html_e( 'No visitor data available yet. Save credentials and refresh the database above.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>
	</div>

	<p class="description">
		<?php
		printf(
			/* translators: %s: settings URL */
			esc_html__( 'Detection toggles, cache TTL, and fallbacks remain under %s.', 'reactwoo-geocore' ),
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings → General', 'reactwoo-geocore' ) . '</a>'
		);
		?>
	</p>
</div>
