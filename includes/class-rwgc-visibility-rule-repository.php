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
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null Sanitized portable rule set.
	 */
	public static function get_rule_set( $post_id ) {
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
