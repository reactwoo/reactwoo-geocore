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
 * **Commercial** plugins register with `attach_bearer_token` true (default) and may provide their own
 * bearer/api-base callbacks. **Geo Core** registers with `attach_bearer_token` false
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
	 * Update-check HTTP timeout. The plugins screen must not block on the old 15s call.
	 */
	const CHECK_TIMEOUT = 8;

	/**
	 * One fresh /updates/check when WordPress is about to download the zip.
	 */
	const DOWNLOAD_CHECK_TIMEOUT = 10;

	/**
	 * License login timeout on the update path. Other API calls keep their own timeout.
	 */
	const LOGIN_TIMEOUT = 10;

	/**
	 * How long a successful check (update or none) is reused. The signed URL is not stored.
	 */
	const OFFER_CACHE_TTL = 3600;

	/**
	 * How long a failed license login is remembered so the plugins screen does not retry it.
	 */
	const LOGIN_FAIL_TTL = 900;

	/**
	 * Package value stored in update_plugins. Replaced with a new signed URL at download time.
	 */
	const PACKAGE_PLACEHOLDER = 'reactwoo-update://';

	/**
	 * @param array<string, mixed> $config {
	 *     @type string $basename      Plugin basename, e.g. reactwoo-geo-ai/reactwoo-geo-ai.php
	 *     @type string $version       Current plugin version.
	 *     @type string $catalog_slug     Release catalog slug (must match CI publish; commercial slugs also need license entitlements).
	 *     @type string $name             Human-readable plugin name.
	 *     @type string $description      Short description for the Plugins API “View details” modal.
	 *     @type bool     $attach_bearer_token If false, HTTP client does not send `Authorization` (use only with a free slug allowed by API `UPDATES_FREE_SLUGS`). Default true. Deprecated alias: `requires_license` (same meaning).
	 *     @type callable $get_bearer_callback Optional callback returning string|null bearer token for this plugin.
	 *     @type callable $get_api_base_callback Optional callback returning string API base for this plugin.
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
	 * @param array<string, mixed> $cfg Item config.
	 * @return string|null
	 */
	private static function item_bearer_for_updates( $cfg, $login_timeout = self::LOGIN_TIMEOUT ) {
		$login_timeout = (int) $login_timeout;
		if ( $login_timeout < 1 ) {
			$login_timeout = self::LOGIN_TIMEOUT;
		}
		if ( is_array( $cfg ) && ! empty( $cfg['get_bearer_callback'] ) && is_callable( $cfg['get_bearer_callback'] ) ) {
			$token = self::invoke_bearer_callback( $cfg['get_bearer_callback'], $login_timeout );
			if ( is_string( $token ) && '' !== $token ) {
				return $token;
			}
			return null;
		}
		return self::get_bearer_for_updates( $login_timeout );
	}

	/**
	 * Pass the update-path login timeout when the callback accepts it.
	 *
	 * @param callable $callback      Bearer callback.
	 * @param int      $login_timeout Seconds.
	 * @return string|null
	 */
	private static function invoke_bearer_callback( $callback, $login_timeout ) {
		$parameters = 0;
		try {
			if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
				$parameters = ( new ReflectionMethod( $callback[0], (string) $callback[1] ) )->getNumberOfParameters();
			} elseif ( is_string( $callback ) || $callback instanceof Closure ) {
				$parameters = ( new ReflectionFunction( $callback ) )->getNumberOfParameters();
			}
		} catch ( Throwable $e ) {
			unset( $e );
			$parameters = 0;
		}
		$token = $parameters >= 1 ? call_user_func( $callback, $login_timeout ) : call_user_func( $callback );
		if ( is_string( $token ) && '' !== $token ) {
			return $token;
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $cfg Item config.
	 * @return string
	 */
	private static function item_api_base( $cfg ) {
		if ( is_array( $cfg ) && ! empty( $cfg['get_api_base_callback'] ) && is_callable( $cfg['get_api_base_callback'] ) ) {
			$base = call_user_func( $cfg['get_api_base_callback'] );
			if ( is_string( $base ) && '' !== trim( $base ) ) {
				return untrailingslashit( trim( $base ) );
			}
		}
		return RWGC_Platform_Client::get_api_base();
	}

	/**
	 * @return void
	 */
	private static function add_hooks() {
		if ( self::$hooks_added ) {
			return;
		}
		self::$hooks_added = true;
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'filter_update_transient' ), 50, 1 );
		add_filter( 'plugins_api', array( __CLASS__, 'filter_plugins_api' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'filter_upgrader_pre_download' ), 10, 4 );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_force_check' ) );
	}

	/**
	 * Clear WordPress plugin update cache and re-query api.reactwoo.com.
	 *
	 * @return void
	 */
	public static function force_check_updates() {
		self::bump_update_cache();
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
	}

	/**
	 * Admin Tools / Settings link: ?page=rwgc-settings&rwgc_force_plugin_updates=1
	 *
	 * @return void
	 */
	public static function handle_admin_force_check() {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['rwgc_force_plugin_updates'] ) || '' === sanitize_text_field( wp_unslash( $_GET['rwgc_force_plugin_updates'] ) ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! in_array( $page, array( 'rwgc-settings', 'rwgc-tools' ), true ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rwgc_force_plugin_updates' ) ) {
			return;
		}

		self::force_check_updates();

		$lines   = array();
		$items   = apply_filters( 'rwgc_satellite_updater_items', self::$items );
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		if ( is_array( $items ) ) {
			foreach ( $items as $slug => $cfg ) {
				if ( ! is_array( $cfg ) ) {
					continue;
				}
				$basename = isset( $cfg['basename'] ) ? (string) $cfg['basename'] : '';
				if ( '' === $basename || ! isset( $plugins[ $basename ] ) ) {
					continue;
				}
				$current = isset( $plugins[ $basename ]['Version'] ) ? (string) $plugins[ $basename ]['Version'] : '';
				$offer   = self::request_update_offer( (string) $slug, $current, self::item_attach_bearer_token( $cfg ), $cfg );
				if ( is_array( $offer ) && ! empty( $offer['version'] ) ) {
					$lines[] = sprintf(
						/* translators: 1: plugin catalog slug, 2: installed version, 3: offered version */
						__( '%1$s: update available (%2$s → %3$s).', 'reactwoo-geocore' ),
						(string) $slug,
						$current,
						(string) $offer['version']
					);
				} else {
					$lines[] = sprintf(
						/* translators: 1: plugin catalog slug, 2: installed version */
						__( '%1$s: no newer release for %2$s (API check).', 'reactwoo-geocore' ),
						(string) $slug,
						$current
					);
				}
			}
		}

		$message = $lines ? implode( ' ', $lines ) : __( 'Plugin update cache cleared. Re-check Plugins → Updates in wp-admin.', 'reactwoo-geocore' );
		add_settings_error( 'rwgc_settings', 'rwgc_force_plugin_updates', $message, 'updated' );
	}

	/**
	 * @param stdClass $transient Update plugins transient.
	 * @return stdClass
	 */
	public static function filter_update_transient( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}
		// WordPress saves the existing object once, changing only last_checked, before it
		// calls api.wordpress.org. That save must not log in or call /updates/check.
		if ( self::save_only_updates_last_checked( $transient ) ) {
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
			$current = (string) $transient->checked[ $basename ];
			if ( '' === $current && isset( $cfg['version'] ) ) {
				$current = (string) $cfg['version'];
			}
			$attach_bearer = self::item_attach_bearer_token( $cfg );
			$offer         = self::offer_for_transient( (string) $slug, $current, $attach_bearer, $cfg );
			if ( null === $offer || empty( $offer['version'] ) ) {
				continue;
			}
			$plugin_info              = new stdClass();
			$plugin_info->slug        = (string) $slug;
			$plugin_info->plugin      = $basename;
			$plugin_info->new_version = $offer['version'];
			$plugin_info->package     = self::package_placeholder( (string) $slug, (string) $offer['version'], $current );
			if ( ! empty( $offer['tested'] ) ) {
				$plugin_info->tested = $offer['tested'];
			}
			if ( ! empty( $offer['requires'] ) ) {
				$plugin_info->requires = $offer['requires'];
			}
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ $basename ] = $plugin_info;
		}

		return $transient;
	}

	/**
	 * Swap the placeholder for a newly signed URL, then download that zip.
	 *
	 * The signed R2 URL lives for about ten minutes. update_plugins can live for twelve hours,
	 * so the URL from the check that filled the transient is not used here.
	 *
	 * @param mixed  $reply      Short-circuit result. False continues the download.
	 * @param string $package    Package URL from the transient.
	 * @param mixed  $upgrader   Upgrader instance (unused).
	 * @param mixed  $hook_extra Extra hook data (unused).
	 * @return mixed Local file path, WP_Error, or the original reply.
	 */
	public static function filter_upgrader_pre_download( $reply, $package, $upgrader = null, $hook_extra = null ) {
		unset( $upgrader, $hook_extra );
		if ( false !== $reply || ! is_string( $package ) ) {
			return $reply;
		}
		$parsed = self::parse_package_placeholder( $package );
		if ( null === $parsed ) {
			return $reply;
		}
		$url = self::fresh_download_url( $parsed );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		if ( ! function_exists( 'download_url' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'download_url' ) ) {
			return new WP_Error(
				'rwgc_updater_no_download',
				__( 'Could not download the ReactWoo update.', 'reactwoo-geocore' )
			);
		}
		return download_url( $url, 300 );
	}

	/**
	 * Commercial satellite updates require a valid ReactWoo product license JWT (same login as API features).
	 *
	 * @return string|null Bearer token, or null if no license / token (do not call the updates API).
	 */
	private static function get_bearer_for_updates( $login_timeout = self::LOGIN_TIMEOUT ) {
		if ( ! class_exists( 'RWGC_Platform_Client', false ) ) {
			return null;
		}
		$token = RWGC_Platform_Client::get_access_token( (int) $login_timeout, true );
		if ( is_wp_error( $token ) || ! is_string( $token ) || '' === $token ) {
			return null;
		}
		return $token;
	}

	/**
	 * @param string               $catalog_slug Catalog slug.
	 * @param string               $current_version Installed version.
	 * @param bool                 $attach_bearer_token Whether to send Authorization: Bearer (must match API policy for this slug).
	 * @param array<string, mixed> $cfg Item config.
	 * @return array{version: string, tested?: string, requires?: string}|null
	 */
	private static function request_update_offer( $catalog_slug, $current_version, $attach_bearer_token = true, $cfg = array() ) {
		return self::offer_for_transient( $catalog_slug, $current_version, $attach_bearer_token, $cfg );
	}

	/**
	 * Cached offer for the plugins list. The signed download URL is never included.
	 *
	 * @param string               $catalog_slug Catalog slug.
	 * @param string               $current_version Installed version.
	 * @param bool                 $attach_bearer_token Whether this slug sends a bearer token.
	 * @param array<string, mixed> $cfg Item config.
	 * @return array{version: string, tested?: string, requires?: string}|null
	 */
	private static function offer_for_transient( $catalog_slug, $current_version, $attach_bearer_token, $cfg ) {
		$cached = self::read_offer_cache( $catalog_slug, $current_version, $cfg );
		if ( ! empty( $cached['hit'] ) ) {
			return $cached['offer'];
		}
		$result = self::perform_update_check( $catalog_slug, $current_version, $attach_bearer_token, $cfg, self::CHECK_TIMEOUT, false, true );
		if ( ! empty( $result['cacheable'] ) ) {
			self::write_offer_cache( $catalog_slug, $current_version, $cfg, $result['offer'] );
		}
		return $result['offer'];
	}

	/**
	 * @param string               $catalog_slug Catalog slug.
	 * @param string               $current_version Installed version.
	 * @param bool                 $attach_bearer_token Whether this slug sends a bearer token.
	 * @param array<string, mixed> $cfg Item config.
	 * @param int                  $timeout Check timeout in seconds.
	 * @param bool                 $keep_package Include download_url in the offer. Transient storage must pass false.
	 * @param bool                 $honor_login_cache Skip login when a recent failure is cached.
	 * @return array{cacheable: bool, offer: array{version: string, package?: string, tested?: string, requires?: string}|null}
	 */
	private static function perform_update_check( $catalog_slug, $current_version, $attach_bearer_token, $cfg, $timeout, $keep_package, $honor_login_cache ) {
		$headers       = array(
			'Content-Type' => 'application/json',
		);
		$debug_context = array(
			'slug'               => (string) $catalog_slug,
			'current_version'    => (string) $current_version,
			'attach_bearer'      => (bool) $attach_bearer_token,
			'requested_sitehost' => '',
			'timeout'            => (int) $timeout,
		);
		if ( $attach_bearer_token ) {
			$bearer = self::bearer_for_check( $cfg, (string) $catalog_slug, self::LOGIN_TIMEOUT, $honor_login_cache );
			if ( null === $bearer ) {
				/**
				 * Commercial slugs need a license JWT for /updates/check; no token means no update row (silent in WP UI).
				 *
				 * @param string               $catalog_slug Catalog slug.
				 * @param array<string, mixed> $cfg          Item config.
				 */
				do_action( 'rwgc_satellite_updater_no_bearer', $catalog_slug, $cfg );
				$debug_context['result'] = 'no_bearer';
				self::debug_log( $debug_context );
				return array(
					'cacheable' => false,
					'offer'     => null,
				);
			}
			$headers['Authorization'] = 'Bearer ' . $bearer;
		}

		$api_base = self::item_api_base( $cfg );
		if ( '' === $api_base ) {
			return array(
				'cacheable' => false,
				'offer'     => null,
			);
		}

		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = is_string( $site_host ) ? $site_host : '';
		if ( '' === $site_host ) {
			$site_host = 'localhost';
		}
		$debug_context['requested_sitehost'] = $site_host;

		$body = array(
			'slug'            => $catalog_slug,
			'current_version' => $current_version,
			'channel'         => 'stable',
			'site_host'       => $site_host,
		);

		$response = wp_remote_post(
			trailingslashit( $api_base ) . 'api/v5/updates/check',
			array(
				'timeout' => (int) $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			/**
			 * Transport failure calling /api/v5/updates/check (timeout, SSL, DNS, etc.).
			 *
			 * @param string               $catalog_slug Catalog slug.
			 * @param \WP_Error            $response     Error object.
			 * @param array<string, mixed> $cfg          Item config.
			 */
			do_action( 'rwgc_satellite_updater_check_transport_error', $catalog_slug, $response, $cfg );
			$debug_context['result']          = 'transport_error';
			$debug_context['transport_error'] = $response->get_error_message();
			self::debug_log( $debug_context );
			return array(
				'cacheable' => false,
				'offer'     => null,
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body  = (string) wp_remote_retrieve_body( $response );
		$data      = json_decode( $raw_body, true );

		/**
		 * Raw HTTP result from /api/v5/updates/check (for diagnostics). Fires even when the response is not usable.
		 *
		 * @param string               $catalog_slug Catalog slug.
		 * @param int                  $http_code    HTTP status code.
		 * @param string               $raw_body     Response body (string).
		 * @param mixed                $data         json_decode result (array|null).
		 * @param array<string, mixed> $cfg          Item config.
		 */
		do_action( 'rwgc_satellite_updater_check_http', $catalog_slug, $http_code, $raw_body, $data, $cfg );
		$debug_context['http_code'] = $http_code;

		if ( 200 !== $http_code || ! is_array( $data ) ) {
			$debug_context['result'] = 'http_non_200';
			$debug_context['body']   = self::compact_json_for_log( $raw_body );
			self::debug_log( $debug_context );
			return array(
				'cacheable' => false,
				'offer'     => null,
			);
		}

		if ( empty( $data['update'] ) ) {
			$debug_context['result'] = 'no_update_payload';
			$debug_context['body']   = self::compact_json_for_log( $raw_body );
			self::debug_log( $debug_context );
			return array(
				'cacheable' => true,
				'offer'     => null,
			);
		}

		if ( empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			$debug_context['result'] = 'no_update_payload';
			$debug_context['body']   = self::compact_json_for_log( $raw_body );
			self::debug_log( $debug_context );
			return array(
				'cacheable' => false,
				'offer'     => null,
			);
		}

		if ( version_compare( $current_version, (string) $data['version'], '>=' ) ) {
			$debug_context['result']          = 'already_up_to_date';
			$debug_context['offered_version'] = (string) $data['version'];
			self::debug_log( $debug_context );
			return array(
				'cacheable' => true,
				'offer'     => null,
			);
		}

		$out = array(
			'version' => (string) $data['version'],
		);
		if ( $keep_package ) {
			$out['package'] = (string) $data['download_url'];
		}
		if ( ! empty( $data['tested_up_to'] ) ) {
			$out['tested'] = (string) $data['tested_up_to'];
		}
		if ( ! empty( $data['min_wp'] ) ) {
			$out['requires'] = (string) $data['min_wp'];
		}
		$debug_context['result']          = 'update_available';
		$debug_context['offered_version'] = (string) $data['version'];
		self::debug_log( $debug_context );

		return array(
			'cacheable' => true,
			'offer'     => $out,
		);
	}

	/**
	 * @param string $slug    Catalog slug.
	 * @param string $version Offered version.
	 * @param string $current Installed version.
	 * @return string
	 */
	public static function package_placeholder( $slug, $version, $current ) {
		return self::PACKAGE_PLACEHOLDER . rawurlencode( (string) $slug )
			. '?version=' . rawurlencode( (string) $version )
			. '&current=' . rawurlencode( (string) $current );
	}

	/**
	 * @param string $package Package string from the transient.
	 * @return array{slug: string, version: string, current: string}|null
	 */
	public static function parse_package_placeholder( $package ) {
		if ( ! is_string( $package ) || 0 !== strpos( $package, self::PACKAGE_PLACEHOLDER ) ) {
			return null;
		}
		$rest  = substr( $package, strlen( self::PACKAGE_PLACEHOLDER ) );
		$parts = explode( '?', $rest, 2 );
		$slug  = sanitize_key( rawurldecode( $parts[0] ) );
		$query = array();
		if ( isset( $parts[1] ) ) {
			parse_str( (string) $parts[1], $query );
		}
		if ( '' === $slug ) {
			return null;
		}
		return array(
			'slug'    => $slug,
			'version' => isset( $query['version'] ) && is_scalar( $query['version'] ) ? (string) $query['version'] : '',
			'current' => isset( $query['current'] ) && is_scalar( $query['current'] ) ? (string) $query['current'] : '',
		);
	}

	/**
	 * @param array{slug: string, version: string, current: string} $parsed Placeholder parts.
	 * @return string|\WP_Error
	 */
	private static function fresh_download_url( array $parsed ) {
		$slug  = $parsed['slug'];
		$items = apply_filters( 'rwgc_satellite_updater_items', self::$items );
		if ( ! is_array( $items ) ) {
			$items = self::$items;
		}
		if ( ! isset( $items[ $slug ] ) || ! is_array( $items[ $slug ] ) ) {
			return new WP_Error(
				'rwgc_updater_unknown_slug',
				__( 'This ReactWoo update is no longer available.', 'reactwoo-geocore' )
			);
		}
		$cfg     = $items[ $slug ];
		$current = isset( $parsed['current'] ) ? (string) $parsed['current'] : '';
		if ( '' === $current && isset( $cfg['version'] ) ) {
			$current = (string) $cfg['version'];
		}
		$result  = self::perform_update_check(
			$slug,
			$current,
			self::item_attach_bearer_token( $cfg ),
			$cfg,
			self::DOWNLOAD_CHECK_TIMEOUT,
			true,
			false
		);
		$package = ( is_array( $result['offer'] ) && ! empty( $result['offer']['package'] ) ) ? (string) $result['offer']['package'] : '';
		if ( '' === $package || ! preg_match( '#^https://#i', $package ) ) {
			return new WP_Error(
				'rwgc_updater_no_package',
				__( 'ReactWoo did not return a download link for this update. Open Plugins and try again.', 'reactwoo-geocore' )
			);
		}
		return $package;
	}

	/**
	 * True when this set_site_transient only bumps last_checked on the object already stored.
	 *
	 * @param object $incoming Transient about to be saved.
	 * @return bool
	 */
	private static function save_only_updates_last_checked( $incoming ) {
		if ( ! is_object( $incoming ) || ! function_exists( 'get_site_transient' ) ) {
			return false;
		}
		$stored = get_site_transient( 'update_plugins' );
		if ( ! is_object( $stored ) ) {
			return false;
		}
		if ( $stored === $incoming ) {
			return true;
		}
		return self::without_last_checked( $stored ) == self::without_last_checked( $incoming );
	}

	/**
	 * @param object $transient Transient object.
	 * @return object
	 */
	private static function without_last_checked( $transient ) {
		$copy = clone $transient;
		unset( $copy->last_checked );
		return $copy;
	}

	/**
	 * @param array<string, mixed> $cfg Item config.
	 * @param string               $slug Catalog slug.
	 * @param int                  $login_timeout Seconds.
	 * @param bool                 $honor_login_cache Whether a cached failure skips login.
	 * @return string|null
	 */
	private static function bearer_for_check( $cfg, $slug, $login_timeout, $honor_login_cache ) {
		$key = self::login_fail_key( $slug, $cfg );
		if ( $honor_login_cache && function_exists( 'get_transient' ) && self::failure_flag_is_active( get_transient( $key ) ) ) {
			return null;
		}
		$token = self::item_bearer_for_updates( $cfg, $login_timeout );
		if ( null === $token ) {
			if ( function_exists( 'set_transient' ) ) {
				$ttl = self::LOGIN_FAIL_TTL;
				set_transient(
					$key,
					array(
						'expires_at' => time() + $ttl,
					),
					$ttl
				);
			}
			return null;
		}
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $key );
		}
		return $token;
	}

	/**
	 * @return void
	 */
	private static function bump_update_cache() {
		if ( function_exists( 'update_option' ) ) {
			update_option( 'rwgc_upd_cache_gen', (string) time(), false );
		}
		if ( class_exists( 'RWGC_Platform_Client', false ) && is_callable( array( 'RWGC_Platform_Client', 'clear_login_failure_cache' ) ) ) {
			RWGC_Platform_Client::clear_login_failure_cache();
		}
	}

	/**
	 * A stored failure flag is active only until its expires_at timestamp.
	 *
	 * The transient TTL is the same number of seconds. expires_at is what callers
	 * should trust if they inspect the stored flag.
	 *
	 * @param mixed $failed Stored flag.
	 * @return bool
	 */
	private static function failure_flag_is_active( $failed ) {
		if ( ! is_array( $failed ) || empty( $failed['expires_at'] ) ) {
			return false;
		}
		return (int) $failed['expires_at'] > time();
	}

	/**
	 * @return string
	 */
	private static function cache_generation() {
		if ( ! function_exists( 'get_option' ) ) {
			return '0';
		}
		$generation = get_option( 'rwgc_upd_cache_gen', '0' );
		return is_scalar( $generation ) ? (string) $generation : '0';
	}

	/**
	 * @param string               $slug Catalog slug.
	 * @param string               $current Installed version.
	 * @param array<string, mixed> $cfg Item config.
	 * @return string
	 */
	private static function offer_cache_key( $slug, $current, $cfg ) {
		$api = self::item_api_base( $cfg );
		return 'rwgc_uo_' . md5( self::cache_generation() . '|' . $slug . '|' . $current . '|' . $api );
	}

	/**
	 * @param string               $slug Catalog slug.
	 * @param array<string, mixed> $cfg Item config.
	 * @return string
	 */
	private static function login_fail_key( $slug, $cfg ) {
		return 'rwgc_ulf_' . md5( self::cache_generation() . '|' . $slug . '|' . self::bearer_discriminator( $cfg ) );
	}

	/**
	 * @param array<string, mixed> $cfg Item config.
	 * @return string
	 */
	private static function bearer_discriminator( $cfg ) {
		if ( is_array( $cfg ) && ! empty( $cfg['get_bearer_callback'] ) ) {
			$callback = $cfg['get_bearer_callback'];
			if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
				$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
				return $class . '::' . (string) $callback[1];
			}
			if ( is_string( $callback ) ) {
				return $callback;
			}
			return 'closure';
		}
		return 'platform';
	}

	/**
	 * @param string               $slug Catalog slug.
	 * @param string               $current Installed version.
	 * @param array<string, mixed> $cfg Item config.
	 * @return array{hit: bool, offer: array<string, string>|null}
	 */
	private static function read_offer_cache( $slug, $current, $cfg ) {
		if ( ! function_exists( 'get_transient' ) ) {
			return array(
				'hit'   => false,
				'offer' => null,
			);
		}
		$raw = get_transient( self::offer_cache_key( $slug, $current, $cfg ) );
		if ( ! is_array( $raw ) || ! array_key_exists( 'offer', $raw ) ) {
			return array(
				'hit'   => false,
				'offer' => null,
			);
		}
		if ( isset( $raw['expires_at'] ) && (int) $raw['expires_at'] <= time() ) {
			return array(
				'hit'   => false,
				'offer' => null,
			);
		}
		$offer = $raw['offer'];
		if ( is_array( $offer ) && isset( $offer['package'] ) ) {
			unset( $offer['package'] );
		}
		return array(
			'hit'   => true,
			'offer' => is_array( $offer ) ? $offer : null,
		);
	}

	/**
	 * @param string               $slug Catalog slug.
	 * @param string               $current Installed version.
	 * @param array<string, mixed> $cfg Item config.
	 * @param array<string, string>|null $offer Offer without a download URL, or null when nothing is newer.
	 * @return void
	 */
	private static function write_offer_cache( $slug, $current, $cfg, $offer ) {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		if ( is_array( $offer ) && isset( $offer['package'] ) ) {
			unset( $offer['package'] );
		}
		$ttl = self::OFFER_CACHE_TTL;
		set_transient(
			self::offer_cache_key( $slug, $current, $cfg ),
			array(
				'expires_at' => time() + $ttl,
				'offer'      => $offer,
			),
			$ttl
		);
	}

	/**
	 * @return bool
	 */
	private static function is_debug_enabled() {
		return class_exists( 'RWGC_Settings', false ) && (bool) RWGC_Settings::get( 'debug_mode', 0 );
	}

	/**
	 * @param array<string, mixed> $line Diagnostic payload.
	 * @return void
	 */
	private static function debug_log( array $line ) {
		if ( ! self::is_debug_enabled() || ! function_exists( 'error_log' ) ) {
			return;
		}
		error_log( '[RWGC Updater] ' . wp_json_encode( $line ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Keep API bodies readable and bounded in debug logs.
	 *
	 * @param string $raw Raw response body.
	 * @return string
	 */
	private static function compact_json_for_log( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		$data = json_decode( $raw, true );
		if ( is_array( $data ) ) {
			$encoded = wp_json_encode( $data );
			if ( is_string( $encoded ) ) {
				return strlen( $encoded ) > 800 ? substr( $encoded, 0, 800 ) . '…' : $encoded;
			}
		}
		return strlen( $raw ) > 800 ? substr( $raw, 0, 800 ) . '…' : $raw;
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
			$bearer = self::bearer_for_check( $cfg, $slug, self::LOGIN_TIMEOUT, true );
			if ( null === $bearer ) {
				return $result;
			}
			$headers['Authorization'] = 'Bearer ' . $bearer;
		}

		$api_base = self::item_api_base( $cfg );
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
				'timeout' => self::CHECK_TIMEOUT,
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
