<?php
/**
 * Suite product + entitlement discovery for capability-aware AI workflows.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only map of which Geo suite products are installed, licensed, and usable.
 */
class RWGC_Suite_Capability_Map {

	/**
	 * @return array<string, mixed>
	 */
	public static function get_map() {
		$map = array(
			'geocore_active'            => true,
			'geocore_version'           => defined( 'RWGC_VERSION' ) ? (string) RWGC_VERSION : '',
			'geocore_pro_active'        => self::plugin_active( 'reactwoo-geocore-pro/reactwoo-geocore-pro.php' ),
			'geocore_pro_licensed'      => function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled(),
			'geo_ai_active'             => self::plugin_active( 'reactwoo-geo-ai/reactwoo-geo-ai.php' ),
			'geo_ai_licensed'           => false,
			'geo_optimise_active'       => self::plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ),
			'geo_optimise_licensed'     => false,
			'legacy_geo_ai_detected'    => self::plugin_active( 'reactwoo-geo-ai/reactwoo-geo-ai.php' ),
			'optimise'                  => array(
				'active'          => self::plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ),
				'version'         => defined( 'RWGO_VERSION' ) ? (string) RWGO_VERSION : '',
				'license'         => 'inactive',
				'ai_review'       => false,
				'recommendations' => false,
				'drafts'          => false,
				'experiments'     => self::plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ),
				'goals'           => self::plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ),
				'reports'         => self::plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ),
			),
			'geo_commerce_active'       => self::plugin_active( 'reactwoo-geo-commerce/reactwoo-geo-commerce.php' ),
			'geo_commerce_licensed'     => self::plugin_active( 'reactwoo-geo-commerce/reactwoo-geo-commerce.php' ),
			'woocommerce_active'        => class_exists( 'WooCommerce', false ),
			'elementor_active'          => did_action( 'elementor/loaded' ) || self::plugin_active( 'elementor/elementor.php' ),
			'gutenberg_available'       => function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( 'page' ),
			'remote_ai_available'       => false,
			'local_fallback_available'  => true,
		);

		if ( $map['geocore_pro_active'] && class_exists( 'RWGCP_Bootstrap', false ) && method_exists( 'RWGCP_Bootstrap', 'is_licensed' ) ) {
			$map['geocore_pro_licensed'] = (bool) RWGCP_Bootstrap::is_licensed();
		}

		if ( $map['geo_ai_active'] && class_exists( 'RWGA_License', false ) ) {
			$map['geo_ai_licensed'] = (bool) RWGA_License::can_run_workflows();
		}

		if ( $map['geo_ai_active'] && class_exists( 'RWGA_Engine', false ) ) {
			$map['remote_ai_available'] = (bool) RWGA_Engine::should_try_remote();
		}

		if ( $map['geo_optimise_active'] && class_exists( 'RWGO_Platform_Client', false ) ) {
			$map['geo_optimise_licensed'] = RWGO_Platform_Client::is_configured();
		}

		if ( ! $map['geo_commerce_licensed'] || ! $map['woocommerce_active'] ) {
			$map['geo_commerce_licensed'] = false;
		}

		/**
		 * @param array<string, mixed> $map Suite capability map.
		 */
		return apply_filters( 'rwgc_suite_capability_map', $map );
	}

	/**
	 * @param string $plugin_file Plugin bootstrap file.
	 * @return bool
	 */
	private static function plugin_active( $plugin_file ) {
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			return RWGC_Admin_UI::is_plugin_active( $plugin_file );
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin_file );
	}
}
