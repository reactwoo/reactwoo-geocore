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

	const META_ENABLED              = '_rwgc_post_geo_enabled';
	const META_MODE                 = '_rwgc_post_geo_mode';
	const META_COUNTRIES            = '_rwgc_post_geo_countries';
	const META_USE_PORTABLE         = '_rwgc_post_use_portable_targeting';
	const META_PORTABLE             = '_rwgc_post_portable_targeting';
	const META_COUNTRY_ENABLED      = '_rwgc_post_country_enabled';
	const META_COUNTRY_MODE         = '_rwgc_post_country_visibility_mode';
	const META_VISIBILITY_ENABLED   = '_rwgc_post_visibility_rules_enabled';
	const META_VISIBILITY_MODE      = '_rwgc_post_visibility_rules_mode';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ), 12 );
		add_filter( 'the_content', array( __CLASS__, 'filter_post_content' ), 8 );
		add_filter( 'the_excerpt', array( __CLASS__, 'filter_post_content' ), 8 );
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
			add_filter( 'rest_prepare_' . $post_type, array( __CLASS__, 'filter_rest_response' ), 10, 3 );
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
			self::register_yes_no_meta( $post_type, self::META_COUNTRY_ENABLED );
			self::register_mode_meta( $post_type, self::META_COUNTRY_MODE );
			self::register_yes_no_meta( $post_type, self::META_VISIBILITY_ENABLED );
			self::register_mode_meta( $post_type, self::META_VISIBILITY_MODE );
		}
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $meta_key  Meta key.
	 * @return void
	 */
	private static function register_yes_no_meta( $post_type, $meta_key ) {
		register_post_meta(
			$post_type,
			$meta_key,
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
	}

	/**
	 * @param string $post_type Post type.
	 * @param string $meta_key  Meta key.
	 * @return void
	 */
	private static function register_mode_meta( $post_type, $meta_key ) {
		register_post_meta(
			$post_type,
			$meta_key,
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
					'enabled'           => self::META_ENABLED,
					'mode'              => self::META_MODE,
					'countries'         => self::META_COUNTRIES,
					'usePortable'       => self::META_USE_PORTABLE,
					'portable'          => self::META_PORTABLE,
					'countryEnabled'    => self::META_COUNTRY_ENABLED,
					'countryMode'       => self::META_COUNTRY_MODE,
					'visibilityEnabled' => self::META_VISIBILITY_ENABLED,
					'visibilityMode'    => self::META_VISIBILITY_MODE,
				),
			)
		);
	}

	/**
	 * Hide geo-restricted post HTML on every public render path.
	 *
	 * The previous is_singular/in_the_loop/is_main_query gate left REST
	 * `content.rendered`, RSS/Atom feeds, and Query Loop / archive `the_content`
	 * unfiltered. Block-editor REST still bypasses the gate so authors can load
	 * the post they are editing.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function filter_post_content( $content ) {
		if ( is_admin() ) {
			return $content;
		}

		$post_id = self::resolve_content_gate_post_id();
		if ( $post_id <= 0 || self::editor_rest_bypasses_post_geo( $post_id ) ) {
			return $content;
		}

		return self::post_geo_allows_content( $post_id ) ? $content : '';
	}

	/**
	 * Empty rendered REST fields for anonymous/public consumers.
	 *
	 * @param mixed $response REST response.
	 * @param mixed $post     Post object.
	 * @param mixed $request  REST request (unused).
	 * @return mixed
	 */
	public static function filter_rest_response( $response, $post, $request = null ) {
		unset( $request );
		$post_id = 0;
		if ( is_object( $post ) && isset( $post->ID ) ) {
			$post_id = absint( $post->ID );
		} elseif ( is_array( $post ) && isset( $post['ID'] ) ) {
			$post_id = absint( $post['ID'] );
		}
		if ( $post_id <= 0 || self::editor_rest_bypasses_post_geo( $post_id ) ) {
			return $response;
		}
		if ( self::post_geo_allows_content( $post_id ) ) {
			return $response;
		}
		if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			$data['content']['rendered'] = '';
		}
		if ( isset( $data['excerpt'] ) && is_array( $data['excerpt'] ) ) {
			$data['excerpt']['rendered'] = '';
		}
		$response->set_data( $data );
		return $response;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool True when geo is inactive or the visitor may see the post.
	 */
	public static function post_geo_allows_content( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return true;
		}
		if ( ! class_exists( 'RWGC_Surface_Settings', false ) || ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			return true;
		}

		$settings = RWGC_Surface_Settings::from_post_meta( $post_id );
		if ( ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
			return true;
		}

		$result = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );
		return ! empty( $result['should_render'] );
	}

	/**
	 * Block editor loads post HTML via REST; never blank it for users who can edit.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function editor_rest_bypasses_post_geo( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! self::is_rest_request() ) {
			return false;
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * @return int
	 */
	private static function resolve_content_gate_post_id() {
		$post_id = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;
		if ( $post_id > 0 ) {
			return $post_id;
		}
		if ( function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_queried_object_id' ) ) {
			return absint( get_queried_object_id() );
		}
		return 0;
	}

	/**
	 * @return bool
	 */
	private static function is_rest_request() {
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			return true;
		}
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
