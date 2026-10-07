<?php
/**
 * Editor-only status for library rules referenced by builders.
 *
 * Front-end matching is unchanged: a missing or unpublished rule still never matches.
 * This class only tells the editor, and it never deletes the stored reference.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Batched publish/draft/trash/missing lookup for users who can edit.
 */
class RWGC_Visibility_Rule_Editor_Status {

	const AJAX_ACTION = 'rwgc_visibility_rule_status';

	const MAX_IDS = 100;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'ajax_lookup' ) );
	}

	/**
	 * Editors (not only administrators) may see why a saved reference does not match.
	 *
	 * @return bool
	 */
	public static function can_read() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * @param array<int, mixed> $ids Rule IDs (numeric, zero, or junk).
	 * @return array<int|string, array<string, mixed>>
	 */
	public static function lookup( array $ids ) {
		$out      = array();
		$positive = array();

		foreach ( $ids as $raw ) {
			if ( ! is_scalar( $raw ) ) {
				continue;
			}
			$key = trim( (string) $raw );
			if ( '' === $key ) {
				continue;
			}
			if ( ! preg_match( '/^[0-9]+$/', $key ) || (int) $key <= 0 ) {
				$out[ $key ] = self::row( 0, 'nonexistent', '', false, false );
				continue;
			}
			$positive[ (int) $key ] = (int) $key;
		}

		if ( ! $positive ) {
			return $out;
		}

		$positive = array_slice( $positive, 0, self::MAX_IDS, true );
		$found    = self::fetch_posts( array_values( $positive ) );

		foreach ( $positive as $id ) {
			$post = isset( $found[ $id ] ) ? $found[ $id ] : null;
			$out[ $id ] = self::row_for_post( $id, $post );
		}

		return $out;
	}

	/**
	 * Library picker rows plus any extra IDs already stored on the document.
	 *
	 * @param array<int, mixed> $extra_ids IDs referenced by the open editor document.
	 * @return array{rules: array<int|string, array<string, mixed>>, lookup: array{ajaxUrl: string, action: string, nonce: string}}
	 */
	public static function editor_bootstrap( array $extra_ids = array() ) {
		$ids = $extra_ids;
		if ( class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			foreach ( RWGC_Visibility_Rule_Repository::get_library_picker_rows() as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$ids[] = $row['id'];
				}
			}
		}

		return array(
			'rules'  => self::lookup( $ids ),
			'lookup' => self::ajax_config(),
		);
	}

	/**
	 * Rule IDs saved on a post (Elementor data, page settings, block attributes, post meta).
	 *
	 * @param int $post_id Post being edited.
	 * @return array<int, int>
	 */
	public static function referenced_ids_for_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}

		$chunks = array(
			get_post_meta( $post_id, '_elementor_data', true ),
			get_post_meta( $post_id, '_elementor_page_settings', true ),
			get_post_meta( $post_id, '_rwgc_post_visibility_rule_library', true ),
			get_post_meta( $post_id, '_rwgc_post_applied_visibility_rule_id', true ),
		);
		if ( function_exists( 'get_post_field' ) ) {
			$chunks[] = get_post_field( 'post_content', $post_id );
		}

		$ids = array();
		foreach ( $chunks as $chunk ) {
			foreach ( self::extract_referenced_ids( $chunk ) as $id ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Pull library-rule IDs out of Elementor JSON or block-comment JSON.
	 *
	 * @param mixed $raw Stored value.
	 * @return array<int, int>
	 */
	public static function extract_referenced_ids( $raw ) {
		$ids = array();
		self::collect_ids( $raw, $ids );
		$out = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @return array{ajaxUrl: string, action: string, nonce: string}
	 */
	public static function ajax_config() {
		return array(
			'ajaxUrl' => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '',
			'action'  => self::AJAX_ACTION,
			'nonce'   => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( self::AJAX_ACTION ) : '',
		);
	}

	/**
	 * @return void
	 */
	public static function ajax_lookup() {
		if ( ! self::can_read() ) {
			if ( function_exists( 'wp_send_json_error' ) ) {
				wp_send_json_error(
					array( 'message' => __( 'You cannot look up visibility rules.', 'reactwoo-geocore' ) ),
					403
				);
			}
			return;
		}

		if ( function_exists( 'check_ajax_referer' ) ) {
			check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		}

		$raw = array();
		if ( isset( $_REQUEST['ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$incoming = wp_unslash( $_REQUEST['ids'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( is_array( $incoming ) ) {
				$raw = $incoming;
			} else {
				$split = preg_split( '/[\s,]+/', (string) $incoming, -1, PREG_SPLIT_NO_EMPTY );
				$raw   = is_array( $split ) ? $split : array();
			}
		}

		$rules = self::lookup( array_slice( array_values( $raw ), 0, self::MAX_IDS ) );
		if ( function_exists( 'wp_send_json_success' ) ) {
			wp_send_json_success( array( 'rules' => $rules ) );
		}
	}

	/**
	 * Consequence copy for an unresolved reference. Does not change evaluation.
	 *
	 * @param string $title Rule title, or empty when the post is gone.
	 * @param int    $id    Rule ID.
	 * @return array{show_if: string, hide_if: string, variant: string}
	 */
	public static function warning_messages( $title, $id ) {
		$title = trim( (string) $title );
		$id    = absint( $id );
		if ( '' !== $title ) {
			$who = sprintf(
				/* translators: %s: visibility rule title */
				__( "The rule '%s' was deleted or is unpublished.", 'reactwoo-geocore' ),
				str_replace( '%', '%%', $title )
			);
		} else {
			$who = sprintf(
				/* translators: %d: visibility rule ID */
				__( 'The rule #%d was deleted or is unpublished.', 'reactwoo-geocore' ),
				$id
			);
		}

		return array(
			'show_if' => $who . ' ' . __( 'This content is now hidden for everyone.', 'reactwoo-geocore' ),
			'hide_if' => $who . ' ' . __( 'This content is now never hidden.', 'reactwoo-geocore' ),
			'variant' => $who . ' ' . __( 'Visitors see the default page.', 'reactwoo-geocore' ),
		);
	}

	/**
	 * Statuses a library rule can be in and still be worth naming in the editor.
	 *
	 * @return array<int, string>
	 */
	private static function query_statuses() {
		return array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft' );
	}

	/**
	 * @param array<int, int> $ids Positive IDs.
	 * @return array<int, WP_Post>
	 */
	private static function fetch_posts( array $ids ) {
		$found = array();
		if ( ! $ids || ! function_exists( 'get_posts' ) ) {
			return $found;
		}

		$rule_type = class_exists( 'RWGC_Visibility_Rule_CPT', false )
			? RWGC_Visibility_Rule_CPT::POST_TYPE
			: 'rwgc_visibility_rule';

		// `post_type => any` drops types with exclude_from_search, which this CPT sets.
		// Query the rule type by name or every published rule looks deleted.
		foreach ( self::query_posts( $ids, $rule_type ) as $post ) {
			$found[ (int) $post->ID ] = $post;
		}

		$missing = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $found[ $id ] ) ) {
				$missing[] = $id;
			}
		}
		if ( ! $missing ) {
			return $found;
		}

		// Leftover ids may be ordinary pages or posts. `any` still skips this CPT,
		// so a hit here is some other type and must not be treated as a rule.
		foreach ( self::query_posts( $missing, 'any' ) as $post ) {
			if ( $rule_type === (string) $post->post_type ) {
				continue;
			}
			$found[ (int) $post->ID ] = $post;
		}

		return $found;
	}

	/**
	 * @param array<int, int> $ids       Positive IDs.
	 * @param string          $post_type Rule post type, or `any` for other types.
	 * @return array<int, WP_Post>
	 */
	private static function query_posts( array $ids, $post_type ) {
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => self::query_statuses(),
				'post__in'               => $ids,
				'posts_per_page'         => count( $ids ),
				'orderby'                => 'post__in',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => true,
			)
		);
		if ( ! is_array( $posts ) ) {
			return array();
		}

		$out = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$out[] = $post;
			}
		}
		return $out;
	}

	/**
	 * @param int          $id   Requested ID.
	 * @param WP_Post|null $post Post or null when the row is gone.
	 * @return array<string, mixed>
	 */
	private static function row_for_post( $id, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return self::row( $id, 'deleted', '', false, false );
		}

		$type = isset( $post->post_type ) ? (string) $post->post_type : '';
		if ( class_exists( 'RWGC_Visibility_Rule_CPT', false ) && RWGC_Visibility_Rule_CPT::POST_TYPE !== $type ) {
			return self::row( $id, 'unresolvable', '', false, false );
		}

		$status = self::map_post_status( isset( $post->post_status ) ? (string) $post->post_status : '' );
		$title  = self::title_for_editor( $post );
		$active = true;
		if ( class_exists( 'RWGC_Variant_Rule_Applications', false ) ) {
			$active = RWGC_Variant_Rule_Applications::is_rule_active_for_frontend( $id );
		}
		$set = null;
		if ( class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			$set = RWGC_Visibility_Rule_Repository::get_rule_set( $id );
		}
		$page_variant = class_exists( 'RWGC_Variant_Rule_Applications', false )
			&& RWGC_Variant_Rule_Applications::is_page_variant_rule( $id );
		$resolvable   = ( 'published' === $status ) && $active && is_array( $set );

		return self::row( $id, $status, $title, $resolvable, $page_variant );
	}

	/**
	 * Published titles are visible to anyone who can edit posts.
	 * Draft, private, and trashed titles need edit access to that rule.
	 *
	 * @param WP_Post $post Rule or other post.
	 * @return string
	 */
	private static function title_for_editor( WP_Post $post ) {
		$title  = isset( $post->post_title ) ? (string) $post->post_title : '';
		$status = isset( $post->post_status ) ? (string) $post->post_status : '';
		if ( 'publish' === $status ) {
			return $title;
		}
		$id = (int) $post->ID;
		if ( $id > 0 && function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $id ) ) {
			return $title;
		}
		return '';
	}

	/**
	 * @param string $post_status WP post_status.
	 * @return string published|draft|trashed|unpublished
	 */
	private static function map_post_status( $post_status ) {
		switch ( $post_status ) {
			case 'publish':
				return 'published';
			case 'draft':
			case 'auto-draft':
				return 'draft';
			case 'trash':
				return 'trashed';
			default:
				return 'unpublished';
		}
	}

	/**
	 * @param int    $id           Rule ID.
	 * @param string $status       published|draft|trashed|deleted|nonexistent|unpublished|unresolvable.
	 * @param string $title        Rule title.
	 * @param bool   $resolvable   True when the front end can evaluate this rule.
	 * @param bool   $page_variant True when the rule was created for a page variant.
	 * @return array<string, mixed>
	 */
	private static function row( $id, $status, $title, $resolvable, $page_variant ) {
		$row = array(
			'id'           => absint( $id ),
			'status'       => (string) $status,
			'title'        => (string) $title,
			'resolvable'   => (bool) $resolvable,
			'page_variant' => (bool) $page_variant,
		);
		if ( ! $resolvable ) {
			$row['messages'] = self::warning_messages( $title, $id );
		}
		return $row;
	}

	/**
	 * @param mixed              $raw Stored value.
	 * @param array<int, string> $ids Collected ID strings.
	 * @return void
	 */
	private static function collect_ids( $raw, array &$ids ) {
		$keys = array(
			'rwgc_visibility_rule_library',
			'rwgc_applied_visibility_rule_id',
			'visibilityRuleLibrary',
			'appliedVisibilityRuleId',
		);

		if ( is_array( $raw ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) && '' !== trim( (string) $raw[ $key ] ) ) {
					$ids[] = trim( (string) $raw[ $key ] );
				}
			}
			foreach ( $raw as $child ) {
				if ( is_array( $child ) ) {
					self::collect_ids( $child, $ids );
				}
			}
			return;
		}

		if ( ! is_string( $raw ) ) {
			return;
		}
		$trim = trim( $raw );
		if ( '' === $trim ) {
			return;
		}
		if ( preg_match( '/^[0-9]+$/', $trim ) ) {
			$ids[] = $trim;
			return;
		}
		$pattern = '/"(?:' . implode( '|', $keys ) . ')"\s*:\s*"?([0-9]+)"?/';
		if ( preg_match_all( $pattern, $raw, $matches ) ) {
			foreach ( $matches[1] as $id ) {
				$ids[] = (string) $id;
			}
		}
	}
}
