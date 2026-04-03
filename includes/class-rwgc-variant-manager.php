<?php
/**
 * Geo Suite — guided variant creation (wraps {@see RWGC_Routing} meta).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates master/secondary relationships without requiring the page meta box UI.
 */
class RWGC_Variant_Manager {

	/**
	 * Create a country-specific variant page linked to a master page (free tier: one variant per master).
	 *
	 * @param int    $master_page_id Master page ID.
	 * @param string $country_iso2   ISO2 country code.
	 * @param string $mode           duplicate|blank.
	 * @return array<string, mixed>|\WP_Error Keys: variant_page_id, master_page_id, edit_url.
	 */
	public static function create_country_variant( $master_page_id, $country_iso2, $mode = 'duplicate' ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new \WP_Error( 'rwgc_vm_forbidden', __( 'You do not have permission to create pages.', 'reactwoo-geocore' ) );
		}

		$master_page_id = absint( $master_page_id );
		$country_iso2   = strtoupper( sanitize_text_field( (string) $country_iso2 ) );
		$mode           = 'blank' === $mode ? 'blank' : 'duplicate';

		if ( $master_page_id <= 0 ) {
			return new \WP_Error( 'rwgc_vm_master', __( 'Select a valid default page.', 'reactwoo-geocore' ) );
		}

		if ( ! preg_match( '/^[A-Z]{2}$/', $country_iso2 ) ) {
			return new \WP_Error( 'rwgc_vm_country', __( 'Choose a valid country.', 'reactwoo-geocore' ) );
		}

		$master = get_post( $master_page_id );
		if ( ! $master || 'page' !== $master->post_type ) {
			return new \WP_Error( 'rwgc_vm_master_post', __( 'The selected page was not found.', 'reactwoo-geocore' ) );
		}

		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return new \WP_Error( 'rwgc_vm_routing', __( 'Routing is not available.', 'reactwoo-geocore' ) );
		}

		if ( RWGC_Routing::master_has_variant( $master_page_id, 0 ) ) {
			return new \WP_Error(
				'rwgc_vm_limit',
				__( 'This default page already has a country-specific version. Free Geo Core allows one linked version per page — upgrade to GeoElementor for more.', 'reactwoo-geocore' )
			);
		}

		if ( RWGC_Routing::is_variant_country_taken( $master_page_id, $country_iso2, 0 ) ) {
			return new \WP_Error( 'rwgc_vm_taken', __( 'That country is already used for another variant of this page.', 'reactwoo-geocore' ) );
		}

		$country_name = $country_iso2;
		if ( class_exists( 'RWGC_Countries', false ) ) {
			$opts = RWGC_Countries::get_options();
			if ( isset( $opts[ $country_iso2 ] ) ) {
				$country_name = (string) $opts[ $country_iso2 ];
			}
		}

		$base_title = get_the_title( $master );
		$title      = sprintf(
			/* translators: 1: page title, 2: country name */
			__( '%1$s — %2$s', 'reactwoo-geocore' ),
			$base_title,
			$country_name
		);

		$content = '';
		if ( 'duplicate' === $mode && isset( $master->post_content ) ) {
			$content = $master->post_content;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$new_id = absint( $new_id );
		if ( $new_id <= 0 ) {
			return new \WP_Error( 'rwgc_vm_insert', __( 'Could not create the new page.', 'reactwoo-geocore' ) );
		}

		// Ensure master is configured as the default page for routing.
		$mconf = RWGC_Routing::get_page_route_config( $master_page_id );
		RWGC_Routing::save_page_route_config(
			$master_page_id,
			array_merge(
				$mconf,
				array(
					'enabled' => true,
					'role'    => 'master',
				)
			)
		);

		RWGC_Routing::save_page_route_config(
			$new_id,
			array(
				'enabled'         => true,
				'role'            => 'variant',
				'master_page_id'  => $master_page_id,
				'country_iso2'    => $country_iso2,
				'default_page_id' => 0,
				'country_page_id' => 0,
			)
		);

		$edit_url = get_edit_post_link( $new_id, 'raw' );

		$result = array(
			'variant_page_id' => $new_id,
			'master_page_id'  => $master_page_id,
			'edit_url'        => $edit_url ? (string) $edit_url : '',
			'country_iso2'    => $country_iso2,
		);

		/**
		 * Fires after a guided variant page is created.
		 *
		 * @param array<string, mixed> $result Keys: variant_page_id, master_page_id, edit_url, country_iso2.
		 */
		do_action( 'rwgc_variant_created', $result );

		if ( class_exists( 'RWGC_Onboarding', false ) ) {
			RWGC_Onboarding::log_activity(
				'variant',
				array(
					/* translators: %s: page title */
					'title' => sprintf( __( 'Variant created: %s', 'reactwoo-geocore' ), $title ),
					'url'   => $result['edit_url'],
				)
			);
		}

		return $result;
	}

	/**
	 * List master → variant relationships for Suite “Page versions” overview.
	 *
	 * @param int $limit Max masters to scan.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_routing_overview_rows( $limit = 80 ) {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return array();
		}

		$masters = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => $limit,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => array(
					array(
						'key'   => RWGC_Routing::META_ENABLED,
						'value' => '1',
					),
					array(
						'key'   => RWGC_Routing::META_ROLE,
						'value' => 'master',
					),
				),
			)
		);

		$rows = array();
		foreach ( $masters as $master_post ) {
			$mid = (int) $master_post->ID;
			$variants = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 5,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_query'     => array(
						array(
							'key'   => RWGC_Routing::META_ENABLED,
							'value' => '1',
						),
						array(
							'key'   => RWGC_Routing::META_ROLE,
							'value' => 'variant',
						),
						array(
							'key'   => RWGC_Routing::META_MASTER_PAGE_ID,
							'value' => (string) $mid,
						),
					),
				)
			);

			$v_row = null;
			if ( ! empty( $variants ) ) {
				$vp       = $variants[0];
				$vcfg     = RWGC_Routing::get_page_route_config( (int) $vp->ID );
				$v_row    = array(
					'variant_id'    => (int) $vp->ID,
					'variant_title' => get_the_title( $vp ),
					'country_iso2'  => isset( $vcfg['country_iso2'] ) ? (string) $vcfg['country_iso2'] : '',
					'edit_variant'  => get_edit_post_link( $vp->ID, 'raw' ),
					'view_variant'  => get_permalink( $vp->ID ),
				);
			}

			$rows[] = array(
				'master_id'    => $mid,
				'master_title' => get_the_title( $master_post ),
				'edit_master'  => get_edit_post_link( $mid, 'raw' ),
				'view_master'  => get_permalink( $mid ),
				'variant'      => $v_row,
			);
		}

		/**
		 * Filter rows for Suite Page versions table.
		 *
		 * @param array<int, array<string, mixed>> $rows Overview rows.
		 */
		return apply_filters( 'rwgc_routing_overview_rows', $rows );
	}
}
