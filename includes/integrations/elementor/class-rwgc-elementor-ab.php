<?php
/**
 * Elementor A/B control for classic elements and Atomic (V4).
 *
 * Two sibling elements, one Variant A and one Variant B on the same test, are
 * the pair. The editor and preview show both. A running test on the front
 * prints only the assigned element. Fragment mode prints A and loads the
 * assigned arm from the render route.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classic controls, Atomic props, save-time pairing, and front-end selection.
 */
final class RWGC_Elementor_AB {

	const SETTING_ROLE     = 'rwgc_ab_role';
	const SETTING_TEST     = 'rwgc_ab_test';
	const SETTING_NAME     = 'rwgc_ab_test_name';
	const SETTING_GOAL     = 'rwgc_ab_goal_type';
	const SETTING_SELECTOR = 'rwgc_ab_goal_selector';
	const SETTING_URL      = 'rwgc_ab_goal_url';
	const SETTING_NOTICE   = 'rwgc_ab_notice';
	const SETTING_PAIR     = 'rwgc_ab_pair_ok';

	const SECTION_ID = 'rwgc_ab_test';

	/**
	 * @var string[]
	 */
	private static $setting_keys = array(
		self::SETTING_ROLE,
		self::SETTING_TEST,
		self::SETTING_NAME,
		self::SETTING_GOAL,
		self::SETTING_SELECTOR,
		self::SETTING_URL,
		self::SETTING_NOTICE,
		self::SETTING_PAIR,
	);

	/**
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * @var array<string, bool>
	 */
	private static $registered_stacks = array();

	/**
	 * @var array<string, bool>
	 */
	private static $should_render_types = array();

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static $buffers = array();

	/**
	 * Elements already handled by before_render this print.
	 *
	 * Generic and per-type hooks both fire. The second call must not open another buffer.
	 *
	 * @var array<string, bool>
	 */
	private static $handled = array();

	/**
	 * @var int
	 */
	private static $render_depth = 0;

	/**
	 * @var int
	 */
	private static $supplying = 0;

	/**
	 * @var array<string, string>|null
	 */
	private static $picker_options = null;

