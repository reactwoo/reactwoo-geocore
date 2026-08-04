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
	 * Nestable Atomic elTypes already hooked this request.
	 *
	 * @var array<string, bool>
	 */
	private static $atomic_hooks_registered = array();

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		$elements = array( 'section', 'column', 'container', 'widget' );
		foreach ( $elements as $type ) {
			add_filter( "elementor/frontend/{$type}/should_render", array( __CLASS__, 'filter_should_render' ), 10, 2 );
			add_action( "elementor/frontend/{$type}/before_render", array( __CLASS__, 'before_render' ), 10, 1 );
		}

		// Atomic nestables use their element slug as get_type() (not widget|section|…).
		add_action( 'elementor/frontend/init', array( __CLASS__, 'register_atomic_nestable_hooks' ), 20 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ), 20 );
	}

	/**
	 * Register should_render for Atomic nestable element types after Elementor registers them.
	 *
	 * @return void
	 */
	public static function register_atomic_nestable_hooks() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance->elements_manager ) ) {
			return;
		}

		$manager = \Elementor\Plugin::$instance->elements_manager;
		if ( ! method_exists( $manager, 'get_element_types' ) ) {
			return;
		}

		$classic = array( 'section', 'column', 'container', 'widget' );
		$atomic_base = '\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Element_Base';

		foreach ( $manager->get_element_types() as $type => $instance ) {
			$type = (string) $type;
			if ( '' === $type || isset( self::$atomic_hooks_registered[ $type ] ) || in_array( $type, $classic, true ) ) {
				continue;
			}

			if ( ! is_object( $instance ) ) {
				continue;
			}

			$is_atomic_nestable = class_exists( $atomic_base, false ) && is_a( $instance, $atomic_base );
			if ( ! $is_atomic_nestable && ! method_exists( $instance, 'get_atomic_settings' ) ) {
				continue;
			}

			// Skip leaf widgets — they still report type "widget" via Widget_Base.
			if ( 'widget' === $type ) {
				continue;
			}

			self::$atomic_hooks_registered[ $type ] = true;
			add_filter( "elementor/frontend/{$type}/should_render", array( __CLASS__, 'filter_should_render' ), 10, 2 );
			add_action( "elementor/frontend/{$type}/before_render", array( __CLASS__, 'before_render' ), 10, 1 );
		}
	}

	/**
	 * Backup CSS when inline render attributes are stripped by optimizers.
	 *
	 * @return void
	 */
	public static function enqueue_styles() {
		if ( ! wp_style_is( 'rwgc-elementor-frontend', 'registered' ) ) {
			wp_register_style( 'rwgc-elementor-frontend', false, array(), defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '1.0.0' );
		}
		wp_enqueue_style( 'rwgc-elementor-frontend' );
		wp_add_inline_style(
			'rwgc-elementor-frontend',
			'.elementor-element.rwgc-geo-element-hidden{display:none!important;visibility:hidden!important;}'
		);
	}

	/**
	 * Resolve display settings — prefer Atomic resolved props when available.
	 *
	 * @param \Elementor\Element_Base $element Element.
	 * @return array<string, mixed>
	 */
	private static function get_element_settings( $element ) {
		$settings     = array();
		$raw_settings = self::get_raw_element_settings( $element );

		if ( is_object( $element ) && method_exists( $element, 'get_atomic_settings' ) ) {
			$atomic = $element->get_atomic_settings();
			if ( is_array( $atomic ) && ! empty( $atomic ) ) {
				$settings = $atomic;
				// Schema type mismatches (legacy CSV string vs string-array) resolve to null.
				if ( self::atomic_countries_need_raw_fallback( $settings, $raw_settings ) ) {
					$settings['egp_countries'] = $raw_settings['egp_countries'];
				}
			}
		}

		if ( empty( $settings ) ) {
			$settings = $raw_settings;
		}

		if ( class_exists( 'RWGC_Surface_Settings', false ) ) {
			$settings = RWGC_Surface_Settings::normalize( $settings );
		}

		return $settings;
	}

	/**
	 * Raw Elementor settings (may still carry Atomic `{ $$type, value }` envelopes).
	 *
	 * @param \Elementor\Element_Base $element Element.
	 * @return array<string, mixed>
	 */
	private static function get_raw_element_settings( $element ) {
		if ( ! is_object( $element ) ) {
			return array();
		}

		if ( method_exists( $element, 'get_settings' ) ) {
			$raw = $element->get_settings();
			if ( is_array( $raw ) && ! empty( $raw ) ) {
				return $raw;
			}
		}

		if ( method_exists( $element, 'get_settings_for_display' ) ) {
			$display = $element->get_settings_for_display();
			if ( is_array( $display ) ) {
				return $display;
			}
		}

		return array();
	}

	/**
	 * @param array<string, mixed> $atomic Resolved Atomic settings.
	 * @param array<string, mixed> $raw    Raw element settings.
	 * @return bool
	 */
	private static function atomic_countries_need_raw_fallback( array $atomic, array $raw ) {
		if ( ! array_key_exists( 'egp_countries', $raw ) ) {
			return false;
		}

		$resolved = $atomic['egp_countries'] ?? null;
		if ( is_string( $resolved ) && '' !== trim( $resolved ) ) {
			return false;
		}
		if ( is_array( $resolved ) && ! empty( $resolved ) ) {
			return false;
		}

		$legacy = $raw['egp_countries'];
		if ( is_string( $legacy ) && '' !== trim( $legacy ) ) {
			return true;
		}
		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Skip rendering geo-hidden elements (preferred over display:none for flex rows/containers).
	 *
	 * @param bool                    $should_render Elementor default.
	 * @param \Elementor\Element_Base $element       Element.
	 * @return bool
	 */
	public static function filter_should_render( $should_render, $element ) {
		if ( ! $should_render || ! is_object( $element ) ) {
			return (bool) $should_render;
		}

		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return true;
		}

		$settings = self::get_element_settings( $element );
		if ( empty( $settings ) ) {
			return (bool) $should_render;
		}

		if ( class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			if ( ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
				return (bool) $should_render;
			}
		} elseif ( empty( $settings['egp_geo_enabled'] ) || 'yes' !== (string) $settings['egp_geo_enabled'] ) {
			return (bool) $should_render;
		}

		return self::settings_should_render( $settings );
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @return void
	 */
	public static function before_render( $element ) {
		if ( ! is_object( $element ) ) {
			return;
		}

		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		$settings = self::get_element_settings( $element );
		if ( empty( $settings ) ) {
			return;
		}

		if ( class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			if ( ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
				return;
			}
		} elseif ( empty( $settings['egp_geo_enabled'] ) || 'yes' !== (string) $settings['egp_geo_enabled'] ) {
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
		if ( class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			return RWGC_Targeting_Surface_Evaluator::parse_countries( $settings );
		}

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
