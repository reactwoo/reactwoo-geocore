<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_target_types = isset( $rwgc_target_types ) && is_array( $rwgc_target_types ) ? $rwgc_target_types : array();
$rwgc_provider_rows = isset( $rwgc_provider_rows ) && is_array( $rwgc_provider_rows ) ? $rwgc_provider_rows : array();
$rwgc_pro_enabled   = ! empty( $rwgc_pro_enabled );
$rwgc_portable_ctx  = isset( $rwgc_portable_ctx ) && is_array( $rwgc_portable_ctx ) ? $rwgc_portable_ctx : array(
	'pro'         => false,
	'audiences'   => array(),
	'campaigns'   => array(),
	'ui_surfaces' => array(),
);
$rwgc_use_platform_shell = ! empty( $rwgc_use_platform_shell );
$rwgc_audiences               = isset( $rwgc_portable_ctx['audiences'] ) && is_array( $rwgc_portable_ctx['audiences'] ) ? $rwgc_portable_ctx['audiences'] : array();
$rwgc_campaigns               = isset( $rwgc_portable_ctx['campaigns'] ) && is_array( $rwgc_portable_ctx['campaigns'] ) ? $rwgc_portable_ctx['campaigns'] : array();
$rwgc_help                    = isset( $rwgc_portable_ctx['help_urls'] ) && is_array( $rwgc_portable_ctx['help_urls'] ) ? $rwgc_portable_ctx['help_urls'] : array();

