<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! isset( $rwgc_rest_enabled ) ) {
	$rwgc_rest_enabled = class_exists( 'RWGC_Settings', false ) ? (bool) RWGC_Settings::get( 'rest_enabled', 1 ) : true;
}
if ( ! isset( $rwgc_location_url ) ) {
	$rwgc_location_url = function_exists( 'rwgc_get_rest_location_url' ) ? rwgc_get_rest_location_url() : '';
}
if ( ! isset( $rwgc_capabilities_url ) ) {
	$rwgc_capabilities_url = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';
}
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Core Usage Guide', 'reactwoo-geocore' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Use this page as a quick reference to set up and integrate geolocation in themes, plugins, and content.', 'reactwoo-geocore' ); ?></p>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-usage' ); ?>

	<div class="rwgc-grid">
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Where to Configure What (Free vs Pro)', 'reactwoo-geocore' ); ?></h2>
			<div class="rwgc-tipbox">
				<p><?php esc_html_e( 'Geo Core owns the shared geo engine and the free baseline routing behavior. GeoElementor extends it for advanced/pro scenarios.', 'reactwoo-geocore' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Geo Core Settings: MaxMind (GeoLite2) credentials for database downloads, cache, and overall geo engine. No ReactWoo product license is required for core geo.', 'reactwoo-geocore' ); ?></li>
					<li><?php esc_html_e( 'Geo Core Free Routing: Edit a page and use "Geo Variant Routing (Free)" to set 1 default + 1 additional country mapping (server-side redirect).', 'reactwoo-geocore' ); ?></li>
					<li><?php esc_html_e( 'GeoElementor Pro: Configure variant groups and advanced/multi-variant routing in GeoElementor admin.', 'reactwoo-geocore' ); ?></li>
				</ul>
			</div>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Geo Core Settings', 'reactwoo-geocore' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=geo-elementor-variants' ) ); ?>" class="button"><?php esc_html_e( 'GeoElementor Groups', 'reactwoo-geocore' ); ?></a>
			</p>
		</div>

		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Why Geo Core', 'reactwoo-geocore' ); ?></h2>
			<ul>
				<li><?php esc_html_e( 'Keep geo logic in one lightweight plugin used by your whole site.', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Use country code for targeting rules and country name for visitor-facing text.', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Start simple with shortcodes, then move to add-ons for advanced targeting workflows.', 'reactwoo-geocore' ); ?></li>
			</ul>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Quick Start', 'reactwoo-geocore' ); ?></h2>
			<ol class="rwgc-steps">
				<li><?php esc_html_e( 'Open Geo Core > Settings and enable geolocation.', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Add your MaxMind Account ID and MaxMind license key (GeoLite2 — third-party; not a ReactWoo product license).', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Go to Tools and run "Update MaxMind Database".', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Use "Test current lookup" in Tools to verify detected country. Optional: same screen has ReactWoo AI reachability / license tests (AI is optional; core geo does not need a ReactWoo key).', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Add shortcode/block/PHP logic where geo targeting is needed.', 'reactwoo-geocore' ); ?></li>
			</ol>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Open Settings', 'reactwoo-geocore' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-tools' ) ); ?>" class="button"><?php esc_html_e( 'Open Tools', 'reactwoo-geocore' ); ?></a>
			</p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Status Check', 'reactwoo-geocore' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Database available', 'reactwoo-geocore' ); ?>:</strong> <?php echo ! empty( $status['exists'] ) ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'Database stale', 'reactwoo-geocore' ); ?>:</strong> <?php echo ! empty( $status['is_stale'] ) ? esc_html__( 'Possibly', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
			</ul>
			<?php if ( ! empty( $status['last_error'] ) ) : ?>
				<p class="description"><?php esc_html_e( 'Last download error:', 'reactwoo-geocore' ); ?> <code><?php echo esc_html( $status['last_error'] ); ?></code></p>
			<?php endif; ?>
		</div>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Builder Usage', 'reactwoo-geocore' ); ?></h2>
		<table class="widefat striped rwgc-snippet-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Builder', 'reactwoo-geocore' ); ?></th>
					<th><?php esc_html_e( 'How to use Geo Core', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Gutenberg', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'Use the Geo Content block for no-code visibility rules, or use the Shortcode block for shortcode snippets.', 'reactwoo-geocore' ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Elementor / Bricks / Builders', 'reactwoo-geocore' ); ?></td>
					<td><?php esc_html_e( 'Elementor free baseline is document-level only (Page/Popup settings: show/hide + countries). Geo Core also provides page-level server-side routing (1 default + 1 additional country variant). Other builders can use shortcode snippets.', 'reactwoo-geocore' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Page Variant Routing (Free)', 'reactwoo-geocore' ); ?></h2>
		<p><?php esc_html_e( 'Geo Core routes visitors at the page level with server-side redirects. This is designed for performance and stability.', 'reactwoo-geocore' ); ?></p>
		<ol class="rwgc-steps">
			<li><?php esc_html_e( 'Open the default page in wp-admin and find "Geo Variant Routing (Free)".', 'reactwoo-geocore' ); ?></li>
			<li><?php esc_html_e( 'Enable routing and select one default fallback page.', 'reactwoo-geocore' ); ?></li>
			<li><?php esc_html_e( 'Add one country ISO2 code (for example GB) and select its mapped page.', 'reactwoo-geocore' ); ?></li>
			<li><?php esc_html_e( 'Save the page. Visitors from the mapped country go to the mapped page; everyone else uses fallback.', 'reactwoo-geocore' ); ?></li>
		</ol>
		<p class="description"><?php esc_html_e( 'Free limit is strict: 1 fallback + 1 country mapping per page. For multi-country routing and advanced logic, use GeoElementor Pro.', 'reactwoo-geocore' ); ?></p>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Shortcodes', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Prefer the Geo Content block for authoring: it uses searchable country pickers. Shortcode attributes below use comma-separated ISO2 codes for backward compatibility only — not the primary configuration pattern (see master plan §5.1).', 'reactwoo-geocore' ); ?></p>
		<table class="widefat striped rwgc-snippet-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Use case', 'reactwoo-geocore' ); ?></th>
					<th><?php esc_html_e( 'Snippet', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Show visitor country name', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_country]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Show visitor country code (ISO2)', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_country_code]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Show visitor currency (ISO3)', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_currency]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Conditional content by country (include list)', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_if country="US,CA"]Special content[/rwgc_if]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Hide content for specific countries (exclude-only)', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_if exclude="US,CA"]Shown elsewhere[/rwgc_if]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Include + exclude (same rules as variant conditions)', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_if country="DE,AT" exclude="CH"]…[/rwgc_if]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Named country groups (registered under Country Groups)', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_if groups="eu"]…[/rwgc_if]</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Exclude a registered group', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_if groups_exclude="blocked"]…[/rwgc_if]</code></td>
				</tr>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Administrators can append ?rwgc_preview_country=GB to any front URL to test detection (see Dashboard).', 'reactwoo-geocore' ); ?></p>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Country Code Quick List', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use ISO2 codes in targeting rules (for example in rwgc_if country="US,CA").', 'reactwoo-geocore' ); ?></p>
		<table class="widefat striped rwgc-snippet-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></th>
					<th><?php esc_html_e( 'Code', 'reactwoo-geocore' ); ?></th>
					<th><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></th>
					<th><?php esc_html_e( 'Code', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td>United States</td><td><code>US</code></td><td>Canada</td><td><code>CA</code></td></tr>
				<tr><td>United Kingdom</td><td><code>GB</code></td><td>Ireland</td><td><code>IE</code></td></tr>
				<tr><td>Australia</td><td><code>AU</code></td><td>New Zealand</td><td><code>NZ</code></td></tr>
				<tr><td>Germany</td><td><code>DE</code></td><td>France</td><td><code>FR</code></td></tr>
				<tr><td>Spain</td><td><code>ES</code></td><td>Italy</td><td><code>IT</code></td></tr>
				<tr><td>Netherlands</td><td><code>NL</code></td><td>Belgium</td><td><code>BE</code></td></tr>
				<tr><td>Sweden</td><td><code>SE</code></td><td>Norway</td><td><code>NO</code></td></tr>
				<tr><td>Denmark</td><td><code>DK</code></td><td>Switzerland</td><td><code>CH</code></td></tr>
				<tr><td>United Arab Emirates</td><td><code>AE</code></td><td>Saudi Arabia</td><td><code>SA</code></td></tr>
				<tr><td>India</td><td><code>IN</code></td><td>Singapore</td><td><code>SG</code></td></tr>
			</tbody>
		</table>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Real-World Usage Examples', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Content placed between opening and closing rwgc_if shortcodes is shown only when conditions match (include/exclude lists use the same ISO2 rules as the engine).', 'reactwoo-geocore' ); ?></p>
		<pre><code><?php echo esc_html( "[rwgc_if country=\"US,CA\"]\nFree shipping for North America this week.\n[/rwgc_if]" ); ?></code></pre>
		<pre><code><?php echo esc_html( "[rwgc_if country=\"GB,IE\"]\nPrices shown include VAT.\n[/rwgc_if]" ); ?></code></pre>
		<pre><code><?php echo esc_html( "[rwgc_if exclude=\"US\"]\nInternational shipping message.\n[/rwgc_if]" ); ?></code></pre>
		<pre><code><?php echo esc_html( "Welcome visitor from [rwgc_country] ([rwgc_country_code]).\nYour default currency is [rwgc_currency]." ); ?></code></pre>
	</div>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'PHP Integration', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Use these helpers in your theme or custom plugin.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Preview: append ?rwgc_preview_country=GB to the URL while logged in as an administrator (filter rwgc_can_preview_geo).', 'reactwoo-geocore' ); ?></p>
			<pre><code><?php echo esc_html( "if ( function_exists( 'rwgc_get_visitor_country' ) ) {\n    \$country = rwgc_get_visitor_country(); // e.g. US\n    \$currency = rwgc_get_visitor_currency(); // e.g. USD\n}" ); ?></code></pre>
			<pre><code><?php echo esc_html( "if ( rwgc_get_visitor_country() === 'GB' ) {\n    echo 'GBP-only message';\n}" ); ?></code></pre>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'REST API', 'reactwoo-geocore' ); ?></h2>
			<?php if ( ! empty( $rwgc_rest_enabled ) ) : ?>
				<p class="description"><?php esc_html_e( 'Visitor location (public when REST is enabled):', 'reactwoo-geocore' ); ?></p>
				<p><code><?php echo ! empty( $rwgc_location_url ) ? esc_html( $rwgc_location_url ) : esc_html( rest_url( 'reactwoo-geocore/v1/location' ) ); ?></code></p>
				<p class="description"><?php esc_html_e( 'Discovery — plugin_slug, text_domain, version, geo_ready, woocommerce_active, event_types, hooks, satellites (Geo AI / Optimise / Commerce ready + version), integration filter/action names (no visitor PII):', 'reactwoo-geocore' ); ?></p>
				<p><code><?php echo ! empty( $rwgc_capabilities_url ) ? esc_html( $rwgc_capabilities_url ) : esc_html__( '(enable REST in Settings)', 'reactwoo-geocore' ); ?></code></p>
				<p class="description"><?php esc_html_e( 'PHP: rwgc_is_geo_core_active(), rwgc_get_rest_location_url(), rwgc_get_rest_capabilities_url(), rwgc_get_rest_v1_url( $endpoint ), rwgc_get_geo_event_types(), rwgc_is_woocommerce_active(). Constants: RWGC_PLUGIN_SLUG, RWGC_TEXT_DOMAIN.', 'reactwoo-geocore' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'REST routes are turned off. Enable “REST API” in Geo Core → Settings to expose /location and /capabilities.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'The location and capabilities endpoints do not require a ReactWoo product license. Optional AI draft endpoints use editor capability plus ReactWoo API credentials when configured.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Example location payload fields: ip, country_code, country_name, city, region, currency, source, cached.', 'reactwoo-geocore' ); ?></p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Gutenberg Block', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'Use the "Geo Content" block to show/hide blocks by visitor country without code.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Tip: set Show Countries for allow-lists and Hide Countries for exclusions.', 'reactwoo-geocore' ); ?></p>
		</div>
	</div>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Events & hooks (developers)', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Structured geo events for analytics and experiments: action and filter rwgc_geo_event (payload array); action rwgc_route_variant_resolved after route decisions; event_type route_redirect before a server-side variant redirect (filter rwgc_emit_route_redirect_event to disable). PHP helpers: rwgc_get_geo_event_types(), rwgc_get_rest_location_url(), rwgc_get_rest_capabilities_url(). See REST API above. Full detail: docs/phases/phase-6.md in the plugin.', 'reactwoo-geocore' ); ?></p>
	</div>
</div>
