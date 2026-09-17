<?php
/**
 * Gutenberg post-level geo must hide content on REST, feeds, and loops.
 *
 * Usage: php tests/test-rwgc-gutenberg-post-geo.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_is_admin']         = false;
$GLOBALS['rwgc_test_is_singular']      = true;
$GLOBALS['rwgc_test_the_id']           = 42;
$GLOBALS['rwgc_test_queried_id']       = 42;
$GLOBALS['rwgc_test_can_edit']         = false;
$GLOBALS['rwgc_test_visitor_country']  = 'DE';
$GLOBALS['rwgc_test_post_meta']        = array();

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
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['rwgc_test_is_admin'] );
	}
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular() {
		return ! empty( $GLOBALS['rwgc_test_is_singular'] );
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return (int) $GLOBALS['rwgc_test_the_id'];
	}
}
if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return (int) $GLOBALS['rwgc_test_queried_id'];
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap, $id = 0 ) {
		unset( $cap, $id );
		return ! empty( $GLOBALS['rwgc_test_can_edit'] );
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$row = isset( $GLOBALS['rwgc_test_post_meta'][ (int) $post_id ] ) ? $GLOBALS['rwgc_test_post_meta'][ (int) $post_id ] : array();
		if ( '' === $key ) {
			return $row;
		}
		if ( ! array_key_exists( $key, $row ) ) {
			return $single ? '' : array();
		}
		return $single ? $row[ $key ] : array( $row[ $key ] );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		return true;
	}
}
if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	function rwgc_get_visitor_country() {
		return (string) $GLOBALS['rwgc_test_visitor_country'];
	}
}
if ( ! function_exists( 'rwgc_normalize_visibility_mode' ) ) {
	function rwgc_normalize_visibility_mode( $mode ) {
		return 'hide_if' === sanitize_key( (string) $mode ) ? 'hide_if' : 'show_if';
	}
}
if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
	function rwgc_visibility_mode_allows_render( $mode, $matched ) {
		return 'hide_if' === rwgc_normalize_visibility_mode( $mode ) ? ! $matched : (bool) $matched;
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_pg_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

/**
 * @return void
 */
function rwgc_pg_us_only_post() {
	$GLOBALS['rwgc_test_post_meta'][42] = array(
		'_rwgc_post_geo_enabled'     => 'yes',
		'_rwgc_post_country_enabled' => 'yes',
		'_rwgc_post_geo_mode'        => 'show_if',
		'_rwgc_post_geo_countries'   => array( 'US' ),
	);
}

rwgc_pg_us_only_post();

rwgc_pg_assert(
	'singular HTML hides for DE visitor',
	'' === RWGC_Gutenberg_Post_Geo::filter_post_content( 'SECRET' )
);

$GLOBALS['rwgc_test_visitor_country'] = 'US';
rwgc_pg_assert(
	'singular HTML shows for US visitor',
	'SECRET' === RWGC_Gutenberg_Post_Geo::filter_post_content( 'SECRET' )
);

$GLOBALS['rwgc_test_visitor_country'] = 'DE';
$GLOBALS['rwgc_test_is_singular']     = false;
rwgc_pg_assert(
	'Query Loop / feed the_content hides for DE visitor',
	'' === RWGC_Gutenberg_Post_Geo::filter_post_content( 'SECRET' )
);
rwgc_pg_assert(
	'Query Loop / feed the_excerpt hides for DE visitor',
	'' === RWGC_Gutenberg_Post_Geo::filter_post_content( 'TEASER' )
);

$GLOBALS['rwgc_test_the_id'] = 0;
rwgc_pg_assert(
	'REST the_content without post setup is a no-op (rest_prepare handles it)',
	'SECRET' === RWGC_Gutenberg_Post_Geo::filter_post_content( 'SECRET' )
);

class RWGC_Test_REST_Response {
	/** @var array<string, mixed> */
	public $data;
	public function __construct( array $data ) {
		$this->data = $data;
	}
	public function get_data() {
		return $this->data;
	}
	public function set_data( $data ) {
		$this->data = $data;
	}
}

$payload = array(
	'content' => array(
		'raw'      => 'SECRET',
		'rendered' => '<p>SECRET</p>',
	),
	'excerpt' => array(
		'rendered' => '<p>TEASER</p>',
	),
);
$post = (object) array( 'ID' => 42 );

$GLOBALS['rwgc_test_the_id'] = 42;
define( 'REST_REQUEST', true );

$anon = RWGC_Gutenberg_Post_Geo::filter_rest_response( new RWGC_Test_REST_Response( $payload ), $post );
rwgc_pg_assert( 'anonymous REST empties content.rendered', '' === $anon->get_data()['content']['rendered'] );
rwgc_pg_assert( 'anonymous REST empties excerpt.rendered', '' === $anon->get_data()['excerpt']['rendered'] );
rwgc_pg_assert( 'anonymous REST keeps content.raw', 'SECRET' === $anon->get_data()['content']['raw'] );

$GLOBALS['rwgc_test_can_edit'] = true;
$editor = RWGC_Gutenberg_Post_Geo::filter_rest_response( new RWGC_Test_REST_Response( $payload ), $post );
rwgc_pg_assert( 'editor REST keeps rendered content', '<p>SECRET</p>' === $editor->get_data()['content']['rendered'] );
rwgc_pg_assert(
	'editor REST the_content bypass',
	'SECRET' === RWGC_Gutenberg_Post_Geo::filter_post_content( 'SECRET' )
);

$GLOBALS['rwgc_test_can_edit']        = false;
$GLOBALS['rwgc_test_post_meta'][42]   = array();
$inactive = RWGC_Gutenberg_Post_Geo::filter_rest_response( new RWGC_Test_REST_Response( $payload ), $post );
rwgc_pg_assert( 'inactive geo leaves REST content', '<p>SECRET</p>' === $inactive->get_data()['content']['rendered'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Gutenberg post geo tests passed.\n";
exit( 0 );
