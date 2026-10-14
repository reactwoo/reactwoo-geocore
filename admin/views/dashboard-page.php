<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_settings = isset( $rwgc_settings ) && is_array( $rwgc_settings ) ? $rwgc_settings : array();
$rwgc_status   = isset( $rwgc_status ) && is_array( $rwgc_status ) ? $rwgc_status : array();
$rwgc_data     = isset( $rwgc_data ) && is_array( $rwgc_data ) ? $rwgc_data : array();

$rwgc_geo_enabled   = (bool) RWGC_Settings::get( 'enabled', 1 );
$rwgc_maxmind_ok    = ! empty( $rwgc_settings['maxmind_license_key'] );
$rwgc_db_ready      = ! empty( $rwgc_status['exists'] );
$rwgc_rest_enabled  = (bool) RWGC_Settings::get( 'rest_enabled', 1 );
$rwgc_overview_rows = class_exists( 'RWGC_Variant_Manager', false ) ? RWGC_Variant_Manager::get_routing_overview_rows() : array();

$rwgc_stat_geo   = $rwgc_geo_enabled ? __( 'Active', 'reactwoo-geocore' ) : __( 'Needs setup', 'reactwoo-geocore' );
$rwgc_stat_geo_t = __( 'Visitor country detection is running.', 'reactwoo-geocore' );

$rwgc_stat_mm   = $rwgc_db_ready ? __( 'Ready', 'reactwoo-geocore' ) : __( 'Missing', 'reactwoo-geocore' );
$rwgc_stat_mm_t = __( 'MaxMind country database status.', 'reactwoo-geocore' );

$rwgc_stat_db   = isset( $rwgc_status['is_stale'] ) && $rwgc_status['is_stale'] ? __( 'Stale', 'reactwoo-geocore' ) : ( $rwgc_db_ready ? __( 'Ready', 'reactwoo-geocore' ) : __( 'Missing', 'reactwoo-geocore' ) );
$rwgc_stat_db_t = __( 'MaxMind country database status.', 'reactwoo-geocore' );

$rwgc_active_rules_count = is_array( $rwgc_overview_rows ) ? count( $rwgc_overview_rows ) : 0;
/**
 * Filter dashboard active rule count so add-ons can contribute popup/section rules.
 *
 * @param int                      $active_rules_count Default count from Geo Core page-version routing rules.
 * @param array<int, array<string, mixed>> $overview_rows      Routing overview rows used for the default count.
 */
$rwgc_active_rules_count = (int) apply_filters( 'rwgc_dashboard_active_rules_count', $rwgc_active_rules_count, $rwgc_overview_rows );

/**
 * Filter dashboard rule match count so runtime/reporting integrations can supply real totals.
 *
 * @param int $matches_count Default 0 when no reporting source is connected.
 */
$rwgc_rule_matches_count = (int) apply_filters( 'rwgc_dashboard_rule_matches_count', 0 );
$rwgc_stat_rest          = (string) max( 0, $rwgc_rule_matches_count );
$rwgc_stat_rest_t        = __( 'Times Geo Core matched visitor conditions.', 'reactwoo-geocore' );

$rwgc_tone_geo   = $rwgc_geo_enabled ? 'success' : 'warning';
$rwgc_tone_mm    = $rwgc_maxmind_ok ? 'success' : 'warning';
$rwgc_tone_db    = $rwgc_db_ready ? 'success' : 'warning';
$rwgc_tone_rest  = $rwgc_rule_matches_count > 0 ? 'success' : 'neutral';

$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();

if ( $rwgc_platform_shell ) {
	$rwgc_quick_actions = array(
		array(
			'url'     => admin_url( 'admin.php?page=rwgc-getting-started' ),
			'label'   => __( 'Setup wizard', 'reactwoo-geocore' ),
			'primary' => true,
		),
		array(
			'url'     => admin_url( 'admin.php?page=rwgc-targeting-hub' ),
			'label'   => __( 'Targeting', 'reactwoo-geocore' ),
			'primary' => false,
		),
		array(
			'url'     => admin_url( 'admin.php?page=rwgc-integrations-hub' ),
			'label'   => __( 'Integrations', 'reactwoo-geocore' ),
			'primary' => false,
		),
	);
} else {
	$rwgc_quick_actions = array(
		array(
			'url'     => admin_url( 'admin.php?page=rwgc-settings' ),
			'label'   => __( 'Configure Detection', 'reactwoo-geocore' ),
			'primary' => true,
		),
		array(
			'url'     => function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ),
			'label'   => __( 'Test Visitor Context', 'reactwoo-geocore' ),
			'primary' => false,
		),
		array(
			'url'     => admin_url( 'admin.php?page=rwgc-usage' ),
			'label'   => __( 'View Reports', 'reactwoo-geocore' ),
			'primary' => false,
		),
	);
}

if ( class_exists( 'RWGC_Module_Registry', false ) ) {
	$rwgc_quick_actions = RWGC_Module_Registry::append_satellite_quick_actions( $rwgc_quick_actions );
}

/**
 * Filter Geo Core dashboard quick actions (Configure Detection, satellites, etc.).
 *
 * @param array<int, array<string, mixed>> $quick_actions Each item: url, label, primary (bool).
 */
$rwgc_quick_actions = apply_filters( 'rwgc_dashboard_quick_actions', $rwgc_quick_actions );

$rwgc_setup_progress = class_exists( 'RWGC_Onboarding', false )
	? RWGC_Onboarding::get_setup_progress()
	: array();
