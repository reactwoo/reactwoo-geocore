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

		if ( 'duplicate' === $mode ) {
			// post_content alone is not enough for Elementor-built pages; document JSON lives in post meta.
			self::copy_elementor_document_meta( $master_page_id, $new_id );
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
	 * Copy Elementor document meta from a master page onto a newly created variant.
	 *
	 * Suite "Duplicate default page" only inserts `post_content`. Elementor stores the editable
	 * document in `_elementor_data` / `_elementor_edit_mode`, so without this copy the variant
	 * opens blank in Elementor and can fail to render as a builder document for geo traffic.
	 *
	 * Route keys inside `_elementor_page_settings` are stripped so master's Elementor routing
	 * SWITCHER values cannot override the Suite variant meta written after insert.
	 *
	 * @param int $source_page_id Master page ID.
	 * @param int $dest_page_id   New variant page ID.
	 * @return bool True when Elementor builder meta was copied.
	 */
	public static function copy_elementor_document_meta( $source_page_id, $dest_page_id ) {
		$source_page_id = absint( $source_page_id );
		$dest_page_id   = absint( $dest_page_id );
		if ( $source_page_id <= 0 || $dest_page_id <= 0 || $source_page_id === $dest_page_id ) {
			return false;
		}

		$edit_mode = (string) get_post_meta( $source_page_id, '_elementor_edit_mode', true );
		$data_raw  = get_post_meta( $source_page_id, '_elementor_data', true );
		$has_data  = ( is_string( $data_raw ) && '' !== $data_raw ) || ( is_array( $data_raw ) && ! empty( $data_raw ) );

		if ( 'builder' !== $edit_mode && ! $has_data ) {
			return false;
		}

		if ( '' !== $edit_mode ) {
			update_post_meta( $dest_page_id, '_elementor_edit_mode', $edit_mode );
		} else {
			update_post_meta( $dest_page_id, '_elementor_edit_mode', 'builder' );
		}

		if ( $has_data ) {
			update_post_meta( $dest_page_id, '_elementor_data', $data_raw );
		}

		foreach ( array( '_elementor_version', '_elementor_pro_version', '_elementor_template_type' ) as $meta_key ) {
			$value = get_post_meta( $source_page_id, $meta_key, true );
			if ( '' !== $value && false !== $value && null !== $value ) {
				update_post_meta( $dest_page_id, $meta_key, $value );
			}
		}

		$page_settings = get_post_meta( $source_page_id, '_elementor_page_settings', true );
		if ( is_array( $page_settings ) ) {
			$clean = $page_settings;
			foreach ( array_keys( $clean ) as $key ) {
				if ( 0 === strpos( (string) $key, 'rwgc_route_' ) ) {
					unset( $clean[ $key ] );
				}
			}
			update_post_meta( $dest_page_id, '_elementor_page_settings', $clean );
		}

		// Page template (e.g. Elementor Canvas) affects frontend chrome.
		$template = get_post_meta( $source_page_id, '_wp_page_template', true );
		if ( is_string( $template ) && '' !== $template ) {
			update_post_meta( $dest_page_id, '_wp_page_template', $template );
		}

		// CSS is post-ID specific; force regeneration on next Elementor render/save.
		delete_post_meta( $dest_page_id, '_elementor_css' );

		return true;
	}

	/**
	 * Link an existing page as a variant of a master (use existing content mode).
	 *
	 * @param int    $master_page_id   Master page ID.
	 * @param int    $variant_page_id  Existing page to link.
	 * @param string $country_iso2     Optional ISO2 for geo routing.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function link_existing_variant( $master_page_id, $variant_page_id, $country_iso2 = '' ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new \WP_Error( 'rwgc_vm_forbidden', __( 'You do not have permission to manage pages.', 'reactwoo-geocore' ) );
		}

		$master_page_id  = absint( $master_page_id );
		$variant_page_id = absint( $variant_page_id );
		$country_iso2    = strtoupper( sanitize_text_field( (string) $country_iso2 ) );
		if ( ! preg_match( '/^[A-Z]{2}$/', $country_iso2 ) ) {
			$country_iso2 = '';
		}

		if ( $master_page_id <= 0 || $variant_page_id <= 0 ) {
			return new \WP_Error( 'rwgc_vm_ids', __( 'Select a default page and an existing page to link.', 'reactwoo-geocore' ) );
		}
		if ( $master_page_id === $variant_page_id ) {
			return new \WP_Error( 'rwgc_vm_same', __( 'The local version must be a different page from the default.', 'reactwoo-geocore' ) );
		}

		$master = get_post( $master_page_id );
		$variant = get_post( $variant_page_id );
		if ( ! $master || 'page' !== $master->post_type ) {
			return new \WP_Error( 'rwgc_vm_master_post', __( 'The default page was not found.', 'reactwoo-geocore' ) );
		}
		if ( ! $variant || 'page' !== $variant->post_type ) {
			return new \WP_Error( 'rwgc_vm_variant_post', __( 'The selected page was not found.', 'reactwoo-geocore' ) );
		}

		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return new \WP_Error( 'rwgc_vm_routing', __( 'Routing is not available.', 'reactwoo-geocore' ) );
		}

		if ( RWGC_Routing::master_has_variant( $master_page_id, $variant_page_id ) ) {
			return new \WP_Error(
				'rwgc_vm_limit',
				__( 'This default page already has a linked local version. Free Geo Core allows one linked version per page.', 'reactwoo-geocore' )
			);
		}

		$vcfg = RWGC_Routing::get_page_route_config( $variant_page_id );
		if ( 'variant' === ( $vcfg['role'] ?? '' ) ) {
			$linked = isset( $vcfg['master_page_id'] ) ? (int) $vcfg['master_page_id'] : 0;
			if ( $linked > 0 && $linked !== $master_page_id ) {
				return new \WP_Error( 'rwgc_vm_variant_taken', __( 'That page is already linked to another default page.', 'reactwoo-geocore' ) );
			}
		}

		if ( '' !== $country_iso2 && RWGC_Routing::is_variant_country_taken( $master_page_id, $country_iso2, $variant_page_id ) ) {
			return new \WP_Error( 'rwgc_vm_taken', __( 'That country is already used for another variant of this page.', 'reactwoo-geocore' ) );
		}

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
			$variant_page_id,
			array(
				'enabled'         => true,
				'role'            => 'variant',
				'master_page_id'  => $master_page_id,
				'country_iso2'    => $country_iso2,
				'default_page_id' => 0,
				'country_page_id' => 0,
			)
		);

		$edit_url = get_edit_post_link( $variant_page_id, 'raw' );
		$result   = array(
			'variant_page_id' => $variant_page_id,
			'master_page_id'  => $master_page_id,
			'edit_url'        => $edit_url ? (string) $edit_url : '',
			'country_iso2'    => $country_iso2,
			'linked_existing' => true,
		);

		do_action( 'rwgc_variant_created', $result );

		if ( class_exists( 'RWGC_Onboarding', false ) ) {
			RWGC_Onboarding::log_activity(
				'variant',
				array(
					'title' => sprintf(
						/* translators: %s: page title */
						__( 'Linked existing page as variant: %s', 'reactwoo-geocore' ),
						get_the_title( $variant )
					),
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
