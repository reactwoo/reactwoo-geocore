<?php
/**
 * Read-only compatibility: register legacy Geo Elementor `geo_rule` CPT when the old plugin is inactive.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps legacy geo_rule posts queryable after Geo Elementor is removed.
 */
class RWGC_Legacy_Geo_Rule_CPT {

	const POST_TYPE = 'geo_rule';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
	}

	/**
	 * @return void
	 */
	public static function register_post_type() {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Geo Rules (legacy)', 'reactwoo-geocore' ),
					'singular_name' => __( 'Geo Rule (legacy)', 'reactwoo-geocore' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'rewrite'             => false,
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
			)
		);
	}
}
