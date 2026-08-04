<?php
/**
 * Elementor Atomic (V4) Geo Visibility controls via official Atomic filters.
 *
 * Classic Advanced-tab controls stay in {@see RWGC_Elementor_Geo_Controls}.
 * Atomic has no Advanced tab — controls land under General (`settings` section).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Geo Visibility props + controls on Atomic widgets/elements.
 */
class RWGC_Elementor_Atomic_Geo {

	/**
	 * @return void
	 */
	public static function init() {
		// Match Elementor promotions: wait for elementor/init so experiments + Atomic API exist.
		add_action( 'elementor/init', array( __CLASS__, 'register_hooks' ), 20 );
	}

	/**
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! self::is_atomic_widgets_active() ) {
			return;
		}

		add_filter( 'elementor/atomic-widgets/props-schema', array( __CLASS__, 'filter_props_schema' ), 20, 1 );
		add_filter( 'elementor/atomic-widgets/controls', array( __CLASS__, 'filter_controls' ), 20, 2 );
	}

	/**
	 * Elementor experiment gate (same as promotions module).
	 *
	 * @return bool
	 */
	private static function is_atomic_widgets_active() {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance;
		if ( ! $plugin || ! isset( $plugin->experiments ) || ! is_object( $plugin->experiments ) ) {
			return false;
		}

		return (bool) $plugin->experiments->is_feature_active( 'e_atomic_elements' );
	}

