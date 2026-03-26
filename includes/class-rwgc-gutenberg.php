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

		$show = true;
		if ( ! empty( $attrs['showCountries'] ) && is_array( $attrs['showCountries'] ) ) {
			$show = in_array( $country, array_map( 'strtoupper', $attrs['showCountries'] ), true );
		}
		if ( ! empty( $attrs['hideCountries'] ) && is_array( $attrs['hideCountries'] ) ) {
			if ( in_array( $country, array_map( 'strtoupper', $attrs['hideCountries'] ), true ) ) {
				$show = false;
			}
		}

		if ( ! $show ) {
			return '';
		}

		return '<div class="rwgc-geo-content">' . do_shortcode( $content ) . '</div>';
	}
}

