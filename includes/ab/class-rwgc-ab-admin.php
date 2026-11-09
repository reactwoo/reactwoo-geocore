<?php
/**
 * Experiences list for free A/B tests.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists rwgc_ab_test posts. End and delete require a nonce and manage_options.
 */
class RWGC_AB_Admin {

	const PAGE_SLUG = 'rwgc-ab-tests';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_screen' ) );
		add_action( 'admin_post_rwgc_ab_end_test', array( __CLASS__, 'handle_end' ) );
		add_action( 'admin_post_rwgc_ab_delete_test', array( __CLASS__, 'handle_delete' ) );
		add_filter( 'rwgc_app_routes', array( __CLASS__, 'filter_routes_for_capability' ), 20 );
	}

	/**
	 * Hide the screen from accounts that cannot manage options.
	 *
	 * @param mixed $routes Routes.
	 * @return array<string, mixed>
	 */
	public static function filter_routes_for_capability( $routes ) {
		if ( ! is_array( $routes ) ) {
			return array();
		}
		if ( ! self::user_can_manage() && isset( $routes[ self::PAGE_SLUG ] ) ) {
			unset( $routes[ self::PAGE_SLUG ] );
		}
		return $routes;
	}

	/**
	 * @return void
	 */
	public static function register_screen() {
		if ( ! function_exists( 'rwgc_register_app_route' ) ) {
			return;
		}
		rwgc_register_app_route(
			array(
				'section'    => 'experiences',
				'module'     => 'core',
				'route'      => 'ab-tests',
				'menu_slug'  => self::PAGE_SLUG,
				'label'      => __( 'A/B tests', 'reactwoo-geocore' ),
				'page_title' => __( 'A/B tests', 'reactwoo-geocore' ),
				'order'      => 10,
				'capability' => 'manage_options',
				'callback'   => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! self::user_can_manage() ) {
			wp_die(
				esc_html( __( 'Sorry, you are not allowed to do that.', 'reactwoo-geocore' ) ),
				'',
				array( 'response' => 403 )
			);
		}

		$notice = isset( $_GET['rwgc_ab_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['rwgc_ab_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag.

		$rwgc_ab_rows   = class_exists( 'RWGC_AB_Tests', false ) ? RWGC_AB_Tests::query() : array();
		$rwgc_ab_notice = $notice;
		include RWGC_PATH . 'admin/views/ab-tests-list.php';
	}

	/**
	 * @return void
	 */
	public static function handle_end() {
		self::assert_manage_options();
		$test_id = isset( $_GET['test_id'] ) ? absint( wp_unslash( $_GET['test_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked next.
		check_admin_referer( 'rwgc_ab_end_test_' . $test_id );

		if ( $test_id <= 0 || ! RWGC_AB_Tests::end( $test_id ) ) {
			self::redirect( 'notfound' );
		}
		self::redirect( 'ended' );
	}

	/**
	 * @return void
	 */
	public static function handle_delete() {
		self::assert_manage_options();
		$test_id = isset( $_GET['test_id'] ) ? absint( wp_unslash( $_GET['test_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked next.
		check_admin_referer( 'rwgc_ab_delete_test_' . $test_id );

		if ( $test_id <= 0 || ! RWGC_AB_Tests::delete( $test_id ) ) {
			self::redirect( 'notfound' );
		}
		self::redirect( 'deleted' );
	}

	/**
	 * Link to the post that owns the test, when one is stored.
	 *
	 * @param array<string, mixed> $config Test config.
	 * @return array{url:string,label:string}
	 */
	public static function source_link( array $config ) {
		$source_id = isset( $config['source_post_id'] ) ? absint( $config['source_post_id'] ) : 0;
		if ( $source_id <= 0 ) {
			return array(
				'url'   => '',
				'label' => '',
			);
		}

		$label = '#' . $source_id;
		if ( function_exists( 'get_the_title' ) ) {
			$title = get_the_title( $source_id );
			if ( is_string( $title ) && '' !== $title ) {
				$label = $title;
			}
		}

		$url = '';
		if ( function_exists( 'get_edit_post_link' ) ) {
			$edit = get_edit_post_link( $source_id, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				$url = $edit;
			}
		}
		if ( '' === $url && function_exists( 'get_permalink' ) ) {
			$view = get_permalink( $source_id );
			if ( is_string( $view ) && '' !== $view ) {
				$url = $view;
			}
		}

		return array(
			'url'   => $url,
			'label' => $label,
		);
	}

	/**
	 * @return bool
	 */
	private static function user_can_manage() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * @return void
	 */
	private static function assert_manage_options() {
		if ( self::user_can_manage() ) {
			return;
		}
		wp_die(
			esc_html( __( 'Sorry, you are not allowed to do that.', 'reactwoo-geocore' ) ),
			'',
			array( 'response' => 403 )
		);
	}

	/**
	 * @param string $notice ended|deleted|notfound.
	 * @return void
	 */
	private static function redirect( $notice ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		if ( '' !== $notice ) {
			$url = add_query_arg( 'rwgc_ab_notice', sanitize_key( $notice ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
