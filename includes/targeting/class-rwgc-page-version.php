<?php
/**
 * Page Version URL helpers (`/page-path/_gc/version-name`).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize version slugs, build paths, and normalize portable condition values.
 */
class RWGC_Page_Version {

	const ROUTE_SEGMENT = '_gc';

	const MAX_VERSION_LENGTH = 80;

	/**
	 * @var string
	 */
	const VERSION_PATTERN = '/^[a-zA-Z0-9_-]{1,80}$/';

	/**
	 * Sanitize a user-entered version name (not a full URL).
	 *
	 * @param mixed  $raw   Raw input.
	 * @param string $error Optional error message by reference.
	 * @return string Empty string when invalid.
	 */
	public static function sanitize_version_name( $raw, &$error = '' ) {
		$error = '';
		$s     = is_string( $raw ) ? trim( $raw ) : trim( (string) $raw );
		if ( '' === $s ) {
			$error = __( 'Enter only the version name, such as campaign_name.', 'reactwoo-geocore' );
			return '';
		}

		$s = self::extract_version_from_pasted_input( $s );
		if ( '' === $s ) {
			$error = __( 'Enter only the version name, such as campaign_name.', 'reactwoo-geocore' );
			return '';
		}

		if ( strlen( $s ) > self::MAX_VERSION_LENGTH ) {
			$error = __( 'Keep the version name under 80 characters.', 'reactwoo-geocore' );
			return '';
		}

		if ( ! preg_match( self::VERSION_PATTERN, $s ) ) {
			$error = __( 'Use letters, numbers, hyphens, and underscores only.', 'reactwoo-geocore' );
			return '';
		}

		return $s;
	}

	/**
	 * Strip slashes, `_gc`, and full URL fragments from pasted values.
	 *
	 * @param string $input User input.
	 * @return string
	 */
	public static function extract_version_from_pasted_input( $input ) {
		$s = trim( (string) $input );
		if ( '' === $s ) {
			return '';
		}

		// Full URL pasted — keep path only.
		if ( false !== strpos( $s, '://' ) ) {
			$path = wp_parse_url( $s, PHP_URL_PATH );
			$s    = is_string( $path ) ? trim( $path, '/' ) : $s;
		}

		$s = trim( $s, '/' );

		// `/_gc/version` or `page/_gc/version`.
		if ( preg_match( '#(?:^|/)' . preg_quote( self::ROUTE_SEGMENT, '#' ) . '/([^/]+)/?$#', $s, $m ) ) {
			return sanitize_text_field( $m[1] );
		}

		// Leading slash only: `/campaign_name`.
		if ( isset( $input[0] ) && '/' === $input[0] && false === strpos( $s, '/' ) ) {
			return trim( $s, '/' );
		}

		// `page/sub/_gc/version` without leading context — take segment after `_gc`.
		$parts = explode( '/', $s );
		$gc_at = array_search( self::ROUTE_SEGMENT, $parts, true );
		if ( false !== $gc_at && isset( $parts[ $gc_at + 1 ] ) ) {
			return sanitize_text_field( (string) $parts[ $gc_at + 1 ] );
		}

		// Plain version slug.
		if ( 1 === count( $parts ) ) {
			return $parts[0];
		}

		// Reject paths that still look like URLs (`about-us/campaign`).
		if ( count( $parts ) > 1 ) {
			return '';
		}

		return $s;
	}

	/**
	 * Normalize portable condition value `{ page_id, version }`.
	 *
	 * @param mixed $raw Raw value from JSON.
	 * @return array{page_id:int,version:string}|null
	 */
	public static function sanitize_condition_value( $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$page_id = isset( $raw['page_id'] ) ? absint( $raw['page_id'] ) : 0;
		$error   = '';
		$version = self::sanitize_version_name( isset( $raw['version'] ) ? $raw['version'] : '', $error );

		if ( $page_id <= 0 || '' === $version ) {
			return null;
		}

		$post = get_post( $page_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return null;
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $page_id ) ) {
			return null;
		}

