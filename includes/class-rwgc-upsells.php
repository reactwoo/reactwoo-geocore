<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add-ons / upsell helper for Geo Core.
 */
class RWGC_Upsells {

	/**
	 * Init hooks (no-op for now).
	 *
	 * @return void
	 */
	public static function init() {
		// Reserved for future enhancements.
	}

	/**
	 * Get add-on cards definition.
	 *
	 * @return array[]
	 */
	public static function get_addons() {
		$addons = array(
			'geoelementor'  => array(
				'title'       => __( 'GeoElementor', 'reactwoo-geocore' ),
				'summary'     => __( 'Premium Elementor geo targeting with popups, sections, widgets, and analytics powered by ReactWoo Geo Core.', 'reactwoo-geocore' ),
				'image'       => RWGC_URL . 'assets/img/addon-geoelementor.png',
				'plugin_file' => 'GeoElementor/elementor-geo-popup.php',
				'cta_label'   => __( 'Learn more', 'reactwoo-geocore' ),
				'cta_url'     => 'https://reactwoo.com/',
			),
			'whmcs_bridge'  => array(
				'title'       => __( 'ReactWoo WHMCS Bridge', 'reactwoo-geocore' ),
				'summary'     => __( 'Sync WHMCS products into WordPress with geo-driven pricing and CTAs that respect visitor country and currency.', 'reactwoo-geocore' ),
				'image'       => RWGC_URL . 'assets/img/addon-whmcs-bridge.png',
				'plugin_file' => 'reactwoo-whmcs-bridge/reactwoo-whmcs-bridge.php',
				'cta_label'   => __( 'Learn more', 'reactwoo-geocore' ),
				'cta_url'     => 'https://reactwoo.com/',
			),
		);

		foreach ( $addons as $slug => &$addon ) {
			$status                 = self::get_addon_status( $addon['plugin_file'] );
			$addon['status']        = $status;
			$addon['status_label']  = 'active' === $status ? __( 'Active', 'reactwoo-geocore' ) : ( 'installed' === $status ? __( 'Installed', 'reactwoo-geocore' ) : __( 'Not installed', 'reactwoo-geocore' ) );
		}
		unset( $addon );

		return $addons;
	}

	/**
	 * Determine addon status.
	 *
	 * @param string $plugin_file Plugin file relative to plugins dir.
	 * @return string one of: active|installed|missing
	 */
	public static function get_addon_status( $plugin_file ) {
		if ( self::is_plugin_active( $plugin_file ) ) {
			return 'active';
		}
		if ( self::is_plugin_installed( $plugin_file ) ) {
			return 'installed';
		}
		return 'missing';
	}

	/**
	 * Whether plugin is installed.
	 *
	 * @param string $plugin_file Relative plugin file.
	 * @return bool
	 */
	public static function is_plugin_installed( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ] );
	}

	/**
	 * Whether plugin is active.
	 *
	 * @param string $plugin_file Relative plugin file.
	 * @return bool
	 */
	public static function is_plugin_active( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin_file );
	}
}

