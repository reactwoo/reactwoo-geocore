<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$option_key = RWGC_Settings::OPTION_KEY;
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Core Settings', 'reactwoo-geocore' ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'rwgc_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable geolocation', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], 1 ); ?> />
						<?php esc_html_e( 'Resolve visitor location and expose helper functions.', 'reactwoo-geocore' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cache results', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[cache_enabled]" value="1" <?php checked( $settings['cache_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Cache geo lookups per IP to improve performance.', 'reactwoo-geocore' ); ?>
					</label>
					<p class="description">
						<label>
							<?php esc_html_e( 'Cache TTL (seconds)', 'reactwoo-geocore' ); ?>
							<input type="number" name="<?php echo esc_attr( $option_key ); ?>[cache_ttl]" value="<?php echo esc_attr( (int) $settings['cache_ttl'] ); ?>" min="60" step="60" />
						</label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'MaxMind account & license', 'reactwoo-geocore' ); ?></th>
				<td>
					<p>
						<label>
							<?php esc_html_e( 'Account ID', 'reactwoo-geocore' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[maxmind_account_id]" value="<?php echo esc_attr( isset( $settings['maxmind_account_id'] ) ? $settings['maxmind_account_id'] : '' ); ?>" class="regular-text" />
						</label>
					</p>
					<p>
						<label>
							<?php esc_html_e( 'License key', 'reactwoo-geocore' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[maxmind_license_key]" value="<?php echo esc_attr( $settings['maxmind_license_key'] ); ?>" class="regular-text" />
						</label>
					</p>
					<p class="description">
						<?php esc_html_e( 'Required for automatic GeoLite2 database downloads. ReactWoo Geo Core uses HTTP Basic Authentication with your Account ID and License Key.', 'reactwoo-geocore' ); ?>
						<?php esc_html_e( 'You need a free MaxMind GeoLite2 license key (commercial use may require a different agreement).', 'reactwoo-geocore' ); ?>
					</p>
					<p class="description">
						<strong><?php esc_html_e( 'How to get your Account ID and License Key:', 'reactwoo-geocore' ); ?></strong><br />
						<?php esc_html_e( '1) Go to maxmind.com and create or log in to your account.', 'reactwoo-geocore' ); ?><br />
						<?php esc_html_e( '2) In your account, open Services → My License Key (or GeoLite2) and create a new key.', 'reactwoo-geocore' ); ?><br />
						<?php esc_html_e( '3) Copy the AccountID and LicenseKey values from the generated GeoIP.conf snippet.', 'reactwoo-geocore' ); ?><br />
						<?php esc_html_e( '4) Paste AccountID and LicenseKey into the fields above, save, then use the Tools tab to download/update the database.', 'reactwoo-geocore' ); ?>
					</p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[auto_update_db]" value="1" <?php checked( $settings['auto_update_db'], 1 ); ?> />
						<?php esc_html_e( 'Automatically update the database when possible.', 'reactwoo-geocore' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fallback location', 'reactwoo-geocore' ); ?></th>
				<td>
					<p>
						<label>
							<?php esc_html_e( 'Fallback country (ISO2)', 'reactwoo-geocore' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[fallback_country]" value="<?php echo esc_attr( $settings['fallback_country'] ); ?>" maxlength="2" class="small-text" />
						</label>
					</p>
					<p>
						<label>
							<?php esc_html_e( 'Fallback currency (ISO3)', 'reactwoo-geocore' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[fallback_currency]" value="<?php echo esc_attr( $settings['fallback_currency'] ); ?>" maxlength="3" class="small-text" />
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'Used when lookups fail or the database is missing.', 'reactwoo-geocore' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'REST API', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[rest_enabled]" value="1" <?php checked( $settings['rest_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Expose /wp-json/reactwoo-geocore/v1/location endpoint.', 'reactwoo-geocore' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug mode', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[debug_mode]" value="1" <?php checked( $settings['debug_mode'], 1 ); ?> />
						<?php esc_html_e( 'Log geo errors to debug.log when WP_DEBUG is enabled.', 'reactwoo-geocore' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button(); ?>
	</form>
</div>

