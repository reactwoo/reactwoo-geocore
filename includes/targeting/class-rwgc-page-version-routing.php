<?php
/**
 * Request routing for Page Version URLs (`/page-path/_gc/version-name`).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers rewrite rules, resolves requests to the base page, and enriches targeting snapshots.
 */
class RWGC_Page_Version_Routing {

	const QUERY_VAR = 'rwgc_page_version';

	const REWRITE_VERSION = '2';

	/**
	 * Parsed request cache.
	 *
	 * @var array{pagename:string,version:string}|null|false
	 */
	private static $parsed_request = false;

	/**
	 * Raw request URI captured before WordPress may rewrite or redirect it.
	 *
	 * @var string
	 */
	private static $raw_request_uri = '';

	/**
	 * @var bool
	 */
	private static $early_bootstrapped = false;

	/**
	 * Capture inbound URI and block canonical redirects as early as the plugin file loads.
	 *
	 * @return void
	 */
	public static function bootstrap_early() {
		if ( self::$early_bootstrapped ) {
			return;
		}
		self::$early_bootstrapped = true;
		self::capture_raw_request_uri();

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		add_filter( 'redirect_canonical', array( __CLASS__, 'filter_redirect_canonical' ), 1, 2 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_wp_redirect' ), 1, 2 );
	}

	/**
	 * @return void
	 */
	public static function init() {
		self::bootstrap_early();
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 5 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_filter( 'request', array( __CLASS__, 'filter_request' ), 1 );
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 1 );
		add_action( 'wp', array( __CLASS__, 'reset_context_snapshot_cache' ), 1 );
		add_filter( 'rwgc_context_snapshot_values', array( __CLASS__, 'filter_snapshot_values' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots_noindex' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'maybe_output_canonical' ), 1 );
	}

	/**
	 * Store the inbound URI before canonical redirects normalize `/_gc/{version}` to `/`.
	 *
	 * @return void
	 */
	/**
	 * Browser-facing request URI for the current hit.
	 *
	 * @return string
	 */
	public static function get_client_request_uri() {
		return isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Whether the client requested a normal URL (not `/_gc/{version}`).
	 *
	 * @return bool
	 */
	public static function client_request_is_base_page_url() {
		$uri = self::get_client_request_uri();
		return '' !== $uri && ! self::uri_contains_page_version_segment( $uri );
	}

	/**
	 * @return array{page_version:string,page_version_page_id:int,page_version_active:bool,page_version_base_path:string}
	 */
	private static function empty_page_version_context() {
		return array(
			'page_version'           => '',
			'page_version_page_id'   => 0,
			'page_version_active'    => false,
			'page_version_base_path' => '',
		);
	}

	public static function capture_raw_request_uri() {
		if ( '' !== self::$raw_request_uri ) {
			return;
		}

		$client_uri = self::get_client_request_uri();
		if ( '' !== $client_uri && ! self::uri_contains_page_version_segment( $client_uri ) ) {
			self::$raw_request_uri = $client_uri;
			return;
		}

		$candidates = array();
		foreach ( array( 'HTTP_X_ORIGINAL_URL', 'HTTP_X_REWRITE_URL', 'REDIRECT_URL', 'UNENCODED_URL', 'REQUEST_URI' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$candidates[] = (string) wp_unslash( $_SERVER[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		foreach ( $candidates as $uri ) {
			if ( self::uri_contains_page_version_segment( $uri ) ) {
				self::$raw_request_uri = $uri;
				return;
			}
		}

		if ( ! empty( $candidates[0] ) ) {
			self::$raw_request_uri = $candidates[ count( $candidates ) - 1 ];
		}
	}

	/**
	 * @return string
	 */
	public static function get_raw_request_uri() {
		if ( '' === self::$raw_request_uri ) {
			self::capture_raw_request_uri();
		}
		return self::$raw_request_uri;
	}

	/**
	 * @param string $uri Full or relative URI/path.
	 * @return bool
	 */
	public static function uri_contains_page_version_segment( $uri ) {
		if ( ! is_string( $uri ) || '' === $uri ) {
			return false;
		}

		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = $uri;
		}

		$rel = trim( $path, '/' );
		if ( '' === $rel ) {
			return false;
		}

		return (bool) preg_match(
			'#(^|/)' . preg_quote( RWGC_Page_Version::ROUTE_SEGMENT, '#' ) . '/[a-zA-Z0-9_-]{1,80}/?$#',
			$rel
		);
	}

	/**
	 * Whether the inbound HTTP request targets a Page Version URL.
	 *
	 * @return bool
	 */
	private static function request_looks_like_page_version() {
		if ( self::client_request_is_base_page_url() ) {
			return false;
		}
		if ( self::is_page_version_request() ) {
			return true;
		}
		if ( null !== self::parse_request_path() ) {
			return true;
		}
		return self::uri_contains_page_version_segment( self::get_raw_request_uri() );
	}

	/**
	 * Prevent WordPress canonical redirect from stripping `/_gc/{version}` (especially on the static front page).
	 *
	 * @param string|false $redirect_url  Canonical redirect target.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function filter_redirect_canonical( $redirect_url, $requested_url ) {
		if ( ! $redirect_url ) {
			return $redirect_url;
		}

		$request_path = '';
		if ( is_string( $requested_url ) && '' !== $requested_url ) {
			$request_path = $requested_url;
		} else {
			$request_path = self::get_raw_request_uri();
		}

		if ( ! self::uri_contains_page_version_segment( $request_path ) ) {
			return $redirect_url;
		}

		// Cancel canonical redirects that would strip `/_gc/{version}` from the inbound URL.
		if ( ! self::uri_contains_page_version_segment( (string) $redirect_url ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Cancel strip-to-home redirects issued while serving a Page Version URL.
	 *
	 * @param string|false $location Redirect target.
	 * @param int          $status   HTTP status code.
	 * @return string|false
	 */
	public static function filter_wp_redirect( $location, $status ) {
		if ( ! $location || ! self::request_looks_like_page_version() ) {
			return $location;
		}

		$home = trailingslashit( home_url( '/' ) );
		if ( trailingslashit( (string) $location ) === $home && in_array( (int) $status, array( 301, 302, 303, 307, 308 ), true ) ) {
			return false;
		}

		return $location;
	}

	/**
	 * @return void
	 */
	public static function register_rewrites() {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([a-zA-Z0-9_-]{1,80})' );
		add_rewrite_rule(
			'^' . preg_quote( RWGC_Page_Version::ROUTE_SEGMENT, '#' ) . '/([a-zA-Z0-9_-]{1,80})/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^(.+?)/' . preg_quote( RWGC_Page_Version::ROUTE_SEGMENT, '#' ) . '/([a-zA-Z0-9_-]{1,80})/?$',
			'index.php?pagename=$matches[1]&' . self::QUERY_VAR . '=$matches[2]',
			'top'
		);

		$stored = get_option( 'rwgc_page_version_rewrite_version', '' );
		if ( self::REWRITE_VERSION !== $stored ) {
			flush_rewrite_rules( false );
			update_option( 'rwgc_page_version_rewrite_version', self::REWRITE_VERSION, false );
		}
	}

	/**
	 * Flush rules on plugin activation.
	 *
	 * @return void
	 */
	public static function activation_flush() {
		self::register_rewrites();
		flush_rewrite_rules();
	}

	/**
	 * @param array<string, mixed> $vars Query vars.
	 * @return array<string, mixed>
	 */
	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Fallback when rewrites are not flushed yet.
	 *
	 * @param array<string, mixed> $query_vars Query vars.
	 * @return array<string, mixed>
	 */
	public static function filter_request( $query_vars ) {
		if ( ! empty( $query_vars[ self::QUERY_VAR ] ) ) {
			return $query_vars;
		}

		$parsed = self::parse_request_path();
		if ( null === $parsed ) {
			return $query_vars;
		}

		$query_vars['pagename']           = $parsed['pagename'];
		$query_vars[ self::QUERY_VAR ]    = $parsed['version'];
		$query_vars['page_id']            = 0;
		$query_vars['name']               = '';
		unset( $query_vars['error'] );

		return $query_vars;
	}

	/**
	 * Ensure WordPress loads the base page for Page Version URLs.
	 *
	 * @param WP_Query $query Main query.
	 * @return void
	 */
	public static function pre_get_posts( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$version = get_query_var( self::QUERY_VAR );
		if ( '' === $version || null === $version ) {
			return;
		}

		$error = '';
		$version = RWGC_Page_Version::sanitize_version_name( $version, $error );
		if ( '' === $version ) {
			$query->set_404();
			return;
		}

		$pagename = get_query_var( 'pagename' );
		if ( ! is_string( $pagename ) ) {
			$pagename = '';
		}

		$post_id = RWGC_Page_Version::resolve_post_id_from_path( $pagename );
		if ( $post_id <= 0 ) {
			$query->set_404();
			return;
		}

		$query->set( 'page_id', $post_id );
		$query->set( 'pagename', '' );
		$query->set( 'name', '' );
		$query->is_page     = true;
		$query->is_singular = true;
		$query->is_404      = false;
		$query->is_home     = false;
	}

	/**
	 * @param array<string, mixed> $merged Snapshot values.
	 * @return array<string, mixed>
	 */
	public static function filter_snapshot_values( $merged ) {
		if ( ! is_array( $merged ) ) {
			$merged = array();
		}

		$pv = self::get_request_page_version_context();
		$merged['page_version']           = (string) ( $pv['page_version'] ?? '' );
		$merged['page_version_page_id']   = (int) ( $pv['page_version_page_id'] ?? 0 );
		$merged['page_version_active']    = ! empty( $pv['page_version_active'] );
		$merged['page_version_base_path'] = (string) ( $pv['page_version_base_path'] ?? '' );

		return $merged;
	}

	/**
	 * Resolve Page Version URL context for the current HTTP request.
	 *
	 * @return array{page_version:string,page_version_page_id:int,page_version_active:bool,page_version_base_path:string}
	 */
	public static function get_request_page_version_context() {
		if ( self::client_request_is_base_page_url() ) {
			return self::empty_page_version_context();
		}

		$error    = '';
		$version  = '';
		$pagename = '';
		$parsed   = self::parse_request_path();

		if ( is_array( $parsed ) ) {
			$version  = RWGC_Page_Version::sanitize_version_name( (string) $parsed['version'], $error );
			$pagename = (string) $parsed['pagename'];
		}

		if ( '' === $version ) {
			$version = RWGC_Page_Version::sanitize_version_name( (string) get_query_var( self::QUERY_VAR ), $error );
		}
		if ( '' === $pagename ) {
			$pagename = (string) get_query_var( 'pagename' );
		}

		$page_id = 0;
		if ( '' !== $version ) {
			$page_id = RWGC_Page_Version::resolve_post_id_from_path( $pagename );
			if ( $page_id <= 0 && function_exists( 'get_queried_object_id' ) ) {
				$page_id = (int) get_queried_object_id();
			}
		}

		return array(
			'page_version'           => $version,
			'page_version_page_id'   => $page_id > 0 ? $page_id : 0,
			'page_version_active'    => ( '' !== $version && $page_id > 0 ),
			'page_version_base_path' => $page_id > 0 ? RWGC_Page_Version::get_post_relative_path( $page_id ) : '',
		);
	}

	/**
	 * Rebuild visitor snapshot after the main query knows the base page.
	 *
	 * @return void
	 */
	public static function reset_context_snapshot_cache() {
		if ( class_exists( 'RWGC_Context_Resolver', false ) ) {
			RWGC_Context_Resolver::reset_cache();
		}
	}

	/**
	 * Discourage indexing of branded version URLs.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function filter_robots_noindex( $robots ) {
		if ( self::is_page_version_request() ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}

	/**
	 * Canonical points at the version URL (trailing slash normalized).
	 *
	 * @return void
	 */
	public static function maybe_output_canonical() {
		if ( ! self::is_page_version_request() ) {
			return;
		}

		$version = (string) get_query_var( self::QUERY_VAR );
		$error   = '';
		$version = RWGC_Page_Version::sanitize_version_name( $version, $error );
		$pagename = get_query_var( 'pagename' );
		if ( ! is_string( $pagename ) ) {
			$pagename = '';
		}
		if ( '' === $version ) {
			return;
		}

		$post_id = RWGC_Page_Version::resolve_post_id_from_path( $pagename );
		if ( $post_id <= 0 ) {
			return;
		}

		$url = RWGC_Page_Version::build_version_url( $post_id, $version );
		if ( '' === $url ) {
			return;
		}

		echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Whether the current request is a Page Version URL.
	 *
	 * @return bool
	 */
	public static function is_page_version_request() {
		if ( self::client_request_is_base_page_url() ) {
			return false;
		}
		$version = get_query_var( self::QUERY_VAR );
		if ( is_string( $version ) && '' !== trim( $version ) ) {
			return true;
		}
		return null !== self::parse_request_path();
	}

	/**
	 * Active version slug for the current request (empty on base page).
	 *
	 * @return string
	 */
	public static function get_active_version() {
		$version = get_query_var( self::QUERY_VAR );
		$error   = '';
		return RWGC_Page_Version::sanitize_version_name( is_string( $version ) ? $version : '', $error );
	}

	/**
	 * Parse `REQUEST_URI` for `path/_gc/version`.
	 *
	 * @return array{pagename:string,version:string}|null
	 */
	public static function parse_request_path() {
		if ( false !== self::$parsed_request ) {
			return self::$parsed_request ?: null;
		}

		if ( self::client_request_is_base_page_url() ) {
			self::$parsed_request = null;
			return null;
		}

		$path = self::get_request_path();

		$home_pattern = '#^' . preg_quote( RWGC_Page_Version::ROUTE_SEGMENT, '#' ) . '/([a-zA-Z0-9_-]{1,80})/?$#';
		if ( '' !== $path && preg_match( $home_pattern, $path, $home_match ) ) {
			$error = '';
			$ver   = RWGC_Page_Version::sanitize_version_name( $home_match[1], $error );
			if ( '' === $ver ) {
				self::$parsed_request = null;
				return null;
			}

			self::$parsed_request = array(
				'pagename' => '',
				'version'  => $ver,
			);
			return self::$parsed_request;
		}

		if ( '' === $path ) {
			self::$parsed_request = null;
			return null;
		}

		$pattern = '#^(.+?)/' . preg_quote( RWGC_Page_Version::ROUTE_SEGMENT, '#' ) . '/([a-zA-Z0-9_-]{1,80})/?$#';
		if ( ! preg_match( $pattern, $path, $m ) ) {
			self::$parsed_request = null;
			return null;
		}

		$error = '';
		$ver   = RWGC_Page_Version::sanitize_version_name( $m[2], $error );
		if ( '' === $ver ) {
			self::$parsed_request = null;
			return null;
		}

		self::$parsed_request = array(
			'pagename' => $m[1],
			'version'  => $ver,
		);
		return self::$parsed_request;
	}

	/**
	 * Request path relative to site home (no query string, no leading/trailing slash).
	 *
	 * @return string
	 */
	public static function get_request_path() {
		$uri = self::get_raw_request_uri();
		if ( '' === $uri && isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return '';
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '/' !== $home_path && '' !== $home_path ) {
			$home_path = trailingslashit( $home_path );
			if ( 0 === strpos( $path, $home_path ) ) {
				$path = substr( $path, strlen( $home_path ) );
			}
		}

		return trim( $path, '/' );
	}

	/**
	 * Editor context extension for portable rule builder.
	 *
	 * @param array<string, mixed> $ctx Context.
	 * @return array<string, mixed>
	 */
	public static function filter_editor_context( $ctx ) {
		if ( ! is_array( $ctx ) ) {
			$ctx = array();
		}

		$ctx['page_version'] = array(
			'document' => RWGC_Page_Version::get_document_context(),
			'pages'    => RWGC_Page_Version::get_page_choices(),
		);

		return $ctx;
	}
}
