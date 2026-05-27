<?php
/**
 * Integrations registry for the ReactWoo Geo platform shell (Integrations section).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects third-party connection rows for the Integrations hub and diagnostics.
 */
class RWGC_Platform_Integrations {

	/**
	 * @return void
	 */
	public static function init() {
		// Reserved for future hooks.
	}

	/**
	 * Registered integration rows.
	 *
	 * Each item: id, label, status (connected|warning|neutral), description, url, provider (optional).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_items() {
		$items = array();

		if ( ! function_exists( 'rwgc_is_pro_enabled' ) || ! rwgc_is_pro_enabled() ) {
			$items[] = array(
				'id'          => 'geocore_pro',
				'label'       => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'status'      => 'neutral',
				'description' => __( 'Unlock Google Ads, GA4 audiences, weather, and advanced targeting.', 'reactwoo-geocore' ),
				'url'         => admin_url( 'admin.php?page=rwgc-addons' ),
				'provider'    => 'geocore_pro',
			);
		}

		/**
		 * Register integration status rows for the Integrations hub.
		 *
		 * @param array<int, array<string, mixed>> $items Integration rows.
		 */
		$items = apply_filters( 'rwgc_platform_integrations', $items );

		return self::sanitize_items( is_array( $items ) ? $items : array() );
	}

	/**
	 * @param array<int, array<string, mixed>> $items Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_items( array $items ) {
		$out   = array();
		$seen  = array();
		$allowed_status = array( 'connected', 'warning', 'neutral' );

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = isset( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$status      = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : 'neutral';
			if ( ! in_array( $status, $allowed_status, true ) ) {
				$status = 'neutral';
			}
			$url = isset( $item['url'] ) ? esc_url_raw( (string) $item['url'] ) : '';
			$out[] = array(
				'id'          => $id,
				'label'       => isset( $item['label'] ) ? (string) $item['label'] : $id,
				'status'      => $status,
				'description' => isset( $item['description'] ) ? (string) $item['description'] : '',
				'url'         => $url,
				'provider'    => isset( $item['provider'] ) ? sanitize_key( (string) $item['provider'] ) : '',
			);
		}

		return $out;
	}
}