?>
<div class="wrap rwgc-wrap rwgc-suite<?php echo $rwgc_platform_shell ? ' rwgc-wrap--platform-overview' : ''; ?>">
	<?php
	RWGC_Admin_UI::render_page_header(
		$rwgc_platform_shell
			? __( 'Overview', 'reactwoo-geocore' )
			: __( 'Geo Core', 'reactwoo-geocore' ),
		$rwgc_platform_shell
			? __( 'Health, setup progress, and shortcuts across ReactWoo Geo.', 'reactwoo-geocore' )
			: __( 'Show or hide content by visitor country.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-dashboard' ); ?>

	<?php
	if ( $rwgc_platform_shell && class_exists( 'RWGC_Admin_UI', false ) ) {
		RWGC_Admin_UI::render_platform_overview_panel( $rwgc_setup_progress );
	}
	?>

	<?php if ( ! $rwgc_platform_shell ) : ?>
	<section class="rwgc-suite-hero" aria-labelledby="rwgc-suite-hero-title">
		<h2 id="rwgc-suite-hero-title"><?php esc_html_e( 'Ready to create visitor rules?', 'reactwoo-geocore' ); ?></h2>
		<p><?php esc_html_e( 'Start with a visibility rule that shows or hides content for selected countries.', 'reactwoo-geocore' ); ?></p>
		<div class="rwgc-suite-hero__actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-visibility-rules' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Geo Rule', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button"><?php esc_html_e( 'Configure Detection', 'reactwoo-geocore' ); ?></a>
			<a href="<?php echo esc_url( function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ) ); ?>" class="button"><?php esc_html_e( 'Test Visitor Context', 'reactwoo-geocore' ); ?></a>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! $rwgc_platform_shell ) : ?>
	<?php
	RWGC_Admin_UI::render_stat_grid_open();
	RWGC_Admin_UI::render_stat_card( __( 'Location Detection', 'reactwoo-geocore' ), $rwgc_stat_geo, array( 'hint' => $rwgc_stat_geo_t, 'tone' => $rwgc_tone_geo ) );
	RWGC_Admin_UI::render_stat_card( __( 'Geo Database', 'reactwoo-geocore' ), $rwgc_stat_db, array( 'hint' => $rwgc_stat_db_t, 'tone' => $rwgc_tone_db ) );
	RWGC_Admin_UI::render_stat_card( __( 'Active Rules', 'reactwoo-geocore' ), (string) max( 0, $rwgc_active_rules_count ), array( 'hint' => __( 'Rules currently controlling content or routing.', 'reactwoo-geocore' ), 'tone' => $rwgc_tone_mm ) );
	RWGC_Admin_UI::render_stat_card( __( 'Rule Matches', 'reactwoo-geocore' ), $rwgc_stat_rest, array( 'hint' => $rwgc_stat_rest_t, 'tone' => $rwgc_tone_rest ) );
	RWGC_Admin_UI::render_stat_grid_close();
	?>

	<div class="rwgc-suite-checklist-wrap">
		<h2><?php esc_html_e( 'Setup checklist', 'reactwoo-geocore' ); ?></h2>
		<ul class="rwgc-suite-checklist">
			<?php
			RWGC_Admin_UI::render_checklist_row(
				$rwgc_maxmind_ok,
				__( 'Save MaxMind (GeoLite2) credentials under Integrations.', 'reactwoo-geocore' ),
				function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ),
				__( 'Open MaxMind integration', 'reactwoo-geocore' )
			);
			RWGC_Admin_UI::render_checklist_row(
				$rwgc_db_ready,
				__( 'Download or upload the country database.', 'reactwoo-geocore' ),
				function_exists( 'rwgc_get_maxmind_admin_url' ) ? rwgc_get_maxmind_admin_url() : admin_url( 'admin.php?page=rwgc-integrations-maxmind' ),
				__( 'Open MaxMind integration', 'reactwoo-geocore' )
			);
			?>
		</ul>
	</div>
	<?php endif; ?>

	<h2 class="screen-reader-text"><?php esc_html_e( 'Quick actions', 'reactwoo-geocore' ); ?></h2>
	<?php RWGC_Admin_UI::render_quick_actions( $rwgc_quick_actions ); ?>

	<section class="rwgc-dashboard-satellites">
		<h2><?php esc_html_e( 'Installed add-ons', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Open an add-on to manage its features.', 'reactwoo-geocore' ); ?></p>
		<?php
		if ( has_action( 'rwgc_dashboard_satellite_panels' ) ) {
			echo '<div class="rwgc-suite-satellite-grid" role="region" aria-label="' . esc_attr__( 'Active add-on summaries', 'reactwoo-geocore' ) . '">';
			/**
			 * Extra summary cards on the Geo Core dashboard (active Geo Commerce, Geo AI, Geo Optimise, etc.).
			 *
			 * @since 1.2.2
			 */
			do_action( 'rwgc_dashboard_satellite_panels' );
			echo '</div>';
		}
		?>
		<?php RWGC_Admin_UI::render_satellite_cards(); ?>
	</section>

	<div class="rwgc-addon-card rwgc-addon-card--visitor">
		<div class="rwgc-addon-card__header">
			<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons dashicons-location"></span></div>
			<div class="rwgc-addon-card__heading">
				<h3><?php esc_html_e( 'Current visitor (admin preview)', 'reactwoo-geocore' ); ?></h3>
			</div>
		</div>
		<?php if ( ! empty( $rwgc_data ) ) : ?>
			<div class="rwgc-visitor-stats">
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $rwgc_data['country_code'] . ' – ' . $rwgc_data['country_name'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $rwgc_data['currency'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $rwgc_data['source'] ); ?></strong>
				</div>
			</div>
		<?php else : ?>
			<p class="rwgc-addon-card__empty"><?php esc_html_e( 'No geo sample in this screen yet.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>
	</div>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Technical reference and developer tools', 'reactwoo-geocore' ); ?></summary>

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

	</details>
</div>