$rwgc_quick_actions = array(
	array(
		'url'     => function_exists( 'rwgc_app_url' ) ? rwgc_app_url( 'targeting', 'rwgc-suite-variants' ) : admin_url( 'admin.php?page=rwgc-suite-variants' ),
		'label'   => __( 'Variants', 'reactwoo-geocore' ),
		'primary' => true,
	),
);
if ( defined( 'RWGO_VERSION' ) ) {
	$rwgc_quick_actions[] = array(
		'url'   => admin_url( 'admin.php?page=rwgo-dashboard' ),
		'label' => __( 'Geo Optimise experiments', 'reactwoo-geocore' ),
	);
}
if ( $rwgc_pro_enabled && ! empty( $rwgc_help['integrations_ga'] ) ) {
	$rwgc_quick_actions[] = array(
		'url'   => (string) $rwgc_help['integrations_ga'],
		'label' => __( 'Integrations', 'reactwoo-geocore' ),
	);
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			$rwgc_use_platform_shell
				? __( 'Rule builder', 'reactwoo-geocore' )
				: __( 'Experience targeting', 'reactwoo-geocore' ),
			__( 'Build who sees what using one rule engine. Elementor, blocks, and shortcodes share the same conditions.', 'reactwoo-geocore' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Experience targeting', 'reactwoo-geocore' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Build who sees what using one rule engine.', 'reactwoo-geocore' ); ?></p>
	<?php endif; ?>

	<?php if ( ! $rwgc_use_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-target-types' ); ?>
	<?php endif; ?>

	<?php if ( function_exists( 'rwgc_is_ready' ) && ! rwgc_is_ready() && function_exists( 'rwgc_get_maxmind_admin_url' ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Country targeting needs a MaxMind GeoLite2 database. Add credentials and download or upload the database under Integrations → MaxMind.', 'reactwoo-geocore' ); ?>
				<a class="button button-secondary" href="<?php echo esc_url( rwgc_get_maxmind_admin_url() ); ?>" style="margin-left:8px;">
					<?php esc_html_e( 'Open MaxMind integration', 'reactwoo-geocore' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php RWGC_Admin_UI::render_quick_actions( $rwgc_quick_actions ); ?>
	<?php endif; ?>

	<div class="rwgc-grid">
		<div class="rwgc-card"><h2><?php esc_html_e( 'Location', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Country and country groups.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Language', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Browser language and locale.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Device', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Desktop, mobile, or tablet.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Time', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'Day and time windows.', 'reactwoo-geocore' ); ?></p></div>
		<div class="rwgc-card"><h2><?php esc_html_e( 'Request', 'reactwoo-geocore' ); ?></h2><p><?php esc_html_e( 'UTM parameters and click IDs from the current request.', 'reactwoo-geocore' ); ?></p></div>
	</div>

	<div class="rwgc-card rwgc-card--full rwgc-rb-playground-card">
		<h2><?php esc_html_e( 'Visibility rule builder', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Define who should see content with visual conditions (AND/OR). Raw JSON is available only under Advanced in Elementor, blocks, and geo rules — not the primary workflow.', 'reactwoo-geocore' ); ?></p>

		<?php if ( $rwgc_pro_enabled ) : ?>
		<div class="rwgc-rb-sync-status">
			<div class="rwgc-rb-sync-status__item">
				<strong><?php esc_html_e( 'GA4 audiences', 'reactwoo-geocore' ); ?></strong>
				<span class="rwgc-rb__pill rwgc-rb__pill--ga4"><?php esc_html_e( 'GA4', 'reactwoo-geocore' ); ?></span>
				<p class="description">
					<?php
					if ( count( $rwgc_audiences ) > 0 ) {
						echo esc_html(
							sprintf(
								/* translators: %d: number of GA4 audiences. */
								_n( '%d audience available', '%d audiences available', count( $rwgc_audiences ), 'reactwoo-geocore' ),
								count( $rwgc_audiences )
							)
						);
					} else {
						esc_html_e( 'No audiences synced yet.', 'reactwoo-geocore' );
					}
					?>
				</p>
			</div>
			<div class="rwgc-rb-sync-status__item">
				<strong><?php esc_html_e( 'Google Ads campaigns', 'reactwoo-geocore' ); ?></strong>
				<span class="rwgc-rb__pill rwgc-rb__pill--ads"><?php esc_html_e( 'Google Ads', 'reactwoo-geocore' ); ?></span>
				<p class="description">
					<?php
					if ( count( $rwgc_campaigns ) > 0 ) {
						echo esc_html(
							sprintf(
								/* translators: %d: number of Google Ads campaigns. */
								_n( '%d campaign available', '%d campaigns available', count( $rwgc_campaigns ), 'reactwoo-geocore' ),
								count( $rwgc_campaigns )
							)
						);
					} else {
						esc_html_e( 'No campaigns synced yet.', 'reactwoo-geocore' );
					}
					?>
				</p>
			</div>
		</div>
		<?php endif; ?>

		<p class="description"><?php esc_html_e( 'Where to use saved rules:', 'reactwoo-geocore' ); ?></p>
		<ul class="ul-disc" style="margin-left:1.25em;">
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['elementor'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['elementor'] ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['block'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['block'] ); ?></li>
			<?php endif; ?>
			<?php if ( ! empty( $rwgc_portable_ctx['ui_surfaces']['geo_rule'] ) ) : ?>
				<li><?php echo esc_html( (string) $rwgc_portable_ctx['ui_surfaces']['geo_rule'] ); ?></li>
			<?php endif; ?>
		</ul>

		<div class="rwgc-rb-playground-wrap">
			<textarea id="rwgc-targeting-playground-json" name="rwgc_targeting_playground_json" rows="4" class="large-text" aria-hidden="true"></textarea>
		</div>
	</div>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Advanced: Provider status and target catalog', 'reactwoo-geocore' ); ?></summary>
		<div class="rwgc-tech-ref-details__inner">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Provider', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'State', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'reactwoo-geocore' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rwgc_provider_rows as $rwgc_row ) : ?>
					<tr>
						<td><?php echo esc_html( isset( $rwgc_row['label'] ) ? (string) $rwgc_row['label'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $rwgc_row['state'] ) ? (string) $rwgc_row['state'] : '' ); ?></td>
						<td><?php echo esc_html( isset( $rwgc_row['detail'] ) ? (string) $rwgc_row['detail'] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description"><?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of registered targeting entries. */
						__( 'Registered targeting entries: %d', 'reactwoo-geocore' ),
						count( $rwgc_target_types )
					)
				);
			?></p>
		</div>
	</details>
</div>
