<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$option_key = RWGC_Settings::OPTION_KEY;
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Core Settings', 'reactwoo-geocore' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Configure detection, database, behavior, and integrations.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-settings' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'rwgc_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr><th colspan="2"><h2><?php esc_html_e( 'Detection', 'reactwoo-geocore' ); ?></h2></th></tr>
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
			<tr><th colspan="2"><h2><?php esc_html_e( 'Database', 'reactwoo-geocore' ); ?></h2></th></tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'MaxMind account (GeoLite2)', 'reactwoo-geocore' ); ?></th>
				<td>
					<p>
						<label>
							<?php esc_html_e( 'Account ID', 'reactwoo-geocore' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[maxmind_account_id]" value="<?php echo esc_attr( isset( $settings['maxmind_account_id'] ) ? $settings['maxmind_account_id'] : '' ); ?>" class="regular-text" />
						</label>
					</p>
					<p>
						<label>
							<?php esc_html_e( 'MaxMind license key', 'reactwoo-geocore' ); ?>
							<input type="text" name="<?php echo esc_attr( $option_key ); ?>[maxmind_license_key]" value="<?php echo esc_attr( $settings['maxmind_license_key'] ); ?>" class="regular-text" />
						</label>
					</p>
					<p class="description">
						<?php esc_html_e( 'This is your MaxMind (third-party) credential for GeoLite2 downloads — not a ReactWoo product license. Geo Core uses HTTP Basic Authentication with your Account ID and MaxMind license key.', 'reactwoo-geocore' ); ?>
						<?php esc_html_e( 'You need a free MaxMind GeoLite2 license (commercial use may require a different agreement).', 'reactwoo-geocore' ); ?>
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
			<tr><th colspan="2"><h2><?php esc_html_e( 'Behaviour', 'reactwoo-geocore' ); ?></h2></th></tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fallback location', 'reactwoo-geocore' ); ?></th>
				<td>
					<p>
						<label for="rwgc_fallback_country"><?php esc_html_e( 'Fallback country', 'reactwoo-geocore' ); ?></label><br />
						<?php
						RWGC_Admin::render_country_select(
							$option_key . '[fallback_country]',
							isset( $settings['fallback_country'] ) ? (string) $settings['fallback_country'] : 'US',
							array(
								'id'               => 'rwgc_fallback_country',
								'class'            => 'rwgc-select-country regular-text',
								'show_option_none' => '',
							)
						);
						?>
					</p>
					<p>
						<label for="rwgc_fallback_currency"><?php esc_html_e( 'Fallback currency', 'reactwoo-geocore' ); ?></label><br />
						<?php
						RWGC_Admin::render_currency_select(
							$option_key . '[fallback_currency]',
							isset( $settings['fallback_currency'] ) ? (string) $settings['fallback_currency'] : 'USD',
							array(
								'id'    => 'rwgc_fallback_currency',
								'class' => 'rwgc-select-currency regular-text',
							)
						);
						?>
					</p>
					<p class="description"><?php esc_html_e( 'Used when lookups fail or the database is missing. Values are chosen from prepopulated lists (no manual ISO codes).', 'reactwoo-geocore' ); ?></p>
				</td>
			</tr>
			<tr><th colspan="2"><h2><?php esc_html_e( 'Integrations', 'reactwoo-geocore' ); ?></h2></th></tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'REST API', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[rest_enabled]" value="1" <?php checked( $settings['rest_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Expose REST routes: /location, /capabilities (discovery, no PII), and authenticated AI draft endpoints.', 'reactwoo-geocore' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'The public location and capabilities endpoints do not require a commercial license. AI draft routes require editor capability; ReactWoo API credentials are configured in commercial satellite plugins (e.g. Geo AI), not in Geo Core.', 'reactwoo-geocore' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<details class="rwgc-tech-ref-details">
			<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Advanced', 'reactwoo-geocore' ); ?></summary>
			<table class="form-table" role="presentation">
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
		</details>

		<?php submit_button(); ?>
	</form>
</div>

