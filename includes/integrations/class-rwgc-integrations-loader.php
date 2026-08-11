<?php
/**
 * Loads GeoCore builder integrations (Elementor, Gutenberg).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration bootstrap.
 */
class RWGC_Integrations_Loader {

	/**
	 * @return void
	 */
	public static function init() {
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-config-debug.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-geo-controls.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-elements.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-frontend.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-popups.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-experience-slots.php';
		require_once RWGC_PATH . 'includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php';
		require_once RWGC_PATH . 'includes/integrations/class-rwgc-cache-compat.php';

		if ( function_exists( 'rwgc_is_woocommerce_active' ) && rwgc_is_woocommerce_active() ) {
			require_once RWGC_PATH . 'includes/integrations/woocommerce/class-rwgc-product-meta.php';
			require_once RWGC_PATH . 'includes/integrations/woocommerce/class-rwgc-product-visibility.php';
			require_once RWGC_PATH . 'includes/integrations/woocommerce/class-rwgc-admin-woocommerce-product-tab.php';
			RWGC_Admin_WooCommerce_Product_Tab::init();
			RWGC_Product_Visibility::init();
		}

		RWGC_Cache_Compat::init();
		RWGC_Elementor_Elements::init();
		RWGC_Elementor_Atomic_Geo::init();
		RWGC_Elementor_Frontend::init();
		RWGC_Elementor_Popups::init();
		RWGC_Elementor_Experience_Slots::init();
		RWGC_Gutenberg_Post_Geo::init();

		/**
		 * GeoCore now registers native Elementor element controls and frontend visibility.
		 *
		 * @param bool $active Default true when this loader ran.
		 */
		add_filter( 'rwgc_elementor_native_elements_active', '__return_true', 5 );
	}

	/**
	 * Whether GeoCore owns Elementor element-level controls (Geo Elementor should defer).
	 *
	 * @return bool
	 */
	public static function elementor_elements_active() {
		return (bool) apply_filters( 'rwgc_elementor_native_elements_active', false );
	}
}
