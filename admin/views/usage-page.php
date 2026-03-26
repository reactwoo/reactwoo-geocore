<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
					<li><?php esc_html_e( 'Geo Core Settings: MaxMind license, cache, and overall geo engine.', 'reactwoo-geocore' ); ?></li>
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
				<li><?php esc_html_e( 'Add your MaxMind Account ID and License Key.', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Go to Tools and run "Update MaxMind Database".', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Use "Test current lookup" in Tools to verify detected country.', 'reactwoo-geocore' ); ?></li>
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
					<td><?php esc_html_e( 'Conditional content by country', 'reactwoo-geocore' ); ?></td>
					<td><code>[rwgc_if country="US,CA"]Special content[/rwgc_if]</code></td>
				</tr>
			</tbody>
		</table>
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
		<p class="description"><?php esc_html_e( 'Content placed between opening and closing rwgc_if shortcodes is shown only for matching countries.', 'reactwoo-geocore' ); ?></p>
		<pre><code><?php echo esc_html( "[rwgc_if country=\"US,CA\"]\nFree shipping for North America this week.\n[/rwgc_if]" ); ?></code></pre>
		<pre><code><?php echo esc_html( "[rwgc_if country=\"GB,IE\"]\nPrices shown include VAT.\n[/rwgc_if]" ); ?></code></pre>
		<pre><code><?php echo esc_html( "Welcome visitor from [rwgc_country] ([rwgc_country_code]).\nYour default currency is [rwgc_currency]." ); ?></code></pre>
	</div>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'PHP Integration', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Use these helpers in your theme or custom plugin.', 'reactwoo-geocore' ); ?></p>
			<pre><code><?php echo esc_html( "if ( function_exists( 'rwgc_get_visitor_country' ) ) {\n    \$country = rwgc_get_visitor_country(); // e.g. US\n    \$currency = rwgc_get_visitor_currency(); // e.g. USD\n}" ); ?></code></pre>
			<pre><code><?php echo esc_html( "if ( rwgc_get_visitor_country() === 'GB' ) {\n    echo 'GBP-only message';\n}" ); ?></code></pre>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'REST API', 'reactwoo-geocore' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Endpoint (when enabled):', 'reactwoo-geocore' ); ?></p>
			<code><?php echo esc_html( rest_url( 'reactwoo-geocore/v1/location' ) ); ?></code>
			<p class="description"><?php esc_html_e( 'Example payload fields: ip, country_code, country_name, city, region, currency, source, cached.', 'reactwoo-geocore' ); ?></p>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Gutenberg Block', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'Use the "Geo Content" block to show/hide blocks by visitor country without code.', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Tip: set Show Countries for allow-lists and Hide Countries for exclusions.', 'reactwoo-geocore' ); ?></p>
		</div>
	</div>
</div>
