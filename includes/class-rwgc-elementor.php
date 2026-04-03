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

		$visitor_preview = self::build_visitor_preview_markup();
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
					. esc_html__( 'Free limit: one variant per master page. Use GeoElementor for multiple variants and advanced rules.', 'reactwoo-geocore' )
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
	private static function build_visitor_preview_markup() {
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
		return RWGC_Countries::get_options();
	}

	/**
	 * Master page options for variant routing selection.
	 *
	 * @return array
	 */
	private static function get_master_page_options() {
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

