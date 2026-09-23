<?php
/**
 * Elementor Experience Slot adapter (WP6).
 *
 * Containers first. Default Elementor output is always Gate B fallback.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects Experience Slot controls and syncs the Core slot registry.
 */
final class RWGC_Elementor_Experience_Slots {

	const SETTING_ENABLE  = 'rwgc_use_experience_slot';
	const SETTING_NAME    = 'rwgc_experience_slot_name';
	const SETTING_ID      = 'rwgc_experience_slot_id';
	const SETTING_MODE    = 'rwgc_experience_slot_cloud_status';
	const SETTING_BINDING = 'rwgc_experience_slot_binding';

	/**
	 * Elements currently wrapped in an output buffer for Gate B render.
	 *
	 * @var array<string, array{slot_id: string}>
	 */
	private static $buffers = array();

	/**
	 * Stack instances that already received the controls section.
	 *
	 * @var array<string, bool>
	 */
	private static $registered_stacks = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_hooks' ), 25 );
	}

	/**
	 * @return void
	 */
	public static function register_hooks() {
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}

		// Containers first; classic sections share the same layout role.
		$control_hooks = array(
			'elementor/element/container/section_layout/after_section_end',
			'elementor/element/section/section_advanced/after_section_end',
		);
		foreach ( $control_hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'register_controls' ), 20, 2 );
		}

		foreach ( array( 'container', 'section' ) as $type ) {
			add_action( "elementor/frontend/{$type}/before_render", array( __CLASS__, 'before_render' ), 5, 1 );
			add_action( "elementor/frontend/{$type}/after_render", array( __CLASS__, 'after_render' ), 999, 1 );
		}

		add_action( 'elementor/editor/after_save', array( __CLASS__, 'after_editor_save' ), 20, 2 );
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @param mixed                   $args Args.
	 * @return void
	 */
	public static function register_controls( $element, $args = null ) {
		unset( $args );
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) ) {
			return;
		}

		$controls = method_exists( $element, 'get_controls' ) ? $element->get_controls() : array();
		if ( is_array( $controls ) && isset( $controls['rwgc_experience_slot_section'] ) ) {
			return;
		}

		$guard = '';
		if ( method_exists( $element, 'get_unique_name' ) ) {
			$guard = (string) $element->get_unique_name();
		}
		if ( '' === $guard && function_exists( 'spl_object_hash' ) ) {
			$guard = spl_object_hash( $element );
		}
		if ( '' !== $guard ) {
			if ( isset( self::$registered_stacks[ $guard ] ) ) {
				return;
			}
			self::$registered_stacks[ $guard ] = true;
		}

		$element->start_controls_section(
			'rwgc_experience_slot_section',
			array(
				'label' => __( 'ReactWoo Experience Slot', 'reactwoo-geocore' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			self::SETTING_ENABLE,
			array(
				'label'        => __( 'Use as Experience Slot', 'reactwoo-geocore' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'reactwoo-geocore' ),
				'label_off'    => __( 'No', 'reactwoo-geocore' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Marks this container as a stable location for alternate experiences. Default design always remains the fallback.', 'reactwoo-geocore' ),
			)
		);

		$element->add_control(
			self::SETTING_NAME,
			array(
				'label'       => __( 'Slot name', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Homepage Hero', 'reactwoo-geocore' ),
				'condition'   => array(
					self::SETTING_ENABLE => 'yes',
				),
			)
		);

		$element->add_control(
			self::SETTING_ID,
			array(
				'label'       => __( 'Slot ID', 'reactwoo-geocore' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Generated automatically on save. Duplicating this element creates a new Slot ID.', 'reactwoo-geocore' ),
				'classes'     => 'rwgc-experience-slot-id',
				'condition'   => array(
					self::SETTING_ENABLE => 'yes',
				),
			)
		);

		$element->add_control(
			self::SETTING_MODE,
			array(
				'label'     => __( 'Cloud status', 'reactwoo-geocore' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'local',
				'options'   => array(
					'local'   => __( 'Local', 'reactwoo-geocore' ),
					'managed' => __( 'Managed', 'reactwoo-geocore' ),
				),
				'description' => __( 'Managed mode is reserved for ReactWoo Cloud. Local keeps decisions on this site.', 'reactwoo-geocore' ),
				'condition' => array(
					self::SETTING_ENABLE => 'yes',
				),
			)
		);

		$element->add_control(
			self::SETTING_BINDING,
			array(
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => '',
			)
		);

		$element->end_controls_section();
	}

	/**
	 * Sync settings against the Experience Slot registry (clone-safe).
	 *
	 * @param array<string, mixed> $settings Element settings.
	 * @param string               $element_id Elementor element ID.
	 * @param string               $page Page reference.
	 * @return array{settings: array<string, mixed>, regenerated: bool, enabled: bool, slot_id: string}
	 */
	public static function sync_settings( array $settings, $element_id, $page = '/' ) {
		$enabled = isset( $settings[ self::SETTING_ENABLE ] ) && 'yes' === (string) $settings[ self::SETTING_ENABLE ];
		if ( ! $enabled ) {
			return array(
				'settings'    => $settings,
				'regenerated' => false,
				'enabled'     => false,
				'slot_id'     => '',
			);
		}

		$name = isset( $settings[ self::SETTING_NAME ] ) ? trim( (string) $settings[ self::SETTING_NAME ] ) : '';
		if ( '' === $name ) {
			$name = 'Experience Slot';
			$settings[ self::SETTING_NAME ] = $name;
		}

		$element_id = sanitize_key( (string) $element_id );
		if ( '' === $element_id ) {
			$element_id = 'unknown';
		}
		$binding = 'elementor:' . $element_id;

		$mode = isset( $settings[ self::SETTING_MODE ] ) ? (string) $settings[ self::SETTING_MODE ] : 'local';
		if ( ! in_array( $mode, array( 'local', 'managed' ), true ) ) {
			$mode = 'local';
		}

		$slot_id = isset( $settings[ self::SETTING_ID ] ) ? trim( (string) $settings[ self::SETTING_ID ] ) : '';

		$result = reactwoo_register_experience_slot(
			array(
				'id'                 => $slot_id,
				'name'               => $name,
				'adapter'            => 'elementor',
				'page'               => (string) $page,
				'variant_types' => array( 'content', 'reactwoo_component', 'native_reference' ),
				'status'        => 'active',
				'metadata'      => array(
					'binding_key'  => $binding,
					'cloud_status' => $mode,
					'elementor_id' => $element_id,
				),
			)
		);

		$regenerated = false;
		if ( is_array( $result ) && isset( $result['slot'] ) ) {
			$slot_id     = $result['slot']->id();
			$regenerated = ! empty( $result['regenerated'] );
		} elseif ( '' === $slot_id || ! RWGC_Experience_Slot_Id::is_valid( $slot_id ) ) {
			$slot_id     = RWGC_Experience_Slot_Id::generate( $name );
			$regenerated = true;
		}

		$settings[ self::SETTING_ID ]      = $slot_id;
		$settings[ self::SETTING_BINDING ] = $binding;
		$settings[ self::SETTING_MODE ]    = $mode;
		$settings[ self::SETTING_NAME ]    = $name;

		return array(
			'settings'    => $settings,
			'regenerated' => $regenerated,
			'enabled'     => true,
			'slot_id'     => $slot_id,
		);
	}

	/**
	 * Persist regenerated Slot IDs after Elementor editor save.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $editor_data Editor elements tree.
	 * @return void
	 */
	public static function after_editor_save( $post_id, $editor_data ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! is_array( $editor_data ) ) {
			return;
		}

		$page    = self::page_reference_for_post( $post_id );
		$changed = false;
		$walked  = self::walk_and_sync_elements( $editor_data, $page, $changed );
		if ( ! $changed ) {
			return;
		}

		$encoded = wp_json_encode( $walked );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return;
		}
		update_post_meta( $post_id, '_elementor_data', wp_slash( $encoded ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $elements Elements.
	 * @param string                           $page Page ref.
	 * @param bool                             $changed Changed flag (by ref).
	 * @return array<int, array<string, mixed>>
	 */
	private static function walk_and_sync_elements( array $elements, $page, &$changed ) {
		foreach ( $elements as $i => $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$el_type = isset( $el['elType'] ) ? (string) $el['elType'] : '';
			if ( in_array( $el_type, array( 'container', 'section' ), true ) ) {
				$settings = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
				$id       = isset( $el['id'] ) ? (string) $el['id'] : '';
				$before   = isset( $settings[ self::SETTING_ID ] ) ? (string) $settings[ self::SETTING_ID ] : '';
				$bind_b   = isset( $settings[ self::SETTING_BINDING ] ) ? (string) $settings[ self::SETTING_BINDING ] : '';
				$sync     = self::sync_settings( $settings, $id, $page );
				if ( $sync['enabled'] ) {
					$el['settings'] = $sync['settings'];
					$after          = (string) $sync['settings'][ self::SETTING_ID ];
					$bind_a         = (string) $sync['settings'][ self::SETTING_BINDING ];
					if ( $before !== $after || $bind_b !== $bind_a || $sync['regenerated'] ) {
						$changed = true;
					}
				}
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::walk_and_sync_elements( $el['elements'], $page, $changed );
			}
			$elements[ $i ] = $el;
		}
		return $elements;
	}

	/**
	 * Whether Elementor geo targeting allows this slot container to print.
	 *
	 * Elementor fires `before_render` / `after_render` actions even when
	 * `should_render` is false. Buffering then would replace the (empty)
	 * default with Cloud variant HTML for a geo-hidden container.
	 *
	 * @param array<string, mixed> $settings Element settings.
	 * @return bool
	 */
	public static function geo_allows_slot_render( array $settings ) {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return true;
		}
		if ( ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			return true;
		}
		if ( ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
			return true;
		}
		if ( class_exists( 'RWGC_Elementor_Frontend', false ) ) {
			return RWGC_Elementor_Frontend::settings_should_render( $settings );
		}
		$result = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );
		return ! empty( $result['should_render'] );
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @return void
	 */
	public static function before_render( $element ) {
		if ( ! is_object( $element ) || self::is_edit_mode() ) {
			return;
		}
		if ( ! method_exists( $element, 'get_settings_for_display' ) ) {
			return;
		}

		$settings = $element->get_settings_for_display();
		if ( ! is_array( $settings ) || empty( $settings[ self::SETTING_ENABLE ] ) || 'yes' !== (string) $settings[ self::SETTING_ENABLE ] ) {
			return;
		}
		if ( ! self::geo_allows_slot_render( $settings ) ) {
			return;
		}

		$element_id = method_exists( $element, 'get_id' ) ? (string) $element->get_id() : '';
		$page       = self::page_reference_for_post( get_the_ID() );
		$sync       = self::sync_settings( $settings, $element_id, $page );
		$slot_id    = $sync['slot_id'];
		if ( '' === $slot_id ) {
			return;
		}

		if ( method_exists( $element, 'set_settings' ) ) {
			$element->set_settings( $sync['settings'] );
		}

		if ( method_exists( $element, 'add_render_attribute' ) ) {
			$element->add_render_attribute(
				'_wrapper',
				array(
					'data-reactwoo-slot'    => '1',
					'data-reactwoo-slot-id' => $slot_id,
					'class'                => 'reactwoo-experience-slot',
				)
			);
			$mode = isset( $sync['settings'][ self::SETTING_MODE ] ) ? (string) $sync['settings'][ self::SETTING_MODE ] : 'local';
			$element->add_render_attribute( '_wrapper', 'data-reactwoo-slot-mode', $mode );
		}

		$key = function_exists( 'spl_object_hash' ) ? spl_object_hash( $element ) : $element_id;
		self::$buffers[ $key ] = array( 'slot_id' => $slot_id );
		ob_start();
	}

	/**
	 * @param \Elementor\Element_Base $element Element.
	 * @return void
	 */
	public static function after_render( $element ) {
		if ( ! is_object( $element ) || self::is_edit_mode() ) {
			return;
		}
		$key = function_exists( 'spl_object_hash' ) ? spl_object_hash( $element ) : ( method_exists( $element, 'get_id' ) ? (string) $element->get_id() : '' );
		if ( '' === $key || ! isset( self::$buffers[ $key ] ) ) {
			return;
		}

		$slot_id = self::$buffers[ $key ]['slot_id'];
		unset( self::$buffers[ $key ] );

		$html = ob_get_clean();
		if ( ! is_string( $html ) ) {
			$html = '';
		}

		/**
		 * Optional Decision Runtime result for the current request (null = default content).
		 *
		 * @param RWGC_Decision_Result|null $decision Decision.
		 */
		$decision = apply_filters( 'reactwoo_current_decision_result', null );
		if ( ! ( $decision instanceof RWGC_Decision_Result ) ) {
			$decision = null;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor HTML + Gate B renderer.
		echo reactwoo_render_experience_slot( $slot_id, $html, $decision );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function page_reference_for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id > 0 && function_exists( 'get_permalink' ) ) {
			$link = get_permalink( $post_id );
			if ( is_string( $link ) && '' !== $link ) {
				$path = wp_parse_url( $link, PHP_URL_PATH );
				return is_string( $path ) && '' !== $path ? $path : '/';
			}
		}
		return '/';
	}

	/**
	 * @return bool
	 */
	private static function is_edit_mode() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance->editor ) ) {
			return false;
		}
		$editor = \Elementor\Plugin::$instance->editor;
		return is_object( $editor ) && method_exists( $editor, 'is_edit_mode' ) && $editor->is_edit_mode();
	}
}
