<?php
/**
 * Assistant target helpers (search / create Elementor popups).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGC_Assistant_Target_Service {

	const POPUP_POST_TYPE = 'elementor_library';

	/**
	 * @param string $query Search phrase.
	 * @param int    $limit Max rows.
	 * @return array{success:bool,results:array<int,array<string,mixed>>}|\WP_Error
	 */
	public static function search_popups( $query = '', $limit = 20 ) {
		if ( ! post_type_exists( self::POPUP_POST_TYPE ) ) {
			return new WP_Error(
				'rwgc_popup_unavailable',
				__( 'Elementor popups are not available on this site.', 'reactwoo-geocore' ),
				array( 'status' => 503 )
			);
		}

		$limit = max( 1, min( 50, (int) $limit ) );
		$args  = array(
			'post_type'              => self::POPUP_POST_TYPE,
			'post_status'            => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page'         => $limit,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'meta_query'             => array(
				array(
					'key'   => '_elementor_template_type',
					'value' => 'popup',
				),
			),
		);

		$query = trim( (string) $query );
		if ( '' !== $query ) {
			$args['s'] = $query;
		}

		$posts = get_posts( $args );
		$rows  = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( 'private' === $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$rows[] = self::format_popup_row( $post );
		}

		return array(
			'success' => true,
			'results' => $rows,
		);
	}

	/**
	 * @param string $title  Popup title.
	 * @param string $status draft|publish.
	 * @param array<string,mixed> $args Optional: force_create (bool).
	 * @return array{success:bool,target?:array<string,mixed>,reason?:string,matches?:array<int,array<string,mixed>>,message?:string}|\WP_Error
	 */
	public static function create_popup( $title, $status = 'draft', $args = array() ) {
		if ( ! post_type_exists( self::POPUP_POST_TYPE ) ) {
			return new WP_Error(
				'rwgc_popup_unavailable',
				__( 'Elementor popups are not available on this site.', 'reactwoo-geocore' ),
				array( 'status' => 503 )
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rwgc_forbidden',
				__( 'You do not have permission to create popups.', 'reactwoo-geocore' ),
				array( 'status' => 403 )
			);
		}

		$title = sanitize_text_field( (string) $title );
		if ( '' === $title ) {
			return new WP_Error(
				'rwgc_invalid_title',
				__( 'Popup name is required.', 'reactwoo-geocore' ),
				array( 'status' => 400 )
			);
		}

		$status = sanitize_key( (string) $status );
		if ( ! in_array( $status, array( 'draft', 'publish', 'private', 'pending' ), true ) ) {
			$status = 'draft';
		}

		$force_create = ! empty( $args['force_create'] );
		if ( ! $force_create ) {
			$matches = self::find_similar_popups( $title );
			if ( ! empty( $matches ) ) {
				return array(
					'success' => false,
					'code'    => 'duplicate_found',
					'reason'  => 'possible_duplicate',
					'matches' => $matches,
					'message' => __( 'A similar popup already exists.', 'reactwoo-geocore' ),
					'details' => array(
						'matches' => $matches,
					),
				);
			}
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => self::POPUP_POST_TYPE,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return new WP_Error(
				'rwgc_popup_create_failed',
				__( 'Could not create the popup.', 'reactwoo-geocore' ),
				array( 'status' => 500 )
			);
		}

		update_post_meta( $post_id, '_elementor_template_type', 'popup' );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_elementor_data', '[]' );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'rwgc_popup_create_failed',
				__( 'Could not load the new popup.', 'reactwoo-geocore' ),
				array( 'status' => 500 )
			);
		}

		$row = self::format_popup_row( $post );
		$row['created_by_assistant'] = true;

		return array(
			'success' => true,
			'target'  => array(
				'type'           => 'popup',
				'id'             => (int) $row['id'],
				'label'          => (string) $row['label'],
				'status'         => 'valid',
				'created_status' => (string) $row['status'],
				'edit_url'       => (string) $row['edit_url'],
				'created_by_assistant' => true,
			),
		);
	}

	/**
	 * Normalise a popup label for title matching (strip trailing type words).
	 *
	 * @param string $label Raw label.
	 * @return string
	 */
	public static function normalize_popup_label( $label ) {
		$s = strtolower( trim( (string) $label ) );
		$s = preg_replace( '/\s+/', ' ', $s );
		$suffixes = array( 'banner popup', 'geo popup', 'pop-up', 'popup', 'modal', 'banner' );
		foreach ( $suffixes as $suffix ) {
			if ( $s === $suffix ) {
				return '';
			}
			$suffix_sp = ' ' . $suffix;
			if ( strlen( $s ) > strlen( $suffix_sp ) && substr( $s, -strlen( $suffix_sp ) ) === $suffix_sp ) {
				$s = trim( substr( $s, 0, -strlen( $suffix_sp ) ) );
				break;
			}
		}
		return trim( $s );
	}

	/**
	 * Find popups with a similar title (exact or close match).
	 *
	 * @param string $title Popup title.
	 * @param int    $limit Max matches.
	 * @return array<int,array{id:int,label:string}>
	 */
	public static function find_similar_popups( $title, $limit = 5 ) {
		if ( ! post_type_exists( self::POPUP_POST_TYPE ) ) {
			return array();
		}

		$title   = trim( (string) $title );
		$needle  = self::normalize_popup_label( $title );
		$limit   = max( 1, min( 10, (int) $limit ) );
		$matches = array();
		$seen    = array();

		if ( '' === $needle ) {
			return array();
		}

		$search = self::search_popups( $title, 20 );
		if ( ! is_wp_error( $search ) && ! empty( $search['results'] ) && is_array( $search['results'] ) ) {
			foreach ( $search['results'] as $row ) {
				$id    = isset( $row['id'] ) ? (int) $row['id'] : 0;
				$label = isset( $row['label'] ) ? (string) $row['label'] : '';
				$hay   = self::normalize_popup_label( $label );
				if ( $id <= 0 || '' === $label || isset( $seen[ $id ] ) ) {
					continue;
				}
				if ( $hay === $needle || false !== strpos( $hay, $needle ) || false !== strpos( $needle, $hay ) ) {
					$matches[]    = array(
						'id'    => $id,
						'label' => $label,
					);
					$seen[ $id ] = true;
				}
				if ( count( $matches ) >= $limit ) {
					return $matches;
				}
			}
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = %s
				WHERE p.post_type = %s AND p.post_status IN ('publish','draft','private','pending')
				LIMIT %d",
				'_elementor_template_type',
				'popup',
				self::POPUP_POST_TYPE,
				50
			)
		);

		foreach ( (array) $ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
				continue;
			}
			$post = get_post( $post_id );
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( 'private' === $post->post_status && ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$label = (string) get_the_title( $post );
			$hay   = self::normalize_popup_label( $label );
			if ( $hay === $needle || false !== strpos( $hay, $needle ) || false !== strpos( $needle, $hay ) ) {
				$matches[]        = array(
					'id'    => $post_id,
					'label' => $label,
				);
				$seen[ $post_id ] = true;
			}
			if ( count( $matches ) >= $limit ) {
				break;
			}
		}

		return $matches;
	}

	/**
	 * @param \WP_Post $post Popup template post.
	 * @return array<string,mixed>
	 */
	public static function format_popup_row( WP_Post $post ) {
		$status = (string) $post->post_status;
		$label  = (string) get_the_title( $post );
		$edit   = '';
		if ( function_exists( 'admin_url' ) ) {
			$edit = admin_url( 'post.php?post=' . (int) $post->ID . '&action=elementor' );
		}

		return array(
			'id'           => (int) $post->ID,
			'label'        => $label,
			'status'       => $status,
			'status_label' => self::status_label( $status ),
			'modified'     => (string) get_post_modified_time( 'Y-m-d H:i', false, $post, true ),
			'edit_url'     => $edit,
		);
	}

	/**
	 * @param string $status Post status slug.
	 * @return string
	 */
	private static function status_label( $status ) {
		switch ( (string) $status ) {
			case 'publish':
				return __( 'Published', 'reactwoo-geocore' );
			case 'draft':
				return __( 'Draft', 'reactwoo-geocore' );
			case 'private':
				return __( 'Private', 'reactwoo-geocore' );
			case 'pending':
				return __( 'Pending', 'reactwoo-geocore' );
			default:
				return ucfirst( str_replace( '_', ' ', (string) $status ) );
		}
	}
}
