<?php
/**
 * Integrations → MaxMind (GeoLite2) credentials and country database.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_option_key   = RWGC_Settings::OPTION_KEY;
$rwgc_settings     = isset( $rwgc_settings ) && is_array( $rwgc_settings ) ? $rwgc_settings : RWGC_Settings::get_settings();
$rwgc_status       = isset( $rwgc_status ) && is_array( $rwgc_status ) ? $rwgc_status : RWGC_MaxMind::get_status();
$rwgc_data         = isset( $rwgc_data ) && is_array( $rwgc_data ) ? $rwgc_data : ( class_exists( 'RWGC_API', false ) ? RWGC_API::get_visitor_data() : array() );
$rwgc_page_url     = function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' );
$rwgc_settings_url = admin_url( 'admin.php?page=rwgc-settings' );

// Handle maintenance actions (update DB, clear cache).
if ( isset( $_GET['rwgc_action'], $_GET['_wpnonce'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rwgc_action = sanitize_key( wp_unslash( $_GET['rwgc_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $rwgc_action, array( 'clear_cache', 'update_db' ), true ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rwgc_maxmind_action_' . $rwgc_action ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'clear_cache' === $rwgc_action && class_exists( 'RWGC_Cache', false ) ) {
			RWGC_Cache::clear_all();
			add_settings_error( 'rwgc_maxmind', 'rwgc_cache_cleared', __( 'Geo cache cleared.', 'reactwoo-geocore' ), 'updated' );
		} elseif ( 'update_db' === $rwgc_action ) {
			$rwgc_result = RWGC_MaxMind::update_database();
			$rwgc_status = RWGC_MaxMind::get_status();
			if ( ! is_wp_error( $rwgc_result ) ) {
				add_settings_error( 'rwgc_maxmind', 'rwgc_db_updated', __( 'MaxMind country database updated.', 'reactwoo-geocore' ), 'updated' );
			}
		}
	}
}

if ( ! empty( $rwgc_status['last_error'] ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html( (string) $rwgc_status['last_error'] ) . '</p></div>';
}
settings_errors( 'rwgc_maxmind' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-maxmind-screen">
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
						<input type="text" class="regular-text" id="rwgc_maxmind_account_id" name="maxmind_account_id" inputmode="numeric" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" value="<?php echo esc_attr( isset( $rwgc_settings['maxmind_account_id'] ) ? (string) $rwgc_settings['maxmind_account_id'] : '' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwgc_maxmind_license_key"><?php esc_html_e( 'License key', 'reactwoo-geocore' ); ?></label></th>
					<td>
						<?php
						$rwgc_license_stored = isset( $rwgc_settings['maxmind_license_key'] ) ? (string) $rwgc_settings['maxmind_license_key'] : '';
						$rwgc_license_shown  = '' !== $rwgc_license_stored ? RWGC_MaxMind::mask_license_key( $rwgc_license_stored ) : '';
						?>
						<input type="text" class="regular-text" id="rwgc_maxmind_license_key" name="maxmind_license_key" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" value="<?php echo esc_attr( $rwgc_license_shown ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Create a free GeoLite2 license at maxmind.com (Account → Manage License Keys). Leave this field unchanged to keep the saved key.', 'reactwoo-geocore' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic updates', 'reactwoo-geocore' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_update_db" value="1" <?php checked( ! empty( $rwgc_settings['auto_update_db'] ) ); ?> />
							<?php esc_html_e( 'Download or refresh the database automatically when possible.', 'reactwoo-geocore' ); ?>
						</label>
						<?php if ( ! empty( $rwgc_status['usable'] ) && empty( $rwgc_status['has_license'] ) ) : ?>
							<p class="description">
								<?php esc_html_e( 'A country database is already on this site, so lookups do not need a license key. Add a MaxMind license key only if you want automatic downloads.', 'reactwoo-geocore' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save credentials', 'reactwoo-geocore' ) ); ?>
		</form>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Country database', 'reactwoo-geocore' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'update_db', $rwgc_page_url ), 'rwgc_maxmind_action_update_db' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Download / refresh database', 'reactwoo-geocore' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'clear_cache', $rwgc_page_url ), 'rwgc_maxmind_action_clear_cache' ) ); ?>" class="button">
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
				<td><code><?php echo esc_html( isset( $rwgc_status['path'] ) ? (string) $rwgc_status['path'] : '' ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Database on disk', 'reactwoo-geocore' ); ?></th>
				<td><?php echo ! empty( $rwgc_status['exists'] ) ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last updated', 'reactwoo-geocore' ); ?></th>
				<td><?php echo ! empty( $rwgc_status['last_updated'] ) ? esc_html( (string) $rwgc_status['last_updated'] ) : esc_html__( 'Unknown', 'reactwoo-geocore' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last successful download', 'reactwoo-geocore' ); ?></th>
				<td><?php echo ! empty( $rwgc_status['last_success_at'] ) ? esc_html( (string) $rwgc_status['last_success_at'] ) : esc_html__( 'Not yet', 'reactwoo-geocore' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last failed download', 'reactwoo-geocore' ); ?></th>
				<td>
					<?php echo ! empty( $rwgc_status['last_failure_at'] ) ? esc_html( (string) $rwgc_status['last_failure_at'] ) : esc_html__( 'Not yet', 'reactwoo-geocore' ); ?>
					<?php if ( ! empty( $rwgc_status['last_error'] ) ) : ?>
						<p class="description"><?php echo esc_html( (string) $rwgc_status['last_error'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Test visitor detection', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Uses the same APIs as the front end. Confirm country before building targeting rules.', 'reactwoo-geocore' ); ?></p>
		<?php if ( ! empty( $rwgc_data ) ) : ?>
			<p><strong><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_data['country_code'] ) ? (string) $rwgc_data['country_code'] : '' ); ?></p>
			<p><strong><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_data['language'] ) ? (string) $rwgc_data['language'] : '' ); ?></p>
			<p><strong><?php esc_html_e( 'Device', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_data['device_type'] ) ? (string) $rwgc_data['device_type'] : '' ); ?></p>
			<details>
				<summary><?php esc_html_e( 'Developer details', 'reactwoo-geocore' ); ?></summary>
				<pre><?php echo esc_html( wp_json_encode( $rwgc_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
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
			'<a href="' . esc_url( $rwgc_settings_url ) . '">' . esc_html__( 'Settings → General', 'reactwoo-geocore' ) . '</a>'
		);
		?>
	</p>
</div>
