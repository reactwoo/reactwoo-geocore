<?php
/**
 * Elementor element-level geo controls (GeoCore Free + Pro-gated portable rules).
 *
 * Uses legacy `egp_*` control keys so existing Elementor JSON and Geo Elementor data keep working.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Geo Targeting section on sections, columns, containers, widgets, popups.
 */
class RWGC_Elementor_Elements {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'elementor/init', array( __CLASS__, 'register_hooks' ), 20 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ), 6 );
	}

	/**
	 * Rule builder + Elementor mount script for portable element controls.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		if ( class_exists( 'RWGC_Targeting_Rule_Builder_Assets', false ) ) {
			RWGC_Targeting_Rule_Builder_Assets::enqueue_elementor();
		}
		if ( class_exists( 'RWGC_Elementor', false ) ) {
			RWGC_Elementor::enqueue_editor_portable_assist();
		}
		self::enqueue_visibility_library_bridge();
	}

	/**
	 * Library picker + rule builder mount for element-level portable controls.
	 *
	 * @return void
	 */
	public static function enqueue_visibility_library_bridge() {
		wp_register_script(
			'rwgc-elementor-library-bridge',
			RWGC_URL . 'assets/js/rwgc-elementor-library-bridge.js',
			array( 'jquery', 'elementor-editor' ),
			RWGC_VERSION,
			true
		);
		wp_localize_script(
			'rwgc-elementor-library-bridge',
			'rwgcElementorLibrary',
			array(
				'library' => self::get_visibility_library_rows(),
			)
		);
		wp_enqueue_script( 'rwgc-elementor-library-bridge' );
	}

	/**
	 * @return array<int, array{id:int,title:string,json:string}>
	 */
	public static function get_visibility_library_rows() {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return array();
		}
		return RWGC_Visibility_Rule_Repository::get_library_picker_rows();
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_visibility_library_select_options() {
		$options = array(
			'' => __( '— Choose saved visibility rule —', 'reactwoo-geocore' ),
		);
		foreach ( self::get_visibility_library_rows() as $row ) {
			if ( empty( $row['id'] ) ) {
				continue;
			}
			$options[ (string) (int) $row['id'] ] = isset( $row['title'] ) ? (string) $row['title'] : ( '#' . (int) $row['id'] );
		}
		return $options;
	}

	/**
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$hooks = array(
			'elementor/element/common/_section_style/after_section_end',
			'elementor/element/section/section_advanced/after_section_end',
			'elementor/element/column/section_advanced/after_section_end',
			'elementor/element/container/section_layout/after_section_end',
			'elementor/element/popup/section_advanced/after_section_end',
		);

		foreach ( $hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'add_geo_targeting_controls' ), 10, 2 );
		}
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @param array<string, mixed>    $args    Args.
	 * @return void
	 */
	public static function add_geo_targeting_controls( $element, $args = null ) {
		unset( $args );

		if ( ! is_object( $element ) || ! method_exists( $element, 'get_controls' ) ) {
			return;
		}

		$controls = $element->get_controls();
		if ( is_array( $controls ) && isset( $controls['egp_geo_tools'] ) ) {
			return;
		}

		$advanced = function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled();

		$element->start_controls_section(
			'egp_geo_tools',
			array(
				'label' => __( 'Geo Targeting', 'reactwoo-geocore' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$preview = self::build_visitor_preview_markup();
		if ( '' !== $preview ) {
			$element->add_control(
				'egp_visitor_geo_preview',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => $preview,
					'content_classes' => 'egp-visitor-geo-preview',
				)
			);
		}

		$element->add_control(
			'egp_geo_enabled',
			array(
				'label'        => __( 'Enable Geo Targeting', 'reactwoo-geocore' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'reactwoo-geocore' ),
				'label_off'    => __( 'Off', 'reactwoo-geocore' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$element->add_control(
			'rwgc_visibility_mode',
			array(
				'label'       => __( 'Visibility mode', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'show_if',
				'options'     => array(
					'show_if' => __( 'Show only when rules match', 'reactwoo-geocore' ),
					'hide_if' => __( 'Hide when rules match', 'reactwoo-geocore' ),
				),
				'description' => __( 'Show mode: visible only to matching visitors. Hide mode: hidden from matching visitors (useful for replacement content).', 'reactwoo-geocore' ),
				'condition'   => array(
					'egp_geo_enabled' => 'yes',
				),
			)
		);

		$countries_html = self::get_countries_select_html();
		$element->add_control(
			'egp_countries_html',
			array(
				'type'      => \Elementor\Controls_Manager::RAW_HTML,
				'raw'       => $countries_html,
				'condition' => array(
					'egp_geo_enabled'                => 'yes',
					'egp_use_portable_geo_targeting' => '',
				),
			)
		);

		$element->add_control(
			'egp_countries',
			array(
				'type'      => \Elementor\Controls_Manager::HIDDEN,
				'default'   => '',
				'condition' => array(
					'egp_geo_enabled'                => 'yes',
					'egp_use_portable_geo_targeting' => '',
				),
			)
		);

		if ( $advanced ) {
			$element->add_control(
				'egp_use_portable_geo_targeting',
				array(
					'label'        => __( 'Use visibility rule builder', 'reactwoo-geocore' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Yes', 'reactwoo-geocore' ),
					'label_off'    => __( 'No', 'reactwoo-geocore' ),
					'return_value' => 'yes',
					'default'      => '',
					'description'  => __( 'Advanced targeting via GeoCore Pro. Pick a saved rule below or build conditions in the rule builder.', 'reactwoo-geocore' ),
					'condition'    => array( 'egp_geo_enabled' => 'yes' ),
				)
			);

			$element->add_control(
				'rwgc_visibility_rule_library',
				array(
					'label'       => __( 'Apply saved visibility rule', 'reactwoo-geocore' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => self::get_visibility_library_select_options(),
					'label_block' => true,
					'description' => __( 'Loads a rule from Targeting → Visibility rules. You can still edit conditions after applying.', 'reactwoo-geocore' ),
					'condition'   => array(
						'egp_geo_enabled'                => 'yes',
						'egp_use_portable_geo_targeting' => 'yes',
					),
				)
			);

			$element->add_control(
				'egp_portable_geo_targeting',
				array(
					'label'       => __( 'Visibility rules', 'reactwoo-geocore' ),
					'type'        => \Elementor\Controls_Manager::TEXTAREA,
					'rows'        => 6,
					'label_block' => true,
					'classes'     => 'egp-portable-geo-targeting-textarea rwgc-rb-textarea-hidden',
					'condition'   => array(
						'egp_geo_enabled'                => 'yes',
						'egp_use_portable_geo_targeting' => 'yes',
					),
				)
			);
		} else {
			$element->add_control(
				'egp_geocore_pro_upgrade',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => '<div style="background:#f0f6fc;border:1px solid #c3dafe;color:#1e3a5f;padding:12px;border-radius:4px;margin-top:8px;">'
						. '<strong>' . esc_html__( 'GeoCore Pro', 'reactwoo-geocore' ) . '</strong><br>'
						. esc_html__( 'Upgrade to GeoCore Pro for multi-condition visibility (device, UTM, audiences, page versions) in Elementor and Gutenberg.', 'reactwoo-geocore' )
						. '</div>',
					'content_classes' => 'egp-upgrade-box',
					'condition'       => array( 'egp_geo_enabled' => 'yes' ),
				)
			);
		}

		$element->end_controls_section();
	}

	/**
	 * @return string
	 */
	private static function build_visitor_preview_markup() {
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
	 * @return string
	 */
	private static function get_countries_select_html() {
		$opts = self::get_country_options();
		$html = '<div class="egp-countries-native"><label class="elementor-control-title">' . esc_html__( 'Target Countries', 'reactwoo-geocore' ) . '</label><div class="elementor-control-input-wrapper">';
		$html .= '<select id="egp_countries_native" class="egp-country-select" multiple size="12" style="width:100%;max-width:100%;min-height:220px;">';
		foreach ( $opts as $code => $name ) {
			$html .= '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
		}
		$html .= '</select><p class="description">' . esc_html__( 'Hold Ctrl/Cmd to select multiple countries. Included in GeoCore Free.', 'reactwoo-geocore' ) . '</p></div></div>';
		return $html;
	}

	/**
	 * @return array<string, string>
	 */
	private static function get_country_options() {
		if ( class_exists( 'RWGC_Countries', false ) ) {
			$list = RWGC_Countries::get_options();
			if ( is_array( $list ) && ! empty( $list ) ) {
				return $list;
			}
		}
		return array();
	}
}
