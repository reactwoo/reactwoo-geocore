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
	 * Known Atomic nestable elTypes (Twig containers). Leaf Atomic widgets still use type "widget".
	 *
	 * @var array<int, string>
	 */
	private static $known_atomic_nestables = array(
		'e-flexbox',
		'e-div-block',
		'e-grid',
		'e-tabs',
		'e-tabs-menu',
		'e-tab',
		'e-tabs-content',
		'e-tabs-content-area',
	);

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}

		$elements = array( 'section', 'column', 'container', 'widget' );
		foreach ( $elements as $type ) {
			self::hook_element_type( $type );
		}

		// Atomic nestables use elType = e-flexbox / e-div-block / … (not widget|section).
		// Do NOT wait on elementor/frontend/init — that is a JS hook and never fires in PHP.
		foreach ( self::$known_atomic_nestables as $type ) {
			self::hook_element_type( $type );
		}

		// Discover any additional Atomic nestables once Elementor finishes registering types.
		add_action( 'elementor/elements/elements_registered', array( __CLASS__, 'register_atomic_nestable_hooks' ), 20 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ), 20 );
	}

	/**
	 * Attach should_render + before_render for one Elementor elType.
	 *
	 * @param string $type Element type slug.
	 * @return void
	 */
	private static function hook_element_type( $type ) {
		$type = (string) $type;
		if ( '' === $type || isset( self::$atomic_hooks_registered[ $type ] ) ) {
			return;
		}

		self::$atomic_hooks_registered[ $type ] = true;
		add_filter( "elementor/frontend/{$type}/should_render", array( __CLASS__, 'filter_should_render' ), 10, 2 );
		add_action( "elementor/frontend/{$type}/before_render", array( __CLASS__, 'before_render' ), 10, 1 );
	}

	/**
	 * Register should_render for Atomic nestable element types after Elementor registers them.
	 *
	 * @param mixed $manager Optional Elements_Manager from elements_registered.
	 * @return void
	 */
	public static function register_atomic_nestable_hooks( $manager = null ) {
		if ( null === $manager ) {
			if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance->elements_manager ) ) {
				return;
			}
			$manager = \Elementor\Plugin::$instance->elements_manager;
		}

		if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_element_types' ) ) {
			return;
		}

		$classic     = array( 'section', 'column', 'container', 'widget' );
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

			self::hook_element_type( $type );
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
			'.elementor-element.rwgc-geo-element-hidden,.e-atomic-element.rwgc-geo-element-hidden{display:none!important;visibility:hidden!important;}'
		);
	}

	/**
	 * Resolve display settings — prefer Atomic resolved props when available.
	 *
	 * When Atomic schema rejects a saved prop (e.g. legacy `string` countries after
	 * the `string-array` chips change), `get_atomic_settings()` returns null for that
	 * key while other props remain. Empty countries then fail-open and show FR content
	 * to UK visitors — recover geo keys from raw `get_settings()`.
	 *
	 * @param \Elementor\Element_Base $element Element.
	 * @return array<string, mixed>
	 */
	private static function get_element_settings( $element ) {
		$settings = array();
		$raw      = array();

		if ( is_object( $element ) && method_exists( $element, 'get_settings' ) ) {
			$maybe_raw = $element->get_settings();
			if ( is_array( $maybe_raw ) ) {
				$raw = $maybe_raw;
			}
		}

		if ( is_object( $element ) && method_exists( $element, 'get_atomic_settings' ) ) {
			$atomic = $element->get_atomic_settings();
			if ( is_array( $atomic ) ) {
				$settings = $atomic;
			}
		}

		if ( empty( $settings ) && is_object( $element ) && method_exists( $element, 'get_settings_for_display' ) ) {
			$display = $element->get_settings_for_display();
			if ( is_array( $display ) ) {
				$settings = $display;
			}
		}

		$settings = self::merge_raw_geo_settings( $settings, $raw );

		if ( class_exists( 'RWGC_Surface_Settings', false ) ) {
			$settings = RWGC_Surface_Settings::normalize( $settings );
		}

		return $settings;
	}

	/**
	 * Overlay raw geo props when Atomic resolve dropped them (null / empty countries).
	 *
	 * @param array<string, mixed> $settings Resolved / display settings.
	 * @param array<string, mixed> $raw      Raw element settings.
	 * @return array<string, mixed>
	 */
	private static function merge_raw_geo_settings( array $settings, array $raw ) {
		if ( empty( $raw ) ) {
			return $settings;
		}

		$geo_keys = array(
			'egp_enable_geo_targeting',
			'egp_geo_enabled',
			'egp_countries',
			'rwgc_country_visibility_mode',
			'rwgc_enable_visibility_rules',
			'rwgc_visibility_rules_mode',
			'rwgc_visibility_rule_library',
			'rwgc_applied_visibility_rule_id',
			'rwgc_visibility_mode',
			'rwgc_geo_mode',
			'rwgc_use_portable_geo_targeting',
			'egp_use_portable_geo_targeting',
			'rwgc_portable_geo_targeting',
			'egp_portable_geo_targeting',
		);

		foreach ( $geo_keys as $key ) {
			if ( ! array_key_exists( $key, $raw ) || ! self::raw_geo_value_has_data( $raw[ $key ] ) ) {
				continue;
			}

			$resolved_missing = ! array_key_exists( $key, $settings ) || null === $settings[ $key ];
			$resolved_empty_countries = ( 'egp_countries' === $key )
				&& array_key_exists( $key, $settings )
				&& (
					( is_array( $settings[ $key ] ) && array() === $settings[ $key ] )
					|| ( is_string( $settings[ $key ] ) && '' === trim( $settings[ $key ] ) )
				);

			if ( $resolved_missing || $resolved_empty_countries ) {
				$settings[ $key ] = $raw[ $key ];
			}
		}

		return $settings;
	}

	/**
	 * @param mixed $value Raw setting value.
	 * @return bool
	 */
	private static function raw_geo_value_has_data( $value ) {
		if ( null === $value || false === $value || '' === $value ) {
			return false;
		}
		if ( is_array( $value ) ) {
			if ( array_key_exists( '$$type', $value ) && array_key_exists( 'value', $value ) ) {
				return self::raw_geo_value_has_data( $value['value'] );
			}
			return array() !== $value;
		}
		return true;
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
