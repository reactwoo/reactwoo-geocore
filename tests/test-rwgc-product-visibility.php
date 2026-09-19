<?php
/**
 * Product geo visibility — catalog listings must honor country targeting.
 *
 * Usage: php tests/test-rwgc-product-visibility.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['rwgc_test_meta']     = array();
$GLOBALS['rwgc_test_parents']  = array();
$GLOBALS['rwgc_test_country']  = 'GB';
$GLOBALS['rwgc_test_is_admin'] = false;

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $str Value.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return is_scalar( $str ) ? (string) $str : '';
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Value.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $maybeint Value.
	 * @return int
	 */
	function absint( $maybeint ) {
		return (int) abs( (float) $maybeint );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @return true
	 */
	function add_filter() {
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @return true
	 */
	function add_action() {
		return true;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['rwgc_test_is_admin'] );
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		$row = isset( $GLOBALS['rwgc_test_meta'][ (int) $post_id ][ $key ] )
			? $GLOBALS['rwgc_test_meta'][ (int) $post_id ][ $key ]
			: '';
		return $single ? $row : array( $row );
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args Args.
	 * @return int[]
	 */
	function get_posts( $args = array() ) {
		unset( $args );
		return array( 10, 11, 12 );
	}
}
if ( ! function_exists( 'wp_get_post_parent_id' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return int
	 */
	function wp_get_post_parent_id( $post_id ) {
		return isset( $GLOBALS['rwgc_test_parents'][ (int) $post_id ] )
			? (int) $GLOBALS['rwgc_test_parents'][ (int) $post_id ]
			: 0;
	}
}
if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return (string) $GLOBALS['rwgc_test_country'];
	}
}
if ( ! function_exists( 'rwgc_normalize_visibility_mode' ) ) {
	/**
	 * @param mixed $mode Mode.
	 * @return string
	 */
	function rwgc_normalize_visibility_mode( $mode ) {
		$raw = sanitize_key( (string) $mode );
		return in_array( $raw, array( 'hide_if', 'hide', 'restrict', 'suppress' ), true ) ? 'hide_if' : 'show_if';
	}
}
if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
	/**
	 * @param mixed $mode    Mode.
	 * @param bool  $matched Match.
	 * @return bool
	 */
	function rwgc_visibility_mode_allows_render( $mode, $matched ) {
		return 'hide_if' === rwgc_normalize_visibility_mode( $mode ) ? ! $matched : (bool) $matched;
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__ ) . '/includes/integrations/woocommerce/class-rwgc-product-meta.php';
require_once dirname( __DIR__ ) . '/includes/integrations/woocommerce/class-rwgc-product-visibility.php';

/**
 * Minimal WP_Query stand-in.
 */
class RWGC_Test_Product_Query {
	/**
	 * @var array<string, mixed>
	 */
	public $vars = array();

	/**
	 * @var bool
	 */
	public $singular = false;

	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : '';
	}

	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}

	/**
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function is_singular( $post_type = '' ) {
		return $this->singular && ( '' === $post_type || 'product' === $post_type );
	}

	/**
	 * @return bool
	 */
	public function is_post_type_archive() {
		return false;
	}
}

$failed = 0;

/**
 * @param string $label Label.
 * @param bool   $ok    OK.
 * @return void
 */
function rwgc_pv_assert( $label, $ok ) {
	global $failed;
	if ( $ok ) {
		echo "OK  $label\n";
		return;
	}
	++$failed;
	echo "FAIL $label\n";
}

/**
 * @param int    $id      Product ID.
 * @param string $mode    Geo mode.
 * @param string[] $countries Countries.
 * @return void
 */
function rwgc_pv_set_product( $id, $mode, array $countries ) {
	$GLOBALS['rwgc_test_meta'][ $id ] = array(
		RWGC_Product_Meta::META_GEO_MODE  => $mode,
		RWGC_Product_Meta::META_COUNTRIES => $countries,
		RWGC_Product_Meta::META_RULE_IDS  => array(),
	);
}

rwgc_pv_set_product( 10, RWGC_Product_Meta::GEO_MODE_SHOW_ONLY_IN, array( 'US' ) );
rwgc_pv_set_product( 11, RWGC_Product_Meta::GEO_MODE_HIDE_IN, array( 'GB' ) );
rwgc_pv_set_product( 12, RWGC_Product_Meta::GEO_MODE_GLOBAL, array() );

$GLOBALS['rwgc_test_country'] = 'GB';
RWGC_Product_Visibility::reset_request_cache();

