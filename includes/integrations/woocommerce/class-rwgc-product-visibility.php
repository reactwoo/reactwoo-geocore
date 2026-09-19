<?php
/**
 * Storefront product visibility from GeoCore product-level targeting meta.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies {@see RWGC_Product_Meta} on WooCommerce catalog listings.
 *
 * `woocommerce_product_is_visible` only hides products in classic PHP templates.
 * WooCommerce Blocks / Store API collections never call that filter, so country
 * targeting must also exclude IDs from product queries.
 */
class RWGC_Product_Visibility {

	/**
	 * Hidden product IDs for this visitor (request memo).
	 *
	 * @var int[]|null
	 */
	private static $hidden_ids = null;

	/**
	 * @var bool
	 */
	private static $resolving_hidden = false;

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'rwgc_is_woocommerce_active' ) || ! rwgc_is_woocommerce_active() ) {
			return;
		}
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'filter_product_visible' ), 20, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'exclude_hidden_from_query' ), 20 );
		add_filter( 'woocommerce_shortcode_products_query', array( __CLASS__, 'filter_shortcode_query_args' ), 20 );
		add_filter( 'woocommerce_store_api_product_query', array( __CLASS__, 'filter_store_api_query_args' ), 20 );
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( __CLASS__, 'filter_data_store_query_args' ), 20, 2 );
	}

	/**
	 * Test helper — drop the per-request hidden-ID memo.
	 *
	 * @return void
	 */
	public static function reset_request_cache() {
		self::$hidden_ids        = null;
		self::$resolving_hidden  = false;
	}

	/**
	 * @param bool $visible    Default visibility.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function filter_product_visible( $visible, $product_id ) {
		if ( ! $visible ) {
			return false;
		}
		return self::is_allowed_for_visitor( $product_id );
	}

	/**
	 * Whether this product may appear for the current visitor.
	 *
	 * Variations inherit the parent product's GeoCore override when they have none.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool
	 */
	public static function is_allowed_for_visitor( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return true;
		}

		$direct = self::evaluate_product( $product_id );
		if ( null !== $direct ) {
			return $direct;
		}

		$parent_id = self::parent_product_id( $product_id );
		if ( $parent_id > 0 && $parent_id !== $product_id ) {
			$parent = self::evaluate_product( $parent_id );
			if ( null !== $parent ) {
				return $parent;
			}
		}

		return true;
	}

	/**
	 * Exclude geo-hidden products from frontend / Store API listing queries.
	 *
	 * Leaves the singular product page and authenticated admin/REST catalog alone
	 * so the existing product-URL contract is unchanged.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public static function exclude_hidden_from_query( $query ) {
		if ( ! self::should_filter_listing_query( $query ) ) {
			return;
		}

		$hidden = self::hidden_product_ids_for_visitor();
		if ( empty( $hidden ) ) {
			return;
		}

		$existing = $query->get( 'post__not_in' );
		$query->set( 'post__not_in', self::merge_not_in( $existing, $hidden ) );
	}

	/**
	 * @param array<string, mixed> $query_args Shortcode WP_Query args.
	 * @return array<string, mixed>
	 */
	public static function filter_shortcode_query_args( $query_args ) {
		if ( ! is_array( $query_args ) || ! self::is_public_product_listing_request() ) {
			return $query_args;
		}
		return self::append_hidden_to_query_args( $query_args );
	}

	/**
	 * @param array<string, mixed> $query_args Store API WP_Query args.
	 * @return array<string, mixed>
	 */
	public static function filter_store_api_query_args( $query_args ) {
		if ( ! is_array( $query_args ) || ! self::is_store_api_product_collection_request() ) {
			return $query_args;
		}
		return self::append_hidden_to_query_args( $query_args );
	}

	/**
	 * @param array<string, mixed> $wp_query_args Data-store WP_Query args.
	 * @param mixed                $query_vars    Unused query vars.
	 * @return array<string, mixed>
	 */
	public static function filter_data_store_query_args( $wp_query_args, $query_vars = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_array( $wp_query_args ) || ! self::is_public_product_listing_request() ) {
			return $wp_query_args;
		}
		return self::append_hidden_to_query_args( $wp_query_args );
	}

	/**
	 * Whether a WP_Query is a public product listing that must honor geo targeting.
	 *
	 * @param mixed $query Query.
	 * @return bool
	 */
	public static function should_filter_listing_query( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return false;
		}
		if ( ! self::is_public_product_listing_request() ) {
			return false;
		}
		if ( self::query_is_singular_product( $query ) ) {
			return false;
		}
		return self::query_targets_products( $query );
	}

	/**
	 * Store API product *collections* (shop, category, search, hand-picked grids).
	 * Single-product GET stays unfiltered so the classic product URL still works.
	 *
	 * @param string $uri Request URI or REST route. Empty reads the current request.
	 * @return bool
	 */
	public static function is_store_api_product_collection_request( $uri = '' ) {
		$path = self::request_path( $uri );
		if ( '' === $path ) {
			return false;
		}

		if ( ! preg_match( '#(?:^|/)wc/store(?:/v[0-9]+)?/products(?:/|$)#', $path ) ) {
			return false;
		}

		// `/products/123` is the single-product resource; `/products/123/variations` is a listing.
		if ( preg_match( '#/products/[0-9]+/?$#', $path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Frontend catalogs and Store API collections — not wp-admin or wc/v3 management.
	 *
	 * @return bool
	 */
	public static function is_public_product_listing_request() {
		if ( function_exists( 'is_admin' ) && is_admin() && ! self::is_rest_request() ) {
			return false;
		}

		if ( self::is_rest_request() ) {
			return self::is_store_api_product_collection_request();
		}

		return true;
	}

	/**
	 * @param mixed $existing Existing post__not_in.
	 * @param int[] $hidden   Hidden IDs.
	 * @return int[]
	 */
	public static function merge_not_in( $existing, array $hidden ) {
		$ids = array();
		if ( is_array( $existing ) ) {
			$ids = $existing;
		} elseif ( is_numeric( $existing ) && (int) $existing > 0 ) {
			$ids = array( (int) $existing );
		}
		foreach ( $hidden as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		return array_values( array_filter( $ids ) );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return bool|null True/false when an override applies; null when none.
	 */
	private static function evaluate_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 || ! class_exists( 'RWGC_Product_Meta', false ) ) {
			return null;
		}
		if ( ! RWGC_Product_Meta::has_geo_override( $product_id ) ) {
			return null;
		}
		if ( ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			return null;
		}

		$settings = RWGC_Product_Meta::to_surface_settings( $product_id );
		if ( empty( $settings ) || ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
			return null;
		}

		$result = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );
		return ! empty( $result['should_render'] );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return int
	 */
	private static function parent_product_id( $product_id ) {
		if ( function_exists( 'wp_get_post_parent_id' ) ) {
			return absint( wp_get_post_parent_id( $product_id ) );
		}
		return 0;
	}

	/**
	 * @return int[]
	 */
	private static function hidden_product_ids_for_visitor() {
		if ( is_array( self::$hidden_ids ) ) {
			return self::$hidden_ids;
		}
		if ( self::$resolving_hidden ) {
			return array();
		}

		self::$resolving_hidden = true;
		$hidden                 = array();

		if ( function_exists( 'get_posts' ) && class_exists( 'RWGC_Product_Meta', false ) ) {
			$candidates = get_posts(
				array(
					'post_type'              => array( 'product', 'product_variation' ),
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'posts_per_page'         => 2000,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'OR',
						array(
							'key'     => RWGC_Product_Meta::META_GEO_MODE,
							'value'   => array( RWGC_Product_Meta::GEO_MODE_HIDE_IN, RWGC_Product_Meta::GEO_MODE_SHOW_ONLY_IN ),
							'compare' => 'IN',
						),
						array(
							'key'     => RWGC_Product_Meta::META_RULE_IDS,
							'compare' => 'EXISTS',
						),
					),
				)
			);
			if ( is_array( $candidates ) ) {
				foreach ( $candidates as $id ) {
					$id = absint( $id );
					if ( $id > 0 && ! self::is_allowed_for_visitor( $id ) ) {
						$hidden[] = $id;
					}
				}
			}
		}

		self::$resolving_hidden = false;
		self::$hidden_ids       = array_values( array_unique( $hidden ) );
		return self::$hidden_ids;
	}

	/**
	 * @param array<string, mixed> $query_args Query args.
	 * @return array<string, mixed>
	 */
	private static function append_hidden_to_query_args( array $query_args ) {
		$hidden = self::hidden_product_ids_for_visitor();
		if ( empty( $hidden ) ) {
			return $query_args;
		}
		$existing                   = isset( $query_args['post__not_in'] ) ? $query_args['post__not_in'] : array();
		$query_args['post__not_in'] = self::merge_not_in( $existing, $hidden );
		return $query_args;
	}

	/**
	 * @param mixed $query Query.
	 * @return bool
	 */
	private static function query_targets_products( $query ) {
		$types = $query->get( 'post_type' );
		if ( empty( $types ) ) {
			if ( method_exists( $query, 'is_post_type_archive' ) && $query->is_post_type_archive( 'product' ) ) {
				return true;
			}
			$tax = $query->get( 'product_cat' );
			if ( empty( $tax ) ) {
				$tax = $query->get( 'product_tag' );
			}
			return ! empty( $tax );
		}

		foreach ( (array) $types as $type ) {
			if ( 'product' === $type || 'product_variation' === $type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $query Query.
	 * @return bool
	 */
	private static function query_is_singular_product( $query ) {
		if ( method_exists( $query, 'is_singular' ) && $query->is_singular( 'product' ) ) {
			return true;
		}
		$p = absint( $query->get( 'p' ) );
		if ( $p > 0 && 1 === absint( $query->get( 'posts_per_page' ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	private static function is_rest_request() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * @param string $uri URI or route.
	 * @return string
	 */
	private static function request_path( $uri ) {
		if ( '' === $uri ) {
			if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
				$uri = (string) $GLOBALS['wp']->query_vars['rest_route'];
			} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}

		$uri = (string) $uri;
		if ( '' === $uri ) {
			return '';
		}

		$path = $uri;
		$qpos = strpos( $uri, '?' );
		if ( false !== $qpos ) {
			$path = substr( $uri, 0, $qpos );
		}

		return strtolower( rtrim( $path, '/' ) );
	}
}
