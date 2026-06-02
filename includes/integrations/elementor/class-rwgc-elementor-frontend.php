<?php
/**
 * Frontend visibility for Elementor elements using saved control settings (egp_* keys).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hides Elementor elements when geo settings do not match the visitor.
 */
class RWGC_Elementor_Frontend {

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		$elements = array( 'section', 'column', 'container', 'widget' );
		foreach ( $elements as $type ) {
			add_action( "elementor/frontend/{$type}/before_render", array( __CLASS__, 'before_render' ), 10, 1 );
		}
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @return void
	 */
	public static function before_render( $element ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) ) {
			return;
		}

		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		$settings = $element->get_settings_for_display();
		if ( ! is_array( $settings ) || empty( $settings['egp_geo_enabled'] ) || 'yes' !== (string) $settings['egp_geo_enabled'] ) {
			return;
		}

		if ( self::settings_should_render( $settings ) ) {
			return;
		}

		if ( method_exists( $element, 'add_render_attribute' ) ) {
			$element->add_render_attribute( '_wrapper', 'class', 'rwgc-geo-element-hidden', true );
			$element->add_render_attribute( '_wrapper', 'style', 'display:none !important;', true );
		}
	}

	/**
	 * Public helper for Gutenberg post-level checks (same rules as Elementor elements).
	 *
	 * @param array<string, mixed> $settings Settings using egp_* / rwgc_* keys.
	 * @return bool
	 */
	public static function settings_match_visitor( array $settings ) {
		if ( class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			$result = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );
			return (bool) $result['rules_match'];
		}

		$countries = self::parse_countries( $settings );
		if ( empty( $countries ) ) {
			return true;
		}

		$country = function_exists( 'rwgc_get_visitor_country' ) ? strtoupper( (string) rwgc_get_visitor_country() ) : '';
		if ( '' === $country ) {
			return true;
		}

		return in_array( $country, $countries, true );
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function settings_should_render( array $settings ) {
		if ( class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			$result = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );
			if ( ! $result['targeting_enabled'] ) {
				return true;
			}
			return (bool) $result['should_render'];
		}

		$matched = self::settings_match_visitor( $settings );
		if ( function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
			$mode = isset( $settings['rwgc_visibility_mode'] ) ? $settings['rwgc_visibility_mode'] : ( isset( $settings['rwgc_geo_mode'] ) ? $settings['rwgc_geo_mode'] : 'show_if' );
			return rwgc_visibility_mode_allows_render( $mode, $matched );
		}
		return $matched;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return array<int, string>
	 */
	private static function parse_countries( array $settings ) {
		$raw = isset( $settings['egp_countries'] ) ? $settings['egp_countries'] : '';
		if ( is_array( $raw ) ) {
			$list = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$list = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
			$list = is_array( $list ) ? $list : array();
		} else {
			$list = array();
		}

		$out = array();
		foreach ( $list as $code ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			if ( 2 === strlen( $code ) ) {
				$out[] = $code;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
