<?php
/**
 * WordPress plugin updates via api.reactwoo.com → R2 signed URLs.
 *
 * Security (read this before changing options): Authorization is enforced on the **API**, not in PHP. The client
 * always sends JSON `slug` (catalog slug). When `UPDATES_REQUIRE_LICENSE_TOKEN` is on, the server requires a valid
 * license JWT for every slug **except** those in `UPDATES_FREE_SLUGS`. Tampering with `attach_bearer_token` here
 * only adds or removes the `Authorization` header; for a paid slug, omitting the header yields **401** and no zip —
 * it does not grant access. You cannot “unlock” a commercial product by flipping a local boolean; you would only
 * get a response for the slug you request, and paid slugs still need JWT server-side.
 *
 * **Commercial** plugins register with `attach_bearer_token` true (default) and send
 * {@see RWGC_Platform_Client::get_access_token()} as Bearer. **Geo Core** registers with `attach_bearer_token` false
 * and catalog slug `reactwoo-geocore` (API must list that slug in `UPDATES_FREE_SLUGS`).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers one satellite plugin with the shared update checker (call from each product’s bootstrap).
 */
class RWGC_Satellite_Updater {

	/**
	 * @var array<string, array<string, string>>
	 */
	private static $items = array();

	/**
	 * @var bool
	 */
	private static $hooks_added = false;

	/**
	 * @param array<string, string> $config {
	 *     @type string $basename      Plugin basename, e.g. reactwoo-geo-ai/reactwoo-geo-ai.php
	 *     @type string $version       Current plugin version.
	 *     @type string $catalog_slug     Release catalog slug (must match CI publish; commercial slugs also need license entitlements).
	 *     @type string $name             Human-readable plugin name.
	 *     @type string $description      Short description for the Plugins API “View details” modal.
	 *     @type bool   $attach_bearer_token If false, HTTP client does not send `Authorization` (use only with a free slug allowed by API `UPDATES_FREE_SLUGS`). Default true. Deprecated alias: `requires_license` (same meaning).
	 * }
	 * @return void
	 */
	public static function register( $config ) {
		if ( ! is_array( $config ) ) {
			return;
		}
		$basename = isset( $config['basename'] ) ? (string) $config['basename'] : '';
		$slug     = isset( $config['catalog_slug'] ) ? sanitize_key( (string) $config['catalog_slug'] ) : '';
		$version  = isset( $config['version'] ) ? (string) $config['version'] : '';
		if ( '' === $basename || '' === $slug || '' === $version ) {
			return;
		}
		$attach_bearer = self::parse_attach_bearer_token( $config );
		self::$items[ $slug ] = array_merge(
			array(
				'basename'            => $basename,
				'version'             => $version,
				'catalog_slug'        => $slug,
				'name'                => isset( $config['name'] ) ? (string) $config['name'] : $slug,
				'description'         => isset( $config['description'] ) ? (string) $config['description'] : '',
				'attach_bearer_token' => $attach_bearer,
			),
			$config
		);
		self::$items[ $slug ]['attach_bearer_token'] = $attach_bearer;

		self::add_hooks();
	}

	/**
	 * Whether to add Authorization: Bearer on update checks. Not a security gate — the API enforces by `slug`.
	 *
	 * @param array<string, mixed> $config Registration config.
	 * @return bool
	 */
	private static function parse_attach_bearer_token( $config ) {
		if ( array_key_exists( 'attach_bearer_token', $config ) ) {
			return (bool) $config['attach_bearer_token'];
		}
		if ( array_key_exists( 'requires_license', $config ) ) {
			return (bool) $config['requires_license'];
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $cfg Item config.
	 * @return bool
	 */
	private static function item_attach_bearer_token( $cfg ) {
		if ( ! is_array( $cfg ) ) {
			return true;
		}
		if ( array_key_exists( 'attach_bearer_token', $cfg ) ) {
			return (bool) $cfg['attach_bearer_token'];
		}
		if ( array_key_exists( 'requires_license', $cfg ) ) {
			return (bool) $cfg['requires_license'];
		}
		return true;
	}

	/**
	 * @return void
	 */
	private static function add_hooks() {
		if ( self::$hooks_added ) {
			return;
		}
		self::$hooks_added = true;
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'filter_update_transient' ), 10, 1 );
		add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api' ), 10, 3 );
	}

	/**
	 * @param stdClass $transient Update plugins transient.
	 * @return stdClass
	 */
	public static function filter_update_transient( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		/**
		 * Filter registered satellite updater configs before checking the API.
		 *
		 * @param array<string, array<string, string>> $items By catalog_slug.
		 */
		$items = apply_filters( 'rwgc_satellite_updater_items', self::$items );
		if ( ! is_array( $items ) ) {
			$items = self::$items;
		}

		foreach ( $items as $slug => $cfg ) {
			if ( ! is_array( $cfg ) ) {
				continue;
			}
			$basename = isset( $cfg['basename'] ) ? (string) $cfg['basename'] : '';
			if ( '' === $basename || ! isset( $transient->checked[ $basename ] ) ) {
				continue;
			}
			$current        = isset( $cfg['version'] ) ? (string) $cfg['version'] : '';
			$attach_bearer = self::item_attach_bearer_token( $cfg );
			$offer          = self::request_update_offer( (string) $slug, $current, $attach_bearer );
			if ( null === $offer ) {
				continue;
			}
			$plugin_info              = new stdClass();
			$plugin_info->slug        = (string) $slug;
			$plugin_info->plugin      = $basename;
			$plugin_info->new_version = $offer['version'];
			$plugin_info->package     = $offer['package'];
			if ( ! empty( $offer['tested'] ) ) {
				$plugin_info->tested = $offer['tested'];
			}
			if ( ! empty( $offer['requires'] ) ) {
				$plugin_info->requires = $offer['requires'];
			}
			$transient->response[ $basename ] = $plugin_info;
		}

		return $transient;
	}

