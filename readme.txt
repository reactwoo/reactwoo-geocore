=== ReactWoo Geo Core ===
Contributors: reactwoo
Tags: geo, geolocation, maxmind, country, currency
Requires at least: 6.2
Tested up to: 6.5.3
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shared geolocation engine for ReactWoo plugins and WordPress sites. Provides MaxMind-based country detection, cache, shortcodes, REST API, and a Gutenberg block.

== Description ==

ReactWoo Geo Core is a free geolocation engine for WordPress.

It provides:

* MaxMind-based country detection (GeoLite2 Country)
* Centralised storage and update of the MaxMind database
* A simple PHP API for add-ons and themes
* Shortcodes for country, city, and currency
* A REST API endpoint for frontend apps
* A basic Gutenberg "Geo Content" block

It is designed to be used on its own, or as a shared geo engine for premium ReactWoo plugins such as GeoElementor and ReactWoo WHMCS Bridge.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/reactwoo-geocore` directory, or install via WordPress plugin upload.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Geo Core → Settings** and enter your MaxMind license key.
4. Use the **Tools** tab to download/update the database and test lookups.

== Frequently Asked Questions ==

= Does this plugin require Elementor? =

No. ReactWoo Geo Core works with any theme and editor. It exposes helper functions, shortcodes, a REST endpoint, and a Gutenberg block.

= Does this plugin include MaxMind? =

No. You must provide your own MaxMind license key and accept their terms of use. The plugin then downloads the GeoLite2 Country database to your site.

== Changelog ==

= 0.1.0 =
* Initial beta release.

