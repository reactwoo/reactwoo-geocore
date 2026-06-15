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
 * Applies {@see RWGC_Product_Meta} on WooCommerce product visibility.
 */
class RWGC_Product_Visibility {

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'rwgc_is_woocommerce_active' ) || ! rwgc_is_woocommerce_active() ) {
			return;
		}
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'filter_product_visible' ), 20, 2 );
	}

	/**
	 * @param bool $visible  Default visibility.
	 * @param int  $product_id Product ID.
	 * @return bool
	 */
	public static function filter_product_visible( $visible, $product_id ) {
		if ( ! $visible ) {
			return false;
		}
		$product_id = absint( $product_id );
		if ( $product_id <= 0 || ! class_exists( 'RWGC_Product_Meta', false ) ) {
			return $visible;
		}
		if ( ! RWGC_Product_Meta::has_geo_override( $product_id ) ) {
			return $visible;
		}
		if ( ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			return $visible;
		}

		$settings = RWGC_Product_Meta::to_surface_settings( $product_id );
		if ( empty( $settings ) || ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
			return $visible;
		}

		$result = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );

		return ! empty( $result['should_render'] );
	}
}
