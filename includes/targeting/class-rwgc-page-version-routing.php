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

	const REWRITE_VERSION = '1';

	/**
	 * Parsed request cache.
	 *
	 * @var array{pagename:string,version:string}|null|false
	 */
	private static $parsed_request = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 5 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_filter( 'request', array( __CLASS__, 'filter_request' ), 1 );
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 1 );
		add_filter( 'rwgc_context_snapshot_values', array( __CLASS__, 'filter_snapshot_values' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots_noindex' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'maybe_output_canonical' ), 1 );
	}

	/**
	 * @return void
	 */
	public static function register_rewrites() {
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([a-zA-Z0-9_-]{1,80})' );
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
		if ( ! is_string( $pagename ) || '' === trim( $pagename ) ) {
			$query->set_404();
			return;
		}

		$post_id = RWGC_Page_Version::resolve_post_id_from_path( $pagename );
		if ( $post_id <= 0 ) {
			$query->set_404();
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			$query->set_404();
			return;
		}

		if ( 'post' === $post_type ) {
			$query->set( 'p', $post_id );
			$query->set( 'page_id', 0 );
			$query->is_single = true;
			$query->is_page   = false;
		} else {
			$query->set( 'page_id', $post_id );
			$query->set( 'p', 0 );
			$query->is_page   = true;
			$query->is_single = false;
		}
		$query->set( 'pagename', '' );
		$query->set( 'name', '' );
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

		$version = get_query_var( self::QUERY_VAR );
		$error   = '';
		$version = RWGC_Page_Version::sanitize_version_name( is_string( $version ) ? $version : '', $error );

		$page_id = 0;
		if ( '' !== $version ) {
			$pagename = get_query_var( 'pagename' );
			if ( is_string( $pagename ) && '' !== trim( $pagename ) ) {
				$page_id = RWGC_Page_Version::resolve_post_id_from_path( $pagename );
			}
			if ( $page_id <= 0 && function_exists( 'get_queried_object_id' ) ) {
				$page_id = (int) get_queried_object_id();
			}
		}

		$merged['page_version']            = $version;
		$merged['page_version_page_id']    = $page_id > 0 ? $page_id : 0;
		$merged['page_version_active']     = ( '' !== $version && $page_id > 0 );
		$merged['page_version_base_path']  = $page_id > 0 ? RWGC_Page_Version::get_post_relative_path( $page_id ) : '';

		return $merged;
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
		if ( ! is_string( $pagename ) || '' === $version ) {
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

		$path = self::get_request_path();
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
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
