<?php
/**
 * CRUD helpers for visibility rule library CPT rows.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistence for {@see RWGC_Visibility_Rule_CPT::POST_TYPE}.
 */
class RWGC_Visibility_Rule_Repository {

	/**
	 * @param array<string, mixed> $args Query args (posts_per_page, post_status, etc.).
	 * @return array<int, WP_Post>
	 */
	public static function query( array $args = array() ) {
		$defaults = array(
			'post_type'      => RWGC_Visibility_Rule_CPT::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 200,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		$q = wp_parse_args( $args, $defaults );
		/** @var array<int, WP_Post> $posts */
		$posts = get_posts( $q );
		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	public static function get_post( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post instanceof WP_Post || RWGC_Visibility_Rule_CPT::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * Published library rules for the shared rule-builder picker.
	 *
	 * @return array<int, array{id:int,title:string,json:string}>
	 */
	public static function get_library_picker_rows() {
		if ( class_exists( 'RWGC_Rule_Registry', false ) ) {
			return RWGC_Rule_Registry::get_library_picker_rows();
		}
		return array();
	}

	/**
	 * @param int  $post_id         Post ID.
	 * @param bool $prefer_registry Whether to resolve through the unified registry first.
	 * @return array<string, mixed>|null Sanitized portable rule set.
	 */
	public static function get_rule_set( $post_id, $prefer_registry = true ) {
		if ( $prefer_registry && class_exists( 'RWGC_Rule_Registry', false ) ) {
			$from_registry = RWGC_Rule_Registry::get_rule_set_by_id( $post_id );
			if ( is_array( $from_registry ) ) {
				return $from_registry;
			}
		}

		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return null;
		}
		$raw = get_post_meta( $post_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return null;
		}
		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
		return is_array( $set ) ? $set : null;
	}

	/**
	 * @param string               $title         Rule title.
	 * @param string               $status        publish|draft.
	 * @param string               $portable_json Portable JSON (pre-sanitize ok).
	 * @param int                  $post_id       Existing ID or 0 for insert.
	 * @return int Post ID or 0 on failure.
	 */
	public static function save( $title, $status, $portable_json, $post_id = 0 ) {
		$title  = sanitize_text_field( (string) $title );
		$status = sanitize_key( (string) $status );
		if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$status = 'draft';
		}
		if ( '' === $title ) {
			$title = __( 'Untitled visibility rule', 'reactwoo-geocore' );
		}

		$portable = RWGC_Visibility_Rule_CPT::sanitize_portable_meta( $portable_json );

		$post_id = absint( $post_id );
		$data    = array(
			'post_type'   => RWGC_Visibility_Rule_CPT::POST_TYPE,
			'post_title'  => $title,
			'post_status' => $status,
		);
		if ( $post_id > 0 ) {
			$data['ID'] = $post_id;
			$result     = wp_update_post( $data, true );
		} else {
			$result = wp_insert_post( $data, true );
		}
		if ( is_wp_error( $result ) || ! $result ) {
			return 0;
		}
		$post_id = (int) $result;
		update_post_meta( $post_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, $portable );
		return $post_id;
	}

	/**
	 * Geo Core visibility rule editor URL (not wp-admin post.php — CPT has show_ui false).
	 *
	 * @param int $post_id Rule post ID.
	 * @return string
	 */
	public static function get_edit_url( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! self::get_post( $post_id ) ) {
			return '';
		}
		$base = admin_url( 'admin.php?page=rwgc-visibility-rules' );
		if ( function_exists( 'rw_geo_app_url' ) ) {
			$app = rw_geo_app_url( 'targeting', 'rwgc-visibility-rules' );
			if ( is_string( $app ) && '' !== $app ) {
				$base = $app;
			}
		}
		return add_query_arg( 'rwgc_edit', $post_id, $base );
	}

	/**
	 * Whether the current user may open the Geo Core visibility rule editor.
	 *
	 * @param int $post_id Rule post ID.
	 * @return bool
	 */
	public static function can_current_user_manage_rule( $post_id ) {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return false;
		}
		return null !== self::get_post( $post_id );
	}

	/**
	 * Verify an assistant-created library rule before exposing edit links.
	 *
	 * @param int $post_id Rule post ID.
	 * @return array{valid:bool,post_id:int,post_type:string,can_edit:bool,has_rule_set:bool,edit_url:string,reason:string}
	 */
	public static function assistant_rule_verification( $post_id ) {
		$post_id = absint( $post_id );
		$out     = array(
			'valid'        => false,
			'post_id'      => $post_id,
			'post_type'    => '',
			'can_edit'     => false,
			'has_rule_set' => false,
			'edit_url'     => '',
			'reason'       => '',
		);
		$post = self::get_post( $post_id );
		if ( ! $post ) {
			$raw = get_post( $post_id );
			if ( $raw instanceof WP_Post ) {
				$out['post_type'] = (string) $raw->post_type;
			}
			$out['reason'] = 'not_visibility_rule';
			return $out;
		}
		$out['post_type']    = (string) $post->post_type;
		$set                 = self::get_rule_set( $post_id );
		$out['has_rule_set'] = is_array( $set ) && ! empty( $set['rules'] );
		$out['can_edit']     = self::can_current_user_manage_rule( $post_id );
		$out['edit_url']     = $out['can_edit'] ? self::get_edit_url( $post_id ) : '';
		$out['valid']        = $out['has_rule_set'] && $out['can_edit'] && '' !== $out['edit_url'];
		if ( ! $out['has_rule_set'] ) {
			$out['reason'] = 'empty_rule_set';
		} elseif ( ! $out['can_edit'] ) {
			$out['reason'] = 'not_editable';
		}
		return $out;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function delete( $post_id ) {
		$post = self::get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		return (bool) wp_trash_post( $post->ID );
	}
}
