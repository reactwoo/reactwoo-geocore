<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps legacy RWGC_Routing post meta into RWGC_Page_Route_Bundle + variants.
 *
 * Redirect parity: master resolution uses published variant child pages plus an optional
 * inline ISO2 → page mapping stored on the master (`country_iso2` + `country_page_id`).
 * Inline variants use priority 55 so they win over DB-linked variants (priority 50) when both match.
 */
class RWGC_Legacy_Route_Mapper {

	/**
	 * Build a canonical bundle for a page from legacy routing config.
	 *
	 * @param int   $page_id Page ID.
	 * @param array $config  Output of RWGC_Routing::get_page_route_config().
	 * @return RWGC_Page_Route_Bundle
	 */
	public static function bundle_from_legacy_config( $page_id, $config ) {
		$bundle          = new RWGC_Page_Route_Bundle();
		$bundle->page_id = absint( $page_id );
		$bundle->enabled = ! empty( $config['enabled'] );
		$bundle->role    = isset( $config['role'] ) ? sanitize_key( (string) $config['role'] ) : 'master';
		if ( ! in_array( $bundle->role, array( 'master', 'variant' ), true ) ) {
			$bundle->role = 'master';
		}

		$bundle->legacy_config = is_array( $config ) ? $config : array();

		$bundle->master_page_id       = isset( $config['master_page_id'] ) ? absint( $config['master_page_id'] ) : 0;
		$bundle->variant_country_iso2 = '';
		$iso                          = isset( $config['country_iso2'] ) ? strtoupper( substr( sanitize_text_field( (string) $config['country_iso2'] ), 0, 2 ) ) : '';

		if ( 'variant' === $bundle->role ) {
			$bundle->variant_country_iso2 = preg_match( '/^[A-Z]{2}$/', $iso ) ? $iso : '';
			$bundle->default_page_id    = $bundle->master_page_id;
			$bundle->variants           = array();
			return $bundle;
		}

		$bundle->role            = 'master';
		$bundle->default_page_id = $bundle->page_id > 0 ? $bundle->page_id : 0;
		$bundle->variants        = self::variants_for_master_from_db( $bundle->page_id );
		self::maybe_append_inline_master_variant( $bundle, $config );

		return $bundle;
	}

	/**
	 * Legacy master meta: map one ISO2 → page id stored on the master (free-tier inline mapping).
	 *
	 * @param RWGC_Page_Route_Bundle $bundle Bundle (master role).
	 * @param array                  $config Legacy config.
	 * @return void
	 */
	private static function maybe_append_inline_master_variant( $bundle, $config ) {
		if ( ! $bundle instanceof RWGC_Page_Route_Bundle || 'master' !== $bundle->role ) {
			return;
		}
		$target = isset( $config['country_page_id'] ) ? absint( $config['country_page_id'] ) : 0;
		$iso    = isset( $config['country_iso2'] ) ? strtoupper( substr( sanitize_text_field( (string) $config['country_iso2'] ), 0, 2 ) ) : '';
		if ( $target <= 0 || ! preg_match( '/^[A-Z]{2}$/', $iso ) ) {
			return;
		}
		$post = get_post( $target );
		if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$bundle->variants[] = new RWGC_Variant(
			array(
				'id'             => 'legacy_inline_' . (int) $bundle->page_id,
				'source_type'    => 'page',
				'source_id'      => (int) $bundle->page_id,
				'label'          => $iso . ' (inline)',
				'target_page_id' => $target,
				'priority'       => 55,
				'conditions'     => array(
					'countries_include' => array( $iso ),
				),
			)
		);
	}

	/**
	 * Discover variant pages attached to a master (legacy meta), as RWGC_Variant list.
	 *
	 * @param int $master_page_id Master page ID.
	 * @return RWGC_Variant[]
	 */
	private static function variants_for_master_from_db( $master_page_id ) {
		$master_page_id = absint( $master_page_id );
		if ( $master_page_id <= 0 ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
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
						'value' => (string) $master_page_id,
					),
				),
			)
		);

		$variants = array();
		foreach ( $post_ids as $pid ) {
			$pid = absint( $pid );
			if ( $pid <= 0 ) {
				continue;
			}
			$v_iso = strtoupper( (string) get_post_meta( $pid, RWGC_Routing::META_COUNTRY_ISO2, true ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $v_iso ) ) {
				continue;
			}
			$variants[] = new RWGC_Variant(
				array(
					'id'             => 'legacy_variant_' . $pid,
					'source_type'    => 'page',
					'source_id'      => $master_page_id,
					'label'          => $v_iso,
					'target_page_id' => $pid,
					'priority'       => 50,
					'conditions'     => array(
						'countries_include' => array( $v_iso ),
					),
				)
			);
		}

		return $variants;
	}
}
