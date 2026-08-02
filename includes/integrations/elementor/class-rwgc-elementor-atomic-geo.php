<?php
/**
 * Elementor Atomic (V4) Geo Visibility section via official Atomic filters.
 *
 * Classic Advanced-tab controls stay in {@see RWGC_Elementor_Geo_Controls}.
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
		add_action( 'elementor/loaded', array( __CLASS__, 'register_hooks' ), 20 );
	}

	/**
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! self::atomic_api_available() ) {
			return;
		}

		add_filter( 'elementor/atomic-widgets/props-schema', array( __CLASS__, 'filter_props_schema' ), 20, 1 );
		add_filter( 'elementor/atomic-widgets/controls', array( __CLASS__, 'filter_controls' ), 20, 2 );
	}

	/**
	 * @return bool
	 */
	private static function atomic_api_available() {
		return class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Section', false )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control', false )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control', false )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control', false )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type', false )
			&& class_exists( '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type', false );
	}

	/**
	 * Merge geo keys into every Atomic props schema (required or Atomic drops controls on save).
	 *
	 * @param array<string, mixed> $schema Props schema.
	 * @return array<string, mixed>
	 */
	public static function filter_props_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			$schema = array();
		}

		$boolean = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type';
		$string  = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type';

		$schema['egp_enable_geo_targeting']        = $boolean::make()->default( false );
		$schema['rwgc_country_visibility_mode']    = $string::make()->enum( array( 'show_if', 'hide_if' ) )->default( 'show_if' );
		$schema['egp_countries']                   = $string::make()->default( '' );
		$schema['rwgc_enable_visibility_rules']    = $boolean::make()->default( false );
		$schema['rwgc_visibility_rules_mode']      = $string::make()->enum( array( 'show_if', 'hide_if' ) )->default( 'show_if' );
		$schema['rwgc_visibility_rule_library']    = $string::make()->default( '' );
		$schema['rwgc_applied_visibility_rule_id'] = $string::make()->default( '' );

		return $schema;
	}

	/**
	 * Append a sibling Geo Visibility section (not Advanced tab).
	 *
	 * @param array<int, mixed>       $controls Existing Atomic sections.
	 * @param \Elementor\Element_Base $element  Element instance.
	 * @return array<int, mixed>
	 */
	public static function filter_controls( $controls, $element = null ) {
		unset( $element );

		if ( ! is_array( $controls ) ) {
			$controls = array();
		}

		$section = self::build_geo_visibility_section();
		if ( null === $section ) {
			return $controls;
		}

		$controls[] = $section;
		return $controls;
	}

	/**
	 * @return \Elementor\Modules\AtomicWidgets\Controls\Section|null
	 */
	private static function build_geo_visibility_section() {
		$section_class = '\Elementor\Modules\AtomicWidgets\Controls\Section';
		$switch        = '\Elementor\Modules\AtomicWidgets\Controls\Types\Switch_Control';
		$select        = '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control';
		$text          = '\Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control';

		$items = array(
			$switch::bind_to( 'egp_enable_geo_targeting' )
				->set_label( __( 'Enable country targeting', 'reactwoo-geocore' ) )
				->set_description( __( 'Limit by visitor country. Leave countries empty to allow all countries.', 'reactwoo-geocore' ) ),
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
			$text::bind_to( 'egp_countries' )
				->set_label( __( 'Countries (ISO codes)', 'reactwoo-geocore' ) )
				->set_placeholder( 'US, GB, DE' )
				->set_description( __( 'Comma-separated ISO 3166-1 alpha-2 codes (e.g. US, GB, DE).', 'reactwoo-geocore' ) ),
		);

		$pro_enabled = function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled();
		if ( $pro_enabled ) {
			$items = array_merge( $items, self::build_visibility_rules_items( $switch, $select ) );
		}

		$section = $section_class::make()
			->set_label( __( 'Geo Visibility', 'reactwoo-geocore' ) )
			->set_id( 'rwgc_geo_visibility' )
			->set_items( $items );

		if ( ! $pro_enabled ) {
			$section->set_description(
				__( 'Multi-condition visibility (device, UTM, audiences, and more) requires GeoCore Pro. Country targeting above is included in GeoCore Free.', 'reactwoo-geocore' )
			);
		}

		return $section;
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
				->set_description( __( 'Use a saved library rule. Independent of country targeting.', 'reactwoo-geocore' ) ),
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
