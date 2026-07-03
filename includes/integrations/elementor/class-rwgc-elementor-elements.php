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
	 * Guards duplicate control injection when Elementor fires the same stack hook more than once.
	 *
	 * @var array<string, bool>
	 */
	private static $registered_stack_instances = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_hooks' ), 20 );
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
				'library'         => self::get_visibility_library_rows(),
				'documentContext' => self::get_editor_document_context(),
				'labels'          => array(
					'compatibleGroup'    => __( 'Compatible rules', 'reactwoo-geocore' ),
					'attentionGroup'     => __( 'Needs attention', 'reactwoo-geocore' ),
					'unavailableGroup'   => __( 'Not available for this context', 'reactwoo-geocore' ),
					'incompatibleNotice' => __( 'This rule may not match in this document context.', 'reactwoo-geocore' ),
					'choosePlaceholder'  => __( '— Choose saved visibility rule —', 'reactwoo-geocore' ),
				),
			)
		);
		wp_enqueue_script( 'rwgc-elementor-library-bridge' );
	}

	/**
	 * @return array<int, array{id:int,title:string,json:string}>
	 */
	public static function get_visibility_library_rows() {
		$rows = array();
		if ( class_exists( 'RWGC_Rule_Registry', false ) ) {
			$rows = RWGC_Rule_Registry::get_library_picker_rows();
		} elseif ( class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			$rows = RWGC_Visibility_Rule_Repository::get_library_picker_rows();
		}

		$context = self::get_editor_document_context();
		if ( ! class_exists( 'RWGC_Rule_Context_Compatibility', false ) ) {
			return $rows;
		}

		$enriched = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$json = isset( $row['json'] ) ? (string) $row['json'] : '';
			$set  = '' !== trim( $json ) ? json_decode( $json, true ) : null;
			$compat = RWGC_Rule_Context_Compatibility::evaluate( is_array( $set ) ? $set : null, $context );
			$reason = ! empty( $compat['reasons'] ) && is_array( $compat['reasons'] )
				? implode( ' ', $compat['reasons'] )
				: '';
			$row['scope_summary'] = (string) ( $compat['scope_summary'] ?? '' );
			$row['compatibility'] = array(
				'status'  => (string) ( $compat['status'] ?? 'compatible' ),
				'reason'  => $reason,
				'reasons' => isset( $compat['reasons'] ) && is_array( $compat['reasons'] ) ? $compat['reasons'] : array(),
			);
			$enriched[] = $row;
		}
		return $enriched;
	}

	/**
	 * Current Elementor document context for library compatibility.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_editor_document_context() {
		$post_id = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( $post_id <= 0 && class_exists( '\Elementor\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'get_post_id' ) ) {
				$post_id = absint( $plugin->editor->get_post_id() );
			}
		}
		if ( class_exists( 'RWGC_Rule_Context_Compatibility', false ) ) {
			return RWGC_Rule_Context_Compatibility::document_context_from_post( $post_id );
		}
		return array(
			'post_id'       => $post_id,
			'post_type'     => '',
			'page_type'     => '',
			'request_uri'   => '',
			'document_type' => 'page',
		);
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
			$key = (string) $row['id'];
			$options[ $key ] = isset( $row['title'] ) ? (string) $row['title'] : $key;
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

		// Popup document targeting lives in RWGC_Elementor document_settings (Geo Visibility). Do not register here — avoids duplicate controls on the popup stack.
		$hooks = array(
			'elementor/element/common/_section_style/after_section_end',
			'elementor/element/section/section_advanced/after_section_end',
			'elementor/element/column/section_advanced/after_section_end',
			'elementor/element/container/section_layout/after_section_end',
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
		if ( is_array( $controls ) && ( isset( $controls['egp_geo_tools'] ) || isset( $controls['rwgc_geo_section'] ) ) ) {
			return;
		}
		$stack_guard_key = '';
		if ( method_exists( $element, 'get_unique_name' ) ) {
			$stack_guard_key = (string) $element->get_unique_name();
		}
		if ( '' === $stack_guard_key && function_exists( 'spl_object_hash' ) ) {
			$stack_guard_key = spl_object_hash( $element );
		}
		if ( '' !== $stack_guard_key ) {
			if ( isset( self::$registered_stack_instances[ $stack_guard_key ] ) ) {
				return;
			}
			self::$registered_stack_instances[ $stack_guard_key ] = true;
		}

		if ( class_exists( 'RWGC_Elementor_Geo_Controls', false ) ) {
			RWGC_Elementor_Geo_Controls::register_section(
				$element,
				array(
					'section_id'   => 'egp_geo_tools',
					'countries_ui' => 'select2',
				)
			);
		}
	}

	/**
	 * @return string
	 */
	public static function get_countries_select_html() {
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
	public static function get_country_options() {
		if ( class_exists( 'RWGC_Countries', false ) ) {
			$list = RWGC_Countries::get_options();
			if ( is_array( $list ) && ! empty( $list ) ) {
				return $list;
			}
		}
		return array();
	}
}