	/**
	 * @return void
	 */
	public static function init() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rwgc_ab_variant_html', array( __CLASS__, 'filter_variant_html' ), 10, 3 );
			// Elementor 3.24+ stores classic element HTML in _elementor_element_cache.
			// A/B output must stay a shortcode so each request runs the decision again.
			add_filter( 'elementor/element/is_dynamic_content', array( __CLASS__, 'filter_is_dynamic_content' ), 10, 3 );
		}
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register_hooks' ), 25 );
		add_action( 'elementor/init', array( __CLASS__, 'register_hooks' ), 30 );
	}

	/**
	 * Classic controls and front-end hooks. Safe when Elementor is inactive.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( self::$hooks_registered || ! self::elementor_is_loaded() || ! function_exists( 'add_action' ) ) {
			return;
		}
		self::$hooks_registered = true;

		$control_hooks = array(
			'elementor/element/container/section_layout/after_section_end',
			'elementor/element/section/section_advanced/after_section_end',
			'elementor/element/column/section_advanced/after_section_end',
			'elementor/element/common/_section_style/after_section_end',
			'elementor/element/common-optimized/_section_style/after_section_end',
		);
		foreach ( $control_hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'register_controls' ), 20, 2 );
		}

		// One buffer per element. Typed hooks also fire, so only the generic pair is used.
		add_action( 'elementor/frontend/before_render', array( __CLASS__, 'before_render' ), 5, 1 );
		add_action( 'elementor/frontend/after_render', array( __CLASS__, 'after_render' ), 999, 1 );
		add_action( 'elementor/editor/after_save', array( __CLASS__, 'after_editor_save' ), 30, 2 );
		add_action( 'elementor/frontend/before_get_builder_content', array( __CLASS__, 'prepare_element_cache' ), 0, 2 );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'enqueue_editor_styles' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue_editor_styles' ) );

		foreach ( array( 'section', 'column', 'container', 'widget', 'e-flexbox', 'e-div-block', 'e-grid' ) as $type ) {
			self::hook_should_render( $type );
		}
		add_action( 'elementor/elements/elements_registered', array( __CLASS__, 'register_atomic_should_render' ), 20, 1 );
		if ( function_exists( 'did_action' ) && did_action( 'elementor/elements/elements_registered' ) ) {
			self::register_atomic_should_render();
		}
	}

	/**
	 * @param mixed $manager Elements manager, when Elementor passes it.
	 * @return void
	 */
	public static function register_atomic_should_render( $manager = null ) {
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
		$types       = $manager->get_element_types();
		if ( ! is_array( $types ) ) {
			return;
		}
		foreach ( $types as $type => $instance ) {
			$type = (string) $type;
			if ( '' === $type || in_array( $type, $classic, true ) || ! is_object( $instance ) ) {
				continue;
			}
			$is_atomic = ( class_exists( $atomic_base, false ) && is_a( $instance, $atomic_base ) )
				|| method_exists( $instance, 'get_atomic_settings' );
			if ( $is_atomic ) {
				self::hook_should_render( $type );
			}
		}
	}

	/**
	 * @param string $type Element type.
	 * @return void
	 */
	private static function hook_should_render( $type ) {
		$type = (string) $type;
		if ( '' === $type || isset( self::$should_render_types[ $type ] ) || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$should_render_types[ $type ] = true;
		add_filter( "elementor/frontend/{$type}/should_render", array( __CLASS__, 'filter_should_render' ), 12, 2 );
		if ( function_exists( 'add_action' ) ) {
			// Atomic Twig types often skip the generic before_render hook. Classic types also fire it; before_render ignores the second call.
			add_action( "elementor/frontend/{$type}/before_render", array( __CLASS__, 'before_render' ), 5, 1 );
			add_action( "elementor/frontend/{$type}/after_render", array( __CLASS__, 'after_render' ), 999, 1 );
		}
	}

	/**
	 * Classic Advanced-tab section. Atomic elements use the V4 sibling section.
	 *
	 * @param mixed $element Element.
	 * @param mixed $args    Hook args.
	 * @return void
	 */
	public static function register_controls( $element, $args = null ) {
		unset( $args );
		if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) || ! method_exists( $element, 'add_control' ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Controls_Manager', false ) || ! defined( 'Elementor\Controls_Manager::TAB_ADVANCED' ) ) {
			return;
		}
		if ( self::is_atomic_element( $element ) ) {
			return;
		}

		$controls = method_exists( $element, 'get_controls' ) ? $element->get_controls() : array();
		if ( is_array( $controls ) && isset( $controls[ self::SECTION_ID ] ) ) {
			return;
		}
		$guard = method_exists( $element, 'get_unique_name' ) ? (string) $element->get_unique_name() : '';
		if ( '' === $guard && function_exists( 'spl_object_hash' ) ) {
			$guard = spl_object_hash( $element );
		}
		if ( '' !== $guard ) {
			if ( isset( self::$registered_stacks[ $guard ] ) ) {
				return;
			}
			self::$registered_stacks[ $guard ] = true;
		}

		$manager = '\Elementor\Controls_Manager';
		$when_on = array(
			self::SETTING_ROLE => array( 'a', 'b' ),
		);

		$element->start_controls_section(
			self::SECTION_ID,
			array(
				'label' => __( 'A/B test', 'reactwoo-geocore' ),
				'tab'   => $manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			self::SETTING_ROLE,
			array(
				'label'   => __( 'A/B test', 'reactwoo-geocore' ),
				'type'    => $manager::SELECT,
				'default' => '',
				'options' => array(
					''  => __( 'Off', 'reactwoo-geocore' ),
					'a' => __( 'Variant A', 'reactwoo-geocore' ),
					'b' => __( 'Variant B', 'reactwoo-geocore' ),
				),
				'description' => __( 'Set one sibling to Variant A and another to Variant B, and choose the same test. Visitors see one element. An unpaired element renders normally.', 'reactwoo-geocore' ),
			)
		);

		$element->add_control(
			self::SETTING_TEST,
			array(
				'label'     => __( 'Test', 'reactwoo-geocore' ),
				'type'      => $manager::SELECT,
				'default'   => 'new',
				'options'   => self::picker_options(),
				'condition' => $when_on,
			)
		);

		$element->add_control(
			self::SETTING_NAME,
			array(
				'label'       => __( 'New test name', 'reactwoo-geocore' ),
				'type'        => $manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Homepage hero', 'reactwoo-geocore' ),
				'condition'   => array(
					self::SETTING_ROLE => array( 'a', 'b' ),
					self::SETTING_TEST => 'new',
				),
			)
		);

		$element->add_control(
			self::SETTING_GOAL,
			array(
				'label'     => __( 'Goal', 'reactwoo-geocore' ),
				'type'      => $manager::SELECT,
				'default'   => 'click',
				'options'   => array(
					'click' => __( 'Click', 'reactwoo-geocore' ),
					'url'   => __( 'URL visit', 'reactwoo-geocore' ),
				),
				'condition' => $when_on,
			)
		);

		$element->add_control(
			self::SETTING_SELECTOR,
			array(
				'label'       => __( 'Click selector', 'reactwoo-geocore' ),
				'type'        => $manager::TEXT,
				'default'     => '',
				'placeholder' => 'a.buy',
				'condition'   => array(
					self::SETTING_ROLE => array( 'a', 'b' ),
					self::SETTING_GOAL => 'click',
				),
			)
		);

		$element->add_control(
			self::SETTING_URL,
			array(
				'label'     => __( 'Goal URL', 'reactwoo-geocore' ),
				'type'      => $manager::TEXT,
				'default'   => '',
				'condition' => array(
					self::SETTING_ROLE => array( 'a', 'b' ),
					self::SETTING_GOAL => 'url',
				),
			)
		);

		$element->add_control(
			self::SETTING_NOTICE,
			array(
				'label'     => __( 'Warning', 'reactwoo-geocore' ),
				'type'      => $manager::TEXTAREA,
				'default'   => '',
				'condition' => array(
					self::SETTING_NOTICE . '!' => '',
				),
			)
		);

		$element->add_control(
			self::SETTING_PAIR,
			array(
				'type'    => $manager::HIDDEN,
				'default' => '',
			)
		);

		$element->end_controls_section();
	}

	/**
	 * Add A/B props when the Atomic string prop type exists.
	 *
	 * A list schema (some Elementor builds) is left unchanged.
	 *
	 * @param mixed $schema Props schema.
	 * @return array<mixed>
	 */
	public static function filter_atomic_props_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return array();
		}
		if ( array() !== $schema && function_exists( 'array_is_list' ) && array_is_list( $schema ) ) {
			return $schema;
		}
		$string = '\Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type';
		if ( ! class_exists( $string, false ) || ! is_callable( array( $string, 'make' ) ) ) {
			return $schema;
		}

		$defaults = array(
			self::SETTING_ROLE     => 'off',
			self::SETTING_TEST     => 'new',
			self::SETTING_NAME     => '',
			self::SETTING_GOAL     => 'click',
			self::SETTING_SELECTOR => '',
			self::SETTING_URL      => '',
			self::SETTING_NOTICE   => '',
			self::SETTING_PAIR     => '',
		);
		try {
			foreach ( $defaults as $key => $default ) {
				if ( isset( $schema[ $key ] ) ) {
					continue;
				}
				$prop = $string::make();
				if ( ! is_object( $prop ) ) {
					continue;
				}
				if ( method_exists( $prop, 'default' ) ) {
					$with = $prop->default( $default );
					$prop = is_object( $with ) ? $with : $prop;
				}
				$schema[ $key ] = $prop;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return $schema;
		}
		return $schema;
	}

	/**
	 * Append an A/B section beside Geo Visibility. Missing V4 control classes skip it.
	 *
	 * @param mixed $controls Atomic sections.
	 * @return mixed
	 */
	public static function filter_atomic_controls( $controls ) {
		if ( ! is_array( $controls ) ) {
			return $controls;
		}
		if ( self::atomic_controls_have_section( $controls, self::SECTION_ID ) ) {
			return $controls;
		}
		try {
			$section = self::atomic_section();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return $controls;
		}
		if ( ! is_object( $section ) ) {
			return $controls;
		}
		$controls[] = $section;
		return $controls;
	}

	/**
	 * Hide the element that is not the rendered arm. Atomic Twig honours this filter.
	 *
	 * @param mixed $should_render Whether Elementor would print the element.
	 * @param mixed $element       Element.
	 * @return bool
	 */
	public static function filter_should_render( $should_render, $element = null ) {
		if ( ! $should_render || self::$supplying > 0 || ! is_object( $element ) ) {
			return (bool) $should_render;
		}
		$decision = self::decision_for_element( $element );
		if ( 'suppress' === $decision['action'] ) {
			return false;
		}
		return (bool) $should_render;
	}

	/**
	 * @param mixed $element Element.
	 * @return void
	 */
	public static function before_render( $element ) {
		if ( self::$supplying > 0 || ! is_object( $element ) ) {
			return;
		}
		$key = self::buffer_key( $element );
		if ( '' === $key || isset( self::$handled[ $key ] ) || isset( self::$buffers[ $key ] ) ) {
			return;
		}
		self::$handled[ $key ] = true;
		$decision = self::decision_for_element( $element );
		if ( 'emit' === $decision['action'] || 'suppress' === $decision['action'] ) {
			self::send_request_nocache();
		}
		if ( 'emit' === $decision['action'] ) {
			self::tag_live_decision( $element, $decision );
		}
		if ( 'label' === $decision['action'] ) {
			self::label_element( $element, $decision );
			if ( self::is_atomic_element( $element ) ) {
				echo self::editor_badge_html( $decision ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- editor_badge_html() escapes the label.
			}
			return;
		}
		if ( 'passthrough' === $decision['action'] ) {
			return;
		}
		self::$buffers[ $key ] = $decision;
		++self::$render_depth;
		ob_start();
	}

	/**
	 * @param mixed $element Element.
	 * @return void
	 */
	public static function after_render( $element ) {
		if ( ! is_object( $element ) ) {
			return;
		}
		$key      = self::buffer_key( $element );
		$decision = ( '' !== $key && isset( self::$buffers[ $key ] ) ) ? self::$buffers[ $key ] : null;
		if ( '' !== $key ) {
			unset( self::$handled[ $key ], self::$buffers[ $key ] );
		}
		if ( null === $decision ) {
			return;
		}
		if ( self::$render_depth > 0 ) {
			--self::$render_depth;
		}
		$html = ob_get_clean();
		if ( ! is_string( $html ) ) {
			$html = '';
		}
		if ( 'suppress' === $decision['action'] ) {
			return;
		}
		echo self::emit_html( $decision, $html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper attributes are escaped. The arm is Elementor's own HTML.
	}

	/**
	 * Keep an A/B element, and any parent that contains one, out of Elementor's element cache.
	 *
	 * @param mixed $is_dynamic Already dynamic.
	 * @param mixed $raw_data   Element raw data, including descendants.
	 * @param mixed $element    Element instance. Unused.
	 * @return bool
	 */
	public static function filter_is_dynamic_content( $is_dynamic, $raw_data = null, $element = null ) {
		unset( $element );
		if ( $is_dynamic ) {
			return true;
		}
		return self::tree_has_ab_role( $raw_data );
	}

	/**
	 * Drop a document cache that froze an A/B arm as HTML.
	 *
	 * A cache that already stores `[elementor-element]` shortcodes is kept.
	 * Those shortcodes render again on each request.
	 *
	 * @param mixed $document  Elementor document or post ID.
	 * @param mixed $is_excerpt Unused.
	 * @return void
	 */
	public static function prepare_element_cache( $document = null, $is_excerpt = null ) {
		unset( $is_excerpt );
		$post_id = self::document_post_id( $document );
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) || ! function_exists( 'delete_post_meta' ) ) {
			return;
		}
		$cached = get_post_meta( $post_id, '_elementor_element_cache', true );
		if ( self::element_cache_is_stale( self::document_elements( $post_id ), self::element_cache_content( $cached ) ) ) {
			delete_post_meta( $post_id, '_elementor_element_cache' );
		}
	}

	/**
	 * True when this document has an A/B element and the stored cache is rendered HTML.
	 *
	 * @param mixed  $elements       Element tree.
	 * @param string $cached_content Cached HTML or shortcode string.
	 * @return bool
	 */
	public static function element_cache_is_stale( $elements, $cached_content ) {
		if ( ! self::tree_has_ab_role( $elements ) ) {
			return false;
		}
		if ( ! is_string( $cached_content ) || '' === $cached_content ) {
			return false;
		}
		return false === strpos( $cached_content, '[elementor-element' );
	}

	/**
	 * HTML for one arm on the fragment render route.
	 *
	 * Another provider may already have filled $html. An Elementor test is read
	 * from the source document. This does not call rwgc_ab_render_test().
	 *
	 * @param mixed      $html    HTML from earlier filters.
	 * @param int|string $test_id Test post ID.
	 * @param string     $arm     Requested arm.
	 * @return string
	 */
	public static function filter_variant_html( $html, $test_id, $arm ) {
		if ( is_string( $html ) && '' !== trim( $html ) ) {
			return $html;
		}
		$rendered = self::variant_html_for_test( $test_id, $arm );
		if ( '' === $rendered ) {
			return is_string( $html ) ? $html : '';
		}
		return $rendered;
	}

	/**
	 * Write the sibling pair into the test and back into the document.
	 *
	 * Reads the latest `_elementor_data` so an earlier save handler is kept.
	 *
	 * @param int   $post_id     Document ID.
	 * @param array $editor_data Editor tree, used when meta is still empty.
	 * @return void
	 */
	public static function after_editor_save( $post_id, $editor_data ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		$elements = self::document_elements( $post_id );
		if ( array() === $elements && is_array( $editor_data ) ) {
			$elements = $editor_data;
		}
		if ( ! is_array( $elements ) || array() === $elements ) {
			return;
		}
		$synced = self::sync_tree( $elements, $post_id );
		if ( self::tree_has_ab_role( $synced['elements'] ) && function_exists( 'delete_post_meta' ) ) {
			delete_post_meta( $post_id, '_elementor_element_cache' );
		}
		if ( empty( $synced['changed'] ) || ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		$encoded = wp_json_encode( $synced['elements'] );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return;
		}
		update_post_meta( $post_id, '_elementor_data', function_exists( 'wp_slash' ) ? wp_slash( $encoded ) : $encoded );
	}

	/**
	 * Pair sibling elements and return the updated tree.
	 *
	 * @param array<int, mixed> $elements Document elements.
	 * @param int               $post_id  Document ID.
	 * @return array{elements: array<int, mixed>, changed: bool}
	 */
	public static function sync_tree( array $elements, $post_id ) {
		$state    = array(
			'seen'    => array(),
			'changed' => false,
			'inside'  => false,
		);
		$elements = self::walk_elements( $elements, absint( $post_id ), $state );
		return array(
			'elements' => $elements,
			'changed'  => (bool) $state['changed'],
		);
	}

	/**
	 * Which element is printed for one set of settings.
	 *
	 * Actions: passthrough (render normally), label (editor or preview), emit
	 * (this arm is the page output), suppress (absent from the HTML).
	 *
	 * @param array<string, mixed> $settings Element settings.
	 * @param array<string, mixed> $context  surface, running, ended, kept, assigned, mode, nested.
	 * @return array{action: string, arm: string, test_id: int, mode: string, warning: string}
	 */
	public static function render_decision( array $settings, array $context ) {
		$role    = self::normalize_role( self::scalar_setting( $settings, self::SETTING_ROLE ) );
		$test_id = absint( self::scalar_setting( $settings, self::SETTING_TEST ) );
		$paired  = ( 'yes' === self::scalar_setting( $settings, self::SETTING_PAIR ) );
		$notice  = self::scalar_setting( $settings, self::SETTING_NOTICE );
		$surface = isset( $context['surface'] ) ? (string) $context['surface'] : 'frontend';
		if ( ! in_array( $surface, array( 'frontend', 'editor', 'preview' ), true ) ) {
			$surface = 'frontend';
		}
		$nested   = ! empty( $context['nested'] );
		$builder  = ( 'editor' === $surface || 'preview' === $surface );
		$assigned = ( isset( $context['assigned'] ) && 'b' === $context['assigned'] ) ? 'b' : 'a';
		$result   = array(
			'action'   => 'passthrough',
			'arm'      => $role,
			'test_id'  => $test_id,
			'mode'     => 'raw',
			'warning'  => $notice,
			'assigned' => $assigned,
		);
		if ( '' === $role ) {
			return $result;
		}
		if ( $nested || ! $paired || $test_id <= 0 ) {
			if ( $builder ) {
				$result['action'] = 'label';
				if ( '' === $result['warning'] ) {
					$result['warning'] = $nested ? self::notice_nested() : self::notice_unpaired();
				}
			}
			return $result;
		}
		if ( $builder ) {
			$result['action']  = 'label';
			$result['warning'] = '';
			return $result;
		}

		$running  = ! empty( $context['running'] );
		$ended    = ! empty( $context['ended'] );
		$kept     = ( isset( $context['kept'] ) && 'b' === $context['kept'] ) ? 'b' : 'a';
		$assigned = ( isset( $context['assigned'] ) && 'b' === $context['assigned'] ) ? 'b' : 'a';
		$mode     = ( isset( $context['mode'] ) && 'fragment' === $context['mode'] ) ? 'fragment' : 'server';
		if ( $ended && ! $running ) {
			$result['action'] = ( $role === $kept ) ? 'emit' : 'suppress';
			$result['mode']   = 'raw';
			return $result;
		}
		if ( ! $running ) {
			return $result;
		}
		if ( 'fragment' === $mode ) {
			$result['action'] = ( 'a' === $role ) ? 'emit' : 'suppress';
			$result['mode']   = 'fragment';
			return $result;
		}
		$result['action'] = ( $role === $assigned ) ? 'emit' : 'suppress';
		$result['mode']   = 'server';
		return $result;
	}

	/**
	 * @return string
	 */
	public static function notice_unpaired() {
		return __( 'Add a sibling element set to the other variant and the same test. This element renders normally until then.', 'reactwoo-geocore' );
	}

	/**
	 * @return string
	 */
	public static function notice_extra() {
		return __( 'Only one Variant A and one Variant B can share this test. This extra element renders normally.', 'reactwoo-geocore' );
	}

	/**
	 * @return string
	 */
	public static function notice_nested() {
		return __( 'This A/B setting is inside another A/B element. Nested pairs are ignored and this element renders normally.', 'reactwoo-geocore' );
	}

	/**
	 * @return string
	 */
	public static function notice_unsaved() {
		return __( 'The A/B test could not be saved. This element renders normally.', 'reactwoo-geocore' );
	}

	/**
	 * Badges for the editor and preview. Not loaded on the public page.
	 *
	 * @return void
	 */
	public static function enqueue_editor_styles() {
		if ( ! self::is_builder_surface() || ! function_exists( 'wp_register_style' ) || ! function_exists( 'wp_add_inline_style' ) ) {
			return;
		}
		if ( ! function_exists( 'wp_style_is' ) || ! wp_style_is( 'rwgc-elementor-ab-editor', 'enqueued' ) ) {
			wp_register_style( 'rwgc-elementor-ab-editor', false, array(), defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '1.9.27' );
			if ( function_exists( 'wp_enqueue_style' ) ) {
				wp_enqueue_style( 'rwgc-elementor-ab-editor' );
			}
			wp_add_inline_style( 'rwgc-elementor-ab-editor', self::editor_css() );
		}
	}

	/**
	 * Drop buffers between tests.
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$buffers            = array();
		self::$handled            = array();
		self::$render_depth       = 0;
		self::$supplying          = 0;
		self::$hooks_registered   = false;
		self::$registered_stacks  = array();
		self::$should_render_types = array();
		self::$picker_options     = null;
	}

	/**
	 * @param array<int, mixed>    $elements Elements.
	 * @param int                  $post_id  Document ID.
	 * @param array<string, mixed> $state    Walk state.
	 * @return array<int, mixed>
	 */
	private static function walk_elements( array $elements, $post_id, array &$state ) {
		$elements = self::pair_level( $elements, $post_id, $state );
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			$role     = self::normalize_role( self::scalar_setting( $settings, self::SETTING_ROLE ) );
			$paired   = ( 'yes' === self::scalar_setting( $settings, self::SETTING_PAIR ) && '' !== $role );
			$was      = ! empty( $state['inside'] );
			if ( $paired ) {
				$state['inside'] = true;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk_elements( $element['elements'], $post_id, $state );
			}
			$state['inside']   = $was;
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/**
	 * @param array<int, mixed>    $elements Elements at one level.
	 * @param int                  $post_id  Document ID.
	 * @param array<string, mixed> $state    Walk state.
	 * @return array<int, mixed>
	 */
	private static function pair_level( array $elements, $post_id, array &$state ) {
		if ( ! empty( $state['inside'] ) ) {
			foreach ( $elements as $index => $element ) {
				if ( ! is_array( $element ) ) {
					continue;
				}
				$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
				if ( '' === self::normalize_role( self::scalar_setting( $settings, self::SETTING_ROLE ) ) ) {
					continue;
				}
				$elements[ $index ] = self::patch_state(
					$element,
					array(
						self::SETTING_PAIR   => '',
						self::SETTING_NOTICE => self::notice_nested(),
					),
					$state
				);
			}
			return $elements;
		}

		$groups = array();
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			$role     = self::normalize_role( self::scalar_setting( $settings, self::SETTING_ROLE ) );
			if ( '' === $role ) {
				$elements[ $index ] = self::patch_state(
					$element,
					array(
						self::SETTING_PAIR   => '',
						self::SETTING_NOTICE => '',
					),
					$state
				);
				continue;
			}
			$key = self::test_group_key( $settings );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'a' => array(),
					'b' => array(),
				);
			}
			$groups[ $key ][ $role ][] = $index;
		}

		foreach ( $groups as $key => $roles ) {
			$as         = $roles['a'];
			$bs         = $roles['b'];
			$pair_index = 0;
			while ( $as && $bs ) {
				$index_a = array_shift( $as );
				$index_b = array_shift( $bs );
				$bound   = self::bind_pair( $elements[ $index_a ], $elements[ $index_b ], $post_id, $state['seen'], 0 === $pair_index );
				$elements[ $index_a ] = $bound['a'];
				$elements[ $index_b ] = $bound['b'];
				if ( ! empty( $bound['changed'] ) ) {
					$state['changed'] = true;
				}
				++$pair_index;
			}
			$notice = ( 0 === $pair_index ) ? self::notice_unpaired() : self::notice_extra();
			foreach ( array_merge( $as, $bs ) as $index ) {
				$elements[ $index ] = self::patch_state(
					$elements[ $index ],
					array(
						self::SETTING_PAIR   => '',
						self::SETTING_NOTICE => $notice,
					),
					$state
				);
			}
		}
		return $elements;
	}

	/**
	 * @param array<string, mixed> $element_a Variant A element.
	 * @param array<string, mixed> $element_b Variant B element.
	 * @param int                  $post_id   Document ID.
	 * @param array<int, bool>     $seen      Test ids already used in this document.
	 * @param bool                 $first     True for the first pair in this test group.
	 * @return array{a: array<string, mixed>, b: array<string, mixed>, changed: bool}
	 */
	private static function bind_pair( array $element_a, array $element_b, $post_id, array &$seen, $first ) {
		$changed  = false;
		$id_a     = self::element_id( isset( $element_a['id'] ) ? $element_a['id'] : '' );
		$id_b     = self::element_id( isset( $element_b['id'] ) ? $element_b['id'] : '' );
		$failed   = array(
			self::SETTING_PAIR   => '',
			self::SETTING_NOTICE => self::notice_unsaved(),
		);
		if ( '' === $id_a || '' === $id_b || ! class_exists( 'RWGC_AB_Tests', false ) ) {
			return array(
				'a'       => self::patch_element( $element_a, $failed, $changed ),
				'b'       => self::patch_element( $element_b, $failed, $changed ),
				'changed' => $changed,
			);
		}

		$settings_a = isset( $element_a['settings'] ) && is_array( $element_a['settings'] ) ? $element_a['settings'] : array();
		$settings_b = isset( $element_b['settings'] ) && is_array( $element_b['settings'] ) ? $element_b['settings'] : array();
		$picker     = self::scalar_setting( $settings_a, self::SETTING_TEST );
		$wanted     = ( $first && 'new' !== $picker && '' !== $picker ) ? absint( $picker ) : 0;
		$row        = null;
		if ( $wanted > 0 && isset( $seen[ $wanted ] ) ) {
			$wanted = 0;
		}
		if ( $wanted > 0 ) {
			$row = RWGC_AB_Tests::get( $wanted );
			if ( ! self::row_owned_here( $row, $post_id ) ) {
				$wanted = 0;
				$row    = null;
			}
		}
		if ( $wanted <= 0 && $first ) {
			$found = self::find_test_for_elements( $post_id, $id_a, $id_b );
			if ( $found > 0 && ! isset( $seen[ $found ] ) ) {
				$candidate = RWGC_AB_Tests::get( $found );
				if ( is_array( $candidate ) && ! self::row_is_ended( $candidate ) ) {
					$wanted = $found;
					$row    = $candidate;
				}
			}
		}
		if ( $wanted > 0 ) {
			$seen[ $wanted ] = true;
		}

		$config = self::pair_config( $post_id, $settings_a, $settings_b, $id_a, $id_b );
		$title  = self::pair_title( $settings_a, $settings_b, $row );
		$test_id = 0;
		if ( $wanted > 0 && is_array( $row ) ) {
			$update = array(
				'title'  => $title,
				'config' => $config,
			);
			if ( ! self::row_is_ended( $row ) ) {
				$update['status'] = 'publish';
			}
			$saved = RWGC_AB_Tests::update( $wanted, $update );
			if ( $saved > 0 ) {
				$test_id = $saved;
			}
		}
		if ( $test_id <= 0 ) {
			$test_id = RWGC_AB_Tests::create(
				array(
					'title'  => $title,
					'status' => 'publish',
					'config' => $config,
				)
			);
			if ( $test_id > 0 ) {
				$seen[ $test_id ] = true;
			}
		}
		if ( $test_id <= 0 ) {
			return array(
				'a'       => self::patch_element( $element_a, $failed, $changed ),
				'b'       => self::patch_element( $element_b, $failed, $changed ),
				'changed' => $changed,
			);
		}

		$stored = RWGC_AB_Tests::get( $test_id );
		$goal   = ( is_array( $stored ) && isset( $stored['config']['goal'] ) && is_array( $stored['config']['goal'] ) ) ? $stored['config']['goal'] : array();
		$patch  = array(
			self::SETTING_TEST     => (string) $test_id,
			self::SETTING_PAIR     => 'yes',
			self::SETTING_NOTICE   => '',
			self::SETTING_NAME     => is_array( $stored ) ? (string) $stored['title'] : $title,
			self::SETTING_GOAL     => ( isset( $goal['type'] ) && 'url' === $goal['type'] ) ? 'url' : 'click',
			self::SETTING_SELECTOR => isset( $goal['selector'] ) ? (string) $goal['selector'] : '',
			self::SETTING_URL      => isset( $goal['url'] ) ? (string) $goal['url'] : '',
		);
		return array(
			'a'       => self::patch_element( $element_a, $patch, $changed ),
			'b'       => self::patch_element( $element_b, $patch, $changed ),
			'changed' => $changed,
		);
	}

	/**
	 * @param array<string, mixed>|null $row     Test row.
	 * @param int                       $post_id Document ID.
	 * @return bool
	 */
	private static function row_owned_here( $row, $post_id ) {
		if ( ! is_array( $row ) ) {
			return false;
		}
		$config  = isset( $row['config'] ) && is_array( $row['config'] ) ? $row['config'] : array();
		$adapter = isset( $config['adapter'] ) ? sanitize_key( (string) $config['adapter'] ) : '';
		$source  = isset( $config['source_post_id'] ) ? absint( $config['source_post_id'] ) : 0;
		if ( 'elementor' !== $adapter ) {
			return false;
		}
		return $source <= 0 || $source === (int) $post_id;
	}

	/**
	 * @param int    $post_id Document ID.
	 * @param string $id_a    Element id.
	 * @param string $id_b    Element id.
	 * @return int
	 */
	private static function find_test_for_elements( $post_id, $id_a, $id_b ) {
		if ( ! class_exists( 'RWGC_AB_Tests', false ) || '' === $id_a || '' === $id_b ) {
			return 0;
		}
		foreach ( RWGC_AB_Tests::query() as $row ) {
			if ( ! self::row_owned_here( $row, $post_id ) || self::row_is_ended( $row ) ) {
				continue;
			}
			$config = isset( $row['config'] ) && is_array( $row['config'] ) ? $row['config'] : array();
			$found_a = isset( $config['element_a'] ) ? (string) $config['element_a'] : '';
			$found_b = isset( $config['element_b'] ) ? (string) $config['element_b'] : '';
			if ( $found_a === $id_a && $found_b === $id_b ) {
				return absint( $row['id'] );
			}
		}
		return 0;
	}

	/**
	 * @param int                  $post_id    Document ID.
	 * @param array<string, mixed> $settings_a Variant A settings.
	 * @param array<string, mixed> $settings_b Variant B settings.
	 * @param string               $id_a       Element id.
	 * @param string               $id_b       Element id.
	 * @return array<string, mixed>
	 */
	private static function pair_config( $post_id, array $settings_a, array $settings_b, $id_a, $id_b ) {
		$goal_settings = $settings_a;
		$type          = self::goal_type( $goal_settings );
		$selector      = self::scalar_setting( $goal_settings, self::SETTING_SELECTOR );
		$url           = self::scalar_setting( $goal_settings, self::SETTING_URL );
		if ( '' === $selector && '' === $url ) {
			$type     = self::goal_type( $settings_b );
			$selector = self::scalar_setting( $settings_b, self::SETTING_SELECTOR );
			$url      = self::scalar_setting( $settings_b, self::SETTING_URL );
		}
		if ( 'url' !== $type ) {
			$url = '';
		} else {
			$selector = '';
		}
		$url = self::safe_url( $url );
		return array(
			'version'        => 1,
			'type'           => 'element',
			'adapter'        => 'elementor',
			'source_post_id' => absint( $post_id ),
			'instance_id'    => substr( 'e_' . strtolower( $id_a ), 0, 64 ),
			'control'        => 'a',
			'variants'       => array(
				array(
					'id'         => 'a',
					'allocation' => 50,
				),
				array(
					'id'         => 'b',
					'allocation' => 50,
				),
			),
			'goal'           => array(
				'type'     => $type,
				'selector' => $selector,
				'url'      => $url,
			),
			'element_a'      => $id_a,
			'element_b'      => $id_b,
		);
	}

	/**
	 * @param array<string, mixed>      $settings_a Variant A settings.
	 * @param array<string, mixed>      $settings_b Variant B settings.
	 * @param array<string, mixed>|null $row        Existing test.
	 * @return string
	 */
	private static function pair_title( array $settings_a, array $settings_b, $row ) {
		$name = self::scalar_setting( $settings_a, self::SETTING_NAME );
		if ( '' === $name ) {
			$name = self::scalar_setting( $settings_b, self::SETTING_NAME );
		}
		if ( '' === $name && is_array( $row ) && isset( $row['title'] ) ) {
			$name = trim( (string) $row['title'] );
		}
		if ( '' === $name ) {
			$name = __( 'A/B test', 'reactwoo-geocore' );
		}
		return $name;
	}

	/**
	 * @param array<string, mixed> $element Element.
	 * @param array<string, mixed> $patch   Setting updates.
	 * @param array<string, mixed> $state   Walk state.
	 * @return array<string, mixed>
	 */
	private static function patch_state( array $element, array $patch, array &$state ) {
		$changed = false;
		$element = self::patch_element( $element, $patch, $changed );
		if ( $changed ) {
			$state['changed'] = true;
		}
		return $element;
	}

	/**
	 * @param array<string, mixed> $element Element.
	 * @param array<string, mixed> $patch   Setting updates.
	 * @param bool                 $changed Changed flag.
	 * @return array<string, mixed>
	 */
	private static function patch_element( array $element, array $patch, &$changed ) {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$before   = self::settings_signature( $settings );
		foreach ( $patch as $key => $value ) {
			$settings = self::assign_setting( $settings, (string) $key, $value );
		}
		if ( $before !== self::settings_signature( $settings ) ) {
			$changed            = true;
			$element['settings'] = $settings;
		}
		return $element;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $key      Key.
	 * @param mixed                $value    New scalar.
	 * @return array<string, mixed>
	 */
	private static function assign_setting( array $settings, $key, $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( self::scalar_setting( $settings, $key ) === $value ) {
			return $settings;
		}
		if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) && array_key_exists( 'value', $settings[ $key ] ) ) {
			$settings[ $key ]['value'] = $value;
			return $settings;
		}
		$settings[ $key ] = $value;
		return $settings;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	private static function settings_signature( array $settings ) {
		$parts = array();
		foreach ( self::$setting_keys as $key ) {
			$parts[] = self::scalar_setting( $settings, $key );
		}
		return implode( "\n", $parts );
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	private static function test_group_key( array $settings ) {
		$picker = self::scalar_setting( $settings, self::SETTING_TEST );
		if ( 'new' === $picker || '' === $picker ) {
			return 'new';
		}
		$id = absint( $picker );
		return $id > 0 ? 'id:' . $id : 'new';
	}

	/**
	 * @param mixed $element Element.
	 * @return array{action: string, arm: string, test_id: int, mode: string, warning: string}
	 */
	private static function decision_for_element( $element ) {
		$settings = self::element_settings( $element );
		return self::decision_for_settings( $settings, self::current_surface(), self::$render_depth > 0 );
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $surface  frontend|editor|preview.
	 * @param bool                 $nested   Inside an arm that is already being rendered.
	 * @return array{action: string, arm: string, test_id: int, mode: string, warning: string}
	 */
	private static function decision_for_settings( array $settings, $surface, $nested ) {
		$context = array(
			'surface'  => $surface,
			'nested'   => $nested,
			'running'  => false,
			'ended'    => false,
			'kept'     => 'a',
			'assigned' => 'a',
			'mode'     => 'server',
		);
		$role    = self::normalize_role( self::scalar_setting( $settings, self::SETTING_ROLE ) );
		$test_id = absint( self::scalar_setting( $settings, self::SETTING_TEST ) );
		$paired  = ( 'yes' === self::scalar_setting( $settings, self::SETTING_PAIR ) );
		if ( '' !== $role && $paired && $test_id > 0 && 'frontend' === $surface && ! $nested ) {
			$context = self::runtime_context( $test_id, $surface, $nested );
		}
		return self::render_decision( $settings, $context );
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $surface Surface.
	 * @param bool   $nested  Nested flag.
	 * @return array<string, mixed>
	 */
	private static function runtime_context( $test_id, $surface, $nested ) {
		$running = false;
		$ended   = false;
		$kept    = 'a';
		if ( class_exists( 'RWGC_AB_Tests', false ) ) {
			$row = RWGC_AB_Tests::get( $test_id );
			if ( is_array( $row ) ) {
				$status  = isset( $row['status'] ) ? (string) $row['status'] : '';
				$running = ( 'publish' === $status );
				$ended   = ( RWGC_AB_Tests::STATUS_ENDED === $status );
				if ( isset( $row['config']['kept'] ) && 'b' === sanitize_key( (string) $row['config']['kept'] ) ) {
					$kept = 'b';
				}
			}
		}
		$running = (bool) apply_filters( 'rwgc_ab_test_is_running', $running, absint( $test_id ) );
		$mode    = 'server';
		if ( $running && class_exists( 'RWGC_AB_Cache', false ) && RWGC_AB_Cache::is_fragment() ) {
			$mode = 'fragment';
		}
		return array(
			'surface'  => $surface,
			'nested'   => $nested,
			'running'  => $running,
			'ended'    => $ended && ! $running,
			'kept'     => $kept,
			'assigned' => $running ? self::assigned_arm( $test_id ) : 'a',
			'mode'     => $mode,
		);
	}

	/**
	 * @param int $test_id Test id.
	 * @return string
	 */
	private static function assigned_arm( $test_id ) {
		if ( class_exists( 'RWGC_AB_Assignment', false ) ) {
			$arm = RWGC_AB_Assignment::variant( $test_id );
			return 'b' === $arm ? 'b' : 'a';
		}
		return 'a';
	}

	/**
	 * Wrap one arm the same way rwgc_ab_render_test() does.
	 *
	 * Elementor markup is already escaped and uses data attributes the geo
	 * content allowlist would remove, so the arm is not passed through that
	 * helper. rwgc_ab_mark_rendered() still applies the cache policy.
	 *
	 * @param array<string, mixed> $decision Decision.
	 * @param string               $html     Element HTML.
	 * @return string
	 */
	private static function emit_html( array $decision, $html ) {
		$html = (string) $html;
		if ( 'raw' === $decision['mode'] ) {
			return $html;
		}
		$test_id = absint( $decision['test_id'] );
		$mode    = ( 'fragment' === $decision['mode'] ) ? 'fragment' : 'server';
		$arm     = ( 'fragment' === $mode || 'b' !== $decision['arm'] ) ? 'a' : 'b';
		if ( $test_id > 0 && function_exists( 'rwgc_ab_mark_rendered' ) ) {
			rwgc_ab_mark_rendered( $test_id );
		}
		$chosen = $arm;
		if ( isset( $decision['assigned'] ) && ( 'a' === $decision['assigned'] || 'b' === $decision['assigned'] ) ) {
			$chosen = $decision['assigned'];
		}
		self::send_request_nocache();
		$goal_attr = class_exists( 'RWGC_AB_Tracker', false ) ? RWGC_AB_Tracker::goal_attribute( $test_id ) : '';
		if ( ! is_string( $goal_attr ) ) {
			$goal_attr = '';
		}
		$wrapper = '<div class="rwgc-ab" data-rwgc-ab="' . esc_attr( (string) $test_id ) . '"' . $goal_attr . ' data-rwgc-ab-mode="' . esc_attr( $mode ) . '" data-rwgc-arm="' . esc_attr( $arm ) . '" data-rwgc-ab-decision="' . esc_attr( $chosen ) . '">' . $html . '</div>';
		if ( function_exists( 'do_action' ) ) {
			do_action( 'rwgc_ab_rendered_test', $test_id, $arm, $mode );
		}
		$filtered = apply_filters( 'rwgc_ab_render_html', $wrapper, $test_id, $arm, $mode );
		return is_string( $filtered ) ? $filtered : $wrapper;
	}

	/**
	 * @param mixed                $element  Element.
	 * @param array<string, mixed> $decision Decision.
	 * @return void
	 */
	private static function label_element( $element, array $decision ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'add_render_attribute' ) ) {
			return;
		}
		$class = 'rwgc-ab-arm';
		$attrs = array(
			'class'              => $class,
			'data-rwgc-ab-label' => ( 'b' === $decision['arm'] ) ? 'B' : 'A',
		);
		if ( '' !== $decision['warning'] ) {
			$attrs['class']                = 'rwgc-ab-arm rwgc-ab-warning';
			$attrs['data-rwgc-ab-warning'] = '1';
		}
		$element->add_render_attribute( '_wrapper', $attrs );
	}

	/**
	 * @param int|string $test_id Test id.
	 * @param string     $arm     a|b.
	 * @return string
	 */
	private static function variant_html_for_test( $test_id, $arm ) {
		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! class_exists( 'RWGC_AB_Tests', false ) ) {
			return '';
		}
		$row = RWGC_AB_Tests::get( $test_id );
		if ( ! is_array( $row ) ) {
			return '';
		}
		$config  = isset( $row['config'] ) && is_array( $row['config'] ) ? $row['config'] : array();
		$adapter = isset( $config['adapter'] ) ? sanitize_key( (string) $config['adapter'] ) : '';
		if ( 'elementor' !== $adapter ) {
			return '';
		}
		$post_id = isset( $config['source_post_id'] ) ? absint( $config['source_post_id'] ) : 0;
		$arm     = ( 'b' === sanitize_key( (string) $arm ) ) ? 'b' : 'a';
		$key     = ( 'b' === $arm ) ? 'element_b' : 'element_a';
		$element_id = isset( $config[ $key ] ) ? self::element_id( $config[ $key ] ) : '';
		if ( $post_id <= 0 || '' === $element_id ) {
			return '';
		}
		$node = self::find_element( self::document_elements( $post_id ), $element_id );
		if ( ! is_array( $node ) ) {
			return '';
		}
		return self::render_node( $node, $post_id );
	}

	/**
	 * @param array<int, mixed> $elements Elements.
	 * @param string            $element_id Element id.
	 * @return array<string, mixed>|null
	 */
	private static function find_element( array $elements, $element_id ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['id'] ) && self::element_id( $element['id'] ) === $element_id ) {
				return $element;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = self::find_element( $element['elements'], $element_id );
				if ( is_array( $found ) ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $node    Element data.
	 * @param int                  $post_id Document ID.
	 * @return string
	 */
	private static function render_node( array $node, $post_id ) {
		++self::$supplying;
		try {
			$override = apply_filters( 'rwgc_ab_elementor_render_node', null, $node, absint( $post_id ) );
			if ( is_string( $override ) ) {
				return $override;
			}
			return self::render_node_with_elementor( $node );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return '';
		} finally {
			if ( self::$supplying > 0 ) {
				--self::$supplying;
			}
		}
	}

	/**
	 * @param array<string, mixed> $node Element data.
	 * @return string
	 */
	private static function render_node_with_elementor( array $node ) {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return '';
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( ! is_object( $plugin ) || ! isset( $plugin->elements_manager ) || ! is_object( $plugin->elements_manager ) ) {
			return '';
		}
		$manager = $plugin->elements_manager;
		if ( ! method_exists( $manager, 'create_element_instance' ) ) {
			return '';
		}
		$instance = $manager->create_element_instance( $node );
		if ( ! is_object( $instance ) ) {
			return '';
		}
		$level = ob_get_level();
		ob_start();
		try {
			if ( method_exists( $instance, 'print_element' ) ) {
				$instance->print_element();
			} elseif ( method_exists( $instance, 'render' ) ) {
				$instance->render();
			}
			$html = ob_get_clean();
			return is_string( $html ) ? $html : '';
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			return '';
		}
	}

	/**
	 * @param mixed $node Element, or a list of elements.
	 * @return bool
	 */
	private static function tree_has_ab_role( $node ) {
		if ( ! is_array( $node ) ) {
			return false;
		}
		if ( self::node_has_ab_role( $node ) ) {
			return true;
		}
		$is_list = function_exists( 'array_is_list' ) ? array_is_list( $node ) : ( array() !== $node && array_keys( $node ) === range( 0, count( $node ) - 1 ) );
		if ( ! $is_list ) {
			return false;
		}
		foreach ( $node as $child ) {
			if ( self::node_has_ab_role( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $node Element raw data.
	 * @return bool
	 */
	private static function node_has_ab_role( $node ) {
		if ( ! is_array( $node ) || array() === $node ) {
			return false;
		}
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : $node;
		if ( '' !== self::normalize_role( self::scalar_setting( $settings, self::SETTING_ROLE ) ) ) {
			return true;
		}
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				if ( self::node_has_ab_role( $child ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param mixed $cache Stored `_elementor_element_cache` value.
	 * @return string
	 */
	private static function element_cache_content( $cache ) {
		if ( is_string( $cache ) && '' !== $cache ) {
			$decoded = json_decode( $cache, true );
			if ( is_array( $decoded ) ) {
				$cache = $decoded;
			} else {
				return $cache;
			}
		}
		if ( ! is_array( $cache ) ) {
			return '';
		}
		if ( isset( $cache['value']['content'] ) && is_string( $cache['value']['content'] ) ) {
			return $cache['value']['content'];
		}
		if ( isset( $cache['content'] ) && is_string( $cache['content'] ) ) {
			return $cache['content'];
		}
		return '';
	}

	/**
	 * @param mixed $document Document or post ID.
	 * @return int
	 */
	private static function document_post_id( $document ) {
		if ( is_numeric( $document ) ) {
			return absint( $document );
		}
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			return absint( $document->get_main_id() );
		}
		if ( is_object( $document ) && isset( $document->post ) && is_object( $document->post ) && isset( $document->post->ID ) ) {
			return absint( $document->post->ID );
		}
		return 0;
	}

	/**
	 * Private, no-store on every Elementor A/B render.
	 *
	 * Fragment mode otherwise allows a shared cache after the cookie is set.
	 * The decision attribute makes this HTML specific to the request.
	 *
	 * @return void
	 */
	private static function send_request_nocache() {
		$emit = apply_filters( 'rwgc_ab_emit_http_headers', true );
		if ( ! $emit ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return;
		}
		if ( function_exists( 'header' ) ) {
			header( 'Cache-Control: private, no-store' );
			header( 'Pragma: no-cache' );
		}
	}

	/**
	 * @param mixed                $element  Element.
	 * @param array<string, mixed> $decision Decision.
	 * @return void
	 */
	private static function tag_live_decision( $element, array $decision ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'add_render_attribute' ) ) {
			return;
		}
		$chosen = ( isset( $decision['assigned'] ) && 'b' === $decision['assigned'] ) ? 'b' : 'a';
		$element->add_render_attribute(
			'_wrapper',
			array(
				'data-rwgc-ab-decision' => $chosen,
			)
		);
	}

	/**
	 * @param int $post_id Document ID.
	 * @return array<int, mixed>
	 */
	private static function document_elements( $post_id ) {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$raw = get_post_meta( absint( $post_id ), '_elementor_data', true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = wp_unslash( $raw );
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param mixed $element Element.
	 * @return array<string, mixed>
	 */
	private static function element_settings( $element ) {
		$settings = array();
		if ( is_object( $element ) && method_exists( $element, 'get_settings' ) ) {
			$raw = $element->get_settings();
			if ( is_array( $raw ) ) {
				$settings = $raw;
			}
		}
		if ( is_object( $element ) && method_exists( $element, 'get_atomic_settings' ) ) {
			$atomic = $element->get_atomic_settings();
			if ( is_array( $atomic ) ) {
				foreach ( self::$setting_keys as $key ) {
					if ( array_key_exists( $key, $atomic ) && null !== $atomic[ $key ] && '' !== $atomic[ $key ] ) {
						$settings[ $key ] = $atomic[ $key ];
					}
				}
			}
		}
		return $settings;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $key      Key.
	 * @return string
	 */
	private static function scalar_setting( array $settings, $key ) {
		if ( ! array_key_exists( $key, $settings ) ) {
			return '';
		}
		$value = $settings[ $key ];
		if ( is_array( $value ) ) {
			if ( array_key_exists( 'value', $value ) && is_scalar( $value['value'] ) ) {
				$value = $value['value'];
			} else {
				return '';
			}
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * @param mixed $role Role value.
	 * @return string
	 */
	private static function normalize_role( $role ) {
		$role = strtolower( trim( (string) $role ) );
		if ( 'a' === $role || 'variant_a' === $role ) {
			return 'a';
		}
		if ( 'b' === $role || 'variant_b' === $role ) {
			return 'b';
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	private static function goal_type( array $settings ) {
		return ( 'url' === self::scalar_setting( $settings, self::SETTING_GOAL ) ) ? 'url' : 'click';
	}

	/**
	 * Drop scriptable URLs before the test store sees them.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function safe_url( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '#^\s*(?:javascript|data):#i', $url ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * @param mixed $id Element id.
	 * @return string
	 */
	private static function element_id( $id ) {
		$id = is_scalar( $id ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $id ) : '';
		if ( strlen( $id ) > 32 ) {
			$id = substr( $id, 0, 32 );
		}
		return $id;
	}

	/**
	 * @param array<string, mixed>|null $row Test row.
	 * @return bool
	 */
	private static function row_is_ended( $row ) {
		return is_array( $row )
			&& class_exists( 'RWGC_AB_Tests', false )
			&& RWGC_AB_Tests::STATUS_ENDED === (string) ( isset( $row['status'] ) ? $row['status'] : '' );
	}

	/**
	 * @return array<string, string>
	 */
	private static function picker_options() {
		if ( is_array( self::$picker_options ) ) {
			return self::$picker_options;
		}
		$options = array(
			'new' => __( 'New test', 'reactwoo-geocore' ),
		);
		if ( class_exists( 'RWGC_AB_Tests', false ) ) {
			$count = 0;
			foreach ( RWGC_AB_Tests::query() as $row ) {
				if ( $count >= 200 ) {
					break;
				}
				$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				if ( $id <= 0 ) {
					continue;
				}
				$title  = isset( $row['title'] ) ? (string) $row['title'] : __( 'A/B test', 'reactwoo-geocore' );
				$status = isset( $row['status'] ) ? RWGC_AB_Tests::status_label( (string) $row['status'] ) : '';
				$options[ (string) $id ] = $title . ' (#' . $id . ( '' !== $status ? ', ' . $status : '' ) . ')';
				++$count;
			}
		}
		self::$picker_options = $options;
		return $options;
	}

	/**
	 * @return object|null
	 */
	private static function atomic_section() {
		$section_class = '\Elementor\Modules\AtomicWidgets\Controls\Section';
		if ( ! class_exists( $section_class, false ) || ! is_callable( array( $section_class, 'make' ) ) ) {
			return null;
		}
		$items = array();
		$role  = self::atomic_select(
			self::SETTING_ROLE,
			__( 'A/B test', 'reactwoo-geocore' ),
			array(
				array(
					'value' => 'off',
					'label' => __( 'Off', 'reactwoo-geocore' ),
				),
				array(
					'value' => 'a',
					'label' => __( 'Variant A', 'reactwoo-geocore' ),
				),
				array(
					'value' => 'b',
					'label' => __( 'Variant B', 'reactwoo-geocore' ),
				),
			)
		);
		if ( ! is_object( $role ) ) {
			return null;
		}
		$items[] = self::atomic_describe( $role, __( 'Set one sibling to Variant A and another to Variant B, and choose the same test. Visitors see one element. An unpaired element renders normally.', 'reactwoo-geocore' ) );

		$test_options = array();
		foreach ( self::picker_options() as $value => $label ) {
			$test_options[] = array(
				'value' => (string) $value,
				'label' => (string) $label,
			);
		}
		$test = self::atomic_select( self::SETTING_TEST, __( 'Test', 'reactwoo-geocore' ), $test_options );
		if ( is_object( $test ) ) {
			$items[] = $test;
		}
		$name = self::atomic_text( self::SETTING_NAME, __( 'New test name', 'reactwoo-geocore' ) );
		if ( is_object( $name ) ) {
			$items[] = $name;
		}
		$goal = self::atomic_select(
			self::SETTING_GOAL,
			__( 'Goal', 'reactwoo-geocore' ),
			array(
				array(
					'value' => 'click',
					'label' => __( 'Click', 'reactwoo-geocore' ),
				),
				array(
					'value' => 'url',
					'label' => __( 'URL visit', 'reactwoo-geocore' ),
				),
			)
		);
		if ( is_object( $goal ) ) {
			$items[] = $goal;
		}
		$selector = self::atomic_text( self::SETTING_SELECTOR, __( 'Click selector', 'reactwoo-geocore' ) );
		if ( is_object( $selector ) ) {
			$items[] = $selector;
		}
		$url = self::atomic_text( self::SETTING_URL, __( 'Goal URL', 'reactwoo-geocore' ) );
		if ( is_object( $url ) ) {
			$items[] = $url;
		}
		$notice = self::atomic_text( self::SETTING_NOTICE, __( 'Warning', 'reactwoo-geocore' ) );
		if ( is_object( $notice ) ) {
			$items[] = $notice;
		}

		$section = $section_class::make();
		if ( ! is_object( $section ) ) {
			return null;
		}
		$section = self::atomic_call( $section, 'set_label', __( 'A/B test', 'reactwoo-geocore' ) );
		$section = self::atomic_call( $section, 'set_id', self::SECTION_ID );
		if ( method_exists( $section, 'set_items' ) ) {
			$section = self::atomic_call( $section, 'set_items', $items );
		} elseif ( method_exists( $section, 'add_item' ) ) {
			foreach ( $items as $item ) {
				$section = self::atomic_call( $section, 'add_item', $item );
			}
		} else {
			return null;
		}
		return $section;
	}

	/**
	 * @param string               $bind    Prop id.
	 * @param string               $label   Label.
	 * @param array<int, mixed>    $options Options.
	 * @return object|null
	 */
	private static function atomic_select( $bind, $label, array $options ) {
		$class = '\Elementor\Modules\AtomicWidgets\Controls\Types\Select_Control';
		if ( ! class_exists( $class, false ) || ! is_callable( array( $class, 'bind_to' ) ) ) {
			return null;
		}
		$control = $class::bind_to( $bind );
		if ( ! is_object( $control ) ) {
			return null;
		}
		$control = self::atomic_call( $control, 'set_label', $label );
		return self::atomic_call( $control, 'set_options', $options );
	}

	/**
	 * @param string $bind  Prop id.
	 * @param string $label Label.
	 * @return object|null
	 */
	private static function atomic_text( $bind, $label ) {
		$candidates = array(
			'\Elementor\Modules\AtomicWidgets\Controls\Types\Text_Control',
			'\Elementor\Modules\AtomicWidgets\Controls\Types\String_Control',
		);
		foreach ( $candidates as $class ) {
			if ( ! class_exists( $class, false ) || ! is_callable( array( $class, 'bind_to' ) ) ) {
				continue;
			}
			$control = $class::bind_to( $bind );
			if ( ! is_object( $control ) ) {
				continue;
			}
			return self::atomic_call( $control, 'set_label', $label );
		}
		return null;
	}

	/**
	 * @param object $control Control.
	 * @param string $text    Description.
	 * @return object
	 */
	private static function atomic_describe( $control, $text ) {
		if ( method_exists( $control, 'set_description' ) ) {
			return self::atomic_call( $control, 'set_description', $text );
		}
		return $control;
	}

	/**
	 * Fluent and mutable Atomic setters both work. A thrown setter is ignored.
	 *
	 * @param object $object Object.
	 * @param string $method Method.
	 * @param mixed  $arg    Argument.
	 * @return object
	 */
	private static function atomic_call( $object, $method, $arg ) {
		if ( ! is_object( $object ) || ! method_exists( $object, $method ) ) {
			return $object;
		}
		try {
			$result = $object->$method( $arg );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return $object;
		}
		return is_object( $result ) ? $result : $object;
	}

	/**
	 * @param array<int, mixed> $controls Controls.
	 * @param string            $id       Section id.
	 * @return bool
	 */
	private static function atomic_controls_have_section( array $controls, $id ) {
		foreach ( $controls as $section ) {
			if ( is_object( $section ) && method_exists( $section, 'get_id' ) && $id === (string) $section->get_id() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $element Element.
	 * @return bool
	 */
	private static function is_atomic_element( $element ) {
		if ( ! is_object( $element ) ) {
			return false;
		}
		$atomic_base = '\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Element_Base';
		if ( class_exists( $atomic_base, false ) && is_a( $element, $atomic_base ) ) {
			return true;
		}
		return method_exists( $element, 'get_atomic_settings' );
	}

	/**
	 * @param mixed $element Element.
	 * @return string
	 */
	private static function buffer_key( $element ) {
		if ( is_object( $element ) && function_exists( 'spl_object_hash' ) ) {
			return spl_object_hash( $element );
		}
		if ( is_object( $element ) && method_exists( $element, 'get_id' ) ) {
			return (string) $element->get_id();
		}
		return '';
	}

	/**
	 * @return string frontend|editor|preview
	 */
	private static function current_surface() {
		$forced = apply_filters( 'rwgc_ab_elementor_surface', '' );
		if ( is_string( $forced ) && in_array( $forced, array( 'frontend', 'editor', 'preview' ), true ) ) {
			return $forced;
		}
		return self::detect_surface();
	}

	/**
	 * Editor, preview, or the public page.
	 *
	 * Elementor 4 can report edit or preview mode on a normal front-end render
	 * of classic elements while Atomic experiments are on. Labels are only for
	 * someone who can edit, and preview also needs the preview query.
	 *
	 * @return string frontend|editor|preview
	 */
	public static function detect_surface() {
		try {
			if ( self::editor_reports_edit_mode() && self::current_user_can_edit() ) {
				return 'editor';
			}
			if ( self::preview_reports_preview_mode() && self::valid_preview_query() && self::current_user_can_edit() ) {
				return 'preview';
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			return 'frontend';
		}
		if ( self::is_admin_or_rest_editor() ) {
			return 'editor';
		}
		return 'frontend';
	}

	/**
	 * @return bool
	 */
	private static function editor_reports_edit_mode() {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return is_object( $plugin )
			&& isset( $plugin->editor )
			&& is_object( $plugin->editor )
			&& method_exists( $plugin->editor, 'is_edit_mode' )
			&& $plugin->editor->is_edit_mode();
	}

	/**
	 * @return bool
	 */
	private static function preview_reports_preview_mode() {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return is_object( $plugin )
			&& isset( $plugin->preview )
			&& is_object( $plugin->preview )
			&& method_exists( $plugin->preview, 'is_preview_mode' )
			&& $plugin->preview->is_preview_mode();
	}

	/**
	 * Preview iframe query: a positive document id, matching the current post when one is set.
	 *
	 * @return bool
	 */
	private static function valid_preview_query() {
		if ( ! isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		$raw = wp_unslash( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $raw ) ) {
			return false;
		}
		$preview_id = absint( $raw );
		if ( $preview_id <= 0 ) {
			return false;
		}
		if ( function_exists( 'get_the_ID' ) ) {
			$current = absint( get_the_ID() );
			if ( $current > 0 && $current !== $preview_id ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * wp-admin Elementor editor/ajax, or a REST request whose route is Elementor's editor.
	 *
	 * @return bool
	 */
	private static function is_admin_or_rest_editor() {
		if ( ! self::current_user_can_edit() ) {
			return false;
		}
		$action = '';
		if ( isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$editor_actions = array(
			'elementor',
			'elementor_ajax',
			'elementor_get_templates',
			'elementor_save_template',
			'elementor_get_template',
			'elementor_delete_template',
			'elementor_import_template',
			'elementor_library_direct_actions',
		);
		if ( function_exists( 'is_admin' ) && is_admin() && in_array( $action, $editor_actions, true ) ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && self::rest_route_is_elementor_editor() ) {
			return true;
		}
		return false;
	}

	/**
	 * @return bool
	 */
	private static function rest_route_is_elementor_editor() {
		$route = '';
		if ( isset( $_REQUEST['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = wp_unslash( $_REQUEST['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$route = is_string( $raw ) ? $raw : '';
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$raw = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$route = is_string( $raw ) ? $raw : '';
		}
		return false !== strpos( $route, '/elementor/v1/' ) || false !== strpos( $route, '/elementor-pro/v1/' );
	}

	/**
	 * Logged-out visitors, and anyone who cannot edit, stay on the public render.
	 *
	 * @return bool
	 */
	private static function current_user_can_edit() {
		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {
			return false;
		}
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		$post_id = 0;
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = wp_unslash( $_GET['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! is_array( $raw ) ) {
				$post_id = absint( $raw );
			}
		}
		if ( $post_id <= 0 && function_exists( 'get_the_ID' ) ) {
			$post_id = absint( get_the_ID() );
		}
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * @return bool
	 */
	private static function is_builder_surface() {
		$surface = self::current_surface();
		return ( 'editor' === $surface || 'preview' === $surface );
	}

	/**
	 * @return bool
	 */
	private static function elementor_is_loaded() {
		if ( class_exists( '\Elementor\Plugin', false ) ) {
			return true;
		}
		return function_exists( 'did_action' ) && (bool) did_action( 'elementor/loaded' );
	}

	/**
	 * Visible badge for Atomic elements, whose root does not print _wrapper classes.
	 *
	 * @param array<string, mixed> $decision Decision.
	 * @return string
	 */
	private static function editor_badge_html( array $decision ) {
		$warning = isset( $decision['warning'] ) && '' !== (string) $decision['warning'];
		$arm     = ( isset( $decision['arm'] ) && 'b' === $decision['arm'] ) ? 'B' : 'A';
		if ( $warning ) {
			$label = __( 'A/B unpaired', 'reactwoo-geocore' );
			$class = 'rwgc-ab-badge rwgc-ab-badge-warning';
			$arm   = 'unpaired';
		} elseif ( 'B' === $arm ) {
			$label = __( 'Variant B', 'reactwoo-geocore' );
			$class = 'rwgc-ab-badge';
		} else {
			$label = __( 'Variant A', 'reactwoo-geocore' );
			$class = 'rwgc-ab-badge';
		}
		return '<span class="' . esc_attr( $class ) . '" data-rwgc-ab-label="' . esc_attr( $arm ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * @return string
	 */
	private static function editor_css() {
		return '.rwgc-ab-arm{position:relative;outline:1px dashed #1d4ed8;outline-offset:2px}'
			. '.rwgc-ab-arm[data-rwgc-ab-label="A"]::before{content:"Variant A"}'
			. '.rwgc-ab-arm[data-rwgc-ab-label="B"]::before{content:"Variant B"}'
			. '.rwgc-ab-arm::before{position:absolute;z-index:20;top:0;left:0;padding:2px 6px;font:600 11px/1.4 sans-serif;color:#fff;background:#1d4ed8}'
			. '.rwgc-ab-arm.rwgc-ab-warning{outline-color:#b45309}'
			. '.rwgc-ab-arm.rwgc-ab-warning::before{content:"A/B unpaired";background:#b45309}'
			. '.rwgc-ab-badge{display:inline-block;margin:0 0 6px;padding:2px 6px;font:600 11px/1.4 sans-serif;color:#fff;background:#1d4ed8}'
			. '.rwgc-ab-badge-warning{background:#b45309}';
	}
}
