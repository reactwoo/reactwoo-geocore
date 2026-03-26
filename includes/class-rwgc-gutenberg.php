<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg block integration for Geo Core.
 */
class RWGC_Gutenberg {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'inject_editor_country_options' ) );
	}

	/**
	 * Register blocks.
	 *
	 * @return void
	 */
	public static function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			RWGC_PATH . 'blocks/geo-content',
			array(
				'render_callback' => array( __CLASS__, 'render_geo_content_block' ),
			)
		);
	}

	/**
	 * Server-side render for geo-content block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Inner content.
	 * @return string
	 */
	public static function render_geo_content_block( $attributes, $content ) {
		$attrs = wp_parse_args(
			is_array( $attributes ) ? $attributes : array(),
			array(
				'showCountries' => array(),
				'hideCountries' => array(),
				'mode'          => 'show',
			)
		);

		$country = strtoupper( rwgc_get_visitor_country() );

		$list = array();
		if ( ! empty( $attrs['showCountries'] ) && is_array( $attrs['showCountries'] ) ) {
			$list = array_map( 'strtoupper', $attrs['showCountries'] );
		}

		$show = true;
		if ( ! empty( $list ) ) {
			if ( 'hide' === $attrs['mode'] ) {
				$show = ! in_array( $country, $list, true );
			} else {
				$show = in_array( $country, $list, true );
			}
		} elseif ( ! empty( $attrs['hideCountries'] ) && is_array( $attrs['hideCountries'] ) ) {
			if ( in_array( $country, array_map( 'strtoupper', $attrs['hideCountries'] ), true ) ) {
				$show = false;
			}
		}

		if ( ! $show ) {
			return '';
		}

		return '<div class="rwgc-geo-content">' . do_shortcode( $content ) . '</div>';
	}

	/**
	 * Inject country options into block editor for geo-content block UI.
	 *
	 * @return void
	 */
	public static function inject_editor_country_options() {
		if ( ! wp_script_is( 'rwgc-geo-content-editor', 'enqueued' ) ) {
			return;
		}

		$json = wp_json_encode( RWGC_Countries::get_options() );
		if ( ! $json ) {
			return;
		}

		wp_add_inline_script(
			'rwgc-geo-content-editor',
			'window.rwgcGeoCountryOptions = ' . $json . ';',
			'before'
		);
	}
}

