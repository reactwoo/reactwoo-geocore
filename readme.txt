=== ReactWoo Geo Core ===
Contributors: reactwoo
Tags: geo, geolocation, country, location, block
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detect a visitor’s country with your MaxMind database, then show content with shortcodes, a block, and rules.

== Description ==

ReactWoo Geo Core detects a visitor’s country on your server and lets you use that result in content, rules, and page routing. Country lookup uses a GeoLite2 Country database that you download with your own MaxMind account. No ReactWoo account is required for the features below.

Included:

* Country detection from the GeoLite2 Country database stored on your site
* Download or upload that database from the MaxMind screen
* Shortcodes for country, country code, city, region, and currency
* A conditional shortcode for showing content to selected countries
* A REST location endpoint for front-end code, and a capabilities endpoint for discovery
* The Geo Content block for the block editor
* Page routing with one default page and one country-specific page
* Visibility rules for country, country group, language, locale, device, time of day, day of week, logged-in state, new and returning visitors, page type, URL, and request parameters (`utm_source`, `utm_medium`, `utm_campaign`, `source`, `medium`, `campaign`, `content`, `term`, `gclid`)
* PHP helpers for themes and other plugins

Optional add-ons are separate plugins. Geo Core keeps working when they are not installed. GeoCore Pro adds audience, weather, and visitor-profile conditions. Other ReactWoo plugins, including GeoElementor, Geo Commerce, and WHMCS Bridge, can use this plugin as their country source. Those products update themselves.

== Installation ==

**No Composer, SSH, or WP-CLI is required on your server.** Geo Core ships with bundled PHP libraries under `vendor/` (MaxMind / GeoIP2). You only upload or update the plugin like any other WordPress plugin.

1. Upload the plugin files to the `/wp-content/plugins/reactwoo-geocore` directory, or install via WordPress plugin upload.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Geo → Integrations → MaxMind (GeoLite2)** and enter your **MaxMind** account credentials. This is a third-party MaxMind license for downloading the database — not a ReactWoo product license. Core geo works without any ReactWoo key.
4. Download or upload the country database and test visitor detection on the same screen.

== Usage ==

After setup, you can use Geo Core in multiple ways:

* **Shortcodes**: `[rwgc_country]`, `[rwgc_country_code]`, `[rwgc_currency]`, `[rwgc_city]`, `[rwgc_region]`
* **Conditional shortcode**: `[rwgc_if country="US,CA"]Special content[/rwgc_if]`
* **PHP helpers**: `rwgc_get_visitor_country()`, `rwgc_get_visitor_currency()`, `rwgc_get_visitor_data()`
* **REST endpoints** (when enabled in settings): `/wp-json/reactwoo-geocore/v1/location` (visitor geo), `/wp-json/reactwoo-geocore/v1/capabilities` (plugin discovery: event types and hooks; no visitor PII)
* **Gutenberg**: Use the **Geo Content** block to show/hide content by country
* **Elementor (free baseline)**: Use Page/Popup document settings for basic show/hide by country. Classic widgets/sections use **Advanced → Geo Visibility**; Elementor Atomic (V4) widgets use a sibling **Geo Visibility** Settings section (ISO country codes + Pro saved rules).
* **Page Variant Routing (free)**: Edit any page and use "Geo Variant Routing (Free)" to set page role (Master/Secondary) with server-side redirect mapping (1 secondary country mapping per master)

For an in-dashboard guide, open **Geo Core → Usage** in wp-admin.

Country targeting uses ISO2 **country codes** (example: `US`, `CA`, `GB`) because they are stable and reliable for logic. Use country **names** for display text to visitors.

Example conditional content:

`[rwgc_if country="US,CA"]Free shipping for North America[/rwgc_if]`

== Frequently Asked Questions ==

= Does this plugin require Elementor? =

No. ReactWoo Geo Core works with any theme and editor. It exposes helper functions, shortcodes, a REST endpoint, and a Gutenberg block.

= Does this plugin include MaxMind? =

No. You must provide your own MaxMind license key and accept their terms of use. The plugin then downloads the GeoLite2 Country database to your site.

= What is included without another plugin? =

Country detection, the shortcodes, the Geo Content block, the REST location endpoint, page routing, and the visibility-rule conditions listed in the description. Request parameters such as `utm_source` and `gclid` are included. You do not need a ReactWoo account for those features.

= Do I need an add-on? =

No. Audience, weather, and visitor-profile conditions are provided by the separate GeoCore Pro plugin. GeoElementor, Geo Commerce, and WHMCS Bridge are also separate plugins. Install one only if you want that product. Geo Core does not hide or limit the included features when an add-on is absent.

= Does Geo Core require a ReactWoo product license? =

