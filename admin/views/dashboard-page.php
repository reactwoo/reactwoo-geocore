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

$stat_geo   = $geo_enabled ? __( 'On', 'reactwoo-geocore' ) : __( 'Off', 'reactwoo-geocore' );
$stat_geo_t = $geo_enabled ? __( 'Visitor geo is active', 'reactwoo-geocore' ) : __( 'Enable geo in Settings', 'reactwoo-geocore' );

$stat_mm   = $maxmind_ok ? __( 'Configured', 'reactwoo-geocore' ) : __( 'Missing', 'reactwoo-geocore' );
$stat_mm_t = $maxmind_ok ? __( 'GeoLite2 credentials saved', 'reactwoo-geocore' ) : __( 'Add MaxMind keys in Settings', 'reactwoo-geocore' );

$stat_db   = $db_ready ? __( 'Ready', 'reactwoo-geocore' ) : __( 'Missing', 'reactwoo-geocore' );
$stat_db_t = $db_ready ? __( 'Country database on disk', 'reactwoo-geocore' ) : __( 'Download from Tools', 'reactwoo-geocore' );

$stat_rest   = $rest_enabled ? __( 'On', 'reactwoo-geocore' ) : __( 'Off', 'reactwoo-geocore' );
$stat_rest_t = $rest_enabled ? __( 'REST discovery & location routes', 'reactwoo-geocore' ) : __( 'Enable in Settings for integrations', 'reactwoo-geocore' );

$tone_geo   = $geo_enabled ? 'success' : 'warning';
$tone_mm    = $maxmind_ok ? 'success' : 'warning';
$tone_db    = $db_ready ? 'success' : 'warning';
$tone_rest  = $rest_enabled ? 'success' : 'neutral';

$quick_actions = array(
	array(
		'url'     => admin_url( 'admin.php?page=rwgc-settings' ),
		'label'   => __( 'Configure geo detection', 'reactwoo-geocore' ),
		'primary' => true,
	),
	array(
		'url'     => admin_url( 'admin.php?page=rwgc-tools' ),
		'label'   => __( 'Database & cache tools', 'reactwoo-geocore' ),
		'primary' => false,
	),
	array(
		'url'     => admin_url( 'admin.php?page=rwgc-addons' ),
		'label'   => __( 'Explore add-ons', 'reactwoo-geocore' ),
		'primary' => false,
	),
	array(
		'url'     => admin_url( 'admin.php?page=rwgc-usage' ),
		'label'   => __( 'Usage guide', 'reactwoo-geocore' ),
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
		__( 'ReactWoo Geo Suite', 'reactwoo-geocore' ),
		__( 'Geo Core is the shared engine for country detection, routing, and integrations. Use this dashboard for setup status, then open each add-on for its own workflows.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-dashboard' ); ?>

	<section class="rwgc-suite-hero" aria-labelledby="rwgc-suite-hero-title">
		<h2 id="rwgc-suite-hero-title"><?php esc_html_e( 'Welcome to Geo Core', 'reactwoo-geocore' ); ?></h2>
		<p><?php esc_html_e( 'You get accurate visitor country data, optional REST endpoints for your stack, and free page-level routing. Premium ReactWoo plugins extend this foundation — they stay separate products but share this engine.', 'reactwoo-geocore' ); ?></p>
		<div class="rwgc-suite-hero__actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Configure detection', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>" class="button"><?php esc_html_e( 'Pages & routing', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>" class="button"><?php esc_html_e( 'Browse add-ons', 'reactwoo-geocore' ); ?></a>
		</div>
	</section>

	<?php
	RWGC_Admin_UI::render_stat_grid_open();
	RWGC_Admin_UI::render_stat_card( __( 'Geo detection', 'reactwoo-geocore' ), $stat_geo, array( 'hint' => $stat_geo_t, 'tone' => $tone_geo ) );
	RWGC_Admin_UI::render_stat_card( __( 'MaxMind (GeoLite2)', 'reactwoo-geocore' ), $stat_mm, array( 'hint' => $stat_mm_t, 'tone' => $tone_mm ) );
	RWGC_Admin_UI::render_stat_card( __( 'IP database', 'reactwoo-geocore' ), $stat_db, array( 'hint' => $stat_db_t, 'tone' => $tone_db ) );
	RWGC_Admin_UI::render_stat_card( __( 'REST API', 'reactwoo-geocore' ), $stat_rest, array( 'hint' => $stat_rest_t, 'tone' => $tone_rest ) );
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

	<?php
	/** Extra dashboard cards (e.g. Geo Commerce summary when the plugin is active). */
	do_action( 'rwgc_dashboard_satellite_panels' );
	?>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Current visitor (admin preview)', 'reactwoo-geocore' ); ?></h2>
			<?php if ( ! empty( $data ) ) : ?>
				<ul>
					<li><strong><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['country_code'] . ' – ' . $data['country_name'] ); ?></li>
					<li><strong><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['currency'] ); ?></li>
					<li><strong><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['source'] ); ?></li>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No geo sample in this screen yet.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<details class="rwgc-suite-details">
		<summary><?php esc_html_e( 'Technical reference (shortcodes, routing limits, feature matrix)', 'reactwoo-geocore' ); ?></summary>

		<div class="rwgc-card rwgc-card--full" style="margin-top:12px;">
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