rwgc_pv_assert( 'US-only product hidden from GB visitor', false === RWGC_Product_Visibility::is_allowed_for_visitor( 10 ) );
rwgc_pv_assert( 'hide-in-GB product hidden from GB visitor', false === RWGC_Product_Visibility::is_allowed_for_visitor( 11 ) );
rwgc_pv_assert( 'global product still visible', true === RWGC_Product_Visibility::is_allowed_for_visitor( 12 ) );
rwgc_pv_assert( 'is_visible filter hides US-only for GB', false === RWGC_Product_Visibility::filter_product_visible( true, 10 ) );

$GLOBALS['rwgc_test_country'] = 'US';
RWGC_Product_Visibility::reset_request_cache();
rwgc_pv_assert( 'US-only product visible to US visitor', true === RWGC_Product_Visibility::is_allowed_for_visitor( 10 ) );
rwgc_pv_assert( 'hide-in-GB product visible to US visitor', true === RWGC_Product_Visibility::is_allowed_for_visitor( 11 ) );

$GLOBALS['rwgc_test_parents'][20] = 10;
$GLOBALS['rwgc_test_country']     = 'GB';
RWGC_Product_Visibility::reset_request_cache();
rwgc_pv_assert( 'variation inherits parent US-only hide', false === RWGC_Product_Visibility::is_allowed_for_visitor( 20 ) );

rwgc_pv_assert(
	'Store API collection is a listing',
	true === RWGC_Product_Visibility::is_store_api_product_collection_request( '/wp-json/wc/store/v1/products?category=12' )
);
rwgc_pv_assert(
	'Store API single product is not a listing',
	false === RWGC_Product_Visibility::is_store_api_product_collection_request( '/wp-json/wc/store/v1/products/10' )
);
rwgc_pv_assert(
	'Store API variations stay a listing',
	true === RWGC_Product_Visibility::is_store_api_product_collection_request( '/wp-json/wc/store/v1/products/10/variations' )
);
rwgc_pv_assert(
	'wc/v3 management is not a Store API listing',
	false === RWGC_Product_Visibility::is_store_api_product_collection_request( '/wp-json/wc/v3/products' )
);

$listing = new RWGC_Test_Product_Query();
$listing->vars['post_type'] = 'product';
rwgc_pv_assert( 'frontend product query is filtered', true === RWGC_Product_Visibility::should_filter_listing_query( $listing ) );

$singular = new RWGC_Test_Product_Query();
$singular->vars['post_type'] = 'product';
$singular->singular          = true;
rwgc_pv_assert( 'singular product page is not filtered', false === RWGC_Product_Visibility::should_filter_listing_query( $singular ) );

$GLOBALS['rwgc_test_is_admin'] = true;
$admin_q                       = new RWGC_Test_Product_Query();
$admin_q->vars['post_type']    = 'product';
rwgc_pv_assert( 'admin product list is not filtered', false === RWGC_Product_Visibility::should_filter_listing_query( $admin_q ) );
$GLOBALS['rwgc_test_is_admin'] = false;

$merged = RWGC_Product_Visibility::merge_not_in( array( 3 ), array( 10, 11 ) );
rwgc_pv_assert( 'merge_not_in keeps existing IDs', array( 3, 10, 11 ) === $merged );

$GLOBALS['rwgc_test_country'] = 'GB';
RWGC_Product_Visibility::reset_request_cache();
$shop = new RWGC_Test_Product_Query();
$shop->vars['post_type']     = 'product';
$shop->vars['post__not_in']  = array( 3 );
RWGC_Product_Visibility::exclude_hidden_from_query( $shop );
rwgc_pv_assert(
	'GB shop query excludes US-only and hide-in-GB products',
	array( 3, 10, 11 ) === $shop->get( 'post__not_in' )
);

$store_args = RWGC_Product_Visibility::filter_store_api_query_args( array( 'post_type' => 'product' ) );
rwgc_pv_assert(
	'Store API args stay untouched when URI is not a collection',
	array( 'post_type' => 'product' ) === $store_args
);
$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/products';
$store_args             = RWGC_Product_Visibility::filter_store_api_query_args( array( 'post_type' => 'product' ) );
rwgc_pv_assert( 'Store API collection args exclude hidden IDs', array( 10, 11 ) === $store_args['post__not_in'] );
unset( $_SERVER['REQUEST_URI'] );

if ( $failed > 0 ) {
	echo "FAILED $failed\n";
	exit( 1 );
}

echo "ALL PASSED\n";
exit( 0 );