No. Detection, shortcodes, the block, page routing, visibility rules, and the public REST location endpoint work without a ReactWoo key. A license key is used only when a separately installed add-on turns on an optional AI tool that calls the ReactWoo API.

= Which PHP version is required? =

PHP 8.1 or newer, and WordPress 6.2 or newer. The MaxMind libraries included with Geo Core require PHP 8.1.

= Does the server need Composer or SSH? =

**No.** All required PHP packages are included in the plugin’s `vendor/` directory. You should never need to run `composer install` on a customer site. (Composer is used only when **we** build the release zip in development or CI.)

= Does this plugin require WooCommerce? =

No. Geo Core runs without WooCommerce. The optional **Geo Commerce** product (separate plugin) adds Woo-specific overlays and uses `rwgc_is_woocommerce_active()` / the REST `woocommerce_active` field for discovery.

= Where do plugin updates come from? =

WordPress.org installations of Geo Core update from WordPress.org. That build does not contact api.reactwoo.com for updates, and it does not update other plugins. The separate ReactWoo release channel (for existing customers) checks api.reactwoo.com for Geo Core only. Commercial add-ons update themselves.

= Does Geo Core send visitor analytics? =

Not unless an administrator opts in on the ReactWoo Cloud screen. That choice is off by default. See External services.

== External services ==

Geo Core runs country detection on your server. It does not call ReactWoo while a visitor page is rendering. The services below are contacted only in the cases described.

= MaxMind GeoLite2 =

An administrator can download the GeoLite2 Country database from Integrations → MaxMind (GeoLite2).

* Service: `https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz`
* Sent: the MaxMind account ID and license key, as HTTP Basic authentication. This plugin does not send the visitor IP address to MaxMind. Country lookups use the database file stored on your server.
* After that: MaxMind redirects to its file host (the host is chosen by MaxMind and can change). The plugin follows that one redirect and does not send the account ID or license key again.
* When: only when an administrator starts the database update.
* Terms: https://www.maxmind.com/en/geolite/eula
* Privacy policy: https://www.maxmind.com/en/privacy-policy

= QUIC.cloud =

Optional, and off unless an administrator enables “My site uses QUIC.cloud CDN”.

* Service: `https://www.quic.cloud/ips-all?json`
* Sent: no visitor data. The plugin requests QUIC.cloud’s published list of edge IP addresses.
* When: a daily background refresh while that setting is on. A copy of the list is also included with the plugin and is used if the refresh fails.
* Terms: https://www.quic.cloud/terms-of-use/
* Privacy policy: https://www.quic.cloud/privacy-policy/

= ReactWoo API =

* Service: `https://api.reactwoo.com`
* Optional AI: when a separately installed commercial add-on provides a ReactWoo license key, Geo Core can send that key and this site’s hostname to `/api/v5/auth/login`, then call AI routes such as `/ai/health`, `/api/v5/ai/assistant/usage`, and `/api/v5/ai/geo/variant-draft`. A variant draft sends the page id, title, excerpt, country code, language, and the editor’s instructions. Geo Core does not call license.reactwoo.com. Licensing for paid add-ons stays on that add-on.
* Updates: the ReactWoo distribution of this plugin (not the WordPress.org build) sends the plugin slug, current version, release channel, and site hostname to `/api/v5/updates/check`. The WordPress.org build does not include that updater. Commercial add-ons update themselves.
* When: only while an administrator uses those AI tools, or while WordPress checks for updates on the ReactWoo distribution.
* Terms: https://reactwoo.com/terms-conditions/
* Privacy policy: https://reactwoo.com/privacy-policy/

= ReactWoo Decision Cloud =

Optional. Nothing is sent until an administrator pairs the site under Integrations → ReactWoo Cloud.

* Service: `https://decision.reactwoo.com/api/v1`
* A stored legacy address of `https://cloud.reactwoo.com/api/v1` is rewritten to Decision Cloud. Geo Core does not call cloud.reactwoo.com.
* Pairing sends the pairing token, site URL, site name, Geo Core version, and a short plugin list (slug, version, and whether it is active).
* While the site stays paired, later administrator or scheduled requests can send the site id, a manifest sync, and a health snapshot (WordPress, PHP, Geo Core, WooCommerce, and Elementor versions, plus heartbeat and queue status). Import sends local audiences, slots, variants, and rules only after an administrator clicks Import.
* Visitor page rendering does not call Decision Cloud.
* Terms: https://reactwoo.com/terms-conditions/
* Privacy policy: https://reactwoo.com/privacy-policy/

= Anonymous Cloud telemetry =

Off by default. An administrator must enable “Share anonymous experience events” on the ReactWoo Cloud screen. Pairing the site does not enable it.

