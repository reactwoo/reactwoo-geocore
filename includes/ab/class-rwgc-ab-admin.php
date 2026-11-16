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
 * Lists rwgc_ab_test posts and one test's results.
 *
 * End, keep, and delete require a nonce and manage_options.
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

		$notice  = isset( $_GET['rwgc_ab_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['rwgc_ab_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag.
		$test_id = isset( $_GET['test_id'] ) ? absint( wp_unslash( (string) $_GET['test_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selector.

		if ( $test_id > 0 && class_exists( 'RWGC_AB_Stats', false ) ) {
			$rwgc_ab_result = RWGC_AB_Stats::for_test( $test_id );
			if ( is_array( $rwgc_ab_result ) ) {
				$rwgc_ab_notice = $notice;
				include RWGC_PATH . 'admin/views/ab-test-results.php';
				return;
			}
			$notice = 'notfound';
		}

		$rwgc_ab_rows   = class_exists( 'RWGC_AB_Tests', false ) ? RWGC_AB_Tests::query() : array();
		$rwgc_ab_notice = $notice;
		self::enqueue_copy_script();
		include RWGC_PATH . 'admin/views/ab-tests-list.php';
	}

	/**
	 * Copy the [rwgc_ab] snippet from the list.
	 *
	 * @return void
	 */
	public static function enqueue_copy_script() {
		if ( ! function_exists( 'wp_enqueue_script' ) || ! defined( 'RWGC_URL' ) ) {
			return;
		}
		$handle = 'rwgc-ab-admin';
		if ( function_exists( 'wp_register_script' ) ) {
			wp_register_script(
				$handle,
				RWGC_URL . 'assets/js/rwgc-ab-admin.js',
				array(),
				defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '1.9.27',
				true
			);
		}
		wp_enqueue_script( $handle );
	}

	/**
	 * @return void
	 */
	public static function handle_end() {
		$test_id = self::posted_absint( 'test_id' );
		self::redirect( self::end_from_request(), $test_id );
	}

	/**
	 * Stop a test, or switch the arm an ended test shows.
	 *
	 * `kept` is a or b. The nonce includes that arm. Calling this again with the
	 * other arm changes who visitors see. It does not start counting again.
	 *
	 * @return string ended|kept-a|kept-b|notfound
	 */
	public static function end_from_request() {
		self::assert_manage_options();
		if ( ! self::is_post() ) {
			return 'notfound';
		}
		$test_id = self::posted_absint( 'test_id' );
		$kept    = self::posted_key( 'kept' );
		if ( 'a' === $kept || 'b' === $kept ) {
			check_admin_referer( 'rwgc_ab_end_test_' . $test_id . '_' . $kept );
		} else {
			$kept = '';
			check_admin_referer( 'rwgc_ab_end_test_' . $test_id );
		}

		if ( $test_id <= 0 || ! class_exists( 'RWGC_AB_Tests', false ) || ! RWGC_AB_Tests::end( $test_id, $kept ) ) {
			return 'notfound';
		}
		if ( 'a' === $kept || 'b' === $kept ) {
			return 'kept-' . $kept;
		}
		return 'ended';
	}

	/**
	 * POST button that ends a test. The nonce is bound to the arm when one is chosen.
	 *
	 * @param int    $test_id Test id.
	 * @param string $kept    a, b, or empty for End without a chosen arm.
	 * @param string $label   Button label.
	 * @param string $class   Button classes.
	 * @param string $confirm Confirm prompt. Empty skips the prompt.
	 * @return void
	 */
	public static function render_end_button( $test_id, $kept, $label, $class, $confirm ) {
		$test_id = absint( $test_id );
		$kept    = sanitize_key( (string) $kept );
		if ( 'a' !== $kept && 'b' !== $kept ) {
			$kept = '';
		}
		$action = ( '' === $kept )
			? 'rwgc_ab_end_test_' . $test_id
			: 'rwgc_ab_end_test_' . $test_id . '_' . $kept;
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-right:8px;">';
		echo '<input type="hidden" name="action" value="rwgc_ab_end_test" />';
		echo '<input type="hidden" name="test_id" value="' . esc_attr( (string) $test_id ) . '" />';
		if ( '' !== $kept ) {
			echo '<input type="hidden" name="kept" value="' . esc_attr( $kept ) . '" />';
		}
		if ( function_exists( 'wp_nonce_field' ) ) {
			wp_nonce_field( $action );
		}
		$onclick = '';
		if ( '' !== (string) $confirm ) {
			$onclick = ' onclick="return confirm( ' . esc_attr( wp_json_encode( (string) $confirm ) ) . ' );"';
		}
		echo '<button type="submit" class="' . esc_attr( (string) $class ) . '"' . $onclick . '>' . esc_html( (string) $label ) . '</button>';
		echo '</form>';
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
	private static function is_post() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared to POST.
		return 'POST' === $method;
	}

	/**
	 * @param string $key Field name.
	 * @return int
	 */
	private static function posted_absint( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller checks the nonce.
			return 0;
		}
		return absint( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * @param string $key Field name.
	 * @return string
	 */
	private static function posted_key( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller checks the nonce.
			return '';
		}
		return sanitize_key( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Experiences → A/B tests and GET /ab/results both use manage_options.
	 *
	 * @return bool
	 */
	public static function user_can_manage() {
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
	 * @param string $notice  ended|kept-a|kept-b|deleted|notfound.
	 * @param int    $test_id Test to reopen after keep.
	 * @return void
	 */
	private static function redirect( $notice, $test_id = 0 ) {
		$url     = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$test_id = absint( $test_id );
		if ( $test_id > 0 && is_string( $notice ) && 0 === strpos( $notice, 'kept-' ) ) {
			$url .= '&test_id=' . $test_id;
		}
		if ( '' !== $notice ) {
			$url = add_query_arg( 'rwgc_ab_notice', sanitize_key( (string) $notice ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
