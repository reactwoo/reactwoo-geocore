<?php
/**
 * Builder/editor request detection for country visibility.
 *
 * Page-version routing lives in Geo Core Pro. This check stays in Geo Core so
 * Elementor editing still skips country show/hide without loading that router.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logged-in builder surfaces that should not enforce visitor country rules.
 */
class RWGC_Builder_Request {

	/**
	 * @var array<string, bool>
	 */
	private static $memo_bypass = array();

	/**
	 * @var bool|null
	 */
	private static $memo_elementor_surface = null;

	/**
	 * @param int|null $post_id Optional document ID.
	 * @return bool
	 */
	public static function is_edit_request( $post_id = null ) {
		$key = null === $post_id ? '_null' : (string) (int) $post_id;
		if ( isset( self::$memo_bypass[ $key ] ) ) {
			return self::$memo_bypass[ $key ];
		}

		$resolved = self::resolve_post_id( $post_id );
		$inner    = self::compute_bypass( $resolved );
		/**
		 * Whether Geo Core should skip visitor-country enforcement on a builder request.
		 *
		 * @param bool $inner    Whether the logged-in editor heuristics matched.
		 * @param int  $resolved Resolved document ID, or 0.
		 */
		$out                       = (bool) apply_filters( 'rwgc_should_bypass_builder_request', $inner, $resolved );
		self::$memo_bypass[ $key ] = $out;
		return $out;
	}

	/**
	 * @param int $resolved_post_id Resolved post ID.
	 * @return bool
	 */
	private static function compute_bypass( $resolved_post_id ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( ! self::is_elementor_surface() ) {
			return false;
		}
		if ( $resolved_post_id > 0 ) {
			return (bool) current_user_can( 'edit_post', $resolved_post_id );
		}
		return (bool) ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) );
	}

	/**
	 * @return bool
	 */
	private static function is_elementor_surface() {
		if ( null !== self::$memo_elementor_surface ) {
			return self::$memo_elementor_surface;
		}

		if ( ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['elementor_library'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::$memo_elementor_surface = true;
			return true;
		}

		if ( ! empty( $_GET['action'] ) && 'elementor' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::$memo_elementor_surface = true;
			return true;
		}

		if ( ! is_admin() && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			self::$memo_elementor_surface = false;
			return false;
		}

		static $deep_in_progress = false;
		if ( $deep_in_progress || ! class_exists( '\Elementor\Plugin', false ) ) {
			self::$memo_elementor_surface = false;
			return false;
		}

		$deep_in_progress = true;
		try {
			$plugin = \Elementor\Plugin::$instance;
			if ( $plugin && isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
				self::$memo_elementor_surface = true;
				return true;
			}
			if ( $plugin && isset( $plugin->preview ) && is_object( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
				self::$memo_elementor_surface = true;
				return true;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			self::$memo_elementor_surface = false;
			return false;
		} finally {
			$deep_in_progress = false;
		}

		self::$memo_elementor_surface = false;
		return false;
	}

	/**
	 * @param int|null $post_id Optional explicit ID.
	 * @return int
	 */
	private static function resolve_post_id( $post_id ) {
		if ( null !== $post_id && (int) $post_id > 0 ) {
			return (int) $post_id;
		}
		if ( ! empty( $_GET['elementor-preview'] ) && is_numeric( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_GET['elementor-preview'];
		}
		if ( ! empty( $_GET['p'] ) && isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_GET['p'];
		}
		if ( ! empty( $_REQUEST['post'] ) && is_numeric( $_REQUEST['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_REQUEST['post'];
		}
		if ( ! empty( $_REQUEST['editor_post_id'] ) && is_numeric( $_REQUEST['editor_post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_REQUEST['editor_post_id'];
		}
		$queried = (int) get_queried_object_id();
		return $queried > 0 ? $queried : 0;
	}
}
