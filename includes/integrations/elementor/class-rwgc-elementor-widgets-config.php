<?php
/**
 * LiteSpeed-safe Elementor widgets-config.
 *
 * Production evidence (1.8.142): the 503 happens inside
 * `$widgets_manager->get_widget_types()` — 112 leftover `elementor/widgets/register`
 * callbacks (Elementor Pro Modules) — before any get_stack() or late-skip can run.
 * These handlers therefore return empty control maps and must not touch the
 * widgets manager. Filter `rwgc_elementor_avoid_widget_manager` to false to
 * rebuild stacks on a fast host.
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
	 * Abort remaining get_stack() calls once the request is this old (LiteSpeed).
	 */
	const REQUEST_BUDGET_MS = 5500;

	/**
	 * If WordPress boot already used this long, skip every get_stack().
	 * Production reactwoo.com spends ~6s before the handler runs.
	 */
	const LATE_BOOT_MS = 4000;

	/**
	 * Max time spent inside the per-widget stack loop.
	 */
	const STACK_BUDGET_MS = 400;

	/**
	 * @var float
	 */
	private static $boot_at = 0.0;

	/**
	 * @return void
	 */
	public static function init() {
		if ( 0.0 === self::$boot_at ) {
			self::$boot_at = microtime( true );
		}
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) && RWGC_Elementor_Config_Debug::is_elementor_ajax_request() ) {
			RWGC_Elementor_Config_Debug::boot();
		}
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_early_finish_widgets_config' ), -1 );
		add_action( 'wp_ajax_elementor_ajax', array( __CLASS__, 'maybe_early_finish_widgets_config' ), 0 );
		self::maybe_early_finish_widgets_config();
		add_action( 'elementor/ajax/register_actions', array( __CLASS__, 'register_actions' ), 20 );
		add_action( 'elementor/editor/before_enqueue_scripts', array( __CLASS__, 'enqueue_hydrate_script' ) );
		// Run before add-on catalogues (default priority 10) so they never eval/register.
		add_action( 'elementor/widgets/register', array( __CLASS__, 'unhook_heavy_addon_registrars' ), 0 );
		add_action( 'elementor/widgets/widgets_registered', array( __CLASS__, 'unhook_heavy_addon_registrars' ), 0 );
		add_action( 'elementor/controls/register', array( __CLASS__, 'unhook_heavy_addon_registrars' ), 0 );
		add_action( 'elementor/controls/controls_registered', array( __CLASS__, 'unhook_heavy_addon_registrars' ), 0 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'log_widgets_registered' ), 999 );
	}

	/**
	 * Return empty widgets-config before the rest of WordPress finishes booting.
	 *
	 * Production 1.8.145: get_widgets_config logs boot then dies with no handler
	 * and no shutdown. LiteSpeed kills the request during later plugin load /
	 * init. The empty map is enough for panel/state-ready.
	 *
	 * @return void
	 */
	public static function maybe_early_finish_widgets_config() {
		if ( ! class_exists( 'RWGC_Elementor_Ajax', false ) ) {
			return;
		}
		if ( ! self::should_avoid_widget_manager() ) {
			return;
		}
		$responses = RWGC_Elementor_Ajax::early_widgets_config_responses();
		if ( ! is_array( $responses ) ) {
			return;
		}
		if ( ! self::verify_elementor_ajax_nonce() ) {
			return;
		}
		$allow = true;
		if ( function_exists( 'apply_filters' ) ) {
			$allow = (bool) apply_filters( 'rwgc_elementor_early_finish_widgets_config', $allow );
		}
		if ( ! $allow ) {
			return;
		}

		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			RWGC_Elementor_Config_Debug::checkpoint(
				'ajax_early_exit',
				array(
					'keys' => implode( ',', array_keys( $responses ) ),
				)
			);
			RWGC_Elementor_Config_Debug::send_headers( 'slim-early' );
		} else {
			self::send_debug_header( 'slim-early' );
		}

		while ( function_exists( 'ob_get_status' ) && ob_get_status() ) {
			ob_end_clean();
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=UTF-8' );
		}
		$json = wp_json_encode(
			array(
				'success' => true,
				'data'    => array(
					'responses' => $responses,
				),
			)
		);
		echo is_string( $json ) ? $json : '{"success":true,"data":{"responses":{}}}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( function_exists( 'wp_die' ) ) {
			wp_die( '', '', array( 'response' => null ) );
		}
		exit;
	}

	/**
	 * @return bool
	 */
	private static function verify_elementor_ajax_nonce() {
		if ( ! function_exists( 'wp_verify_nonce' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce = isset( $_REQUEST['_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['_nonce'] ) ) : '';
		return ( '' !== $nonce && false !== wp_verify_nonce( $nonce, 'elementor_ajax' ) );
	}

	/**
	 * Remove Unlimited Elements / similar catalogue callbacks before they register widgets.
	 *
	 * UE `onWidgetsRegistered` preloads the addon DB and eval()s a class per widget. That
	 * runs on every widgets-config request and is enough for LiteSpeed to 503 even when
	 * Geo Core later skips get_stack().
	 *
	 * @return void
	 */
	public static function unhook_heavy_addon_registrars() {
		if ( ! class_exists( 'RWGC_Elementor_Ajax', false ) ) {
			return;
		}
		// Hydrate asks for one widget by name: its add-on must register to have a stack.
		if ( RWGC_Elementor_Ajax::is_widget_hydrate_ajax() || ! RWGC_Elementor_Ajax::is_heavy_elementor_ajax() ) {
			return;
		}

		$hook_name = function_exists( 'current_filter' ) ? current_filter() : '';
		if ( '' === $hook_name || ! isset( $GLOBALS['wp_filter'][ $hook_name ] ) ) {
			return;
		}

		$hook = $GLOBALS['wp_filter'][ $hook_name ];
		if ( ! is_object( $hook ) || empty( $hook->callbacks ) || ! is_array( $hook->callbacks ) ) {
			return;
		}

		$to_remove = array();
		$seen      = array();
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			if ( (int) $priority <= 0 ) {
				continue;
			}
			foreach ( $callbacks as $cb ) {
				if ( empty( $cb['function'] ) || ! is_array( $cb['function'] ) ) {
					continue;
				}
				$fn    = $cb['function'];
				$class = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
				$seen[] = $class;
				if ( self::is_heavy_addon_registrar( $class ) ) {
					$to_remove[] = array(
						'fn'       => $fn,
						'priority' => (int) $priority,
						'class'    => $class,
					);
				}
			}
		}

		$unhooked = array();
		foreach ( $to_remove as $item ) {
			remove_action( $hook_name, $item['fn'], $item['priority'] );
			$unhooked[] = $item['class'];
		}

		$left = array_values( array_diff( array_unique( $seen ), $unhooked ) );
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			RWGC_Elementor_Config_Debug::set_summary( 'unhooked', count( $unhooked ) );
			RWGC_Elementor_Config_Debug::set_summary( 'left', implode( ',', array_slice( self::short_class_list( $left ), 0, 12 ) ) );
			RWGC_Elementor_Config_Debug::checkpoint(
				'unhook',
				array(
					'hook'    => $hook_name,
					'unhook'  => implode( ',', array_slice( self::short_class_list( $unhooked ), 0, 8 ) ),
					'left'    => implode( ',', array_slice( self::short_class_list( $left ), 0, 12 ) ),
					'seen_n'  => count( $seen ),
				)
			);
		}
	}

	/**
	 * @param object $manager Widgets manager.
	 * @return void
	 */
	public static function log_widgets_registered( $manager ) {
		if ( ! class_exists( 'RWGC_Elementor_Config_Debug', false ) || ! RWGC_Elementor_Config_Debug::is_elementor_ajax_request() ) {
			return;
		}
		$count = 0;
		if ( is_object( $manager ) && method_exists( $manager, 'get_widget_types' ) ) {
			$types = $manager->get_widget_types();
			$count = is_array( $types ) ? count( $types ) : 0;
		}
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			RWGC_Elementor_Config_Debug::set_summary( 'registered', $count );
			RWGC_Elementor_Config_Debug::checkpoint( 'widgets_registered', array( 'count' => $count ) );
		}
	}

	/**
	 * @param list<string> $classes Class names.
	 * @return list<string>
	 */
	private static function short_class_list( array $classes ) {
		$out = array();
		foreach ( $classes as $class ) {
			$parts = explode( '\\', (string) $class );
			$out[] = end( $parts );
		}
		return $out;
	}

	/**
	 * @param string $class Fully-qualified class name.
	 * @return bool
	 */
	public static function is_heavy_addon_registrar( $class ) {
		$class = (string) $class;
		$needles = array(
			'UniteCreator',
			'UnlimitedElements',
			'Unlimited_Elements',
			'UCAddon_',
			'Essential_Addons',
			'EssentialAddons',
			'Jet_Engine',
			'Jet_Elements',
			'Jet_Woo',
			'PremiumAddons',
			'Premium_Addons',
			'ACPT_Elementor',
			'ACPT\\',
			'RW_WHMCS',
		);
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $class, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $note Slim / error marker for Network-tab confirmation.
	 * @return void
	 */
	private static function send_debug_header( $note ) {
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			RWGC_Elementor_Config_Debug::send_headers( $note );
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$ver = defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '0';
		header( 'X-RWGC-Widgets-Config: ' . $ver . '; ' . $note );
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
		$ajax->register_ajax_action( 'rwgc_get_widget_config', array( __CLASS__, 'ajax_get_single_widget_config' ) );
	}

	/**
	 * Load one widget stack when the editor inspector opens.
	 *
	 * @return void
	 */
	public static function enqueue_hydrate_script() {
		wp_enqueue_script(
			'rwgc-elementor-widget-hydrate',
			RWGC_URL . 'assets/js/rwgc-elementor-widget-hydrate.js',
			array( 'jquery', 'elementor-editor' ),
			defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '0',
			true
		);
	}

	/**
	 * Single-widget controls for the inspector. Fresh request — not the bulk 503 path.
	 *
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, mixed>
	 */
	public static function ajax_get_single_widget_config( array $data ) {
		$empty = array();
		try {
			if ( ! class_exists( '\Elementor\Plugin', false ) ) {
				return $empty;
			}
			$name = isset( $data['widget'] ) ? sanitize_key( (string) $data['widget'] ) : '';
			if ( '' === $name ) {
				return $empty;
			}

			$plugin = \Elementor\Plugin::$instance;
			$plugin->documents->check_permissions( $data['editor_post_id'] ?? 0 );

			$wanted = array( 'common', 'common-optimized', $name );
			$out    = array();
			$counts = array();
			foreach ( array_unique( $wanted ) as $widget_key ) {
				$widget = $plugin->widgets_manager->get_widget_types( $widget_key );
				if ( ! is_object( $widget ) ) {
					$counts[] = $widget_key . ':missing';
					continue;
				}
				$payload            = self::widget_controls_payload( $widget );
				$out[ $widget_key ] = $payload;
				$counts[]           = $widget_key . ':' . count( $payload['controls'] );
			}
			if ( empty( $out[ $name ]['controls'] ) ) {
				$out[ $name ] = self::stub_tabs_payload();
			}

			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint(
					'ajax_single_widget',
					array(
						'widget' => $name,
						'keys'   => implode( ',', array_keys( $out ) ),
						'counts' => implode( ',', $counts ),
					)
				);
			}
			self::send_debug_header( 'single' );
			return $out;
		} catch ( \Throwable $e ) {
			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint( 'ajax_error', array( 'msg' => substr( $e->getMessage(), 0, 120 ) ) );
			}
			return $empty;
		}
	}

	/**
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, mixed>
	 */
	public static function ajax_get_widgets_config( array $data ) {
		try {
			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint( 'ajax_get_widgets_config_start', array() );
			}
			if ( ! class_exists( '\Elementor\Plugin', false ) ) {
				self::send_debug_header( 'no-elementor' );
				return array();
			}

			$plugin = \Elementor\Plugin::$instance;
			$plugin->documents->check_permissions( $data['editor_post_id'] ?? 0 );

			if ( self::should_avoid_widget_manager() ) {
				return self::finish_empty_bulk( 'slim-empty', false );
			}

			$types = $plugin->widgets_manager->get_widget_types();
			$stats = self::empty_build_stats();
			if ( self::should_skip_all_stacks() ) {
				$config = self::empty_config_from_types( $types, $data, $stats );
				self::finish_build_stats( $stats, 'slim-late' );
				return $config;
			}

			$config = array();
			$n      = 0;
			foreach ( $types as $widget_key => $widget ) {
				++$n;
				if ( isset( $data['exclude'][ $widget_key ] ) ) {
					++$stats['excluded'];
					continue;
				}
				self::progress_checkpoint( $n, (string) $widget_key, $stats );
				if ( self::should_cut_stacks( $stats ) ) {
					$config[ $widget_key ] = self::empty_controls_payload();
					++$stats['cut'];
				} else {
					$config[ $widget_key ] = self::timed_widget_payload( $widget, $widget_key, $stats );
				}
			}
			self::finish_build_stats( $stats, $stats['cut'] > 0 ? 'slim-cut' : 'slim' );

			return $config;
		} catch ( \Exception $e ) {
			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint( 'ajax_exception', array( 'msg' => substr( $e->getMessage(), 0, 120 ) ) );
			}
			throw $e;
		} catch ( \Throwable $e ) {
			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint( 'ajax_error', array( 'msg' => substr( $e->getMessage(), 0, 120 ) ) );
			}
			self::send_debug_header( 'error' );
			return array();
		}
	}

	/**
	 * @param array<string, mixed> $data Request data.
	 * @return array<string, mixed>
	 */
	public static function ajax_refresh_widgets_config( array $data ) {
		$empty = array(
			'widgets'    => array(),
			'categories' => array(),
		);
		try {
			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint( 'ajax_refresh_widgets_config_start', array() );
			}
			if ( ! class_exists( '\Elementor\Plugin', false ) ) {
				self::send_debug_header( 'no-elementor' );
				return $empty;
			}

			$plugin = \Elementor\Plugin::$instance;
			$plugin->documents->check_permissions( $data['editor_post_id'] ?? 0 );

			if ( self::should_avoid_widget_manager() ) {
				return self::finish_empty_bulk( 'slim-empty', true );
			}

			$types = $plugin->widgets_manager->get_widget_types();
			$stats = self::empty_build_stats();
			if ( self::should_skip_all_stacks() ) {
				$empty_map = self::empty_config_from_types( $types, $data, $stats );
				self::finish_build_stats( $stats, 'slim-refresh-late' );
				return array(
					'widgets'    => $empty_map,
					'categories' => $plugin->elements_manager->get_categories(),
				);
			}

			$widgets = array();
			$n       = 0;
			foreach ( $types as $widget_key => $widget ) {
				++$n;
				self::progress_checkpoint( $n, (string) $widget_key, $stats );
				if ( self::should_cut_stacks( $stats ) ) {
					$payload = self::empty_controls_payload();
					++$stats['cut'];
				} else {
					$payload = self::timed_widget_payload( $widget, $widget_key, $stats );
				}
				if ( empty( $payload['controls'] ) ) {
					$widgets[ $widget_key ] = array(
						'controls'      => array(),
						'tabs_controls' => array(),
					);
					continue;
				}
				$widget_config                  = $widget->get_config();
				$widget_config['controls']      = $payload['controls'];
				$widget_config['tabs_controls'] = $payload['tabs_controls'];
				$widgets[ $widget_key ]         = $widget_config;
			}
			self::finish_build_stats( $stats, $stats['cut'] > 0 ? 'slim-refresh-cut' : 'slim-refresh' );

			return array(
				'widgets'    => $widgets,
				'categories' => $plugin->elements_manager->get_categories(),
			);
		} catch ( \Exception $e ) {
			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint( 'ajax_exception', array( 'msg' => substr( $e->getMessage(), 0, 120 ) ) );
			}
			throw $e;
		} catch ( \Throwable $e ) {
			if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
				RWGC_Elementor_Config_Debug::checkpoint( 'ajax_error', array( 'msg' => substr( $e->getMessage(), 0, 120 ) ) );
			}
			self::send_debug_header( 'error' );
			return $empty;
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_build_stats() {
		return array(
			'kept'       => 0,
			'skipped'    => 0,
			'ours'       => 0,
			'excluded'   => 0,
			'slowest'    => '',
			'slowest_ms' => 0,
			'ours_ms'    => 0,
			'cut'        => 0,
			'loop_start' => microtime( true ),
		);
	}

	/**
	 * @return array{controls: array<string, mixed>, tabs_controls: array<string, mixed>}
	 */
	/**
	 * Tab labels are plain strings (Controls_Manager::get_tabs), not objects.
	 *
	 * @return array{controls: array<string, mixed>, tabs_controls: array<string, string>}
	 */
	private static function stub_tabs_payload() {
		$tabs = array(
			'content'  => 'Content',
			'style'    => 'Style',
			'advanced' => 'Advanced',
			'layout'   => 'Layout',
		);
		if ( class_exists( '\Elementor\Controls_Manager', false ) ) {
			$registered = \Elementor\Controls_Manager::get_tabs();
			if ( is_array( $registered ) && ! empty( $registered ) ) {
				$tabs = $registered;
			}
		}
		return array(
			'controls'      => array(),
			'tabs_controls' => $tabs,
		);
	}

	/**
	 * @return array{controls: array<string, mixed>, tabs_controls: array<string, mixed>}
	 */
	private static function empty_controls_payload() {
		return array(
			'controls'      => array(),
			'tabs_controls' => array(),
		);
	}

	/**
	 * Do not call get_widget_types() on bulk widgets-config.
	 *
	 * That method fires every `elementor/widgets/register` callback. On
	 * production that is 112 leftover registrars and LiteSpeed 503s before
	 * late-skip can run.
	 *
	 * @return bool
	 */
	public static function should_avoid_widget_manager() {
		$avoid = true;
		if ( function_exists( 'apply_filters' ) ) {
			return (bool) apply_filters( 'rwgc_elementor_avoid_widget_manager', $avoid );
		}
		return $avoid;
	}

	/**
	 * @param string $note    Header note.
	 * @param bool   $refresh Refresh-widgets payload shape.
	 * @return array<string, mixed>
	 */
	private static function finish_empty_bulk( $note, $refresh ) {
		$stats = self::empty_build_stats();
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			RWGC_Elementor_Config_Debug::checkpoint( 'ajax_empty_return', array( 'note' => (string) $note ) );
		}
		self::finish_build_stats( $stats, (string) $note );
		if ( $refresh ) {
			return array(
				'widgets'    => array(),
				'categories' => array(),
			);
		}
		return array();
	}

	/**
	 * Production boot is already ~6s. Do not start get_stack() at all.
	 *
	 * @return bool
	 */
	public static function should_skip_all_stacks() {
		$late = self::LATE_BOOT_MS;
		if ( function_exists( 'apply_filters' ) ) {
			$late = (int) apply_filters( 'rwgc_elementor_widgets_config_late_boot_ms', $late );
		}
		return self::request_elapsed_ms() >= $late;
	}

	/**
	 * @return int
	 */
	public static function request_elapsed_ms() {
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			$ms = RWGC_Elementor_Config_Debug::elapsed_ms_public();
			if ( $ms > 0 ) {
				return $ms;
			}
		}
		if ( self::$boot_at > 0 ) {
			return (int) round( ( microtime( true ) - self::$boot_at ) * 1000 );
		}
		return 0;
	}

	/**
	 * @param array<string, object> $types Widget types.
	 * @param array<string, mixed>  $data  Request data.
	 * @param array<string, mixed>  $stats Running totals (by ref).
	 * @return array<string, array{controls: array<string, mixed>, tabs_controls: array<string, mixed>}>
	 */
	private static function empty_config_from_types( $types, array $data, array &$stats ) {
		$config = array();
		if ( ! is_array( $types ) ) {
			return $config;
		}
		foreach ( $types as $widget_key => $widget ) {
			if ( isset( $data['exclude'][ $widget_key ] ) ) {
				++$stats['excluded'];
				continue;
			}
			$config[ $widget_key ] = self::empty_controls_payload();
			++$stats['cut'];
		}
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			RWGC_Elementor_Config_Debug::checkpoint(
				'late_skip',
				array(
					'cut'   => (int) $stats['cut'],
					'types' => count( $types ),
				)
			);
		}
		return $config;
	}

	/**
	 * Stop calling get_stack() so LiteSpeed receives a finished JSON response.
	 *
	 * @param array<string, mixed> $stats Running totals.
	 * @return bool
	 */
	public static function should_cut_stacks( array $stats ) {
		$request_budget = self::REQUEST_BUDGET_MS;
		$stack_budget   = self::STACK_BUDGET_MS;
		if ( function_exists( 'apply_filters' ) ) {
			$request_budget = (int) apply_filters( 'rwgc_elementor_widgets_config_request_budget_ms', $request_budget );
			$stack_budget   = (int) apply_filters( 'rwgc_elementor_widgets_config_stack_budget_ms', $stack_budget );
		}

		$request_ms = self::request_elapsed_ms();
		$loop_ms    = (int) round( ( microtime( true ) - (float) $stats['loop_start'] ) * 1000 );

		return ( $request_ms >= $request_budget ) || ( $loop_ms >= $stack_budget );
	}

	/**
	 * @param int                  $n         Widgets visited.
	 * @param string               $widget_key Last widget name.
	 * @param array<string, mixed> $stats     Running totals.
	 * @return void
	 */
	private static function progress_checkpoint( $n, $widget_key, array $stats ) {
		if ( ! class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			return;
		}
		RWGC_Elementor_Config_Debug::checkpoint(
			'ajax_progress',
			array(
				'n'       => (int) $n,
				'last'    => (string) $widget_key,
				'kept'    => (int) $stats['kept'],
				'skipped' => (int) $stats['skipped'],
				'cut'     => (int) $stats['cut'],
			)
		);
	}

	/**
	 * @param object               $widget     Widget instance.
	 * @param string               $widget_key Widget name.
	 * @param array<string, mixed> $stats      Running totals (by ref).
	 * @return array{controls: array<string, mixed>, tabs_controls: array<string, mixed>}
	 */
	private static function timed_widget_payload( $widget, $widget_key, array &$stats ) {
		$class = is_object( $widget ) ? get_class( $widget ) : '';
		$ours  = class_exists( 'RWGC_Elementor_Config_Debug', false ) && RWGC_Elementor_Config_Debug::is_our_entry( $class );
		$start = microtime( true );
		$payload = self::widget_controls_payload( $widget );
		$elapsed = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( empty( $payload['controls'] ) ) {
			++$stats['skipped'];
		} else {
			++$stats['kept'];
		}
		if ( $ours ) {
			++$stats['ours'];
			$stats['ours_ms'] += $elapsed;
		}
		if ( $elapsed > (int) $stats['slowest_ms'] ) {
			$stats['slowest_ms'] = $elapsed;
			$stats['slowest']    = $widget_key . ':' . $elapsed;
		}

		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) && ( $ours || $elapsed >= 80 ) ) {
			RWGC_Elementor_Config_Debug::log(
				$ours ? 'our_widget' : 'slow_widget',
				array(
					'widget'   => (string) $widget_key,
					'class'    => $class,
					'ms'       => $elapsed,
					'controls' => is_array( $payload['controls'] ) ? count( $payload['controls'] ) : 0,
					'ours'     => $ours ? 1 : 0,
				)
			);
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $stats Build stats.
	 * @param string               $note  Header note.
	 * @return void
	 */
	private static function finish_build_stats( array $stats, $note ) {
		if ( class_exists( 'RWGC_Elementor_Config_Debug', false ) ) {
			RWGC_Elementor_Config_Debug::set_summary( 'kept', (int) $stats['kept'] );
			RWGC_Elementor_Config_Debug::set_summary( 'skipped', (int) $stats['skipped'] );
			RWGC_Elementor_Config_Debug::set_summary( 'ours', (int) $stats['ours'] );
			RWGC_Elementor_Config_Debug::set_summary( 'ours_ms', (int) $stats['ours_ms'] );
			RWGC_Elementor_Config_Debug::set_summary( 'excluded', (int) $stats['excluded'] );
			RWGC_Elementor_Config_Debug::set_summary( 'slowest', (string) $stats['slowest'] );
			RWGC_Elementor_Config_Debug::set_summary( 'cut', (int) $stats['cut'] );
			RWGC_Elementor_Config_Debug::checkpoint(
				'ajax_done',
				array(
					'kept'     => (int) $stats['kept'],
					'skipped'  => (int) $stats['skipped'],
					'ours'     => (int) $stats['ours'],
					'ours_ms'  => (int) $stats['ours_ms'],
					'slowest'  => (string) $stats['slowest'],
					'cut'      => (int) $stats['cut'],
				)
			);
		}
		self::send_debug_header( $note );
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

		$name    = (string) $widget->get_name();
		$class   = get_class( $widget );
		$hydrate = class_exists( 'RWGC_Elementor_Ajax', false ) && RWGC_Elementor_Ajax::is_widget_hydrate_ajax();

		if ( $hydrate ) {
			// Hydrate carries the inspector's own widget plus the shared common stacks.
			$wanted = RWGC_Elementor_Ajax::hydrate_widget_name();
			if ( $name !== $wanted && 'common' !== $name && 'common-optimized' !== $name ) {
				return $empty;
			}
		} elseif ( self::should_skip_full_stack( $name, $class ) ) {
			return $empty;
		}

		if ( ! method_exists( $widget, 'get_stack' ) ) {
			return $empty;
		}

		$stack = $widget->get_stack( false );
		$controls = ( isset( $stack['controls'] ) && is_array( $stack['controls'] ) ) ? $stack['controls'] : array();

		return array(
			'controls'      => $hydrate ? $controls : self::slim_controls( $controls ),
			'tabs_controls' => method_exists( $widget, 'get_tabs_controls' ) ? $widget->get_tabs_controls() : array(),
		);
	}

	/**
	 * Skip get_stack() for add-on catalogues and our own bulk-path widgets.
	 * Keep Elementor core / Pro / Geo / Social / Reviews. Atomic and WHMCS
	 * stay listed in the panel with empty stacks (Elementor 4.2 has no hydrate).
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
			'RWGC_',
			'RWSC_',
			'GRP_',
		);
		$skip_kept_prefixes = array(
			'ElementorPro\\Modules\\Woocommerce\\',
			'ElementorPro\\Modules\\LoopBuilder\\',
			'ElementorPro\\Modules\\MegaMenu\\',
		);
		foreach ( $skip_kept_prefixes as $prefix ) {
			if ( 0 === strpos( $widget_class, $prefix ) ) {
				return true;
			}
		}

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