	/**
	 * Autoload Atomic API classes when filters run (editor / widget config).
	 *
	 * @return bool
	 */
	private static function atomic_api_available() {
		return class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Section' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Chips_Control' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Array_Prop_Type' )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type' );
	}

	/**
	 * Merge geo keys into every Atomic props schema (required or Atomic drops controls on save).
	 *
	 * @param array<string, mixed> $schema Props schema.
	 * @return array<string, mixed>
	 */
	public static function filter_props_schema( $schema ) {
		if ( ! self::atomic_api_available() ) {
			return is_array( $schema ) ? $schema : array();
		}

		if ( ! is_array( $schema ) ) {
			$schema = array();
		}

		$boolean      = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type';
		$string       = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type';
		$string_array = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Array_Prop_Type';
		$union        = '\Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type';

		if ( ! isset( $schema['egp_enable_geo_targeting'] ) ) {
			$schema['egp_enable_geo_targeting'] = $boolean::make()->default( false );
		}
		if ( ! isset( $schema['rwgc_country_visibility_mode'] ) ) {
			$schema['rwgc_country_visibility_mode'] = $string::make()->enum( array( 'show_if', 'hide_if' ) )->default( 'show_if' );
		}
		// Chips use string-array; keep legacy string (ISO / CSV) resolvable so targeting does not fail-open.
		$schema['egp_countries'] = $union::make()
			->add_prop_type( $string_array::make() )
			->add_prop_type( $string::make() )
			->default( array(), 'string-array' );
		if ( ! isset( $schema['rwgc_enable_visibility_rules'] ) ) {
			$schema['rwgc_enable_visibility_rules'] = $boolean::make()->default( false );
		}
		if ( ! isset( $schema['rwgc_visibility_rules_mode'] ) ) {
			$schema['rwgc_visibility_rules_mode'] = $string::make()->enum( array( 'show_if', 'hide_if' ) )->default( 'show_if' );
		}
		if ( ! isset( $schema['rwgc_visibility_rule_library'] ) ) {
			$schema['rwgc_visibility_rule_library'] = $string::make()->default( '' );
		}
		if ( ! isset( $schema['rwgc_applied_visibility_rule_id'] ) ) {
			$schema['rwgc_applied_visibility_rule_id'] = $string::make()->default( '' );
		}

		return $schema;
	}

	/**
	 * Inject Geo Visibility into General (`settings`) when present; otherwise append a sibling section.
	 *
	 * @param array<int, mixed>       $controls Existing Atomic sections.
	 * @param \Elementor\Element_Base $element  Element instance.
	 * @return array<int, mixed>
	 */
	public static function filter_controls( $controls, $element = null ) {
		unset( $element );

		if ( ! self::atomic_api_available() ) {
			return is_array( $controls ) ? $controls : array();
		}

		if ( ! is_array( $controls ) ) {
			$controls = array();
		}

		if ( self::controls_already_injected( $controls ) ) {
			return $controls;
		}

		$items = self::build_geo_visibility_items();
		if ( empty( $items ) ) {
			return $controls;
		}

		$section_class = '\Elementor\Modules\AtomicWidgets\Controls\Section';
		$settings      = self::find_settings_section( $controls );

		if ( $settings instanceof $section_class ) {
			foreach ( $items as $item ) {
				$settings->add_item( $item );
			}
			return $controls;
		}

		$controls[] = $section_class::make()
			->set_label( __( 'Geo Visibility', 'reactwoo-geocore' ) )
			->set_id( 'rwgc_geo_visibility' )
			->set_items( $items );

		return $controls;
	}

	/**
	 * @param array<int, mixed> $controls Controls list.
	 * @return bool
	 */
	private static function controls_already_injected( array $controls ) {
		$section_class = '\Elementor\Modules\AtomicWidgets\Controls\Section';

		foreach ( $controls as $section ) {
			if ( ! ( $section instanceof $section_class ) ) {
				continue;
			}

			if ( 'rwgc_geo_visibility' === $section->get_id() ) {
				return true;
			}

			foreach ( (array) $section->get_items() as $item ) {
				if ( is_object( $item ) && method_exists( $item, 'get_bind' ) && 'egp_enable_geo_targeting' === $item->get_bind() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param array<int, mixed> $controls Controls list.
	 * @return object|null
	 */
	private static function find_settings_section( array $controls ) {
		$section_class = '\Elementor\Modules\AtomicWidgets\Controls\Section';

		foreach ( $controls as $section ) {
			if ( $section instanceof $section_class && 'settings' === $section->get_id() ) {
				return $section;
			}
		}

		return null;
	}

	/**
	 * @return array<int, mixed>
	 */
	private static function build_geo_visibility_items() {
		$switch = '\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control';
		$select = '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control';
		$chips  = '\Elementor\Modules\AtomicWidgets\Controls\Types\Chips_Control';

		$items = array(
			$switch::bind_to( 'egp_enable_geo_targeting' )
				->set_label( __( 'Enable country targeting', 'reactwoo-geocore' ) )
				->set_description( __( 'Limit by visitor country. Leave countries empty to allow all countries.', 'reactwoo-geocore' ) )
				->set_meta(
					array(
						'topDivider' => true,
					)
				),
			$select::bind_to( 'rwgc_country_visibility_mode' )
				->set_label( __( 'Country visibility', 'reactwoo-geocore' ) )
				->set_options(
					array(
						array(
							'value' => 'show_if',
							'label' => __( 'Show only when country matches', 'reactwoo-geocore' ),
						),
						array(
							'value' => 'hide_if',
							'label' => __( 'Hide when country matches', 'reactwoo-geocore' ),
						),
					)
				),
			$chips::bind_to( 'egp_countries' )
				->set_label( __( 'Countries', 'reactwoo-geocore' ) )
				->set_options( self::get_country_chip_options() )
				->set_free_chips( false )
				->set_description( __( 'Search and pick countries. Leave empty for all countries.', 'reactwoo-geocore' ) ),
		);

		$pro_enabled = function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled();
		if ( $pro_enabled ) {
			$items = array_merge( $items, self::build_visibility_rules_items( $switch, $select ) );
		}

		return $items;
	}

	/**
	 * Pro-gated library rule controls.
	 *
	 * @param string $switch Switch control class.
	 * @param string $select Select control class.
	 * @return array<int, mixed>
	 */
	private static function build_visibility_rules_items( $switch, $select ) {
		return array(
			$switch::bind_to( 'rwgc_enable_visibility_rules' )
				->set_label( __( 'Enable visibility rules', 'reactwoo-geocore' ) )
				->set_description( __( 'Use a saved library rule. Independent of country targeting.', 'reactwoo-geocore' ) )
				->set_meta(
					array(
						'topDivider' => true,
					)
				),
			$select::bind_to( 'rwgc_visibility_rules_mode' )
				->set_label( __( 'Visibility rules mode', 'reactwoo-geocore' ) )
				->set_options(
					array(
						array(
							'value' => 'show_if',
							'label' => __( 'Show only when rules match', 'reactwoo-geocore' ),
						),
						array(
							'value' => 'hide_if',
							'label' => __( 'Hide when rules match', 'reactwoo-geocore' ),
						),
					)
				),
			$select::bind_to( 'rwgc_visibility_rule_library' )
				->set_label( __( 'Apply saved visibility rule', 'reactwoo-geocore' ) )
				->set_options( self::get_library_select_options_for_atomic() )
				->set_description( __( 'Portable library rules only (Targeting → Visibility rules).', 'reactwoo-geocore' ) ),
		);
	}

	/**
	 * Canonical country list as Atomic chips { value, label } rows.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function get_country_chip_options() {
		static $rows = null;
		if ( null !== $rows ) {
			return $rows;
		}

		$options = array();
		if ( class_exists( 'RWGC_Elementor_Geo_Controls', false ) ) {
			$options = RWGC_Elementor_Geo_Controls::get_country_options();
		} elseif ( class_exists( 'RWGC_Elementor_Elements', false ) ) {
			$options = RWGC_Elementor_Elements::get_country_options();
		} elseif ( class_exists( 'RWGC_Countries', false ) ) {
			$list = RWGC_Countries::get_options();
			$options = is_array( $list ) ? $list : array();
		}

		$rows = array();
		foreach ( $options as $value => $label ) {
			$rows[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}

		return $rows;
	}

	/**
	 * Classic SELECT options (id => title) converted to Atomic { value, label } rows.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function get_library_select_options_for_atomic() {
		$options = array(
			'' => __( '— Choose saved visibility rule —', 'reactwoo-geocore' ),
		);

		if ( class_exists( 'RWGC_Elementor_Elements', false ) ) {
			$options = RWGC_Elementor_Elements::get_visibility_library_select_options();
		}

		$rows = array();
		foreach ( $options as $value => $label ) {
			$rows[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}

		return $rows;
	}
}
