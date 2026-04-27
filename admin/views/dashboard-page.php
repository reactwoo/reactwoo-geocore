<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = isset( $settings ) && is_array( $settings ) ? $settings : array();
$status   = isset( $status ) && is_array( $status ) ? $status : array();
$data     = isset( $data ) && is_array( $data ) ? $data : array();

$geo_enabled   = (bool) RWGC_Settings::get( 'enabled', 1 );
$maxmind_ok    = ! empty( $settings['maxmind_license_key'] );
$db_ready      = ! empty( $status['exists'] );
$rest_enabled  = (bool) RWGC_Settings::get( 'rest_enabled', 1 );

$stat_geo   = $geo_enabled ? __( 'Active', 'reactwoo-geocore' ) : __( 'Needs setup', 'reactwoo-geocore' );
$stat_geo_t = __( 'Visitor country detection is running.', 'reactwoo-geocore' );

$stat_mm   = $db_ready ? __( 'Ready', 'reactwoo-geocore' ) : __( 'Missing', 'reactwoo-geocore' );
$stat_mm_t = __( 'MaxMind country database status.', 'reactwoo-geocore' );

$stat_db   = isset( $status['is_stale'] ) && $status['is_stale'] ? __( 'Stale', 'reactwoo-geocore' ) : ( $db_ready ? __( 'Ready', 'reactwoo-geocore' ) : __( 'Missing', 'reactwoo-geocore' ) );
$stat_db_t = __( 'MaxMind country database status.', 'reactwoo-geocore' );

$stat_rest   = __( '0', 'reactwoo-geocore' );
$stat_rest_t = __( 'Rule matches (coming from reports data).', 'reactwoo-geocore' );

$tone_geo   = $geo_enabled ? 'success' : 'warning';
$tone_mm    = $maxmind_ok ? 'success' : 'warning';
$tone_db    = $db_ready ? 'success' : 'warning';
$tone_rest  = $rest_enabled ? 'success' : 'neutral';

$quick_actions = array(
	array(
		'url'     => admin_url( 'admin.php?page=rwgc-settings' ),
		'label'   => __( 'Configure Detection', 'reactwoo-geocore' ),
		'primary' => true,
	),
	array(
		'url'     => admin_url( 'admin.php?page=rwgc-tools' ),
		'label'   => __( 'Test Visitor Context', 'reactwoo-geocore' ),
		'primary' => false,
	),
	array(
		'url'     => admin_url( 'admin.php?page=rwgc-usage' ),
		'label'   => __( 'View Reports', 'reactwoo-geocore' ),
		'primary' => false,
	),
);

