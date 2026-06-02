<?php
/**
 * Unified targeting rules index (Targeting → Rules).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates portable, Elementor, routing, and commerce targeting rules for one list UI.
 */
class RWGC_Admin_Targeting_Rules_Index {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_rows() {
		$rows = array();

		$rows = array_merge( $rows, self::get_visibility_library_rows() );
		$rows = array_merge( $rows, self::get_geo_elementor_rows() );
		$rows = array_merge( $rows, self::get_page_routing_rows() );
		$rows = array_merge( $rows, self::get_commerce_rule_rows() );

		/**
		 * @param array<int, array<string, mixed>> $rows Rule rows for Targeting → Rules.
		 */
		$rows = apply_filters( 'rwgc_targeting_rules_index_rows', $rows );

		usort(
			$rows,
			static function ( $a, $b ) {
				$ta = isset( $a['updated_ts'] ) ? (int) $a['updated_ts'] : 0;
				$tb = isset( $b['updated_ts'] ) ? (int) $b['updated_ts'] : 0;
				return $tb <=> $ta;
			}
		);

		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_visibility_library_rows() {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return array();
		}

		$out = array();
		foreach ( RWGC_Visibility_Rule_Repository::query() as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$summary = self::summarize_portable_meta( (int) $post->ID, RWGC_Visibility_Rule_CPT::META_PORTABLE );
			$out[]   = array(
				'name'         => $post->post_title,
				'source'       => __( 'Geo Core', 'reactwoo-geocore' ),
				'location'     => __( 'Portable rule library', 'reactwoo-geocore' ),
				'targeting'    => $summary,
				'status'       => 'publish' === $post->post_status ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'      => get_the_modified_date( '', $post ),
				'updated_ts'   => (int) get_post_modified_time( 'U', true, $post ),
				'edit_url'     => admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=' . (int) $post->ID ),
				'edit_label'   => __( 'Edit', 'reactwoo-geocore' ),
			);
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_geo_elementor_rows() {
		if ( ! post_type_exists( 'geo_rule' ) ) {
			return array();
		}

		$out   = array();
		$posts = get_posts(
			array(
				'post_type'      => 'geo_rule',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		foreach ( $posts as $rule_post ) {
			if ( ! $rule_post instanceof WP_Post ) {
				continue;
			}

			$target_type = (string) get_post_meta( (int) $rule_post->ID, 'egp_target_type', true );
			$target_id   = (int) get_post_meta( (int) $rule_post->ID, 'egp_target_id', true );
			$countries   = get_post_meta( (int) $rule_post->ID, 'egp_countries', true );
			$is_active   = '1' === (string) get_post_meta( (int) $rule_post->ID, 'egp_active', true ) || 'publish' === $rule_post->post_status;

			$country_label = self::summarize_portable_meta( (int) $rule_post->ID, 'egp_portable_targeting' );
			if ( __( 'No conditions saved', 'reactwoo-geocore' ) === $country_label && is_array( $countries ) && ! empty( $countries ) ) {
				$country_label = implode( ', ', array_map( 'strval', $countries ) );
			} elseif ( __( 'No conditions saved', 'reactwoo-geocore' ) === $country_label ) {
				$country_label = __( 'Any visitor', 'reactwoo-geocore' );
			}

			$location = self::format_elementor_target( $target_type, $target_id );

			$out[] = array(
				'name'       => $rule_post->post_title,
				'source'     => __( 'Geo Elementor', 'reactwoo-geocore' ),
				'location'   => $location,
				'targeting'  => $country_label,
				'status'     => $is_active ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'    => get_the_modified_date( '', $rule_post ),
				'updated_ts' => (int) get_post_modified_time( 'U', true, $rule_post ),
				'edit_url'   => get_edit_post_link( (int) $rule_post->ID, 'raw' ),
				'edit_label' => __( 'Edit', 'reactwoo-geocore' ),
			);
		}

		return $out;
	}

	/**
	 * @param string $target_type Target type slug.
	 * @param int    $target_id   Target post/term id.
	 * @return string
	 */
	private static function format_elementor_target( $target_type, $target_id ) {
		$type_label = $target_type ? ucwords( str_replace( '_', ' ', $target_type ) ) : __( 'Content', 'reactwoo-geocore' );
		if ( $target_id > 0 ) {
			$target_post = get_post( $target_id );
			if ( $target_post ) {
				return sprintf(
					/* translators: 1: content type 2: title */
					__( '%1$s — %2$s', 'reactwoo-geocore' ),
					$type_label,
					$target_post->post_title
				);
			}
		}
		return $type_label;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_page_routing_rows() {
		if ( ! class_exists( 'RWGC_Variant_Manager', false ) ) {
			return array();
		}

		$overview = RWGC_Variant_Manager::get_routing_overview_rows();
		if ( ! is_array( $overview ) ) {
			return array();
		}

		$out = array();
		foreach ( $overview as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$condition = ! empty( $row['variant']['country_iso2'] )
				? (string) $row['variant']['country_iso2']
				: __( 'Default visitor', 'reactwoo-geocore' );
			$target    = ! empty( $row['variant']['variant_title'] )
				? (string) $row['variant']['variant_title']
				: __( 'Default page', 'reactwoo-geocore' );

			$out[] = array(
				'name'       => sprintf(
					/* translators: %s page title */
					__( 'Route %s', 'reactwoo-geocore' ),
					(string) ( $row['master_title'] ?? '' )
				),
				'source'     => __( 'Geo Core routing', 'reactwoo-geocore' ),
				'location'   => (string) ( $row['master_title'] ?? __( 'Page', 'reactwoo-geocore' ) ),
				'targeting'  => $condition,
				'status'     => __( 'Active', 'reactwoo-geocore' ),
				'updated'    => '',
				'updated_ts' => 0,
				'edit_url'   => admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_master_page_id=' . (int) ( $row['master_id'] ?? 0 ) ),
				'edit_label' => __( 'Edit route', 'reactwoo-geocore' ),
			);
		}

		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_commerce_rule_rows() {
		if ( ! class_exists( 'RWGCM_Rule_Store', false ) ) {
			return array();
		}

		$out = array();
		foreach ( RWGCM_Rule_Store::get_all_rules() as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$name = isset( $rule['name'] ) ? (string) $rule['name'] : __( 'Commerce rule', 'reactwoo-geocore' );
			$type = isset( $rule['rule_type'] ) ? (string) $rule['rule_type'] : '';
			$out[] = array(
				'name'       => $name,
				'source'     => __( 'Geo Commerce', 'reactwoo-geocore' ),
				'location'   => $type ? ucwords( str_replace( '_', ' ', $type ) ) : __( 'Store', 'reactwoo-geocore' ),
				'targeting'  => self::summarize_commerce_rule( $rule ),
				'status'     => ( ! empty( $rule['status'] ) && 'active' === $rule['status'] ) ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'    => isset( $rule['updated_at'] ) ? (string) $rule['updated_at'] : '',
				'updated_ts' => isset( $rule['updated_at'] ) ? strtotime( (string) $rule['updated_at'] ) : 0,
				'edit_url'   => admin_url( 'admin.php?page=rwgcm-pricing' ),
				'edit_label' => __( 'Open commerce rules', 'reactwoo-geocore' ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $rule Commerce rule row.
	 * @return string
	 */
	private static function summarize_commerce_rule( array $rule ) {
		if ( ! empty( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
			return sprintf(
				/* translators: %d condition count */
				_n( '%d condition', '%d conditions', count( $rule['conditions'] ), 'reactwoo-geocore' ),
				count( $rule['conditions'] )
			);
		}
		return __( 'Geo visitor conditions', 'reactwoo-geocore' );
	}

	/**
	 * @param int    $post_id Post id.
	 * @param string $meta_key Meta key for portable JSON.
	 * @return string
	 */
	private static function summarize_portable_meta( $post_id, $meta_key ) {
		$raw = (string) get_post_meta( $post_id, $meta_key, true );
		if ( '' === trim( $raw ) ) {
			return __( 'No conditions saved', 'reactwoo-geocore' );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return __( 'Custom conditions', 'reactwoo-geocore' );
		}
		if ( ! empty( $decoded['groups'] ) && is_array( $decoded['groups'] ) ) {
			$count = 0;
			foreach ( $decoded['groups'] as $group ) {
				if ( is_array( $group ) && ! empty( $group['conditions'] ) && is_array( $group['conditions'] ) ) {
					$count += count( $group['conditions'] );
				}
			}
			if ( $count > 0 ) {
				return sprintf(
					/* translators: %d condition count */
					_n( '%d condition', '%d conditions', $count, 'reactwoo-geocore' ),
					$count
				);
			}
		}
		return __( 'Portable targeting rules', 'reactwoo-geocore' );
	}
}
