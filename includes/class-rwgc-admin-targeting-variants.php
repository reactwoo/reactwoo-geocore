<?php
/**
 * Targeting → Variants table (country page variants, not experiments).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Country page variant rows for the Targeting Variants tab.
 */
class RWGC_Admin_Targeting_Variants {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_table_rows() {
		if ( ! class_exists( 'RWGC_Variant_Manager', false ) ) {
			return array();
		}

		$rows = array();
		foreach ( RWGC_Variant_Manager::get_routing_overview_rows() as $overview ) {
			if ( ! is_array( $overview ) ) {
				continue;
			}
			$master_id    = (int) ( $overview['master_id'] ?? 0 );
			$master_title = (string) ( $overview['master_title'] ?? '' );
			$variant      = isset( $overview['variant'] ) && is_array( $overview['variant'] ) ? $overview['variant'] : null;

			if ( ! is_array( $variant ) ) {
				$rows[] = array(
					'default_page'  => $master_title,
					'variant_page'  => '—',
					'type'          => __( 'Country', 'reactwoo-geocore' ),
					'condition'     => __( 'Not configured', 'reactwoo-geocore' ),
					'status'        => __( 'Needs variant', 'reactwoo-geocore' ),
					'status_tone'   => 'warning',
					'edit_url'      => admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_master_page_id=' . $master_id ),
					'preview_url'   => '',
					'master_id'     => $master_id,
				);
				continue;
			}

			$variant_id    = (int) ( $variant['variant_id'] ?? 0 );
			$variant_title = (string) ( $variant['variant_title'] ?? '' );
			$country       = (string) ( $variant['country_iso2'] ?? '' );
			$vp            = $variant_id > 0 ? get_post( $variant_id ) : null;
			$status        = __( 'Active', 'reactwoo-geocore' );
			$status_tone   = 'success';

			if ( ! $vp ) {
				$status      = __( 'Missing variant page', 'reactwoo-geocore' );
				$status_tone = 'error';
			} elseif ( in_array( $vp->post_status, array( 'draft', 'pending' ), true ) ) {
				$status      = __( 'Draft variant page', 'reactwoo-geocore' );
				$status_tone = 'warning';
			} elseif ( 'private' === $vp->post_status ) {
				$status      = __( 'Private variant page', 'reactwoo-geocore' );
				$status_tone = 'warning';
			}

			$rows[] = array(
				'default_page'  => $master_title,
				'variant_page'  => $variant_title,
				'type'          => __( 'Country', 'reactwoo-geocore' ),
				'condition'     => '' !== $country ? strtoupper( $country ) : __( 'Any visitor', 'reactwoo-geocore' ),
				'status'        => $status,
				'status_tone'   => $status_tone,
				'edit_url'      => admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_master_page_id=' . $master_id ),
				'preview_url'   => isset( $variant['view_variant'] ) ? (string) $variant['view_variant'] : '',
				'master_id'     => $master_id,
				'variant_id'    => $variant_id,
			);
		}

		/**
		 * @param array<int, array<string, mixed>> $rows Variant table rows.
		 */
		return apply_filters( 'rwgc_targeting_variant_rows', $rows );
	}
}