	/**
	 * Commercial satellite updates require a valid ReactWoo product license JWT (same login as API features).
	 *
	 * @return string|null Bearer token, or null if no license / token (do not call the updates API).
	 */
	private static function get_bearer_for_updates() {
		if ( ! class_exists( 'RWGC_Platform_Client', false ) ) {
			return null;
		}
		$token = RWGC_Platform_Client::get_access_token();
		if ( is_wp_error( $token ) || ! is_string( $token ) || '' === $token ) {
			return null;
		}
		return $token;
	}

	/**
	 * @param string $catalog_slug Catalog slug.
	 * @param string $current_version Installed version.
	 * @param bool   $attach_bearer_token Whether to send Authorization: Bearer (must match API policy for this slug).
	 * @return array{version: string, package: string, tested?: string, requires?: string}|null
	 */
	private static function request_update_offer( $catalog_slug, $current_version, $attach_bearer_token = true ) {
		$headers = array(
			'Content-Type' => 'application/json',
		);
		if ( $attach_bearer_token ) {
			$bearer = self::get_bearer_for_updates();
			if ( null === $bearer ) {
				return null;
			}
			$headers['Authorization'] = 'Bearer ' . $bearer;
		}

		$api_base = RWGC_Platform_Client::get_api_base();
		if ( '' === $api_base ) {
			return null;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = is_string( $site_host ) ? $site_host : '';
		if ( '' === $site_host ) {
			$site_host = 'localhost';
		}

		$body = array(
			'slug'            => $catalog_slug,
			'current_version' => $current_version,
			'channel'         => 'stable',
			'site_host'       => $site_host,
		);

		$response = wp_remote_post(
			trailingslashit( $api_base ) . 'api/v5/updates/check',
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['update'] ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return null;
		}
		if ( version_compare( $current_version, (string) $data['version'], '>=' ) ) {
			return null;
		}

		$out = array(
			'version' => (string) $data['version'],
			'package' => (string) $data['download_url'],
		);
		if ( ! empty( $data['tested_up_to'] ) ) {
			$out['tested'] = (string) $data['tested_up_to'];
		}
		if ( ! empty( $data['min_wp'] ) ) {
			$out['requires'] = (string) $data['min_wp'];
		}

		return $out;
	}

	/**
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public static function filter_plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || ! is_string( $args->slug ) ) {
			return $result;
		}

		$items = apply_filters( 'rwgc_satellite_updater_items', self::$items );
		if ( ! is_array( $items ) ) {
			$items = self::$items;
		}

		$slug = sanitize_key( $args->slug );
		if ( ! isset( $items[ $slug ] ) || ! is_array( $items[ $slug ] ) ) {
			return $result;
		}
		$cfg = $items[ $slug ];

		$attach_bearer = self::item_attach_bearer_token( $cfg );
		$headers       = array(
			'Content-Type' => 'application/json',
		);
		if ( $attach_bearer ) {
			$bearer = self::get_bearer_for_updates();
			if ( null === $bearer ) {
				return $result;
			}
			$headers['Authorization'] = 'Bearer ' . $bearer;
		}

		$api_base = RWGC_Platform_Client::get_api_base();
		if ( '' === $api_base ) {
			return $result;
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = is_string( $site_host ) && '' !== $site_host ? $site_host : 'localhost';

		$body = array(
			'slug'            => $slug,
			'current_version' => '0.0.0',
			'channel'         => 'stable',
			'site_host'       => $site_host,
		);

		$response = wp_remote_post(
			trailingslashit( $api_base ) . 'api/v5/updates/check',
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $result;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			return $result;
		}

		$info               = new stdClass();
		$info->name         = isset( $cfg['name'] ) ? (string) $cfg['name'] : $slug;
		$info->slug         = $slug;
		$info->version      = (string) $data['version'];
		$info->requires     = ! empty( $data['min_wp'] ) ? (string) $data['min_wp'] : '';
		$info->tested       = ! empty( $data['tested_up_to'] ) ? (string) $data['tested_up_to'] : '';
		$info->requires_php = ! empty( $data['min_php'] ) ? (string) $data['min_php'] : '';
		$info->author       = '<a href="https://reactwoo.com">ReactWoo</a>';
		$info->homepage     = 'https://reactwoo.com';
		$info->sections     = array(
			'description' => isset( $cfg['description'] ) ? (string) $cfg['description'] : '',
			'changelog'     => ! empty( $data['changelog_html'] ) ? (string) $data['changelog_html'] : '',
		);

		return $info;
	}
}