if ( class_exists( 'RWGA_Plugin', false ) ) {
	$quick_actions[] = array(
		'url'     => admin_url( 'admin.php?page=rwga-dashboard' ),
		'label'   => __( 'Open Geo AI', 'reactwoo-geocore' ),
		'primary' => false,
	);
}
if ( class_exists( 'RWGCM_Plugin', false ) ) {
	$quick_actions[] = array(
		'url'     => admin_url( 'admin.php?page=rwgcm-dashboard' ),
		'label'   => __( 'Open Geo Commerce', 'reactwoo-geocore' ),
		'primary' => false,
	);
}
if ( class_exists( 'RWGO_Plugin', false ) ) {
	$quick_actions[] = array(
		'url'     => admin_url( 'admin.php?page=rwgo-dashboard' ),
		'label'   => __( 'Open Geo Optimise', 'reactwoo-geocore' ),
		'primary' => false,
	);
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Geo Core', 'reactwoo-geocore' ),
		__( 'Control content and page versions by visitor location.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-dashboard' ); ?>

	<section class="rwgc-suite-hero" aria-labelledby="rwgc-suite-hero-title">
		<h2 id="rwgc-suite-hero-title"><?php esc_html_e( 'Ready to create visitor rules?', 'reactwoo-geocore' ); ?></h2>
		<p><?php esc_html_e( 'Start with simple rules to show, hide, redirect, or route page versions by visitor conditions.', 'reactwoo-geocore' ); ?></p>
		<div class="rwgc-suite-hero__actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-suite-variants' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Geo Rule', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button"><?php esc_html_e( 'Configure Detection', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-tools' ) ); ?>" class="button"><?php esc_html_e( 'Test Visitor Context', 'reactwoo-geocore' ); ?></a>
		</div>
	</section>

	<?php
	RWGC_Admin_UI::render_stat_grid_open();
	RWGC_Admin_UI::render_stat_card( __( 'Location Detection', 'reactwoo-geocore' ), $stat_geo, array( 'hint' => $stat_geo_t, 'tone' => $tone_geo ) );
	RWGC_Admin_UI::render_stat_card( __( 'Geo Database', 'reactwoo-geocore' ), $stat_db, array( 'hint' => $stat_db_t, 'tone' => $tone_db ) );
	RWGC_Admin_UI::render_stat_card( __( 'Active Rules', 'reactwoo-geocore' ), __( '0', 'reactwoo-geocore' ), array( 'hint' => __( 'Rules currently controlling content or routing.', 'reactwoo-geocore' ), 'tone' => $tone_mm ) );
	RWGC_Admin_UI::render_stat_card( __( 'Rule Matches', 'reactwoo-geocore' ), $stat_rest, array( 'hint' => $stat_rest_t, 'tone' => $tone_rest ) );
	RWGC_Admin_UI::render_stat_grid_close();
	?>

	<div class="rwgc-suite-checklist-wrap">
		<h2><?php esc_html_e( 'Setup checklist', 'reactwoo-geocore' ); ?></h2>
		<ul class="rwgc-suite-checklist">
			<?php
			RWGC_Admin_UI::render_checklist_row(
				$maxmind_ok,
				__( 'Save MaxMind (GeoLite2) credentials under Settings.', 'reactwoo-geocore' ),
				admin_url( 'admin.php?page=rwgc-settings' ),
				__( 'Open Settings', 'reactwoo-geocore' )
			);
			RWGC_Admin_UI::render_checklist_row(
				$db_ready,
				__( 'Download or upload the country database (Tools).', 'reactwoo-geocore' ),
				admin_url( 'admin.php?page=rwgc-tools' ),
				__( 'Open Tools', 'reactwoo-geocore' )
			);
			RWGC_Admin_UI::render_checklist_row(
				true,
				__( 'Geo Core routing is ready — edit a page to use “Geo Variant Routing (Free)” when needed.', 'reactwoo-geocore' )
			);
			$addons = class_exists( 'RWGC_Upsells', false ) ? RWGC_Upsells::get_addons() : array();
			$any_addon_active = false;
			foreach ( $addons as $ad ) {
				if ( isset( $ad['status'] ) && 'active' === $ad['status'] ) {
					$any_addon_active = true;
					break;
				}
			}
			RWGC_Admin_UI::render_checklist_row(
				$any_addon_active,
				__( 'Install ReactWoo add-ons (GeoElementor, bridges, …) from Add-ons when you need them.', 'reactwoo-geocore' ),
				admin_url( 'admin.php?page=rwgc-addons' ),
				__( 'View add-ons', 'reactwoo-geocore' )
			);
			?>
		</ul>
	</div>

	<h2 class="screen-reader-text"><?php esc_html_e( 'Quick actions', 'reactwoo-geocore' ); ?></h2>
	<?php RWGC_Admin_UI::render_quick_actions( $quick_actions ); ?>

	<h2><?php esc_html_e( 'Installed ReactWoo satellites', 'reactwoo-geocore' ); ?></h2>
	<p class="description"><?php esc_html_e( 'These plugins extend Geo Core. Each keeps its own screens — open one to work on pricing, AI, experiments, or Elementor rules.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin_UI::render_satellite_cards(); ?>

	<div class="rwgc-addon-card-stack" role="region" aria-label="<?php esc_attr_e( 'Add-on summaries', 'reactwoo-geocore' ); ?>">
	<?php
	/** Extra dashboard cards (e.g. Geo Commerce summary when the plugin is active). */
	do_action( 'rwgc_dashboard_satellite_panels' );
	?>
	</div>

	<div class="rwgc-addon-card rwgc-addon-card--visitor">
		<div class="rwgc-addon-card__header">
			<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons dashicons-location"></span></div>
			<div class="rwgc-addon-card__heading">
				<h3><?php esc_html_e( 'Current visitor (admin preview)', 'reactwoo-geocore' ); ?></h3>
			</div>
		</div>
		<?php if ( ! empty( $data ) ) : ?>
			<div class="rwgc-visitor-stats">
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $data['country_code'] . ' – ' . $data['country_name'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $data['currency'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $data['source'] ); ?></strong>
				</div>
			</div>
		<?php else : ?>
			<p class="rwgc-addon-card__empty"><?php esc_html_e( 'No geo sample in this screen yet.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>
	</div>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Technical reference and developer tools', 'reactwoo-geocore' ); ?></summary>

		<div class="rwgc-card rwgc-card--full rwgc-tech-ref-details__inner">
			<h2><?php esc_html_e( 'Feature split', 'reactwoo-geocore' ); ?></h2>
			<table class="rwui-matrix">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Capability', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Geo Core (free baseline)', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'GeoElementor (Pro extension)', 'reactwoo-geocore' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Shared geo engine and MaxMind management', 'reactwoo-geocore' ); ?></td>
						<td><?php esc_html_e( 'Yes', 'reactwoo-geocore' ); ?></td>
						<td><?php esc_html_e( 'Uses Geo Core', 'reactwoo-geocore' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Page-level server-side routing', 'reactwoo-geocore' ); ?></td>
						<td><?php esc_html_e( 'Yes (1 default + 1 additional country)', 'reactwoo-geocore' ); ?></td>
						<td><?php esc_html_e( 'Extends for advanced variants', 'reactwoo-geocore' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Variant groups / advanced mappings', 'reactwoo-geocore' ); ?></td>
						<td><?php esc_html_e( 'No', 'reactwoo-geocore' ); ?></td>
						<td><?php esc_html_e( 'Yes', 'reactwoo-geocore' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Preview & conditional content', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Administrators can simulate another country on the front end:', 'reactwoo-geocore' ); ?> <code>?rwgc_preview_country=GB</code></p>
			<p><strong><?php esc_html_e( 'Shortcode examples', 'reactwoo-geocore' ); ?>:</strong></p>
			<ul class="rwgc-docs-list">
				<li><code>[rwgc_if country="GB,US"]…[/rwgc_if]</code></li>
				<li><code>[rwgc_if exclude="US,CA"]…[/rwgc_if]</code></li>
			</ul>
			<p class="description"><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ); ?>"><?php esc_html_e( 'Full reference in Usage', 'reactwoo-geocore' ); ?></a></p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Free variant routing', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'Server-side routing with a free limit of one default fallback plus one country variant per master page.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Use the page editor’s “Geo Variant Routing (Free)” panel. For multiple variants per page, use GeoElementor.', 'reactwoo-geocore' ); ?></p>
		</div>
	</details>
</div>
