<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_preview_snapshot = isset( $rwgc_preview_snapshot ) && is_array( $rwgc_preview_snapshot ) ? $rwgc_preview_snapshot : array();
$rwgc_preview_url           = admin_url( 'admin.php?page=rwgc-context-preview' );
$rwgc_weather_connected     = (bool) apply_filters( 'rwgc_weather_targets_configured', false );
$rwgc_weather_facet_defs    = class_exists( 'RWGCP_Weather_Facets', false ) ? RWGCP_Weather_Facets::get_definitions() : array();
$rwgc_selected_facets       = array();
if ( isset( $_GET['rwgc_weather_facet'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rwgc_selected_facets = array_map( 'sanitize_key', (array) map_deep( wp_unslash( $_GET['rwgc_weather_facet'] ), 'sanitize_text_field' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
} elseif ( ! empty( $rwgc_preview_snapshot['weather']['facets'] ) && is_array( $rwgc_preview_snapshot['weather']['facets'] ) ) {
	$rwgc_selected_facets = array_map( 'strval', $rwgc_preview_snapshot['weather']['facets'] );
}
$rwgc_preview_weather_facets = isset( $rwgc_preview_snapshot['weather']['facets'] ) && is_array( $rwgc_preview_snapshot['weather']['facets'] )
	? implode( ', ', $rwgc_preview_snapshot['weather']['facets'] )
	: '';
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Visitor test (advanced)', 'reactwoo-geocore' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Use this advanced tool to simulate visitor values. Most users should test the live visitor on Integrations → MaxMind.', 'reactwoo-geocore' ); ?>
	</p>

	<form method="get" action="<?php echo esc_url( $rwgc_preview_url ); ?>" class="rwgc-context-preview-form">
		<input type="hidden" name="page" value="rwgc-context-preview" />
		<input type="hidden" name="rwgc_preview" value="1" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwgc_country"><?php esc_html_e( 'Country (ISO2)', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_country" id="rwgc_country" type="text" class="regular-text" maxlength="2" value="<?php echo isset( $_GET['rwgc_country'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_country'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_currency"><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_currency" id="rwgc_currency" type="text" class="regular-text" maxlength="8" value="<?php echo isset( $_GET['rwgc_currency'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_currency'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_language"><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_language" id="rwgc_language" type="text" class="regular-text" value="<?php echo isset( $_GET['rwgc_language'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_language'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_locale"><?php esc_html_e( 'Locale', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_locale" id="rwgc_locale" type="text" class="regular-text" value="<?php echo isset( $_GET['rwgc_locale'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_locale'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_device_type"><?php esc_html_e( 'Device type', 'reactwoo-geocore' ); ?></label></th>
				<td>
					<select name="rwgc_device_type" id="rwgc_device_type">
						<?php
						$rwgc_cur = isset( $_GET['rwgc_device_type'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rwgc_device_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
						foreach ( array( '', 'mobile', 'tablet', 'desktop' ) as $rwgc_opt ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $rwgc_opt ),
								selected( $rwgc_cur, $rwgc_opt, false ),
								$rwgc_opt ? esc_html( $rwgc_opt ) : esc_html__( '(no override)', 'reactwoo-geocore' )
							);
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_time_of_day"><?php esc_html_e( 'Time of day', 'reactwoo-geocore' ); ?></label></th>
				<td>
					<select name="rwgc_time_of_day" id="rwgc_time_of_day">
						<?php
						$rwgc_cur = isset( $_GET['rwgc_time_of_day'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rwgc_time_of_day'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
						foreach ( array( '', 'morning', 'afternoon', 'evening', 'night' ) as $rwgc_opt ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $rwgc_opt ),
								selected( $rwgc_cur, $rwgc_opt, false ),
								$rwgc_opt ? esc_html( $rwgc_opt ) : esc_html__( '(no override)', 'reactwoo-geocore' )
							);
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_day_of_week"><?php esc_html_e( 'Day of week', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_day_of_week" id="rwgc_day_of_week" type="text" class="regular-text" placeholder="monday" value="<?php echo isset( $_GET['rwgc_day_of_week'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_day_of_week'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
?>" /></td>
			</tr>
			<?php if ( $rwgc_weather_connected && ! empty( $rwgc_weather_facet_defs ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Shopping weather', 'reactwoo-geocore' ); ?></th>
				<td>
					<fieldset class="rwgc-preview-weather-facets">
						<legend class="screen-reader-text"><?php esc_html_e( 'Shopping weather facets', 'reactwoo-geocore' ); ?></legend>
						<?php foreach ( $rwgc_weather_facet_defs as $rwgc_row ) : ?>
							<?php
							if ( ! is_array( $rwgc_row ) || empty( $rwgc_row['slug'] ) ) {
								continue;
							}
							$rwgc_slug = (string) $rwgc_row['slug'];
							?>
							<label style="display:inline-block;margin:0 12px 6px 0;">
								<input type="checkbox" name="rwgc_weather_facet[]" value="<?php echo esc_attr( $rwgc_slug ); ?>" <?php checked( in_array( $rwgc_slug, $rwgc_selected_facets, true ) ); ?> />
								<?php echo esc_html( isset( $rwgc_row['label'] ) ? (string) $rwgc_row['label'] : $rwgc_slug ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Simulates visitor shopping weather for rule and merchandising previews.', 'reactwoo-geocore' ); ?></p>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php submit_button( __( 'Apply preview', 'reactwoo-geocore' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Friendly result', 'reactwoo-geocore' ); ?></h2>
	<p><strong><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_preview_snapshot['country'] ) ? (string) $rwgc_preview_snapshot['country'] : '' ); ?></p>
	<p><strong><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_preview_snapshot['language'] ) ? (string) $rwgc_preview_snapshot['language'] : '' ); ?></p>
	<p><strong><?php esc_html_e( 'Device', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_preview_snapshot['device_type'] ) ? (string) $rwgc_preview_snapshot['device_type'] : '' ); ?></p>
	<?php if ( $rwgc_weather_connected ) : ?>
		<p><strong><?php esc_html_e( 'Shopping weather', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $rwgc_preview_weather_facets ? $rwgc_preview_weather_facets : __( '(none)', 'reactwoo-geocore' ) ); ?></p>
	<?php endif; ?>
	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Developer details', 'reactwoo-geocore' ); ?></summary>
		<pre class="rwgc-code-block" style="max-height:28em;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;"><?php echo esc_html( wp_json_encode( $rwgc_preview_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</details>
</div>
