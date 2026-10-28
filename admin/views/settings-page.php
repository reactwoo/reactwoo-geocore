<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_option_key = RWGC_Settings::OPTION_KEY;
$rwgc_wporg = function_exists( 'rwgc_is_wordpress_org_distribution' ) && rwgc_is_wordpress_org_distribution();
?>
<div class="wrap rwgc-wrap rwgc-settings-screen">
	<h1><?php esc_html_e( 'Geo Core Settings', 'reactwoo-geocore' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Configure detection, database, behavior, and integrations.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-settings' ); ?>
	<?php settings_errors( 'rwgc_settings' ); ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'rwgc_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<tr><th colspan="2"><h2><?php esc_html_e( 'Detection', 'reactwoo-geocore' ); ?></h2></th></tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable geolocation', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $rwgc_option_key ); ?>[enabled]" value="1" <?php checked( $rwgc_settings['enabled'], 1 ); ?> />
						<?php esc_html_e( 'Resolve visitor location and expose helper functions.', 'reactwoo-geocore' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'QUIC.cloud', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $rwgc_option_key ); ?>[quic_cloud_cdn]" value="1" <?php checked( (int) $rwgc_settings['quic_cloud_cdn'], 1 ); ?> />
						<?php esc_html_e( 'My site uses QUIC.cloud CDN', 'reactwoo-geocore' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Turn this on only if this site is served through QUIC.cloud. Geo Core then reads the visitor IP from the forwarding header when the connection comes from a QUIC.cloud server. A direct visitor still cannot change their country by sending that header.', 'reactwoo-geocore' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cloudflare', 'reactwoo-geocore' ); ?></th>
				<td>
					<?php
					$rwgc_cf_mode = class_exists( 'RWGC_GeoIP', false ) ? RWGC_GeoIP::cloudflare_mode() : 'off';
					$rwgc_cf_on   = in_array( $rwgc_cf_mode, array( 'on', 'legacy' ), true );
					?>
					<input type="hidden" name="<?php echo esc_attr( $rwgc_option_key ); ?>[behind_cloudflare]" value="0" />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $rwgc_option_key ); ?>[behind_cloudflare]" value="1" <?php checked( $rwgc_cf_on ); ?> />
						<?php esc_html_e( 'Behind Cloudflare', 'reactwoo-geocore' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Turn this on only if this site is served through Cloudflare. Geo Core then uses CF-Connecting-IP and CF-IPCountry when the connection comes from a published Cloudflare address. A direct visitor still cannot change their country by sending those headers.', 'reactwoo-geocore' ); ?>
					</p>
					<?php if ( 'legacy' === $rwgc_cf_mode ) : ?>
						<p class="description">
							<?php esc_html_e( 'This box stays on until you save, because an older version trusted Cloudflare whenever the connection came from Cloudflare. Saving confirms that choice.', 'reactwoo-geocore' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_trusted_proxies"><?php esc_html_e( 'Trusted proxies', 'reactwoo-geocore' ); ?></label></th>
				<td>
					<textarea id="rwgc_trusted_proxies" name="<?php echo esc_attr( $rwgc_option_key ); ?>[trusted_proxies]" rows="6" cols="40" class="large-text code" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"><?php echo esc_textarea( isset( $rwgc_settings['trusted_proxies'] ) ? (string) $rwgc_settings['trusted_proxies'] : '' ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'IP addresses or CIDR ranges, one per line. Geo Core reads forwarding headers only when the connection comes from one of these, from QUIC.cloud while that option is on, or from Cloudflare while Behind Cloudflare is on. The visitor address is the rightmost forwarding hop that is not itself a trusted proxy. Add 127.0.0.1 only when a reverse proxy on this server sets that header.', 'reactwoo-geocore' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cache results', 'reactwoo-geocore' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $rwgc_option_key ); ?>[cache_enabled]" value="1" <?php checked( $rwgc_settings['cache_enabled'], 1 ); ?> />
						<?php esc_html_e( 'Cache geo lookups per IP to improve performance.', 'reactwoo-geocore' ); ?>
					</label>
					<p class="description">
						<label>
							<?php esc_html_e( 'Cache TTL (seconds)', 'reactwoo-geocore' ); ?>
							<input type="number" name="<?php echo esc_attr( $rwgc_option_key ); ?>[cache_ttl]" value="<?php echo esc_attr( (int) $rwgc_settings['cache_ttl'] ); ?>" min="60" step="60" />
						</label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'MaxMind (GeoLite2)', 'reactwoo-geocore' ); ?></th>
				<td>
					<p class="description">
						<?php esc_html_e( 'Credentials, database download, upload, and visitor detection tests live under Integrations.', 'reactwoo-geocore' ); ?>
					</p>
					<p>
						<a class="button button-secondary" href="<?php echo esc_url( function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ) ); ?>">
							<?php esc_html_e( 'Open MaxMind integration', 'reactwoo-geocore' ); ?>
						</a>
					</p>
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
							$rwgc_option_key . '[fallback_country]',
							isset( $rwgc_settings['fallback_country'] ) ? (string) $rwgc_settings['fallback_country'] : 'US',
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
							$rwgc_option_key . '[fallback_currency]',
							isset( $rwgc_settings['fallback_currency'] ) ? (string) $rwgc_settings['fallback_currency'] : 'USD',
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
						<input type="checkbox" name="<?php echo esc_attr( $rwgc_option_key ); ?>[rest_enabled]" value="1" <?php checked( $rwgc_settings['rest_enabled'], 1 ); ?> />
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
							<input type="checkbox" name="<?php echo esc_attr( $rwgc_option_key ); ?>[debug_mode]" value="1" <?php checked( $rwgc_settings['debug_mode'], 1 ); ?> />
							<?php
							if ( $rwgc_wporg ) {
								esc_html_e( 'Log geo diagnostics to debug.log.', 'reactwoo-geocore' );
							} else {
								esc_html_e( 'Log geo and plugin updater diagnostics to debug.log.', 'reactwoo-geocore' );
							}
							?>
						</label>
					</td>
				</tr>
				<?php if ( ! $rwgc_wporg ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Plugin updates', 'reactwoo-geocore' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'This ReactWoo release checks api.reactwoo.com for new Geo Core releases. WordPress may cache “no update” for several hours after a failed check. Commercial add-ons update themselves. The WordPress.org build of Geo Core does not include this check.', 'reactwoo-geocore' ); ?></p>
						<p>
							<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwgc-settings&rwgc_force_plugin_updates=1' ), 'rwgc_force_plugin_updates' ) ); ?>">
								<?php esc_html_e( 'Clear update cache & check now', 'reactwoo-geocore' ); ?>
							</a>
						</p>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</details>

		<?php submit_button(); ?>
	</form>
</div>

