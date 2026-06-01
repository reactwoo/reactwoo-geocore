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
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-elements.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-frontend.php';
		require_once RWGC_PATH . 'includes/integrations/elementor/class-rwgc-elementor-popups.php';
		require_once RWGC_PATH . 'includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php';

		RWGC_Elementor_Elements::init();
		RWGC_Elementor_Frontend::init();
		RWGC_Elementor_Popups::init();
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
