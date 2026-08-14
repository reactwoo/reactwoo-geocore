<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic Elementor geo visibility controls for Geo Core.
 *
 * Free baseline: show/hide by country for page and popup documents.
 */
class RWGC_Elementor {

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_hooks' ), 20 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( __CLASS__, 'enqueue_editor_portable_assist' ) );
	}

	/**
	 * Register control and render hooks.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Add controls only at document level (page/post/popup settings).
		add_action( 'elementor/element/wp-page/document_settings/after_section_end', array( __CLASS__, 'add_document_controls' ), 10, 2 );
		add_action( 'elementor/element/wp-post/document_settings/after_section_end', array( __CLASS__, 'add_document_controls' ), 10, 2 );
		add_action( 'elementor/element/popup/document_settings/after_section_end', array( __CLASS__, 'add_document_controls' ), 10, 2 );

		// Enforce page-level visibility on frontend Elementor-rendered pages.
		add_filter( 'elementor/frontend/the_content', array( __CLASS__, 'filter_document_content' ), 10, 1 );
	}

	/**
	 * Add Geo Core controls to Elementor document settings.
	 *
	 * @param \Elementor\Element_Base $element Elementor document.
	 * @return void
	 */
	public static function add_document_controls( $element ) {
		$controls = $element->get_controls();
		if ( is_array( $controls ) && isset( $controls['rwgc_geo_section'] ) ) {
			return;
		}

		$element->start_controls_section(
			'rwgc_geo_section',
			array(
				'label' => __( 'Geo Visibility', 'reactwoo-geocore' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$visitor_preview = class_exists( 'RWGC_Elementor_Options', false )
			? RWGC_Elementor_Options::visitor_preview( array( __CLASS__, 'build_visitor_preview_markup' ) )
			: self::build_visitor_preview_markup();
		if ( $visitor_preview !== '' ) {
			$element->add_control(
				'rwgc_geo_visitor_preview',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => $visitor_preview,
					'content_classes' => 'rwgc-geo-visitor-preview',
				)
			);
		}

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
				'label'       => __( 'Country visibility', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'show_if',
				'options'     => array(
					'show_if' => __( 'Show only when country matches', 'reactwoo-geocore' ),
					'hide_if' => __( 'Hide when country matches', 'reactwoo-geocore' ),
				),
				'condition'   => array(
					'egp_enable_geo_targeting' => 'yes',
				),
			)
		);

		$element->add_control(
			'egp_countries',
			array(
				'label'       => __( 'Countries', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'options'     => self::get_country_options(),
				'description' => __( 'Search and pick countries. Leave empty for all countries.', 'reactwoo-geocore' ),
				'condition'   => array(
					'egp_enable_geo_targeting' => 'yes',
				),
			)
		);

		if ( function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled() ) {
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

			if ( class_exists( 'RWGC_Elementor_Elements', false ) ) {
				$library_options = array( '' => __( '— Choose saved visibility rule —', 'reactwoo-geocore' ) );
				if ( ! $heavy ) {
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
			}

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
		} elseif ( function_exists( 'rwgc_advanced_targeting_enabled' ) && ! rwgc_advanced_targeting_enabled() ) {
			$element->add_control(
				'rwgc_geo_upgrade_note',
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => '<div style="margin-top:8px;color:#6b7280;">'
						. esc_html__( 'Multi-condition visibility (device, UTM, audiences, and more) is available with GeoCore Pro across Elementor, Gutenberg, and supported builders. Country targeting above is included in GeoCore Free.', 'reactwoo-geocore' )
						. '</div>',
					'content_classes' => 'rwgc-geo-upgrade-note',
					'condition'       => array(
						'egp_enable_geo_targeting' => 'yes',
					),
				)
			);
		}

		$element->add_control(
			'rwgc_route_heading',
			array(
				'type'      => \Elementor\Controls_Manager::HEADING,
				'label'     => __( 'Page Variant Routing (Free)', 'reactwoo-geocore' ),
				'separator' => 'before',
			)
		);

		$element->add_control(
			'rwgc_route_enabled',
			array(
				'label'        => __( 'Enable Page Variant Routing', 'reactwoo-geocore' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'reactwoo-geocore' ),
				'label_off'    => __( 'Off', 'reactwoo-geocore' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$element->add_control(
			'rwgc_route_role',
			array(
				'label'     => __( 'Page role', 'reactwoo-geocore' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'master',
				'options'   => array(
					'master'  => __( 'Master (default page)', 'reactwoo-geocore' ),
					'variant' => __( 'Secondary (country-specific page)', 'reactwoo-geocore' ),
				),
				'condition' => array(
					'rwgc_route_enabled' => 'yes',
				),
			)
		);

		$element->add_control(
			'rwgc_route_master_page_id',
			array(
				'label'       => __( 'Master page', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => false,
				'label_block' => true,
				'options'     => self::get_master_page_options(),
				'condition'   => array(
					'rwgc_route_enabled'       => 'yes',
					'rwgc_route_role'          => 'variant',
				),
			)
		);

		$element->add_control(
			'rwgc_route_country_iso2',
			array(
				'label'       => __( 'Variant country', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => false,
				'label_block' => true,
				'options'     => self::get_country_options(),
				'condition'   => array(
					'rwgc_route_enabled'       => 'yes',
					'rwgc_route_role'          => 'variant',
				),
			)
		);

		$element->add_control(
			'rwgc_route_free_note',
			array(
				'type'      => \Elementor\Controls_Manager::RAW_HTML,
				'raw'       => '<div style="margin-top:8px;color:#6b7280;">'
					. esc_html__( 'Free limit: one variant per master page. Use GeoCore Pro for multiple variants and advanced rules.', 'reactwoo-geocore' )
					. '</div>',
				'condition' => array(
					'rwgc_route_enabled' => 'yes',
				),
			)
		);

		$element->end_controls_section();
	}

	/**
	 * HTML for “current connection” geo (country, city, region, IP) in the editor.
	 *
	 * @return string Empty if Geo Core is not available or not ready.
	 */
	public static function build_visitor_preview_markup() {
		if ( ! function_exists( 'rwgc_is_ready' ) || ! rwgc_is_ready() || ! function_exists( 'rwgc_get_visitor_data' ) ) {
			return '';
		}
		$d      = rwgc_get_visitor_data();
		$ip     = isset( $d['ip'] ) ? (string) $d['ip'] : '';
		$cc     = isset( $d['country_code'] ) ? strtoupper( (string) $d['country_code'] ) : '';
		$cn     = isset( $d['country_name'] ) ? (string) $d['country_name'] : '';
		$city   = isset( $d['city'] ) ? (string) $d['city'] : '';
		$region = isset( $d['region'] ) ? (string) $d['region'] : '';
		$line1  = $cc;
		if ( $cn !== '' ) {
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
	 * Filter Elementor document content by geo rules.
	 *
	 * @param string $content Elementor-rendered content.
	 * @return string
	 */
	public static function filter_document_content( $content ) {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return $content;
		}

		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $content;
		}

		if ( ! is_singular() ) {
			return $content;
		}

		$post_id = get_queried_object_id();
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request( $post_id ) ) {
			return $content;
		}

		if ( ! $post_id ) {
			return $content;
		}

		$settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			return $content;
		}

		$eval_settings = array(
			'egp_enable_geo_targeting'         => isset( $settings['egp_enable_geo_targeting'] ) ? (string) $settings['egp_enable_geo_targeting'] : '',
			'egp_geo_enabled'                 => isset( $settings['egp_geo_enabled'] ) ? (string) $settings['egp_geo_enabled'] : '',
			'rwgc_enable_visibility_rules'    => isset( $settings['rwgc_enable_visibility_rules'] ) ? (string) $settings['rwgc_enable_visibility_rules'] : '',
			'rwgc_use_portable_geo_targeting' => isset( $settings['rwgc_use_portable_geo_targeting'] ) ? (string) $settings['rwgc_use_portable_geo_targeting'] : '',
			'egp_use_portable_geo_targeting'  => isset( $settings['egp_use_portable_geo_targeting'] ) ? (string) $settings['egp_use_portable_geo_targeting'] : '',
			'rwgc_portable_geo_targeting'      => isset( $settings['rwgc_portable_geo_targeting'] ) ? wp_unslash( (string) $settings['rwgc_portable_geo_targeting'] ) : '',
			'egp_portable_geo_targeting'       => isset( $settings['egp_portable_geo_targeting'] ) ? wp_unslash( (string) $settings['egp_portable_geo_targeting'] ) : '',
			'rwgc_visibility_rule_library'     => isset( $settings['rwgc_visibility_rule_library'] ) ? (string) $settings['rwgc_visibility_rule_library'] : '',
			'rwgc_applied_visibility_rule_id'  => isset( $settings['rwgc_applied_visibility_rule_id'] ) ? (string) $settings['rwgc_applied_visibility_rule_id'] : '',
			'egp_countries'                   => isset( $settings['egp_countries'] ) ? $settings['egp_countries'] : array(),
			'rwgc_country_visibility_mode'    => isset( $settings['rwgc_country_visibility_mode'] ) ? (string) $settings['rwgc_country_visibility_mode'] : ( isset( $settings['rwgc_visibility_mode'] ) ? (string) $settings['rwgc_visibility_mode'] : 'show_if' ),
			'rwgc_visibility_rules_mode'      => isset( $settings['rwgc_visibility_rules_mode'] ) ? (string) $settings['rwgc_visibility_rules_mode'] : ( isset( $settings['rwgc_visibility_mode'] ) ? (string) $settings['rwgc_visibility_mode'] : 'show_if' ),
			'rwgc_visibility_mode'            => isset( $settings['rwgc_visibility_mode'] ) ? (string) $settings['rwgc_visibility_mode'] : 'show_if',
		);

		if ( ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) || ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $eval_settings ) ) {
			return $content;
		}

		if ( class_exists( 'RWGC_Elementor_Frontend', false ) ) {
			return RWGC_Elementor_Frontend::settings_should_render( $eval_settings ) ? $content : '';
		}

		$selected = array();
		if ( isset( $settings['egp_countries'] ) && is_array( $settings['egp_countries'] ) ) {
			$selected = array_map( 'strtoupper', array_map( 'sanitize_text_field', $settings['egp_countries'] ) );
		}
		if ( empty( $selected ) ) {
			return $content;
		}

		$mode    = isset( $settings['rwgc_visibility_mode'] ) ? (string) $settings['rwgc_visibility_mode'] : ( isset( $settings['rwgc_geo_mode'] ) ? (string) $settings['rwgc_geo_mode'] : 'show_if' );
		$country = strtoupper( rwgc_get_visitor_country() );

		if ( '' === $country ) {
			return $content;
		}

		$match = in_array( $country, $selected, true );
		if ( function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
			return rwgc_visibility_mode_allows_render( $mode, $match ) ? $content : '';
		}
		return $match ? $content : '';
	}

	/**
	 * Help text for portable JSON control (Pro-aware).
	 *
	 * @return string
	 */
	private static function portable_targeting_control_description() {
		if ( (bool) apply_filters( 'rwgc_pro_enabled', false ) ) {
			return __( 'Pick countries, GA4 audiences, campaigns, and traffic signals with the rule builder, or apply a saved rule from Targeting → Visibility rules. GeoCore Pro supplies synced Google lists after you connect in Integrations.', 'reactwoo-geocore' );
		}
		return __( 'Pick countries and built-in visitor signals with the rule builder, or apply a saved visibility rule from the library. GeoCore Pro unlocks synced GA4 audiences and Google Ads campaigns.', 'reactwoo-geocore' );
	}

	/**
	 * Elementor editor: JS to write portable JSON from quick-insert buttons.
	 *
	 * @return void
	 */
	public static function enqueue_editor_portable_assist() {
		if ( ! function_exists( 'rwgc_get_portable_targeting_editor_context' ) ) {
			return;
		}
		wp_register_script(
			'rwgc-portable-elementor',
			RWGC_URL . 'assets/js/portable-targeting-elementor.js',
			array( 'jquery', 'elementor-editor', RWGC_Targeting_Rule_Builder_Assets::SCRIPT_HANDLE ),
			RWGC_VERSION,
			true
		);
		wp_localize_script(
			'rwgc-portable-elementor',
			'rwgcPortableTargetingAssist',
			rwgc_get_portable_targeting_editor_context()
		);
		wp_enqueue_script( 'rwgc-portable-elementor' );
		if ( class_exists( 'RWGC_Elementor_Elements', false ) ) {
			RWGC_Elementor_Elements::enqueue_visibility_library_bridge();
		}
	}

	/**
	 * Country options for controls.
	 *
	 * @return array
	 */
	private static function get_country_options() {
		if ( class_exists( 'RWGC_Elementor_Options', false ) ) {
			return RWGC_Elementor_Options::countries();
		}
		if ( class_exists( 'RWGC_Elementor_Geo_Controls', false ) ) {
			return RWGC_Elementor_Geo_Controls::get_country_options();
		}
		return class_exists( 'RWGC_Countries', false ) ? RWGC_Countries::get_options() : array();
	}

	/**
	 * Master page options for variant routing selection.
	 *
	 * @return array
	 */
	private static function get_master_page_options() {
		if ( class_exists( 'RWGC_Elementor_Options', false ) ) {
			return RWGC_Elementor_Options::master_pages();
		}

		$options = array(
			'' => __( '-- Select master page --', 'reactwoo-geocore' ),
		);

		$pages = get_pages(
			array(
				'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'sort_column' => 'post_title',
			)
		);

		$masters = array();
		foreach ( $pages as $page ) {
			if ( ! ( $page instanceof \WP_Post ) ) {
				continue;
			}

			$config = RWGC_Routing::get_page_route_config( (int) $page->ID );
			if ( empty( $config['enabled'] ) || 'master' !== $config['role'] ) {
				continue;
			}

			$title = $page->post_title ? $page->post_title : ( '#' . (string) $page->ID );
			$masters[ (string) $page->ID ] = $title . ' (#' . (string) $page->ID . ')';
		}

		if ( ! empty( $masters ) ) {
			$options = $options + $masters;
		} else {
			$options[''] = __( '-- No enabled master pages found --', 'reactwoo-geocore' );
		}

		return $options;
	}
}

