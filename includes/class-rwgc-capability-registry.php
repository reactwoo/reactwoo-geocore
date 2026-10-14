<?php
/**
 * Central product capability registry for targeting included with Geo Core.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capabilities included with this plugin. Other plugins add their own through rwgc_capability_definitions.
 */
class RWGC_Capability_Registry {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_definitions() {
		$defs = array(
			'country_rules' => array(
				'label'       => __( 'Visibility rules', 'reactwoo-geocore' ),
				'description' => __( 'Show or hide content by country, language, device, time, visitor, page, and request parameters.', 'reactwoo-geocore' ),
				'product'     => __( 'Geo Core', 'reactwoo-geocore' ),
				'area'        => 'targeting',
				'badge'       => __( 'Included', 'reactwoo-geocore' ),
				'available'   => true,
			),
		);

		/**
		 * @param array<string, array<string, mixed>> $defs Capability definitions.
		 */
		return apply_filters( 'rwgc_capability_definitions', $defs );
	}

	/**
	 * @param string $id Capability id.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		$id   = sanitize_key( (string) $id );
		$defs = self::get_definitions();
		return isset( $defs[ $id ] ) ? $defs[ $id ] : null;
	}

	/**
	 * @param string $id Capability id.
	 * @return array{state:string,label:string,badge:string,reason:string,learn_url:string,activate_url:string,upgrade_url:string}
	 */
	public static function get_status( $id ) {
		$def = self::get( $id );
		if ( ! is_array( $def ) ) {
			return array(
				'state'         => 'unavailable',
				'label'         => '',
				'badge'         => '',
				'reason'        => '',
				'learn_url'     => '',
				'activate_url'  => '',
				'upgrade_url'   => '',
			);
		}

		$label = (string) ( $def['label'] ?? '' );
		$badge = (string) ( $def['badge'] ?? '' );

		if ( ! empty( $def['available'] ) ) {
			return array(
				'state'         => 'included',
				'label'         => $label,
				'badge'         => $badge ? $badge : __( 'Included', 'reactwoo-geocore' ),
				'reason'        => __( 'Included with Geo Core.', 'reactwoo-geocore' ),
				'learn_url'     => '',
				'activate_url'  => '',
				'upgrade_url'   => '',
			);
		}

		if ( ! empty( $def['requires_pro'] ) ) {
			$pro_on = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
			if ( ! $pro_on ) {
				return array(
					'state'        => 'unavailable',
					'label'        => '',
					'badge'        => '',
					'reason'       => '',
					'learn_url'    => '',
					'activate_url' => '',
					'upgrade_url'  => '',
				);
			}
			return array(
				'state'        => 'available',
				'label'        => $label,
				'badge'        => $badge,
				'reason'       => __( 'Available on this site.', 'reactwoo-geocore' ),
				'learn_url'    => '',
				'activate_url' => '',
				'upgrade_url'  => '',
			);
		}

		if ( ! empty( $def['requires_plugin'] ) ) {
			$plugin = (string) $def['requires_plugin'];
			$active = function_exists( 'is_plugin_active' ) ? is_plugin_active( $plugin ) : false;
			if ( ! $active && ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$active = is_plugin_active( $plugin );
			}
			if ( ! $active || ( ! empty( $def['requires_wc'] ) && ! class_exists( 'WooCommerce', false ) ) ) {
				return array(
					'state'        => 'unavailable',
					'label'        => '',
					'badge'        => '',
					'reason'       => '',
					'learn_url'    => '',
					'activate_url' => '',
					'upgrade_url'  => '',
				);
			}
			$slug = isset( $def['admin_slug'] ) ? (string) $def['admin_slug'] : '';
			return array(
				'state'        => 'available',
				'label'        => $label,
				'badge'        => $badge,
				'reason'       => __( 'Available on this site.', 'reactwoo-geocore' ),
				'learn_url'    => '',
				'activate_url' => $slug ? admin_url( 'admin.php?page=' . $slug ) : '',
				'upgrade_url'  => '',
			);
		}

		return array(
			'state'        => 'unavailable',
			'label'        => $label,
			'badge'        => $badge,
			'reason'       => '',
			'learn_url'    => '',
			'activate_url' => '',
			'upgrade_url'  => '',
		);
	}

	/**
	 * @param string $id Capability id.
	 * @return bool
	 */
	public static function is_usable( $id ) {
		$status = self::get_status( $id );
		return in_array( $status['state'], array( 'included', 'available' ), true );
	}

	/**
	 * Export for assistant JS.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function export_for_assistant() {
		$out = array();
		foreach ( array_keys( self::get_definitions() ) as $id ) {
			$out[ $id ] = self::get_status( $id );
		}
		return $out;
	}

	/**
	 * @param string $id Capability id.
	 * @return void
	 */
	public static function render_badge( $id ) {
		$status = self::get_status( $id );
		if ( '' === $status['badge'] ) {
			return;
		}
		$tone = 'included' === $status['state'] ? 'success' : ( 'locked' === $status['state'] || 'not_installed' === $status['state'] ? 'neutral' : 'info' );
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_badge( (string) $status['badge'], $tone );
			return;
		}
		echo '<span class="rwgc-cap-badge rwgc-cap-badge--' . esc_attr( $tone ) . '">' . esc_html( (string) $status['badge'] ) . '</span>';
	}
}
