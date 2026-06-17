<?php
/**
 * Central product capability registry for targeting, experiences, and upgrade UX.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability-aware gating for assistants, wizards, and admin cards.
 */
class RWGC_Capability_Registry {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_definitions() {
		$defs = array(
			'country_variants'       => array(
				'label'       => __( 'Country variants', 'reactwoo-geocore' ),
				'description' => __( 'Route matching visitors to a different full page by country.', 'reactwoo-geocore' ),
				'product'     => __( 'Geo Core', 'reactwoo-geocore' ),
				'area'        => 'targeting',
				'badge'       => __( 'Included', 'reactwoo-geocore' ),
				'available'   => true,
			),
			'advanced_variants'      => array(
				'label'          => __( 'Advanced variants', 'reactwoo-geocore' ),
				'description'    => __( 'Audience, campaign, weather, time, and ads-based page variants.', 'reactwoo-geocore' ),
				'product'        => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'area'           => 'targeting',
				'badge'          => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'requires_pro'   => true,
				'upgrade_slug'   => 'reactwoo-geocore-pro',
			),
			'country_rules'          => array(
				'label'       => __( 'Country rules', 'reactwoo-geocore' ),
				'description' => __( 'Show, hide, or change content inside a page by country.', 'reactwoo-geocore' ),
				'product'     => __( 'Geo Core', 'reactwoo-geocore' ),
				'area'        => 'targeting',
				'badge'       => __( 'Included', 'reactwoo-geocore' ),
				'available'   => true,
			),
			'advanced_rules'         => array(
				'label'        => __( 'Advanced rules', 'reactwoo-geocore' ),
				'description'  => __( 'Audience, campaign, weather, and time-window content rules.', 'reactwoo-geocore' ),
				'product'      => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'area'         => 'targeting',
				'badge'        => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'requires_pro' => true,
				'upgrade_slug' => 'reactwoo-geocore-pro',
			),
			'experiences'            => array(
				'label'           => __( 'Experiences', 'reactwoo-geocore' ),
				'description'     => __( 'Split traffic, measure conversions, and choose a winner.', 'reactwoo-geocore' ),
				'product'         => __( 'Geo Optimise', 'reactwoo-geocore' ),
				'area'            => 'experiences',
				'badge'           => __( 'Geo Optimise', 'reactwoo-geocore' ),
				'requires_plugin' => 'reactwoo-geo-optimise/reactwoo-geo-optimise.php',
				'admin_slug'      => 'rwgo-dashboard',
			),
			'commerce_rules'         => array(
				'label'           => __( 'Commerce rules', 'reactwoo-geocore' ),
				'description'     => __( 'Pricing, shipping, and coupon rules for WooCommerce.', 'reactwoo-geocore' ),
				'product'         => __( 'Geo Commerce', 'reactwoo-geocore' ),
				'area'            => 'targeting',
				'badge'           => __( 'Geo Commerce', 'reactwoo-geocore' ),
				'requires_plugin' => 'reactwoo-geo-commerce/reactwoo-geo-commerce.php',
				'requires_wc'     => true,
			),
			'variant_type_audience'  => array(
				'label'        => __( 'Audience', 'reactwoo-geocore' ),
				'product'      => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'badge'        => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'requires_pro' => true,
				'parent'       => 'advanced_variants',
			),
			'variant_type_campaign'  => array(
				'label'        => __( 'Campaign', 'reactwoo-geocore' ),
				'product'      => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'badge'        => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'requires_pro' => true,
				'parent'       => 'advanced_variants',
			),
			'variant_type_weather'   => array(
				'label'        => __( 'Weather', 'reactwoo-geocore' ),
				'product'      => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'badge'        => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'requires_pro' => true,
				'parent'       => 'advanced_variants',
			),
			'variant_type_time'      => array(
				'label'        => __( 'Time', 'reactwoo-geocore' ),
				'product'      => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'badge'        => __( 'GeoCore Pro', 'reactwoo-geocore' ),
				'requires_pro' => true,
				'parent'       => 'advanced_variants',
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
			return array(
				'state'         => $pro_on ? 'available' : 'locked',
				'label'         => $label,
				'badge'         => $badge,
				'reason'        => $pro_on
					? __( 'GeoCore Pro is active on this site.', 'reactwoo-geocore' )
					: __( 'Requires GeoCore Pro licence.', 'reactwoo-geocore' ),
				'learn_url'     => admin_url( 'admin.php?page=rwgc-addons' ),
				'activate_url'  => admin_url( 'admin.php?page=rwgc-settings-hub' ),
				'upgrade_url'   => admin_url( 'admin.php?page=rwgc-addons' ),
			);
		}

		if ( ! empty( $def['requires_plugin'] ) ) {
			$plugin = (string) $def['requires_plugin'];
			$active = is_plugin_active( $plugin );
			if ( ! $active && ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				$active = is_plugin_active( $plugin );
			}
			if ( ! empty( $def['requires_wc'] ) && ! class_exists( 'WooCommerce', false ) ) {
				return array(
					'state'        => 'locked',
					'label'        => $label,
					'badge'        => __( 'Requires WooCommerce', 'reactwoo-geocore' ),
					'reason'       => __( 'Install and activate WooCommerce first.', 'reactwoo-geocore' ),
					'learn_url'    => admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ),
					'activate_url' => '',
					'upgrade_url'  => admin_url( 'admin.php?page=rwgc-addons' ),
				);
			}
			$slug = isset( $def['admin_slug'] ) ? (string) $def['admin_slug'] : '';
			return array(
				'state'         => $active ? 'available' : 'not_installed',
				'label'         => $label,
				'badge'         => $badge,
				'reason'        => $active
					? sprintf(
						/* translators: %s: product name */
						__( '%s is installed.', 'reactwoo-geocore' ),
						(string) ( $def['product'] ?? '' )
					)
					: sprintf(
						/* translators: %s: product name */
						__( 'Install %s to unlock this.', 'reactwoo-geocore' ),
						(string) ( $def['product'] ?? '' )
					),
				'learn_url'     => admin_url( 'admin.php?page=rwgc-addons' ),
				'activate_url'  => $slug ? admin_url( 'admin.php?page=' . $slug ) : '',
				'upgrade_url'   => admin_url( 'admin.php?page=rwgc-addons' ),
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