* When it is on, and only while the site is paired, Geo Core sets a first-party cookie named `rwgc_vid` (a random id, about one year). It stores the event type, experience, variant, audience, and goal ids, and an optional order total. Email addresses are removed before anything is queued.
* Those events are sent to Decision Cloud on a later administrator or scheduled flush. They are not sent while visitor HTML is generated.
* Turning the setting off stops collection and clears `rwgc_vid`.
* Other cookies (`rwgc_returning`, `rwgc_rv`, `rwgc_ft`, `rwgc_st`, `rwgc_cc`, `rwgc_pv`) stay on this site for detection, returning visitors, and full-page cache variation. Telemetry does not upload them.

== Screenshots ==

1. The Geo Core overview in wp-admin, with setup progress and shortcuts to the main sections.
2. Integrations → MaxMind (GeoLite2), where you add a MaxMind account and download or upload the country database.
3. Targeting rules, with a sample rule named United Kingdom visitors.
4. Geo insights, including the shortcodes that ship with Geo Core.
5. Geo Core settings, including country detection and cache options.
6. Integrations → ReactWoo Cloud. Anonymous experience events stay off until an administrator opts in.

== Changelog ==

Recent releases are listed here. Older notes: https://github.com/reactwoo/reactwoo-geocore/blob/main/CHANGELOG.md and the history of this file on GitHub.

= 1.9.0 =
* Requires PHP 8.1.
* Anonymous Cloud telemetry, including the visitor cookie, stays off until an administrator opts in.
* UTM and click-id conditions are included in Geo Core. Audience, weather, and profile conditions remain in the separate GeoCore Pro plugin.
* The WordPress.org build does not check api.reactwoo.com for updates. A Pro add-on that still calls the old Core updater helper is ignored instead of causing an error. Pro add-ons update themselves.

= 1.8.170 =
* Editors warn when a block or element still points at a deleted, missing, draft, or trashed visibility rule. The reference stays until you pick another rule or clear it.
* Editor status lookup queries the visibility-rule post type, so published rules no longer look deleted. Unpublished rule titles are shown only to users who can edit that rule.

= 1.8.169 =
* With “My site uses QUIC.cloud CDN” on, a site whose PHP sees a loopback or private address (nginx/php-fpm, Docker, local LiteSpeed proxy) no longer geolocates visitors as the QUIC.cloud edge server.

= 1.8.168 =
* Elementor: filling the shared country list no longer clears countries already saved on a control.
* GeoIP: ignore client forwarding headers unless the connection is from Cloudflare or a private reverse proxy.
* Settings: optional “My site uses QUIC.cloud CDN” checkbox. Off by default. When on, only a QUIC.cloud server connection uses the visitor IP from the forwarding header.
* LiteSpeed: vary the page cache from the server-resolved country and page version, not from visitor cookies.
* Visibility rules: a missing, draft, trashed, or deleted rule no longer crashes the page, and it never matches.
* Elementor: a logged-out `?elementor-preview` link no longer skips document geo rules.

= 1.8.167 =
* Elementor: ship the country catalogue once and fill classic and Atomic country controls from that list.
* Elementor V4: keep Geo Visibility working when Atomic widgets are active.

= 1.8.166 =
* Elementor builder: drop leftover `$heavy` Geo Visibility flag (PHP warning could corrupt editor AJAX) and memoize widget visitor preview.

= 1.8.165 =
* First-visit returning/new targeting stays new across later pages and AJAX in the same session.

= 1.8.164 =
* **Returning visitors:** New vs returning conditions now evaluate in portable rules, Commerce, and Cloud. A first-seen cookie marks the next visit as returning; UTM on the first hit does not.

= 1.8.163 =
* PLAN.md §19 steps 13–14: production catalogue SQL, license package, and Cloud commerce flag verified. Remaining: settings product IDs if empty, identity Sign in, paid checkout E2E, Gate E.

= 1.8.162 =
* PLAN.md remaining Local stop-ships closed: §20 shipped defaults, Local Woo E2E, Figma §16 visual screens. Production Cloud commerce stays off.

= 1.8.161 =
* Gate D live Local loop passed: cached Cloud variant still renders after Decision Cloud is stopped. Production Cloud commerce stays off.

= 1.8.160 =
* **Gate D:** Request-time Decision Runtime evaluates the cached Cloud manifest for Experience Slots. Portal `op`/`type` conditions alias to `operator`/`capability`. No Cloud HTTP on the visitor path.

== Upgrade Notice ==

= 1.9.0 =
Requires PHP 8.1. Anonymous Cloud telemetry is off until an administrator opts in. UTM and click-id conditions are included.
