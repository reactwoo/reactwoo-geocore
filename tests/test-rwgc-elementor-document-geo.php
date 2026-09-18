<?php
/**
 * Elementor document geo must hide content on REST, feeds, and loops.
 *
 * Usage: php tests/test-rwgc-elementor-document-geo.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_is_admin']        = false;
$GLOBALS['rwgc_test_is_singular']     = true;
$GLOBALS['rwgc_test_the_id']          = 42;
$GLOBALS['rwgc_test_queried_id']      = 42;
$GLOBALS['rwgc_test_can_edit']        = false;
$GLOBALS['rwgc_test_visitor_country'] = 'DE';
$GLOBALS['rwgc_test_post_meta']       = array();

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
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return false;
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
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
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
if ( ! function_exists( 'did_action' ) ) {
	function did_action() {
		return 1;
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

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-elementor.php';

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok OK.
 * @return void
 */
function rwgc_el_doc_assert( $label, $ok ) {
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
function rwgc_el_doc_us_only_page() {
	$GLOBALS['rwgc_test_post_meta'][42] = array(
		'_elementor_page_settings' => array(
			'egp_enable_geo_targeting'      => 'yes',
			'egp_countries'                 => array( 'US' ),
			'rwgc_visibility_mode'          => 'show_if',
			'rwgc_country_visibility_mode'  => 'show_if',
		),
	);
}

rwgc_el_doc_us_only_page();

rwgc_el_doc_assert(
	'singular HTML hides for DE visitor',
	'' === RWGC_Elementor::filter_document_content( 'SECRET' )
);

$GLOBALS['rwgc_test_visitor_country'] = 'US';
rwgc_el_doc_assert(
	'singular HTML shows for US visitor',
	'SECRET' === RWGC_Elementor::filter_document_content( 'SECRET' )
);

$GLOBALS['rwgc_test_visitor_country'] = 'DE';
$GLOBALS['rwgc_test_is_singular']     = false;
rwgc_el_doc_assert(
	'Query Loop / feed the_content hides for DE visitor',
	'' === RWGC_Elementor::filter_document_content( 'SECRET' )
);
rwgc_el_doc_assert(
	'Query Loop / feed the_excerpt hides for DE visitor',
	'' === RWGC_Elementor::filter_document_content( 'TEASER' )
);

$GLOBALS['rwgc_test_the_id'] = 0;
rwgc_el_doc_assert(
	'REST the_content without post setup is a no-op (rest_prepare handles it)',
	'SECRET' === RWGC_Elementor::filter_document_content( 'SECRET' )
);

class RWGC_Test_Elementor_REST_Response {
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
		'rendered' => '<div class="elementor elementor-42">SECRET</div>',
	),
	'excerpt' => array(
		'rendered' => '<p>TEASER</p>',
	),
);
$post = (object) array( 'ID' => 42 );

$GLOBALS['rwgc_test_the_id'] = 42;
define( 'REST_REQUEST', true );

$anon = RWGC_Elementor::filter_rest_response( new RWGC_Test_Elementor_REST_Response( $payload ), $post );
rwgc_el_doc_assert( 'anonymous REST empties content.rendered', '' === $anon->get_data()['content']['rendered'] );
rwgc_el_doc_assert( 'anonymous REST empties excerpt.rendered', '' === $anon->get_data()['excerpt']['rendered'] );
rwgc_el_doc_assert( 'anonymous REST keeps content.raw', 'SECRET' === $anon->get_data()['content']['raw'] );

$GLOBALS['rwgc_test_can_edit'] = true;
$editor = RWGC_Elementor::filter_rest_response( new RWGC_Test_Elementor_REST_Response( $payload ), $post );
rwgc_el_doc_assert( 'editor REST keeps rendered content', '<div class="elementor elementor-42">SECRET</div>' === $editor->get_data()['content']['rendered'] );
rwgc_el_doc_assert(
	'editor REST the_content bypass',
	'SECRET' === RWGC_Elementor::filter_document_content( 'SECRET' )
);

$GLOBALS['rwgc_test_can_edit']      = false;
$GLOBALS['rwgc_test_post_meta'][42] = array();
$inactive = RWGC_Elementor::filter_rest_response( new RWGC_Test_Elementor_REST_Response( $payload ), $post );
rwgc_el_doc_assert( 'inactive geo leaves REST content', '<div class="elementor elementor-42">SECRET</div>' === $inactive->get_data()['content']['rendered'] );

if ( $failed > 0 ) {
	fwrite( STDERR, "\n$failed assertion(s) failed\n" );
	exit( 1 );
}
echo "\nAll Elementor document geo tests passed.\n";
exit( 0 );
