<?php
/**
 * LiteSpeed-safe Elementor widgets-config (replace get_stack for third-party widgets).
 *
 * Elementor 4.2 has no per-widget hydration. `get_widgets_config` / `refresh_widgets_config`
 * call get_stack() for every registered widget. Add-on catalogues (Unlimited Elements, etc.)
 * make that request large enough for LiteSpeed to return HTTP 503 and spin the Elements panel.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces Elementor bulk widget-config AJAX with a slimmer builder.
 */
class RWGC_Elementor_Widgets_Config {

	const MAX_SELECT_OPTIONS = 24;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register_actions' ), 20 );
	}

	/**
	 * Overwrite Elementor's bulk config actions after they register.
	 *
	 * @param object $ajax Elementor ajax manager.
	 * @return void
	 */
	public static function register_actions( $ajax ) {
		if ( ! is_object( $ajax ) || ! method_exists( $ajax, 'register_ajax_action' ) ) {
			return;
		}
		$ajax->register_ajax_action( 'get_widgets_config', array( __CLASS__, 'ajax_get_widgets_config' ) );
		$ajax->register_ajax_action( 'refresh_widgets_config', array( __CLASS__, 'ajax_refresh_widgets_config' ) );
	}

	/**
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, mixed>
	 */
	public static function ajax_get_widgets_config( array $data ) {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return array();
		}

		$plugin = \Elementor\Plugin::$instance;
		$plugin->documents->check_permissions( $data['editor_post_id'] ?? 0 );

		$config = array();
		foreach ( $plugin->widgets_manager->get_widget_types() as $widget_key => $widget ) {
			if ( isset( $data['exclude'][ $widget_key ] ) ) {
				continue;
			}
			$config[ $widget_key ] = self::widget_controls_payload( $widget );
		}

		return $config;
	}

	/**
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, mixed>
	 */
	public static function ajax_refresh_widgets_config( array $data ) {
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return array(
				'widgets'    => array(),
				'categories' => array(),
			);
		}

		$plugin = \Elementor\Plugin::$instance;
		$plugin->documents->check_permissions( $data['editor_post_id'] ?? 0 );

		$widgets = array();
		foreach ( $plugin->widgets_manager->get_widget_types() as $widget_key => $widget ) {
			$widget_config             = $widget->get_config();
			$payload                   = self::widget_controls_payload( $widget );
			$widget_config['controls'] = $payload['controls'];
			$widget_config['tabs_controls'] = $payload['tabs_controls'];
			$widgets[ $widget_key ]    = $widget_config;
		}

		return array(
			'widgets'    => $widgets,
			'categories' => $plugin->elements_manager->get_categories(),
		);
	}

	/**
	 * @param object $widget Widget instance.
	 * @return array{controls: array<string, mixed>, tabs_controls: array<string, mixed>}
	 */
	public static function widget_controls_payload( $widget ) {
		$empty = array(
			'controls'      => array(),
			'tabs_controls' => array(),
		);

		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return $empty;
		}

		$name  = (string) $widget->get_name();
		$class = get_class( $widget );
		if ( self::should_skip_full_stack( $name, $class ) ) {
			return $empty;
		}

		if ( ! method_exists( $widget, 'get_stack' ) ) {
			return $empty;
		}

		$stack = $widget->get_stack( false );
		$controls = ( isset( $stack['controls'] ) && is_array( $stack['controls'] ) ) ? $stack['controls'] : array();

		return array(
			'controls'      => self::slim_controls( $controls ),
			'tabs_controls' => method_exists( $widget, 'get_tabs_controls' ) ? $widget->get_tabs_controls() : array(),
		);
	}

	/**
	 * Skip get_stack() for add-on catalogues. Keep Elementor / Pro / ReactWoo / Atomic.
	 *
	 * @param string $widget_name Widget name.
	 * @param string $widget_class Fully-qualified class.
	 * @return bool
	 */
	public static function should_skip_full_stack( $widget_name, $widget_class ) {
		$widget_name  = (string) $widget_name;
		$widget_class = (string) $widget_class;

		$keep_class_prefixes = array(
			'Elementor\\',
			'ElementorPro\\',
			'ReactWoo\\',
			'RWGC_',
			'RW_Elementor_',
			'RWSC_',
			'GRP_',
		);
		foreach ( $keep_class_prefixes as $prefix ) {
			if ( 0 === strpos( $widget_class, $prefix ) ) {
				$skip = false;
				/**
				 * Filter whether a kept-namespace widget should still skip get_stack().
				 *
				 * @param bool   $skip         Skip full stack.
				 * @param string $widget_name  Widget name.
				 * @param string $widget_class Class name.
				 */
				if ( function_exists( 'apply_filters' ) ) {
					return (bool) apply_filters( 'rwgc_elementor_skip_widget_stack', $skip, $widget_name, $widget_class );
				}
				return false;
			}
		}

		$skip_name_prefixes = array(
			'ucaddon_',
			'ucaddon-cat-',
			'eael-',
			'eael_',
			'jet-',
			'jet_',
			'premium-',
			'uael-',
		);
		$skip = true;
		foreach ( $skip_name_prefixes as $prefix ) {
			if ( 0 === strpos( $widget_name, $prefix ) ) {
				$skip = true;
				break;
			}
		}

		/**
		 * Filter whether to skip get_stack() for this widget during bulk config.
		 *
		 * @param bool   $skip         Default true for unknown third-party widgets.
		 * @param string $widget_name  Widget name.
		 * @param string $widget_class Class name.
		 */
		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'rwgc_elementor_skip_widget_stack', $skip, $widget_name, $widget_class );
		}
		return $skip;
	}

	/**
	 * Cap large select/select2 option maps so LiteSpeed is not asked to emit megabytes of JSON.
	 *
	 * @param array<string, mixed> $controls Control stack.
	 * @return array<string, mixed>
	 */
	public static function slim_controls( array $controls ) {
		$max = self::MAX_SELECT_OPTIONS;
		if ( function_exists( 'apply_filters' ) ) {
			$max = (int) apply_filters( 'rwgc_elementor_max_select_options', $max );
		}
		if ( $max < 1 ) {
			return $controls;
		}

		foreach ( $controls as $key => $control ) {
			if ( ! is_array( $control ) || empty( $control['options'] ) || ! is_array( $control['options'] ) ) {
				continue;
			}
			if ( count( $control['options'] ) > $max ) {
				$controls[ $key ]['options'] = array_slice( $control['options'], 0, $max, true );
			}
		}

		return $controls;
	}
}
