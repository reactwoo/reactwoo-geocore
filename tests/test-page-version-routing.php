<?php
/**
 * CLI regression tests for Page Version URL routing (minimal WordPress stubs).
 *
 * Run: php tests/test-page-version-routing.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return (int) abs( (float) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( (string) $string, '/' ) . '/';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return 0;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		/**
		 * @var int
		 */
		public $ID;

		/**
		 * @var string
		 */
		public $post_type;

		/**
		 * @var string
		 */
		public $post_status;

		/**
		 * @param int    $id Post ID.
		 * @param string $type Post type.
		 * @param string $status Post status.
		 */
		public function __construct( $id, $type = 'page', $status = 'publish' ) {
			$this->ID          = (int) $id;
			$this->post_type   = $type;
			$this->post_status = $status;
		}
	}
}

$GLOBALS['rwgc_test_pages_by_path'] = array(
	'pricing' => new WP_Post( 123, 'page', 'publish' ),
);

if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $page_path, $output = OBJECT, $post_type = 'page' ) {
		unset( $output, $post_type );
		$key = trim( (string) $page_path, '/' );
		return isset( $GLOBALS['rwgc_test_pages_by_path'][ $key ] ) ? $GLOBALS['rwgc_test_pages_by_path'][ $key ] : null;
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-page-version.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-page-version-routing.php';

class RWGC_Test_Query {
	/**
	 * @var array<string, mixed>
	 */
	public $vars = array();

	/**
	 * @var bool
	 */
	public $is_page = false;

	/**
	 * @var bool
	 */
	public $is_singular = false;

	/**
	 * @var bool
	 */
	public $is_404 = false;

	/**
	 * @var bool
	 */
	public $is_home = true;

	/**
	 * @param array<string, mixed> $vars Query vars.
	 */
	public function __construct( array $vars = array() ) {
		$this->vars = $vars;
	}

	/**
	 * @return bool
	 */
	public function is_main_query() {
		return true;
	}

	/**
	 * @param string $key Query var.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}

	/**
	 * @return void
	 */
	public function set_404() {
		$this->is_404 = true;
	}
}

/**
 * @return void
 */
function rwgc_test_reset_parsed_request() {
	$prop = new ReflectionProperty( 'RWGC_Page_Version_Routing', 'parsed_request' );
	$prop->setAccessible( true );
	$prop->setValue( null, false );
}

/**
 * @param bool   $condition Assertion.
 * @param string $message Failure message.
 * @return void
 */
function rwgc_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$_SERVER['REQUEST_URI'] = '/pricing/?rwgc_page_version=campaign';
rwgc_test_reset_parsed_request();
rwgc_test_assert( ! RWGC_Page_Version_Routing::is_page_version_request(), 'Bare query var must not count as a page-version request.' );

$snapshot = RWGC_Page_Version_Routing::filter_snapshot_values( array() );
rwgc_test_assert( empty( $snapshot['page_version_active'] ), 'Bare query var must not activate page-version snapshot.' );
rwgc_test_assert( '' === $snapshot['page_version'], 'Bare query var must not set page-version slug.' );

$query = new RWGC_Test_Query(
	array(
		'pagename'          => 'pricing',
		'rwgc_page_version' => 'campaign',
	)
);
RWGC_Page_Version_Routing::pre_get_posts( $query );
rwgc_test_assert( empty( $query->vars['page_id'] ), 'Bare query var must not rewrite the main query.' );
rwgc_test_assert( ! $query->is_404, 'Bare query var must not force a 404.' );

$_SERVER['REQUEST_URI'] = '/pricing/_gc/campaign/';
rwgc_test_reset_parsed_request();
rwgc_test_assert( RWGC_Page_Version_Routing::is_page_version_request(), 'Branded /_gc/ path should count as a page-version request.' );

$query = new RWGC_Test_Query();
RWGC_Page_Version_Routing::pre_get_posts( $query );
rwgc_test_assert( 123 === $query->vars['page_id'], 'Branded /_gc/ path should rewrite to the base page.' );
rwgc_test_assert( 'campaign' === $query->vars['rwgc_page_version'], 'Branded /_gc/ path should preserve the sanitized version slug.' );
rwgc_test_assert( $query->is_page && $query->is_singular && ! $query->is_home && ! $query->is_404, 'Branded /_gc/ path should mark the main query singular.' );

$snapshot = RWGC_Page_Version_Routing::filter_snapshot_values( array() );
rwgc_test_assert( ! empty( $snapshot['page_version_active'] ), 'Branded /_gc/ path should activate page-version snapshot.' );
rwgc_test_assert( 123 === $snapshot['page_version_page_id'], 'Branded /_gc/ path should resolve the base page ID.' );
rwgc_test_assert( 'campaign' === $snapshot['page_version'], 'Branded /_gc/ path should expose the version slug.' );

fwrite( STDOUT, "OK: Page Version URL routing tests passed.\n" );
exit( 0 );
