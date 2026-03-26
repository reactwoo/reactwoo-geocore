<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API endpoints for Geo Core.
 */
class RWGC_REST {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! RWGC_Settings::get( 'rest_enabled', 1 ) ) {
			return;
		}

		register_rest_route(
			'reactwoo-geocore/v1',
			'/location',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_location' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);
	}

	/**
	 * Permissions check for location endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function permissions_check( $request ) {
		// This endpoint is intentionally public; site owners can disable it in settings.
		return true;
	}

	/**
	 * Get visitor location payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_location( $request ) {
		$data = RWGC_API::get_visitor_data();
		return rest_ensure_response( $data );
	}
}