		return array(
			'page_id' => $page_id,
			'version' => $version,
		);
	}

	/**
	 * Relative site path for a page/post (no trailing slash), e.g. `about-us` or `parent/child`.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_post_relative_path( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return '';
		}

		$path = wp_parse_url( $permalink, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return '';
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '/' !== $home_path && '' !== $home_path ) {
			$home_path = trailingslashit( $home_path );
			if ( 0 === strpos( $path, $home_path ) ) {
				$path = substr( $path, strlen( $home_path ) );
			}
		}

		return trim( $path, '/' );
	}

	/**
	 * Build relative Page Version path (no leading slash).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $version Version slug.
	 * @return string
	 */
	public static function build_version_relative_path( $post_id, $version ) {
		$base = self::get_post_relative_path( $post_id );
		$ver  = self::sanitize_version_name( $version );
		if ( '' === $base || '' === $ver ) {
			return '';
		}
		return $base . '/' . self::ROUTE_SEGMENT . '/' . $ver;
	}

	/**
	 * Absolute front-end URL for a page version.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $version Version slug.
	 * @return string
	 */
	public static function build_version_url( $post_id, $version ) {
		$rel = self::build_version_relative_path( $post_id, $version );
		if ( '' === $rel ) {
			return '';
		}
		return home_url( '/' . $rel . '/' );
	}

	/**
	 * Resolve a relative path (no leading slash) to a page/post ID.
	 *
	 * @param string $pagename Path segments (pagename query style).
	 * @return int
	 */
	public static function resolve_post_id_from_path( $pagename ) {
		$pagename = trim( (string) $pagename, '/' );
		if ( '' === $pagename ) {
			return 0;
		}

		$page = get_page_by_path( $pagename, OBJECT, array( 'page', 'post' ) );
		if ( $page instanceof WP_Post ) {
			return (int) $page->ID;
		}

		if ( function_exists( 'url_to_postid' ) ) {
			$post_id = absint( url_to_postid( home_url( '/' . $pagename . '/' ) ) );
			$post    = $post_id > 0 ? get_post( $post_id ) : null;
			if ( $post instanceof WP_Post && in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
				return (int) $post->ID;
			}
		}

		return 0;
	}

	/**
	 * Document context for rule builder UIs.
	 *
	 * @param int $post_id Optional explicit post ID.
	 * @return array{id:int,path:string,title:string}|null
	 */
	public static function get_document_context( $post_id = 0 ) {
		$post_id = $post_id > 0 ? absint( $post_id ) : self::resolve_document_post_id();
		if ( $post_id <= 0 ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return null;
		}

		$path = self::get_post_relative_path( $post_id );
		if ( '' === $path ) {
			return null;
		}

		return array(
			'id'    => $post_id,
			'path'  => '/' . $path,
			'title' => get_the_title( $post ),
		);
	}

	/**
	 * Best-effort current edited post ID (Elementor, block editor, classic).
	 *
	 * @return int
	 */
	public static function resolve_document_post_id() {
		$post_id = 0;

		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( $post_id <= 0 && function_exists( 'get_the_ID' ) ) {
			$post_id = (int) get_the_ID();
		}

		if ( $post_id <= 0 && did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin', false ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->documents ) && is_object( $plugin->documents ) && method_exists( $plugin->documents, 'get_current' ) ) {
				$doc = $plugin->documents->get_current();
				if ( $doc && method_exists( $doc, 'get_main_id' ) ) {
					$post_id = (int) $doc->get_main_id();
				}
			}
		}

		/**
		 * Post ID for Page Version URL builder context (Elementor/Gutenberg/admin).
		 *
		 * @param int $post_id Resolved ID (0 if unknown).
		 */
		return (int) apply_filters( 'rwgc_page_version_document_post_id', $post_id );
	}

	/**
	 * Published pages/posts for admin picker when document context is unknown.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array{id:int,path:string,title:string}>
	 */
	public static function get_page_choices( $limit = 80 ) {
		$limit = max( 10, min( 200, (int) $limit ) );
		$posts = get_posts(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$path = self::get_post_relative_path( (int) $post->ID );
			if ( '' === $path ) {
				continue;
			}
			$out[] = array(
				'id'    => (int) $post->ID,
				'path'  => '/' . $path,
				'title' => get_the_title( $post ),
			);
		}
		return $out;
	}
}
