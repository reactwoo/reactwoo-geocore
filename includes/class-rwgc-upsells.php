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
				'plugin_file' => 'geo-elementor/elementor-geo-popup.php',
				'alt_files'   => array( 'GeoElementor/elementor-geo-popup.php' ),
				'cta_label'   => __( 'Learn more', 'reactwoo-geocore' ),
				'cta_url'     => 'https://reactwoo.com/',
			),
			'whmcs_bridge'  => array(
				'title'       => __( 'ReactWoo WHMCS Bridge', 'reactwoo-geocore' ),
				'summary'     => __( 'Sync WHMCS products into WordPress with geo-driven pricing and CTAs that respect visitor country and currency.', 'reactwoo-geocore' ),
				'plugin_file' => 'reactwoo-whmcs-bridge/reactwoo-whmcs-bridge.php',
				'cta_label'   => __( 'Learn more', 'reactwoo-geocore' ),
				'cta_url'     => 'https://reactwoo.com/',
			),
		);

		foreach ( $addons as $slug => &$addon ) {
			$status                 = self::get_addon_status( $addon );
			$addon['status']        = $status;
			$addon['status_label']  = 'active' === $status ? __( 'Active', 'reactwoo-geocore' ) : ( 'installed' === $status ? __( 'Installed', 'reactwoo-geocore' ) : __( 'Not installed', 'reactwoo-geocore' ) );
			$addon['image']         = self::resolve_addon_image( $slug, $addon );
		}
		unset( $addon );

		return $addons;
	}

	/**
	 * Resolve add-on brand image with fallback strategy.
	 *
	 * Priority:
	 * 1) Declared addon image (if valid)
	 * 2) Local bundled fallback brand image (Geo Core admin assets)
	 * 3) Plugin-owned icon/banner asset (if plugin folder exists)
	 *
	 * @param string               $slug  Add-on slug.
	 * @param array<string, mixed> $addon Add-on definition.
	 * @return string
	 */
	private static function resolve_addon_image( $slug, $addon ) {
		$existing = isset( $addon['image'] ) && is_string( $addon['image'] ) ? trim( $addon['image'] ) : '';
		if ( '' !== $existing ) {
			return $existing;
		}

		$fallback = RWGC_PATH . 'admin/assets/brands/' . sanitize_key( $slug ) . '-card.svg';
		if ( file_exists( $fallback ) ) {
			return RWGC_URL . 'admin/assets/brands/' . sanitize_key( $slug ) . '-card.svg';
		}

		$plugin_file = isset( $addon['plugin_file'] ) && is_string( $addon['plugin_file'] ) ? $addon['plugin_file'] : '';
		$detected    = self::detect_plugin_brand_image( $plugin_file );
		if ( '' !== $detected ) {
			return $detected;
		}

		$default = RWGC_PATH . 'admin/assets/brands/reactwoo-default-card.svg';
		if ( file_exists( $default ) ) {
			return RWGC_URL . 'admin/assets/brands/reactwoo-default-card.svg';
		}
		return '';
	}

	/**
	 * Try to detect reusable plugin brand assets from known locations.
	 *
	 * @param string $plugin_file Relative plugin entrypoint file.
	 * @return string
	 */
	private static function detect_plugin_brand_image( $plugin_file ) {
		if ( '' === $plugin_file || false === strpos( $plugin_file, '/' ) ) {
			return '';
		}
		$plugin_dir = dirname( $plugin_file );
		if ( '.' === $plugin_dir || '' === $plugin_dir ) {
			return '';
		}

		$base_path = trailingslashit( WP_PLUGIN_DIR ) . $plugin_dir . '/';
		$base_url  = trailingslashit( content_url( 'plugins/' . $plugin_dir ) );

		$candidates = array(
			'assets/images/icon.svg',
			'assets/images/icon.png',
			'assets/images/icon-256x256.png',
			'assets/images/icon-128x128.png',
			'assets/images/logo.svg',
			'assets/images/logo.png',
			'assets/images/feature-banner.png',
			'assets/banner-772x250.png',
			'assets/icon-256x256.png',
			'assets/icon-128x128.png',
		);

		foreach ( $candidates as $rel ) {
			$path = $base_path . $rel;
			if ( file_exists( $path ) ) {
				return $base_url . $rel;
			}
		}
		return '';
	}

	/**
	 * Determine addon status.
	 *
	 * @param array<string, mixed>|string $addon_or_plugin_file Add-on definition or plugin file.
	 * @return string one of: active|installed|missing
	 */
	public static function get_addon_status( $addon_or_plugin_file ) {
		$plugin_paths = self::get_addon_plugin_paths( $addon_or_plugin_file );
		if ( self::is_plugin_active( $plugin_paths ) ) {
			return 'active';
		}
		if ( self::is_plugin_installed( $plugin_paths ) ) {
			return 'installed';
		}
		return 'missing';
	}

	/**
	 * Normalize add-on plugin paths (main file + alternate file candidates).
	 *
	 * @param array<string, mixed>|string $addon_or_plugin_file Add-on definition or plugin file.
	 * @return array<int, string>
	 */
	private static function get_addon_plugin_paths( $addon_or_plugin_file ) {
		$paths = array();
		if ( is_string( $addon_or_plugin_file ) ) {
			$paths[] = $addon_or_plugin_file;
		} elseif ( is_array( $addon_or_plugin_file ) ) {
			if ( ! empty( $addon_or_plugin_file['plugin_file'] ) && is_string( $addon_or_plugin_file['plugin_file'] ) ) {
				$paths[] = $addon_or_plugin_file['plugin_file'];
			}
			if ( ! empty( $addon_or_plugin_file['alt_files'] ) && is_array( $addon_or_plugin_file['alt_files'] ) ) {
				foreach ( $addon_or_plugin_file['alt_files'] as $alt_file ) {
					if ( is_string( $alt_file ) && '' !== $alt_file ) {
						$paths[] = $alt_file;
					}
				}
			}
		}
		return array_values( array_unique( $paths ) );
	}

	/**
	 * Whether plugin is installed.
	 *
	 * @param array<int, string>|string $plugin_files Relative plugin file(s).
	 * @return bool
	 */
	public static function is_plugin_installed( $plugin_files ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		$paths   = is_array( $plugin_files ) ? $plugin_files : array( $plugin_files );
		foreach ( $paths as $plugin_file ) {
			if ( is_string( $plugin_file ) && isset( $plugins[ $plugin_file ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether plugin is active.
	 *
	 * @param array<int, string>|string $plugin_files Relative plugin file(s).
	 * @return bool
	 */
	public static function is_plugin_active( $plugin_files ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$paths = is_array( $plugin_files ) ? $plugin_files : array( $plugin_files );
		foreach ( $paths as $plugin_file ) {
			if ( is_string( $plugin_file ) && is_plugin_active( $plugin_file ) ) {
				return true;
			}
		}
		return false;
	}
}

