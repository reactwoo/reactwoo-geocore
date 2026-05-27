<?php
/**
 * Reusable portable visibility rule sets (library CPT).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers {@see RWGC_Visibility_Rule_CPT::POST_TYPE} for Core-owned rule library entries.
 */
class RWGC_Visibility_Rule_CPT {

	const POST_TYPE = 'rwgc_visibility_rule';

	const META_PORTABLE = '_rwgc_portable_targeting';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 8 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 9 );
	}

	/**
	 * @return void
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Visibility rules', 'reactwoo-geocore' ),
					'singular_name' => __( 'Visibility rule', 'reactwoo-geocore' ),
					'add_new_item'  => __( 'Add visibility rule', 'reactwoo-geocore' ),
					'edit_item'     => __( 'Edit visibility rule', 'reactwoo-geocore' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_PORTABLE,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_portable_meta' ),
				'auth_callback'     => static function () {
					return current_user_can( RWGC_Admin::required_capability() );
				},
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * @param mixed $value Raw meta.
	 * @return string Sanitized JSON string or empty.
	 */
	public static function sanitize_portable_meta( $value ) {
		$raw = is_string( $value ) ? $value : '';
		if ( '' === trim( $raw ) ) {
			return '';
		}
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return '';
		}
		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
		if ( ! is_array( $set ) ) {
			return '';
		}
		return (string) wp_json_encode( $set );
	}
}
