<?php
/**
 * Post-level geo visibility for the block editor (parity with Elementor document settings).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers post meta and applies visibility on the front end.
 */
class RWGC_Gutenberg_Post_Geo {

	const META_ENABLED   = '_rwgc_post_geo_enabled';
	const META_MODE      = '_rwgc_post_geo_mode';
	const META_COUNTRIES = '_rwgc_post_geo_countries';
	const META_USE_PORTABLE = '_rwgc_post_use_portable_targeting';
	const META_PORTABLE  = '_rwgc_post_portable_targeting';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ), 12 );
		add_filter( 'the_content', array( __CLASS__, 'filter_post_content' ), 8 );
	}

	/**
	 * @return void
	 */
	public static function register_meta() {
		$post_types = apply_filters(
			'rwgc_gutenberg_post_geo_post_types',
			array( 'post', 'page' )
		);

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_ENABLED,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					'sanitize_callback' => static function ( $value ) {
						return 'yes' === (string) $value ? 'yes' : '';
					},
				)
			);
			register_post_meta(
				$post_type,
				self::META_MODE,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					'sanitize_callback' => static function ( $value ) {
						return function_exists( 'rwgc_normalize_visibility_mode' ) ? rwgc_normalize_visibility_mode( $value ) : sanitize_key( (string) $value );
					},
				)
			);
			register_post_meta(
				$post_type,
				self::META_COUNTRIES,
				array(
					'type'              => 'array',
					'single'            => true,
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					'sanitize_callback' => array( __CLASS__, 'sanitize_countries' ),
				)
			);
			register_post_meta(
				$post_type,
				self::META_USE_PORTABLE,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					'sanitize_callback' => static function ( $value ) {
						return 'yes' === (string) $value ? 'yes' : '';
					},
				)
			);
			register_post_meta(
				$post_type,
				self::META_PORTABLE,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					'sanitize_callback' => array( __CLASS__, 'sanitize_portable' ),
				)
			);
		}
	}

	/**
	 * @return bool
	 */
	public static function can_edit_meta() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param mixed $value Raw countries.
	 * @return array<int, string>
	 */
	public static function sanitize_countries( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $code ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			if ( 2 === strlen( $code ) ) {
				$out[] = $code;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $value Raw JSON.
	 * @return string
	 */
	public static function sanitize_portable( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return '';
		}
		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $value );
		return is_array( $set ) ? wp_json_encode( $set ) : '';
	}

	/**
	 * @return void
	 */
	public static function enqueue_editor() {
		if ( ! class_exists( 'RWGC_Targeting_Rule_Builder_Assets', false ) ) {
			return;
		}

		RWGC_Targeting_Rule_Builder_Assets::enqueue_block_editor();

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! method_exists( $screen, 'is_block_editor' ) || ! $screen->is_block_editor() ) {
			return;
		}

		wp_enqueue_script(
			'rwgc-post-geo-editor',
			RWGC_URL . 'assets/js/rwgc-post-geo-editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'rwgc-rule-builder' ),
			RWGC_VERSION,
			true
		);

		wp_localize_script(
			'rwgc-post-geo-editor',
			'rwgcPostGeoEditor',
			array(
				'advancedTargeting' => function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled(),
				'countries'         => class_exists( 'RWGC_Countries', false ) ? RWGC_Countries::get_options() : array(),
				'meta'              => array(
					'enabled'   => self::META_ENABLED,
					'mode'      => self::META_MODE,
					'countries' => self::META_COUNTRIES,
					'usePortable' => self::META_USE_PORTABLE,
					'portable'  => self::META_PORTABLE,
				),
			)
		);
	}

	/**
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_post_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || 'yes' !== (string) get_post_meta( $post_id, self::META_ENABLED, true ) ) {
			return $content;
		}

		$settings = array(
			'egp_geo_enabled'                => 'yes',
			'egp_use_portable_geo_targeting' => (string) get_post_meta( $post_id, self::META_USE_PORTABLE, true ),
			'egp_portable_geo_targeting'     => (string) get_post_meta( $post_id, self::META_PORTABLE, true ),
			'egp_countries'                  => get_post_meta( $post_id, self::META_COUNTRIES, true ),
			'rwgc_visibility_mode'           => (string) get_post_meta( $post_id, self::META_MODE, true ),
			'rwgc_geo_mode'                  => (string) get_post_meta( $post_id, self::META_MODE, true ),
		);

		if ( ! class_exists( 'RWGC_Elementor_Frontend', false ) ) {
			return $content;
		}

		return RWGC_Elementor_Frontend::settings_should_render( $settings ) ? $content : '';
	}
}
