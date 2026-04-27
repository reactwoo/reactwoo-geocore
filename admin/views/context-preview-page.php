<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_preview_snapshot = isset( $rwgc_preview_snapshot ) && is_array( $rwgc_preview_snapshot ) ? $rwgc_preview_snapshot : array();
$preview_url           = admin_url( 'admin.php?page=rwgc-context-preview' );
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Visitor test (advanced)', 'reactwoo-geocore' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Use this advanced tool to simulate visitor values. Most users should use Tools > Test current visitor.', 'reactwoo-geocore' ); ?>
	</p>

	<form method="get" action="<?php echo esc_url( $preview_url ); ?>" class="rwgc-context-preview-form">
		<input type="hidden" name="page" value="rwgc-context-preview" />
		<input type="hidden" name="rwgc_preview" value="1" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwgc_country"><?php esc_html_e( 'Country (ISO2)', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_country" id="rwgc_country" type="text" class="regular-text" maxlength="2" value="<?php echo isset( $_GET['rwgc_country'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_country'] ) ) ) : ''; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_currency"><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_currency" id="rwgc_currency" type="text" class="regular-text" maxlength="8" value="<?php echo isset( $_GET['rwgc_currency'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_currency'] ) ) ) : ''; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_language"><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_language" id="rwgc_language" type="text" class="regular-text" value="<?php echo isset( $_GET['rwgc_language'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_language'] ) ) ) : ''; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_locale"><?php esc_html_e( 'Locale', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_locale" id="rwgc_locale" type="text" class="regular-text" value="<?php echo isset( $_GET['rwgc_locale'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_locale'] ) ) ) : ''; ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_device_type"><?php esc_html_e( 'Device type', 'reactwoo-geocore' ); ?></label></th>
				<td>
					<select name="rwgc_device_type" id="rwgc_device_type">
						<?php
						$cur = isset( $_GET['rwgc_device_type'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rwgc_device_type'] ) ) : '';
						foreach ( array( '', 'mobile', 'tablet', 'desktop' ) as $opt ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $opt ),
								selected( $cur, $opt, false ),
								$opt ? esc_html( $opt ) : esc_html__( '(no override)', 'reactwoo-geocore' )
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
						$cur = isset( $_GET['rwgc_time_of_day'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rwgc_time_of_day'] ) ) : '';
						foreach ( array( '', 'morning', 'afternoon', 'evening', 'night' ) as $opt ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $opt ),
								selected( $cur, $opt, false ),
								$opt ? esc_html( $opt ) : esc_html__( '(no override)', 'reactwoo-geocore' )
							);
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rwgc_day_of_week"><?php esc_html_e( 'Day of week', 'reactwoo-geocore' ); ?></label></th>
				<td><input name="rwgc_day_of_week" id="rwgc_day_of_week" type="text" class="regular-text" placeholder="monday" value="<?php echo isset( $_GET['rwgc_day_of_week'] ) ? esc_attr( sanitize_text_field( wp_unslash( (string) $_GET['rwgc_day_of_week'] ) ) ) : ''; ?>" /></td>
			</tr>
		</table>
		<?php submit_button( __( 'Apply preview', 'reactwoo-geocore' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Friendly result', 'reactwoo-geocore' ); ?></h2>
	<p><strong><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_preview_snapshot['country'] ) ? (string) $rwgc_preview_snapshot['country'] : '' ); ?></p>
	<p><strong><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_preview_snapshot['language'] ) ? (string) $rwgc_preview_snapshot['language'] : '' ); ?></p>
	<p><strong><?php esc_html_e( 'Device', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( isset( $rwgc_preview_snapshot['device_type'] ) ? (string) $rwgc_preview_snapshot['device_type'] : '' ); ?></p>
	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Developer details', 'reactwoo-geocore' ); ?></summary>
		<pre class="rwgc-code-block" style="max-height:28em;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;"><?php echo esc_html( wp_json_encode( $rwgc_preview_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</details>
</div>
