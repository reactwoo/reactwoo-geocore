<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only “preview as country” via query string (Phase 3 free UX).
 *
 * Usage (logged-in user who passes {@see self::can_preview_geo()}):
 * Add `?rwgc_preview_country=GB` to any front-end URL. Geo payloads and shortcodes
 * use the overridden ISO2 until the parameter is removed.
 */
class RWGC_Preview {

	const QUERY_VAR = 'rwgc_preview_country';

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_geo_data', array( __CLASS__, 'filter_geo_data' ), 999 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_menu' ), 99 );
	}

	/**
	 * Override resolved geo when preview query is present and allowed.
	 *
	 * @param array $data Geo payload.
	 * @return array
	 */
	public static function filter_geo_data( $data ) {
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$iso = self::get_requested_iso2();
		if ( '' === $iso || ! self::can_preview_geo() ) {
			return $data;
		}

		$data['country_code'] = $iso;
		$data['country_name'] = class_exists( 'RWGC_GeoIP', false ) ? RWGC_GeoIP::get_country_name( $iso ) : $iso;
		$cur                  = class_exists( 'RWGC_API', false ) ? RWGC_API::get_currency_for_country( $iso ) : '';
		if ( '' !== $cur ) {
			$data['currency'] = $cur;
		}
		$data['source'] = 'preview';
		if ( isset( $data['cached'] ) ) {
			$data['cached'] = false;
		}

		return $data;
	}

	/**
	 * Whether the current user may use geo preview overrides.
	 *
	 * @return bool
	 */
	public static function can_preview_geo() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$can = current_user_can( 'manage_options' );
		/**
		 * Whether the current user may use `rwgc_preview_country` overrides.
		 *
		 * @param bool $can Default: administrators only.
		 */
		return (bool) apply_filters( 'rwgc_can_preview_geo', $can );
	}

	/**
	 * ISO2 from request if well-formed.
	 *
	 * @return string Uppercase ISO2 or empty string.
	 */
	public static function get_requested_iso2() {
		if ( empty( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$raw = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$iso = strtoupper( substr( $raw, 0, 2 ) );
		return preg_match( '/^[A-Z]{2}$/', $iso ) ? $iso : '';
	}

	/**
	 * Admin bar notice when preview is active.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
	 * @return void
	 */
	public static function admin_bar_menu( $wp_admin_bar ) {
		if ( ! $wp_admin_bar instanceof WP_Admin_Bar ) {
			return;
		}
		$iso = self::get_requested_iso2();
		if ( '' === $iso || ! self::can_preview_geo() ) {
			return;
		}

		$exit = remove_query_arg( self::QUERY_VAR );
		$wp_admin_bar->add_node(
			array(
				'id'     => 'rwgc-geo-preview',
				'parent' => 'top-secondary',
				'title'  => sprintf(
					/* translators: %s: ISO2 country code */
					esc_html__( 'Geo preview: %s', 'reactwoo-geocore' ),
					esc_html( $iso )
				),
				'href'   => $exit ? esc_url( $exit ) : '',
			)
		);
	}
}
