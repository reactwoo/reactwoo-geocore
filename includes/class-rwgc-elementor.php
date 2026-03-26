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
		add_action( 'elementor/init', array( __CLASS__, 'register_hooks' ) );
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

		$element->add_control(
			'egp_enable_geo_targeting',
			array(
				'label'        => __( 'Enable Geo Visibility', 'reactwoo-geocore' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'reactwoo-geocore' ),
				'label_off'    => __( 'Off', 'reactwoo-geocore' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$element->add_control(
			'rwgc_geo_mode',
			array(
				'label'     => __( 'Mode', 'reactwoo-geocore' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'show',
				'options'   => array(
					'show' => __( 'Show for selected countries', 'reactwoo-geocore' ),
					'hide' => __( 'Hide for selected countries', 'reactwoo-geocore' ),
				),
				'condition' => array(
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
				'condition'   => array(
					'egp_enable_geo_targeting' => 'yes',
				),
			)
		);

		$element->add_control(
			'rwgc_geo_upgrade_note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => '<div style="margin-top:8px;color:#6b7280;">'
					. esc_html__( 'Need advanced rules, multiple conditions, or analytics? Upgrade with GeoElementor.', 'reactwoo-geocore' )
					. '</div>',
				'content_classes' => 'rwgc-geo-upgrade-note',
				'condition'       => array(
					'egp_enable_geo_targeting' => 'yes',
				),
			)
		);

		$element->end_controls_section();
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
		if ( ! $post_id ) {
			return $content;
		}

		$settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			return $content;
		}

		if ( empty( $settings['egp_enable_geo_targeting'] ) || 'yes' !== (string) $settings['egp_enable_geo_targeting'] ) {
			return $content;
		}

		$selected = array();
		if ( isset( $settings['egp_countries'] ) && is_array( $settings['egp_countries'] ) ) {
			$selected = array_map( 'strtoupper', array_map( 'sanitize_text_field', $settings['egp_countries'] ) );
		}
		if ( empty( $selected ) ) {
			return $content;
		}

		$mode    = isset( $settings['rwgc_geo_mode'] ) ? sanitize_key( (string) $settings['rwgc_geo_mode'] ) : 'show';
		$country = strtoupper( rwgc_get_visitor_country() );

		if ( '' === $country ) {
			return $content;
		}

		$match       = in_array( $country, $selected, true );
		$should_hide = ( 'show' === $mode && ! $match ) || ( 'hide' === $mode && $match );

		return $should_hide ? '' : $content;
	}

	/**
	 * Country options for controls.
	 *
	 * @return array
	 */
	private static function get_country_options() {
		return array(
			'US' => 'United States',
			'CA' => 'Canada',
			'GB' => 'United Kingdom',
			'IE' => 'Ireland',
			'AU' => 'Australia',
			'NZ' => 'New Zealand',
			'DE' => 'Germany',
			'FR' => 'France',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'NL' => 'Netherlands',
			'BE' => 'Belgium',
			'SE' => 'Sweden',
			'NO' => 'Norway',
			'DK' => 'Denmark',
			'CH' => 'Switzerland',
			'AE' => 'United Arab Emirates',
			'SA' => 'Saudi Arabia',
			'IN' => 'India',
			'SG' => 'Singapore',
			'JP' => 'Japan',
			'BR' => 'Brazil',
			'MX' => 'Mexico',
			'ZA' => 'South Africa',
		);
	}
}

