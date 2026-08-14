<?php
/**
 * Shared Elementor geo visibility controls (document + element surfaces).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers split country targeting and visibility rules controls on Elementor stacks.
 */
class RWGC_Elementor_Geo_Controls {

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @param array<string, mixed>    $args {
	 *     @type string $section_id Section control id.
	 *     @type string $countries_ui select2|native (select2 matches document/page settings; native is legacy)
	 * }
	 * @return void
	 */
	public static function register_section( $element, array $args = array() ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_controls' ) ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'section_id'   => 'rwgc_geo_section',
				'countries_ui' => 'select2',
			)
		);

		$section_id = sanitize_key( (string) $args['section_id'] );
		$controls   = $element->get_controls();
		if ( is_array( $controls ) && isset( $controls[ $section_id ] ) ) {
			return;
		}

		$element->start_controls_section(
			$section_id,
			array(
				'label' => __( 'Geo Visibility', 'reactwoo-geocore' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		self::add_visitor_preview( $element );
		self::add_country_targeting_controls( $element, (string) $args['countries_ui'] );
		self::add_visibility_rules_controls( $element );
		self::add_legacy_element_sync_controls( $element );

		$element->end_controls_section();
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @return void
	 */
	public static function add_visitor_preview( $element ) {
		$preview = self::build_visitor_preview_markup();
		if ( '' === $preview ) {
			return;
		}
		$element->add_control(
			'rwgc_geo_visitor_preview',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => $preview,
				'content_classes' => 'rwgc-geo-visitor-preview',
			)
		);
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @param string                  $countries_ui native|select2
	 * @return void
	 */
	public static function add_country_targeting_controls( $element, $countries_ui = 'select2' ) {
		$element->add_control(
			'rwgc_country_heading',
			array(
				'type'      => \Elementor\Controls_Manager::HEADING,
				'label'     => __( 'Country targeting', 'reactwoo-geocore' ),
				'separator' => 'before',
			)
		);

		$element->add_control(
			'egp_enable_geo_targeting',
			array(
				'label'        => __( 'Enable country targeting', 'reactwoo-geocore' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'reactwoo-geocore' ),
				'label_off'    => __( 'Off', 'reactwoo-geocore' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Limit by visitor country. Leave countries empty to allow all countries.', 'reactwoo-geocore' ),
			)
		);

		$element->add_control(
			'rwgc_country_visibility_mode',
			array(
				'label'     => __( 'Country visibility', 'reactwoo-geocore' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'show_if',
				'options'   => array(
					'show_if' => __( 'Show only when country matches', 'reactwoo-geocore' ),
					'hide_if' => __( 'Hide when country matches', 'reactwoo-geocore' ),
				),
				'condition' => array(
					'egp_enable_geo_targeting' => 'yes',
				),
			)
		);

		if ( 'native' === $countries_ui && class_exists( 'RWGC_Elementor_Elements', false ) ) {
			$element->add_control(
				'egp_countries_html',
				array(
					'type'      => \Elementor\Controls_Manager::RAW_HTML,
					'raw'       => RWGC_Elementor_Elements::get_countries_select_html(),
					'condition' => array(
						'egp_enable_geo_targeting' => 'yes',
					),
				)
			);
			$element->add_control(
				'egp_countries',
				array(
					'type'      => \Elementor\Controls_Manager::HIDDEN,
					'default'   => '',
					'condition' => array(
						'egp_enable_geo_targeting' => 'yes',
					),
				)
			);
			return;
		}

		$element->add_control(
			'egp_countries',
			array(
				'label'       => __( 'Countries', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => self::get_country_options(),
				'description' => __( 'Search and pick countries (same as page and popup settings). Leave empty for all countries.', 'reactwoo-geocore' ),
				'condition'   => array(
					'egp_enable_geo_targeting' => 'yes',
				),
			)
		);
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @return void
	 */
	public static function add_visibility_rules_controls( $element ) {
		if ( ! function_exists( 'rwgc_advanced_targeting_enabled' ) || ! rwgc_advanced_targeting_enabled() ) {
			$element->add_control(
				'rwgc_geo_upgrade_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => '<div style="margin-top:8px;color:#6b7280;">'
						. esc_html__( 'Multi-condition visibility (device, UTM, audiences, and more) is available with GeoCore Pro across Elementor, Gutenberg, and supported builders. Country targeting above is included in GeoCore Free.', 'reactwoo-geocore' )
						. '</div>',
					'content_classes' => 'rwgc-geo-upgrade-note',
				)
			);
			return;
		}

		$element->add_control(
			'rwgc_visibility_rules_heading',
			array(
				'type'      => \Elementor\Controls_Manager::HEADING,
				'label'     => __( 'Visibility rules', 'reactwoo-geocore' ),
				'separator' => 'before',
			)
		);

		$element->add_control(
			'rwgc_enable_visibility_rules',
			array(
				'label'        => __( 'Enable visibility rules', 'reactwoo-geocore' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'reactwoo-geocore' ),
				'label_off'    => __( 'Off', 'reactwoo-geocore' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Use saved or custom rules (page version URL, device, etc.). Independent of country targeting.', 'reactwoo-geocore' ),
			)
		);

		$element->add_control(
			'rwgc_visibility_rules_mode',
			array(
				'label'     => __( 'Visibility rules mode', 'reactwoo-geocore' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'show_if',
				'options'   => array(
					'show_if' => __( 'Show only when rules match', 'reactwoo-geocore' ),
					'hide_if' => __( 'Hide when rules match', 'reactwoo-geocore' ),
				),
				'condition' => array(
					'rwgc_enable_visibility_rules' => 'yes',
				),
			)
		);

		$element->add_control(
			'rwgc_use_portable_geo_targeting',
			array(
				'type'         => \Elementor\Controls_Manager::HIDDEN,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$library_options = array( '' => __( '— Choose saved visibility rule —', 'reactwoo-geocore' ) );
		if ( class_exists( 'RWGC_Elementor_Options', false ) ) {
			$library_options = RWGC_Elementor_Options::visibility_library_select();
		} elseif ( class_exists( 'RWGC_Elementor_Elements', false ) ) {
			$library_options = RWGC_Elementor_Elements::get_visibility_library_select_options();
		}

		$element->add_control(
			'rwgc_visibility_rule_library',
			array(
				'label'       => __( 'Apply saved visibility rule', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $library_options,
				'label_block' => true,
				'description' => __( 'Portable library rules only (Targeting → Visibility rules).', 'reactwoo-geocore' ),
				'condition'   => array(
					'rwgc_enable_visibility_rules' => 'yes',
				),
			)
		);

		$element->add_control(
			'rwgc_applied_visibility_rule_id',
			array(
				'type'      => \Elementor\Controls_Manager::HIDDEN,
				'default'   => '',
				'condition' => array(
					'rwgc_enable_visibility_rules' => 'yes',
				),
			)
		);

		$element->add_control(
			'rwgc_portable_geo_targeting',
			array(
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'label_block' => true,
				'classes'     => 'rwgc-portable-geo-targeting-textarea rwgc-rb-textarea-hidden',
				'condition'   => array(
					'rwgc_enable_visibility_rules' => 'yes',
				),
			)
		);

		$element->add_control(
			'egp_use_portable_geo_targeting',
			array(
				'type'         => \Elementor\Controls_Manager::HIDDEN,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$element->add_control(
			'egp_portable_geo_targeting',
			array(
				'type'      => \Elementor\Controls_Manager::HIDDEN,
				'default'   => '',
				'classes'   => 'egp-portable-geo-targeting-textarea',
			)
		);
	}

	/**
	 * Legacy Elementor element keys still present in saved JSON.
	 *
	 * @param \Elementor\Element_Base $element Element.
	 * @return void
	 */
	public static function add_legacy_element_sync_controls( $element ) {
		$element->add_control(
			'egp_geo_enabled',
			array(
				'type'         => \Elementor\Controls_Manager::HIDDEN,
				'default'      => '',
				'return_value' => 'yes',
			)
		);
		$element->add_control(
			'rwgc_visibility_mode',
			array(
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => 'show_if',
			)
		);
	}

	/**
	 * @return string
	 */
	public static function build_visitor_preview_markup() {
		if ( ! function_exists( 'rwgc_is_ready' ) || ! rwgc_is_ready() || ! function_exists( 'rwgc_get_visitor_data' ) ) {
			return '';
		}
		$d      = rwgc_get_visitor_data();
		$cc     = isset( $d['country_code'] ) ? strtoupper( (string) $d['country_code'] ) : '';
		$cn     = isset( $d['country_name'] ) ? (string) $d['country_name'] : '';
		$city   = isset( $d['city'] ) ? (string) $d['city'] : '';
		$region = isset( $d['region'] ) ? (string) $d['region'] : '';
		$ip     = isset( $d['ip'] ) ? (string) $d['ip'] : '';
		$line1  = $cc;
		if ( '' !== $cn ) {
			$line1 .= ' (' . $cn . ')';
		}
		return '<div style="margin-bottom:10px;padding:8px;border:1px solid #e5e7eb;border-radius:4px;background:#f9fafb;font-size:12px;line-height:1.5;color:#374151;">'
			. '<strong>' . esc_html__( 'Detected for your connection', 'reactwoo-geocore' ) . '</strong><br>'
			. esc_html( $line1 !== '' ? $line1 : '—' ) . '<br>'
			. esc_html__( 'City', 'reactwoo-geocore' ) . ': ' . esc_html( $city !== '' ? $city : '—' ) . '<br>'
			. esc_html__( 'Region', 'reactwoo-geocore' ) . ': ' . esc_html( $region !== '' ? $region : '—' ) . '<br>'
			. esc_html__( 'IP', 'reactwoo-geocore' ) . ': ' . esc_html( $ip !== '' ? $ip : '—' )
			. '</div>';
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_country_options() {
		if ( class_exists( 'RWGC_Elementor_Options', false ) ) {
			return RWGC_Elementor_Options::countries();
		}
		if ( class_exists( 'RWGC_Countries', false ) ) {
			$list = RWGC_Countries::get_options();
			if ( is_array( $list ) && ! empty( $list ) ) {
				return $list;
			}
		}
		return array();
	}
}
