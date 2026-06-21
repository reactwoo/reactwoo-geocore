<?php
/**
 * Unified visibility rule registry for builders, admin, REST, and frontend evaluation.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source for saved visibility rules (Geo Core library + legacy geo_rule CPT).
 */
class RWGC_Rule_Registry {

	const SOURCE_RWGC_LIBRARY = 'rwgc_visibility_rule';
	const SOURCE_LEGACY_GEO   = 'geo_elementor_legacy';
	const LEGACY_ID_PREFIX    = 'legacy_';

	/**
	 * Normalized rules for builder dropdowns and admin pickers.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_rules_for_builder() {
		$rows = array();
		$rows = array_merge( $rows, self::get_rwgc_library_rows() );
		$rows = array_merge( $rows, self::get_legacy_geo_rule_rows() );

		/**
		 * @param array<int, array<string, mixed>> $rows Normalized builder rules.
		 */
		return apply_filters( 'rwgc_visibility_rules_for_builder', $rows );
	}

	/**
	 * Picker rows for builder dropdowns — portable library CPT only (not section/geo_rule rows).
	 *
	 * @return array<int, array{id:string,title:string,json:string}>
	 */
	public static function get_portable_library_picker_rows() {
		$out = array();
		foreach ( self::get_rwgc_library_rows() as $row ) {
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' === $id ) {
				continue;
			}
			$json = ! empty( $row['json'] ) && is_string( $row['json'] ) ? $row['json'] : '';
			if ( '' === trim( $json ) && ! empty( $row['rules'] ) && is_array( $row['rules'] ) ) {
				$encoded = wp_json_encode( $row['rules'] );
				$json    = is_string( $encoded ) ? $encoded : '';
			}
			if ( '' === trim( $json ) ) {
				continue;
			}
			$out[] = array(
				'id'    => $id,
				'title' => isset( $row['label'] ) ? (string) $row['label'] : $id,
				'json'  => $json,
			);
		}
		return $out;
	}

	/**
	 * Picker rows ({id, title, json}) for Elementor/Gutenberg JS bridges.
	 *
	 * @return array<int, array{id:string,title:string,json:string}>
	 */
	public static function get_library_picker_rows() {
		return self::get_portable_library_picker_rows();
	}

	/**
	 * @param string|int $rule_id Registry id (numeric, legacy_N, or rwgc post id string).
	 * @return array<string, mixed>|null Normalized row.
	 */
	public static function get_rule_row( $rule_id ) {
		$rule_id = (string) $rule_id;
		if ( '' === $rule_id ) {
			return null;
		}
		foreach ( self::get_rules_for_builder() as $row ) {
			if ( isset( $row['id'] ) && (string) $row['id'] === $rule_id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Sanitized portable rule set for a registry id.
	 *
	 * @param string|int $rule_id Registry id.
	 * @return array<string, mixed>|null
	 */
	public static function get_rule_set_by_id( $rule_id ) {
		$row = self::get_rule_row( $rule_id );
		if ( is_array( $row ) && ! empty( $row['rules'] ) && is_array( $row['rules'] ) ) {
			return $row['rules'];
		}
		$post_id = absint( $rule_id );
		if ( $post_id > 0 && class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			$from_post = RWGC_Visibility_Rule_Repository::get_rule_set( $post_id, false );
			if ( is_array( $from_post ) ) {
				return $from_post;
			}
		}
		return null;
	}

	/**
	 * Resolve portable rule set from Elementor/Gutenberg/popup settings array.
	 *
	 * @param array<string, mixed> $settings Control settings (egp_* / rwgc_* keys).
	 * @return array<string, mixed>|null
	 */
	public static function resolve_rule_set_from_settings( array $settings ) {
		$library_id = '';
		if ( ! empty( $settings['rwgc_visibility_rule_library'] ) ) {
			$library_id = (string) $settings['rwgc_visibility_rule_library'];
		}
		if ( '' === $library_id && ! empty( $settings['rwgc_applied_visibility_rule_id'] ) ) {
			$library_id = (string) $settings['rwgc_applied_visibility_rule_id'];
		}

		if ( '' !== $library_id ) {
			$from_library = self::get_rule_set_by_id( $library_id );
			if ( is_array( $from_library ) ) {
				return $from_library;
			}
			$post_id = absint( $library_id );
			if ( $post_id > 0 && class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
				$from_post = RWGC_Visibility_Rule_Repository::get_rule_set( $post_id, false );
				if ( is_array( $from_post ) ) {
					return $from_post;
				}
			}
		}

		$raw = '';
		if ( ! empty( $settings['egp_portable_geo_targeting'] ) ) {
			$raw = (string) $settings['egp_portable_geo_targeting'];
		} elseif ( ! empty( $settings['rwgc_portable_geo_targeting'] ) ) {
			$raw = (string) $settings['rwgc_portable_geo_targeting'];
		}

		if ( '' === trim( $raw ) || ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return null;
		}

		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
		return is_array( $set ) ? $set : null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_rwgc_library_rows() {
		if ( ! class_exists( 'RWGC_Visibility_Rule_CPT', false ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => RWGC_Visibility_Rule_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$set = self::rule_set_from_post_meta( (int) $post->ID, RWGC_Visibility_Rule_CPT::META_PORTABLE );
			if ( null === $set ) {
				continue;
			}
			$mode = isset( $set['mode'] ) ? (string) $set['mode'] : 'show_if';
			$json = wp_json_encode( $set );
			$rows[] = array(
				'id'             => (string) (int) $post->ID,
				'source'         => self::SOURCE_RWGC_LIBRARY,
				'label'          => $post->post_title ? $post->post_title : __( 'Untitled visibility rule', 'reactwoo-geocore' ),
				'rules'          => $set,
				'visibilityMode' => function_exists( 'rwgc_normalize_visibility_mode' ) ? rwgc_normalize_visibility_mode( $mode ) : 'show_if',
				'json'           => is_string( $json ) ? $json : '',
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_legacy_geo_rule_rows() {
		if ( ! post_type_exists( 'geo_rule' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'geo_rule',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$set = self::rule_set_from_post_meta( (int) $post->ID, 'egp_portable_targeting' );
			if ( null === $set ) {
				$countries = get_post_meta( (int) $post->ID, 'egp_countries', true );
				if ( ! is_array( $countries ) || empty( $countries ) ) {
					continue;
				}
				$set = self::countries_only_rule_set( $countries );
			}

			$mode = isset( $set['mode'] ) ? (string) $set['mode'] : 'show_if';
			$json = wp_json_encode( $set );
			$rows[] = array(
				'id'             => self::LEGACY_ID_PREFIX . (int) $post->ID,
				'source'         => self::SOURCE_LEGACY_GEO,
				'label'          => $post->post_title ? $post->post_title : __( 'Legacy Geo Rule', 'reactwoo-geocore' ),
				'rules'          => $set,
				'visibilityMode' => function_exists( 'rwgc_normalize_visibility_mode' ) ? rwgc_normalize_visibility_mode( $mode ) : 'show_if',
				'json'           => is_string( $json ) ? $json : '',
			);
		}

		return $rows;
	}

	/**
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Portable JSON meta key.
	 * @return array<string, mixed>|null
	 */
	private static function rule_set_from_post_meta( $post_id, $meta_key ) {
		$raw = get_post_meta( absint( $post_id ), $meta_key, true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) || ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return null;
		}
		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
		return is_array( $set ) ? $set : null;
	}

	/**
	 * @param array<int|string> $countries Country codes.
	 * @return array<string, mixed>
	 */
	private static function countries_only_rule_set( array $countries ) {
		$codes = array();
		foreach ( $countries as $code ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			if ( 2 === strlen( $code ) ) {
				$codes[] = $code;
			}
		}
		if ( empty( $codes ) ) {
			return array(
				'enabled' => true,
				'mode'    => 'show_if',
				'match'   => 'any',
				'rules'   => array(),
			);
		}

		return array(
			'enabled' => true,
			'mode'    => 'show_if',
			'match'   => 'any',
			'rules'   => array(
				array(
					'match'      => 'any',
					'conditions' => array(
						array(
							'type'     => 'country',
							'operator' => 'in',
							'value'    => $codes,
						),
					),
				),
			),
		);
	}
}

if ( ! function_exists( 'rwgc_get_visibility_rules_for_builder' ) ) {
	/**
	 * @return array<int, array<string, mixed>>
	 */
	function rwgc_get_visibility_rules_for_builder() {
		if ( ! class_exists( 'RWGC_Rule_Registry', false ) ) {
			return array();
		}
		return RWGC_Rule_Registry::get_rules_for_builder();
	}
}
