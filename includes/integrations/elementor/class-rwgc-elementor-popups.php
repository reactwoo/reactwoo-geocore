<?php
/**
 * Elementor Pro popup geo targeting (legacy Geo Elementor data + geo_rule CPT).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend popup visibility for egp_* page settings and geo_rule posts (target_type popup).
 */
class RWGC_Elementor_Popups {

	const META_PREFIX = 'egp_';

	/**
	 * Bytes of popup HTML emitted by RWGC force-print this request.
	 *
	 * @var array<int, int>
	 */
	private static $force_emitted_popup_bytes = array();

	/**
	 * Per-request memoization caches. Popup targeting is queried from many hooks
	 * (wp_head, wp_enqueue_scripts, before/after_do_popup, several wp_footer
	 * priorities); without caching each call re-runs unbounded get_posts() queries
	 * and re-loads/renders Elementor popup documents, which spams the debug log and
	 * can exhaust PHP memory on heavy pages.
	 *
	 * @var array<int, bool|null>
	 */
	private static $decision_cache = array();

	/**
	 * @var array<int, array<string, mixed>|null>
	 */
	private static $settings_cache = array();

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static $triggers_cache = array();

	/**
	 * @var array<int, array<string, mixed>>|null
	 */
	private static $config_map_cache = null;

	/**
	 * @var array<int|string, array<string, mixed>>|null
	 */
	private static $page_settings_map_cache = null;

	/**
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			return;
		}
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return;
		}

		add_filter( 'elementor_pro/popup/should_show', array( __CLASS__, 'filter_popup_should_show' ), 5, 2 );
		add_filter( 'elementor/document/wrapper_attributes', array( __CLASS__, 'filter_popup_wrapper_attributes' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'ensure_allowed_popups_in_location' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_allowed_popup_assets' ), 15 );
		add_action( 'wp_head', array( __CLASS__, 'print_popup_antiflash_head' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'print_popup_show_patch_script' ), 2 );
		add_action( 'elementor/theme/before_do_popup', array( __CLASS__, 'ensure_allowed_popups_in_location' ), 0 );
		add_action( 'elementor/theme/after_do_popup', array( __CLASS__, 'force_print_missing_allowed_popups' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'ensure_allowed_popups_in_location' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'prepare_allowed_popups_before_elementor_print' ), 9 );
		add_action( 'wp_footer', array( __CLASS__, 'force_print_missing_allowed_popups' ), 11 );
		add_action( 'wp_footer', array( __CLASS__, 'force_print_missing_allowed_popups' ), 999 );
		add_action( 'wp_footer', array( __CLASS__, 'print_popup_show_patch_script' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'print_popup_dom_guard_script' ), 99 );
	}

	/**
	 * Ensure geo-allowed popups are enqueued before Elementor prints the popup location.
	 *
	 * Variant URLs and some theme-builder conditions omit popups from the location list,
	 * which strips page-load triggers and leaves no modal markup for showPopup().
	 *
	 * @return void
	 */
	public static function ensure_allowed_popups_in_location() {
		static $logged = false;

		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		if ( ! class_exists( '\ElementorPro\Modules\Popup\Module', false ) ) {
			return;
		}

		$allowed = self::get_allowed_popup_ids_for_request();
		if ( empty( $allowed ) ) {
			return;
		}

		self::merge_allowed_popups_into_condition_cache( $allowed );

		foreach ( $allowed as $popup_id ) {
			\ElementorPro\Modules\Popup\Module::add_popup_to_location( $popup_id );
		}

		if ( ! $logged && self::is_debug_enabled() && function_exists( 'error_log' ) ) {
			$logged = true;
			error_log( 'RWGC Popup Location Inject ' . wp_json_encode( array( 'popup_ids' => $allowed ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Enqueue popup CSS/assets for geo-allowed templates so forced prints carry styles and triggers.
	 *
	 * @return void
	 */
	public static function enqueue_allowed_popup_assets() {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		if ( ! class_exists( '\Elementor\Plugin', false ) || ! class_exists( '\ElementorPro\Modules\Popup\Module', false ) ) {
			return;
		}

		$allowed = self::get_allowed_popup_ids_for_request();
		if ( empty( $allowed ) ) {
			return;
		}

		self::merge_allowed_popups_into_condition_cache( $allowed );

		foreach ( $allowed as $popup_id ) {
			$popup_id = (int) $popup_id;
			if ( $popup_id <= 0 ) {
				continue;
			}

			\ElementorPro\Modules\Popup\Module::add_popup_to_location( $popup_id );

			if ( class_exists( '\Elementor\Core\Files\CSS\Post', false ) ) {
				$css = \Elementor\Core\Files\CSS\Post::create( $popup_id );
				if ( $css && method_exists( $css, 'enqueue' ) ) {
					$css->enqueue();
				}
			}

			wp_enqueue_style( 'e-popup' );
		}
	}

	/**
	 * Final merge before Elementor prints popups at wp_footer:10.
	 *
	 * @return void
	 */
	public static function prepare_allowed_popups_before_elementor_print() {
		self::ensure_allowed_popups_in_location();
	}

	/**
	 * Ensure geo-allowed popup wrappers include location class and page-load triggers.
	 *
	 * @param array<string, mixed> $attributes Wrapper attributes.
	 * @param object               $document   Elementor document.
	 * @return array<string, mixed>
	 */
	public static function filter_popup_wrapper_attributes( $attributes, $document ) {
		if ( ! is_array( $attributes ) || ! $document || ! method_exists( $document, 'get_name' ) || 'popup' !== $document->get_name() ) {
			return $attributes;
		}

		$popup_id = method_exists( $document, 'get_main_id' ) ? (int) $document->get_main_id() : 0;
		if ( $popup_id <= 0 || true !== self::popup_should_display( $popup_id ) ) {
			return $attributes;
		}

		if ( isset( $attributes['class'] ) && false === strpos( (string) $attributes['class'], 'elementor-location-popup' ) ) {
			$attributes['class'] .= ' elementor-location-popup';
		}

		$settings = array();
		if ( ! empty( $attributes['data-elementor-settings'] ) ) {
			$decoded = json_decode( (string) $attributes['data-elementor-settings'], true );
			if ( is_array( $decoded ) ) {
				$settings = $decoded;
			}
		}

		if ( empty( $settings['triggers'] ) && method_exists( $document, 'get_display_settings' ) ) {
			$display = $document->get_display_settings();
			if ( is_array( $display ) && ! empty( $display['triggers'] ) && is_object( $display['triggers'] ) && method_exists( $display['triggers'], 'get_frontend_settings' ) ) {
				$settings['triggers'] = $display['triggers']->get_frontend_settings();
			}
		}

		if ( ! empty( $settings ) ) {
			$attributes['data-elementor-settings'] = wp_json_encode( $settings );
		}

		return $attributes;
	}

	/**
	 * Print geo-allowed popup documents when Elementor theme conditions did not output them.
	 *
	 * @return void
	 */
	public static function force_print_missing_allowed_popups() {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\ElementorPro\Plugin', false ) ) {
			return;
		}

		$theme_builder = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
		if ( ! $theme_builder || ! method_exists( $theme_builder, 'get_document' ) ) {
			return;
		}

		$locations_manager = $theme_builder->get_locations_manager();
		$allowed           = self::get_allowed_popup_ids_for_request();
		if ( empty( $allowed ) ) {
			return;
		}

		self::merge_allowed_popups_into_condition_cache( $allowed );

		$forced = array();
		$trace  = array();
		foreach ( $allowed as $popup_id ) {
			$popup_id = (int) $popup_id;
			if ( $popup_id <= 0 ) {
				continue;
			}

			if ( ! empty( self::$force_emitted_popup_bytes[ $popup_id ] ) ) {
				$trace[] = array(
					'popup_id' => $popup_id,
					'action'   => 'skip_already_emitted',
					'bytes'    => (int) self::$force_emitted_popup_bytes[ $popup_id ],
				);
				continue;
			}

			$elementor_marked_printed = $locations_manager->is_printed( 'popup', $popup_id );
			$document                 = self::resolve_popup_document( $theme_builder, $popup_id );
			if ( ! $document || ! method_exists( $document, 'print_content' ) ) {
				$trace[] = array(
					'popup_id' => $popup_id,
					'action'   => 'skip_no_document',
				);
				continue;
			}

			if ( 'publish' !== get_post_status( $popup_id ) ) {
				$trace[] = array(
					'popup_id' => $popup_id,
					'action'   => 'skip_not_publish',
				);
				continue;
			}

			\ElementorPro\Modules\Popup\Module::add_popup_to_location( $popup_id );

			if ( class_exists( '\Elementor\Plugin', false ) ) {
				\Elementor\Plugin::$instance->frontend->enqueue_styles();
			}

			if ( class_exists( '\Elementor\Core\Files\CSS\Post', false ) ) {
				$css = \Elementor\Core\Files\CSS\Post::create( $popup_id );
				if ( $css && method_exists( $css, 'enqueue' ) ) {
					$css->enqueue();
				}
			}

			wp_enqueue_style( 'e-popup' );

			$has_triggers = self::popup_has_page_load_trigger( $popup_id );

			ob_start();
			self::with_popup_location_context(
				$locations_manager,
				static function () use ( $document ) {
					$document->print_content();
				}
			);
			$html  = (string) ob_get_clean();
			$bytes = strlen( trim( $html ) );

			if ( $bytes > 0 ) {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor document output.
				self::$force_emitted_popup_bytes[ $popup_id ] = $bytes;
				if ( ! $elementor_marked_printed ) {
					$locations_manager->set_is_printed( 'popup', $popup_id );
				}
				$forced[] = $popup_id;
				$trace[]  = array(
					'popup_id'                   => $popup_id,
					'action'                     => 'forced',
					'bytes'                      => $bytes,
					'elementor_marked_printed'   => $elementor_marked_printed,
					'page_load_trigger'          => $has_triggers,
					'has_elementor_root'         => false !== strpos( $html, 'data-elementor-type="popup"' ),
				);
			} else {
				$trace[] = array(
					'popup_id'                 => $popup_id,
					'action'                   => 'empty_print_content',
					'elementor_marked_printed' => $elementor_marked_printed,
				);
			}
		}

		if ( self::is_debug_enabled() && function_exists( 'error_log' ) ) {
			if ( ! empty( $forced ) ) {
				error_log( 'RWGC Popup Force Print ' . wp_json_encode( array( 'popup_ids' => $forced ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			error_log( 'RWGC Popup Force Print Trace ' . wp_json_encode( $trace ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Run a callback while Elementor's locations manager thinks the popup location is active.
	 *
	 * @param object   $locations_manager Elementor locations manager.
	 * @param callable $callback          Callback to run.
	 * @return mixed
	 */
	private static function with_popup_location_context( $locations_manager, $callback ) {
		if ( ! is_callable( $callback ) ) {
			return null;
		}

		$previous = null;
		$changed  = false;

		try {
			$ref  = new \ReflectionClass( $locations_manager );
			$prop = $ref->getProperty( 'current_location' );
			$prop->setAccessible( true );
			$previous = $prop->getValue( $locations_manager );
			$prop->setValue( $locations_manager, 'popup' );
			$changed = true;
		} catch ( \ReflectionException $e ) {
			unset( $e );
		}

		try {
			return call_user_func( $callback );
		} finally {
			if ( $changed ) {
				try {
					$ref  = new \ReflectionClass( $locations_manager );
					$prop = $ref->getProperty( 'current_location' );
					$prop->setAccessible( true );
					$prop->setValue( $locations_manager, $previous );
				} catch ( \ReflectionException $e ) {
					unset( $e );
				}
			}
		}
	}

	/**
	 * Merge geo-allowed popups into Elementor's popup location cache so triggers survive get_frontend_settings().
	 *
	 * @param int[] $allowed Popup template IDs.
	 * @return void
	 */
	private static function merge_allowed_popups_into_condition_cache( array $allowed ) {
		if ( empty( $allowed ) || ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module', false ) ) {
			return;
		}

		$theme_builder = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		$conditions    = $theme_builder->get_conditions_manager();
		if ( ! $conditions || ! method_exists( $conditions, 'get_documents_for_location' ) ) {
			return;
		}

		$documents = $conditions->get_documents_for_location( 'popup' );
		if ( ! is_array( $documents ) ) {
			$documents = array();
		}

		$merged = false;
		foreach ( $allowed as $popup_id ) {
			$popup_id = (int) $popup_id;
			if ( $popup_id <= 0 || isset( $documents[ $popup_id ] ) ) {
				continue;
			}

			$document = self::resolve_popup_document( $theme_builder, $popup_id );
			if ( ! $document ) {
				continue;
			}

			$documents[ $popup_id ] = $document;
			$merged                 = true;
		}

		if ( ! $merged ) {
			return;
		}

		try {
			$ref  = new \ReflectionClass( $conditions );
			$prop = $ref->getProperty( 'location_cache' );
			$prop->setAccessible( true );
			$cache           = $prop->getValue( $conditions );
			$cache['popup']  = $documents;
			$prop->setValue( $conditions, $cache );
		} catch ( \ReflectionException $e ) {
			unset( $e );
		}
	}

	/**
	 * @param object $theme_builder Elementor theme builder module.
	 * @param int    $popup_id Popup template ID.
	 * @return object|null
	 */
	private static function resolve_popup_document( $theme_builder, $popup_id ) {
		$document = null;
		if ( $theme_builder && method_exists( $theme_builder, 'get_document' ) ) {
			$document = $theme_builder->get_document( $popup_id );
		}

		if ( ( ! $document || ! method_exists( $document, 'print_content' ) ) && class_exists( '\Elementor\Plugin', false ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $popup_id );
		}

		return ( $document && method_exists( $document, 'print_content' ) ) ? $document : null;
	}

	/**
	 * Popup template IDs geo targeting allows on this request.
	 *
	 * @return int[]
	 */
	private static function get_allowed_popup_ids_for_request() {
		$config_map = self::build_popup_config_map();
		if ( empty( $config_map ) ) {
			return array();
		}

		$allowed = array();
		foreach ( array_keys( $config_map ) as $popup_id ) {
			$popup_id = (int) $popup_id;
			if ( $popup_id > 0 && true === self::popup_should_display( $popup_id ) ) {
				$allowed[] = $popup_id;
			}
		}

		return $allowed;
	}

	/**
	 * @param bool  $should_show Current decision.
	 * @param mixed $popup Popup instance or ID.
	 * @return bool
	 */
	public static function filter_popup_should_show( $should_show, $popup ) {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return (bool) $should_show;
		}

		$popup_id = self::resolve_popup_id( $popup );
		if ( ! $popup_id ) {
			return (bool) $should_show;
		}

		$decision = self::popup_should_display( $popup_id );
		if ( null !== $decision ) {
			return $decision;
		}

		return (bool) $should_show;
	}

	/**
	 * Skip geo-blocked popup templates when Elementor resolves the popup location.
	 *
	 * @param int    $theme_template_id Popup template ID.
	 * @param string $location          Theme location slug.
	 * @return int
	 */
	public static function filter_popup_theme_template_id( $theme_template_id, $location ) {
		if ( 'popup' !== $location ) {
			return (int) $theme_template_id;
		}

		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return (int) $theme_template_id;
		}

		$popup_id = absint( $theme_template_id );
		if ( $popup_id <= 0 ) {
			return (int) $theme_template_id;
		}

		if ( false === self::popup_should_display( $popup_id ) ) {
			return 0;
		}

		return $popup_id;
	}

	/**
	 * @param int $popup_id Popup template ID.
	 * @return bool|null True = show, false = hide, null = no geo targeting configured.
	 */
	public static function popup_should_display( $popup_id ) {
		$popup_id = absint( $popup_id );
		if ( ! $popup_id ) {
			return null;
		}

		if ( array_key_exists( $popup_id, self::$decision_cache ) ) {
			return self::$decision_cache[ $popup_id ];
		}

		self::$decision_cache[ $popup_id ] = self::evaluate_popup_should_display( $popup_id );

		return self::$decision_cache[ $popup_id ];
	}

	/**
	 * Resolve a popup visibility decision (uncached). Use {@see popup_should_display()}.
	 *
	 * @param int $popup_id Popup template ID.
	 * @return bool|null True = show, false = hide, null = no geo targeting configured.
	 */
	private static function evaluate_popup_should_display( $popup_id ) {
		$settings = self::get_popup_targeting_settings( $popup_id );
		if ( null === $settings || ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			return null;
		}

		if ( ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
			return null;
		}

		$visitor = function_exists( 'rwgc_get_visitor_data' ) ? rwgc_get_visitor_data() : array();
		$cc      = isset( $visitor['country_code'] ) ? strtoupper( (string) $visitor['country_code'] ) : strtoupper( (string) rwgc_get_visitor_country() );

		if ( '' === $cc && 'country_list' === RWGC_Targeting_Surface_Evaluator::get_primary_evaluation_reason( $settings ) ) {
			$fallback_decision = self::resolve_unknown_country_fallback(
				isset( $settings['egp_fallback_behavior'] ) ? (string) $settings['egp_fallback_behavior'] : 'inherit'
			);
			self::debug_log_popup_decision( $popup_id, $settings, array(
				'rules_match'   => false,
				'should_render' => $fallback_decision,
				'reason'        => 'unknown_country_fallback',
			) );
			return $fallback_decision;
		}

		$result  = RWGC_Targeting_Surface_Evaluator::evaluate( $settings );
		$decision = (bool) $result['should_render'];

		self::debug_log_popup_decision( $popup_id, $settings, $result );

		return $decision;
	}

	/**
	 * Hide popups that will not display before Elementor opens them (reduces flash).
	 *
	 * @return void
	 */
	public static function print_popup_antiflash_head() {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		$blocked = self::get_blocked_popup_ids_for_request();
		if ( empty( $blocked ) ) {
			return;
		}

		$css = '';
		foreach ( $blocked as $popup_id ) {
			$id = absint( $popup_id );
			if ( $id <= 0 ) {
				continue;
			}
			$css .= '.elementor-popup-modal[data-elementor-id="' . $id . '"],'
				. '#elementor-popup-modal-' . $id
				. '{display:none!important;visibility:hidden!important;opacity:0!important;}';
		}

		if ( '' !== $css ) {
			echo '<style id="rwgc-popup-antiflash">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		wp_print_inline_script_tag(
			'window.rwgcPopupGeoBlocked=' . wp_json_encode( array_values( $blocked ) ) . ';'
		);
	}

	/**
	 * Popup IDs that should not display on this request.
	 *
	 * @return int[]
	 */
	private static function get_blocked_popup_ids_for_request() {
		$blocked = array();
		foreach ( array_keys( self::build_popup_config_map() ) as $popup_id ) {
			$popup_id = (int) $popup_id;
			if ( $popup_id <= 0 ) {
				continue;
			}
			if ( false === self::popup_should_display( $popup_id ) ) {
				$blocked[] = $popup_id;
			}
		}
		return $blocked;
	}

	/**
	 * @param array<string, mixed> $page_settings Elementor page settings.
	 * @return bool
	 */
	private static function page_settings_country_enabled( array $page_settings ) {
		return ( ! empty( $page_settings['egp_enable_geo_targeting'] ) && 'yes' === (string) $page_settings['egp_enable_geo_targeting'] )
			|| ( ! empty( $page_settings['egp_geo_enabled'] ) && 'yes' === (string) $page_settings['egp_geo_enabled'] );
	}

	/**
	 * @param array<string, mixed> $page_settings Elementor page settings.
	 * @return bool
	 */
	private static function page_settings_visibility_enabled( array $page_settings ) {
		if ( ! empty( $page_settings['rwgc_enable_visibility_rules'] ) && 'yes' === (string) $page_settings['rwgc_enable_visibility_rules'] ) {
			return true;
		}
		if ( ! empty( $page_settings['rwgc_use_portable_geo_targeting'] ) && 'yes' === (string) $page_settings['rwgc_use_portable_geo_targeting'] ) {
			return true;
		}
		if ( ! empty( $page_settings['egp_use_portable_geo_targeting'] ) && 'yes' === (string) $page_settings['egp_use_portable_geo_targeting'] ) {
			return true;
		}
		if ( ! empty( $page_settings['rwgc_applied_visibility_rule_id'] ) ) {
			return true;
		}
		if ( ! empty( $page_settings['rwgc_visibility_rule_library'] ) ) {
			return true;
		}
		$raw = '';
		if ( ! empty( $page_settings['rwgc_portable_geo_targeting'] ) ) {
			$raw = (string) $page_settings['rwgc_portable_geo_targeting'];
		} elseif ( ! empty( $page_settings['egp_portable_geo_targeting'] ) ) {
			$raw = (string) $page_settings['egp_portable_geo_targeting'];
		}
		return '' !== trim( $raw );
	}

	/**
	 * @param array<string, mixed> $page_settings Elementor page settings.
	 * @return bool
	 */
	private static function page_settings_have_targeting( array $page_settings ) {
		return self::page_settings_country_enabled( $page_settings ) || self::page_settings_visibility_enabled( $page_settings );
	}

	/**
	 * @return void
	 */
	public static function print_popup_show_patch_script() {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		$config_map = self::build_popup_config_map();
		if ( empty( $config_map ) ) {
			if ( self::is_debug_enabled() && function_exists( 'error_log' ) ) {
				error_log( 'RWGC Popup Config Trace ' . wp_json_encode( array( 'detected_popups' => 0, 'note' => 'No Elementor popups have geo targeting page settings or active geo_rule popup rows.' ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return;
		}

		$visitor = strtoupper( (string) rwgc_get_visitor_country() );
		$blocked = array();
		$trace   = array();

		$variant_active = class_exists( 'RWGC_Page_Version_Routing', false )
			&& RWGC_Page_Version_Routing::is_page_version_request();

		foreach ( $config_map as $popup_id => $config ) {
			$popup_id = (int) $popup_id;
			$decision = self::popup_should_display( $popup_id );
			$show     = null === $decision ? true : (bool) $decision;
			$config_map[ $popup_id ]['rwgc_show']             = $show;
			$config_map[ $popup_id ]['page_load_fallback']    = $show && $variant_active && self::popup_has_page_load_trigger( $popup_id );
			$config_map[ $popup_id ]['page_load_delay_ms']    = (int) round( self::get_page_load_delay_seconds( $popup_id ) * 1000 );
			if ( false === $decision ) {
				$blocked[] = $popup_id;
			}
			$trace[] = array(
				'popup_id'            => $popup_id,
				'decision'            => null === $decision ? 'null(show)' : ( $decision ? 'show' : 'block' ),
				'rwgc_show'           => $config_map[ $popup_id ]['rwgc_show'],
				'page_load_fallback'  => $config_map[ $popup_id ]['page_load_fallback'],
			);
		}

		if ( self::is_debug_enabled() && function_exists( 'error_log' ) ) {
			error_log( 'RWGC Popup Config Trace ' . wp_json_encode( array( 'visitor_country' => $visitor, 'detected_popups' => count( $config_map ), 'decisions' => $trace ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		$printed = true;

		wp_print_inline_script_tag(
			self::build_popup_runtime_script( $config_map, $visitor, $blocked )
		);
	}

	/**
	 * Late DOM guard: hide popup modals that should not display.
	 *
	 * @return void
	 */
	public static function print_popup_dom_guard_script() {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		$config_map = self::build_popup_config_map();
		if ( empty( $config_map ) ) {
			return;
		}

		$blocked = array();
		foreach ( $config_map as $popup_id => $config ) {
			if ( false === self::popup_should_display( (int) $popup_id ) ) {
				$blocked[] = (int) $popup_id;
			}
		}

		if ( empty( $blocked ) ) {
			return;
		}

		$blocked_json = wp_json_encode( $blocked );
		wp_print_inline_script_tag(
			"(function(){\n"
			. 'var blocked=' . $blocked_json . ";\n"
			. "function hidePopups(){\n"
			. "  blocked.forEach(function(id){\n"
			. "    var sel = '.elementor-popup-modal[data-elementor-id=\"' + id + '\"]' +\n"
			. "      ',#elementor-popup-modal-' + id +\n"
			. "      ',.elementor-popup-modal[data-elementor-id=\"' + id + '\"]';\n"
			. "    document.querySelectorAll(sel).forEach(function(el){\n"
			. "      el.style.display = 'none';\n"
			. "      el.setAttribute('aria-hidden','true');\n"
			. "    });\n"
			. "  });\n"
			. "}\n"
			. "if(document.readyState === 'loading'){\n"
			. "  document.addEventListener('DOMContentLoaded', hidePopups);\n"
			. "}else{\n"
			. "  hidePopups();\n"
			. "}\n"
			. "var tick=0;var iv=setInterval(function(){hidePopups();tick++;if(tick>12){clearInterval(iv);}},100);\n"
			. "})();"
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_popup_config_map() {
		if ( null !== self::$config_map_cache ) {
			return self::$config_map_cache;
		}

		$map = self::collect_popup_page_settings_map();
		if ( ! post_type_exists( 'geo_rule' ) ) {
			self::$config_map_cache = $map;
			return $map;
		}

		$rules = get_posts(
			array(
				'post_type'      => 'geo_rule',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_PREFIX . 'target_type',
						'value' => 'popup',
					),
					array(
						'key'   => self::META_PREFIX . 'active',
						'value' => '1',
					),
				),
			)
		);

		foreach ( $rules as $rule ) {
			if ( ! ( $rule instanceof WP_Post ) ) {
				continue;
			}
			$popup_id = absint( get_post_meta( $rule->ID, self::META_PREFIX . 'target_id', true ) );
			if ( $popup_id <= 0 ) {
				continue;
			}

			$countries = get_post_meta( $rule->ID, self::META_PREFIX . 'countries', true );
			$mode       = 'show_if';

			$raw_portable = get_post_meta( $rule->ID, self::META_PREFIX . 'portable_targeting', true );
			if ( is_string( $raw_portable ) && '' !== trim( $raw_portable )
				&& class_exists( 'RWGC_Targeting_Rule_Set_Schema', false )
				&& class_exists( 'RWGC_Rule_Evaluator', false )
				&& class_exists( 'RWGC_Context_Resolver', false ) ) {
				$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw_portable );
				if ( is_array( $set ) && ! empty( $set['mode'] ) ) {
					$mode = function_exists( 'rwgc_normalize_visibility_mode' )
						? rwgc_normalize_visibility_mode( $set['mode'] )
						: sanitize_key( (string) $set['mode'] );
				}
			}

			if ( ! isset( $map[ $popup_id ] ) ) {
				$map[ $popup_id ] = array(
					'countries'        => self::normalize_country_list( $countries ),
					'visibility_mode' => $mode,
					'source'            => 'geo_rule',
				);
			} else {
				$map[ $popup_id ]['countries']        = array_values( array_unique( array_merge( $map[ $popup_id ]['countries'], self::normalize_country_list( $countries ) ) ) );
				$map[ $popup_id ]['visibility_mode'] = $mode;
			}
		}

		self::$config_map_cache = $map;

		return $map;
	}

	/**
	 * @param array<int|string, array<string, mixed>> $map Popup config map.
	 * @param string $visitor Visitor country.
	 * @param int[] $blocked Popup IDs to force-hide.
	 * @return string
	 */
	private static function build_popup_runtime_script( $map, $visitor, $blocked ) {
		$popup_data   = wp_json_encode( $map );
		$blocked_data = wp_json_encode( array_values( $blocked ) );
		$variant_data = wp_json_encode( self::get_variant_route_context_for_script() );
		$debug_flag   = self::is_debug_enabled() ? 'true' : 'false';

		$template = <<<'JS'
(function(){
var userCountry=__USER_COUNTRY__;
var popupData=__POPUP_DATA__;
var blocked=__BLOCKED__;
var variantCtx=__VARIANT_CTX__;
var rwgcPopupDebug=__DEBUG_FLAG__;
var suppressUntil={};var suppressMs=4000;var fallbackDone={};var fallbackStarted={};
function dbg(){if(!rwgcPopupDebug||!window.console){return;}try{console.log.apply(console,['[RWGC Popup]'].concat([].slice.call(arguments)));}catch(e){}}
function meta(pid){if(pid==null){return null;}var k=String(pid);return popupData[k]||popupData[pid]||null;}
function norm(v){if(v==null){return null;}if(typeof v==='number'){return v;}if(typeof v==='string'){var n=parseInt(v,10);return isNaN(n)?v:n;}if(typeof v==='object'){if(v.id!=null){return norm(v.id);}if(v.popupId!=null){return norm(v.popupId);}if(v.popup_id!=null){return norm(v.popup_id);}if(v.detail&&v.detail.id!=null){return norm(v.detail.id);}if(v.popup&&v.popup.id!=null){return norm(v.popup.id);}if(typeof v.getSettings==='function'){var sid=v.getSettings('id');if(sid!=null){return norm(sid);}}}return v;}
function isBlockedList(pid){if(!pid){return false;}var k=String(pid);return blocked.indexOf(parseInt(k,10))!==-1||blocked.indexOf(k)!==-1;}
function shouldBlockOpen(pid){if(!pid){return false;}if(isBlockedList(pid)){return true;}var m=meta(pid);if(m&&m.rwgc_show===false){return true;}if(m&&m.rwgc_show===true){return false;}return false;}
function isSuppressingReopen(pid){if(!pid){return false;}var m=meta(pid);if(m&&m.rwgc_show===true){return false;}var until=suppressUntil[String(pid)];return until&&Date.now()<until;}
function suppressReopenBriefly(pid){if(!pid){return;}suppressUntil[String(pid)]=Date.now()+suppressMs;}
function getPopupDoc(pid){var docs=getPopupDocuments();if(!docs||!pid){return null;}return docs[pid]||docs[String(pid)]||null;}
function resolvePopupIdFromArgs(args){if(!args||!args.length){return null;}var i,pid;for(i=0;i<args.length;i++){pid=norm(args[i]);if(pid){return pid;}}return null;}
function popupIdFromModal(modal){if(!modal){return null;}var id=modal.getAttribute('data-elementor-id');if(id!=null&&id!==''){return norm(id);}if(modal.id&&modal.id.indexOf('elementor-popup-modal-')===0){return norm(modal.id.slice('elementor-popup-modal-'.length));}var inner=modal.querySelector('[data-elementor-type="popup"]');if(inner){var innerId=inner.getAttribute('data-elementor-id');if(innerId!=null&&innerId!==''){return norm(innerId);}}return null;}
function getPopupDocuments(){var mgr=window.elementorFrontend&&elementorFrontend.documentsManager;return mgr&&mgr.documents?mgr.documents:null;}
function resolveDocId(doc,docKey){var pid=norm(docKey);if(doc&&typeof doc.getSettings==='function'){var sid=doc.getSettings('id');if(sid!=null){pid=norm(sid);}}return pid;}
function patchSingleDocument(doc,docKey){if(!doc){return false;}var docId=resolveDocId(doc,docKey);if(typeof doc.showModal!=='function'||doc.__rwgcShowModalPatch){return false;}var os=doc.showModal;doc.showModal=function(){if(docId&&shouldBlockOpen(docId)){return;}if(docId&&isSuppressingReopen(docId)){return;}return os.apply(doc,arguments);};doc.__rwgcShowModalPatch=1;return true;}
function forceClosePopup(pid){pid=norm(pid);if(!pid){return;}suppressReopenBriefly(pid);try{var doc=getPopupDoc(pid);if(doc&&typeof doc.getModal==='function'){doc.getModal().hide();return;}}catch(e){}try{var modals=document.querySelectorAll('.elementor-popup-modal');for(var i=0;i<modals.length;i++){var mid=popupIdFromModal(modals[i]);if(mid&&String(mid)===String(pid)){var m=modals[i];m.style.display='none';m.setAttribute('aria-hidden','true');}}}catch(e2){}}
function resolveClosePopupId(args){if(!args||!args.length){return null;}var pid=resolvePopupIdFromArgs(args);if(pid&&meta(pid)){return pid;}if(args.length>1&&window.jQuery){var evt=args[1];if(evt&&evt.target){var el=jQuery(evt.target).parents('[data-elementor-type="popup"]').data('elementorId');if(el!=null){return norm(el);}}}return pid;}
function applyOpen(orig,scope,args){var pid=resolvePopupIdFromArgs(args);if(pid&&shouldBlockOpen(pid)){return false;}return orig.apply(scope,args);}
function wrapClose(orig,scope,args){var pid=resolveClosePopupId(args);if(pid){suppressReopenBriefly(pid);}return orig.apply(scope,args);}
function patchPopupDocuments(){var docs=getPopupDocuments();if(!docs){return false;}var patched=false;Object.keys(docs).forEach(function(docKey){if(patchSingleDocument(docs[docKey],docKey)){patched=true;}});return patched;}
function patchModule(){var mod=window.elementorProFrontend&&elementorProFrontend.modules&&elementorProFrontend.modules.popup;if(!mod){return false;}if(typeof mod.showPopup==='function'&&!mod.__rwgcPopupGeoPatch){var o=mod.showPopup;mod.showPopup=function(){return applyOpen(o,this,arguments);};mod.__rwgcPopupGeoPatch=1;}if(typeof mod.triggerPopup==='function'&&!mod.__rwgcTriggerPatch){var t=mod.triggerPopup;mod.triggerPopup=function(){return applyOpen(t,this,arguments);};mod.__rwgcTriggerPatch=1;}if(typeof mod.closePopup==='function'&&!mod.__rwgcClosePatch){var c=mod.closePopup;mod.closePopup=function(){return wrapClose(c,this,arguments);};mod.__rwgcClosePatch=1;}return true;}
function patchFinalShowPopup(){var mod=window.elementorProFrontend&&elementorProFrontend.modules&&elementorProFrontend.modules.popup;if(!mod||typeof mod.showPopup!=='function'){return false;}var current=mod.showPopup;if(current.__rwgcFinalOuter===current){return true;}var inner=current;var wrapped=function(){var pid=resolvePopupIdFromArgs(arguments);if(pid&&!shouldBlockOpen(pid)){clearEgpReopenBlock(pid);clearPopupDisableStorage(pid);}if(pid&&shouldBlockOpen(pid)){dbg('block showPopup',pid);return false;}return inner.apply(this,arguments);};wrapped.__rwgcFinalOuter=wrapped;wrapped.__rwgcInner=inner;mod.showPopup=wrapped;dbg('final showPopup wrap');return true;}
function bindPopupEvents(){if(window.__rwgcPopupGeoEventPatch){return;}window.__rwgcPopupGeoEventPatch=1;var jq=window.jQuery;if(!jq){return;}var onShow=function(evt,popupId,docInst){var pid=norm(popupId);if(docInst){patchSingleDocument(docInst,pid);}};var targets=[];if(window.elementorFrontend&&elementorFrontend.elements){if(elementorFrontend.elements.$document){targets.push(elementorFrontend.elements.$document);}if(elementorFrontend.elements.$window){targets.push(elementorFrontend.elements.$window);}}targets.push(jq(document));targets.forEach(function($el){if($el&&typeof $el.on==='function'){$el.on('elementor/popup/show',onShow);}});}
function installCloseCapture(){if(window.__rwgcPopupCloseCapture){return;}window.__rwgcPopupCloseCapture=1;document.addEventListener('click',function(e){var t=e.target;if(!t||!t.closest){return;}var modal=t.closest('.elementor-popup-modal');if(!modal){return;}var closeBtn=t.closest('.dialog-close-button,.eicon-close');var overlay=t.classList&&t.classList.contains('dialog-widget-overlay');if(!closeBtn&&!overlay){return;}var pid=popupIdFromModal(modal);if(pid){forceClosePopup(pid);}},true);document.addEventListener('keydown',function(e){if(!e||e.key!=='Escape'){return;}var modals=document.querySelectorAll('.elementor-popup-modal');for(var i=0;i<modals.length;i++){if(modals[i].offsetParent===null){continue;}var pid=popupIdFromModal(modals[i]);if(pid){forceClosePopup(pid);break;}}},true);}
function findPopupElementorRoot(pid){var sel='[data-elementor-type="popup"][data-elementor-id="'+pid+'"],.elementor-'+pid+'[data-elementor-type="popup"]';var nodes=document.querySelectorAll(sel);return nodes.length?nodes[0]:null;}
function findPopupMarkup(pid){var root=findPopupElementorRoot(pid);if(root){return root;}var sel='#elementor-popup-modal-'+pid+',.elementor-popup-modal[data-elementor-id="'+pid+'"]';var nodes=document.querySelectorAll(sel);return nodes.length?nodes[0]:null;}
function findPopupModal(pid){return findPopupMarkup(pid);}
function clearEgpReopenBlock(pid){if(!pid){return;}try{sessionStorage.removeItem('egp_closed_'+String(pid));}catch(e){}}
function clearPopupDisableStorage(pid){if(!pid||!window.elementorFrontend||!elementorFrontend.storage){return;}try{elementorFrontend.storage.set('popup_'+pid+'_disable',false);if(typeof elementorFrontend.storage.remove==='function'){elementorFrontend.storage.remove('popup_'+pid+'_disable');}}catch(e){}}
function isPopupDocReady(doc){return !!(doc&&doc.elementHTML&&typeof doc.getModal==='function');}
function ensurePopupDocument(pid){pid=norm(pid);if(!pid){return null;}var doc=getPopupDoc(pid);if(doc){return doc;}var root=findPopupElementorRoot(pid);if(!root||!window.jQuery||!elementorFrontend||!elementorFrontend.documentsManager){dbg('ensure doc miss',pid,'root=',!!root);return null;}var mgr=elementorFrontend.documentsManager;if(mgr.documents[pid]||mgr.documents[String(pid)]){return getPopupDoc(pid);}try{var $el=jQuery(root);mgr.attachDocumentClass($el);doc=getPopupDoc(pid);patchSingleDocument(doc,pid);dbg('attached popup doc',pid,!!doc);return doc;}catch(e){dbg('attach doc failed',pid,e&&e.message?e.message:e);return null;}}
function bindLatePopupDocuments(){if(!variantCtx||!variantCtx.active){return;}Object.keys(popupData).forEach(function(key){var m=popupData[key];if(!m||m.rwgc_show!==true){return;}var pid=parseInt(key,10);if(!pid){return;}ensurePopupDocument(pid);});}
function forceOpenViaModal(doc,pid){if(!doc||typeof doc.getModal!=='function'){return false;}clearEgpReopenBlock(pid);clearPopupDisableStorage(pid);try{if(!doc.elementHTML){var root=findPopupElementorRoot(pid);if(root){doc.elementHTML=root.outerHTML;}}if(!doc.elementHTML){dbg('forceOpen no html',pid);return false;}doc.$element=jQuery(doc.elementHTML);if(doc.elements&&typeof doc.getSettings==='function'){doc.elements.$elements=doc.$element.find(doc.getSettings('selectors.elements'));}var modal=doc.getModal();if(!modal){dbg('forceOpen no modal',pid);return false;}modal.setMessage(doc.$element).show();try{document.body.classList.add('elementor-popup-modal-open');}catch(e){}dbg('forceOpenViaModal',pid,isPopupVisible(pid));return isPopupVisible(pid);}catch(e){dbg('forceOpenViaModal err',pid,e&&e.message?e.message:e);return false;}}
function isPopupDocOpen(pid){var doc=getPopupDoc(pid);if(!doc||typeof doc.getModal!=='function'){return false;}try{var modal=doc.getModal();if(modal&&typeof modal.isVisible==='function'){return !!modal.isVisible();}}catch(e){}return false;}
function isPopupVisible(pid){if(isPopupDocOpen(pid)){return true;}var nodes=document.querySelectorAll('#elementor-popup-modal-'+pid+',.elementor-popup-modal[data-elementor-id="'+pid+'"]');for(var i=0;i<nodes.length;i++){var st=window.getComputedStyle?window.getComputedStyle(nodes[i]):null;if(st&&st.display==='none'){continue;}if(st&&st.visibility==='hidden'){continue;}if(nodes[i].offsetParent!==null){return true;}}return false;}
function forceShowModalDom(pid){var modal=findPopupModal(pid);if(!modal){return false;}modal.style.setProperty('display','flex','important');modal.style.setProperty('visibility','visible','important');modal.style.setProperty('opacity','1','important');modal.removeAttribute('aria-hidden');try{document.body.classList.add('elementor-popup-modal-open');}catch(e){}return isPopupVisible(pid);}
function openAllowedPopup(pid){pid=norm(pid);if(!pid||shouldBlockOpen(pid)){return false;}clearEgpReopenBlock(pid);clearPopupDisableStorage(pid);patchRuntime();var doc=ensurePopupDocument(pid);var mod=window.elementorProFrontend&&elementorProFrontend.modules&&elementorProFrontend.modules.popup;dbg('open attempt',pid,'doc=',!!doc,'ready=',isPopupDocReady(doc),'egp_closed=',(function(){try{return sessionStorage.getItem('egp_closed_'+String(pid))==='1';}catch(e){return false;}})());if(doc&&isPopupDocReady(doc)&&typeof doc.showModal==='function'){try{doc.showModal();}catch(e){dbg('showModal error',pid,e&&e.message?e.message:e);}}else if(mod&&typeof mod.showPopup==='function'){mod.showPopup({id:pid});}if(!isPopupVisible(pid)&&doc){forceOpenViaModal(doc,pid);}if(!isPopupVisible(pid)){forceShowModalDom(pid);}return isPopupVisible(pid);}
function tryPageLoadFallbackFor(pid,m){if(fallbackDone[String(pid)]||fallbackStarted[String(pid)]){return;}fallbackStarted[String(pid)]=1;var delay=parseInt(m.page_load_delay_ms,10)||0;var attempt=0;var maxAttempts=20;var tick=function(){if(fallbackDone[String(pid)]){return;}patchRuntime();if(isPopupVisible(pid)){fallbackDone[String(pid)]=1;dbg('fallback open ok',pid);return;}attempt++;var fdoc=getPopupDoc(pid);dbg('fallback attempt',pid,attempt,'modal=',!!findPopupModal(pid),'doc=',!!fdoc,'ready=',isPopupDocReady(fdoc),'egp_closed=',(function(){try{return sessionStorage.getItem('egp_closed_'+String(pid))==='1';}catch(e){return false;}})());openAllowedPopup(pid);if(isPopupVisible(pid)){fallbackDone[String(pid)]=1;dbg('fallback open ok',pid);return;}if(attempt>=maxAttempts){fallbackDone[String(pid)]=1;dbg('fallback gave up',pid,'modal=',!!findPopupModal(pid),'doc=',!!getPopupDoc(pid),'markup=',!!findPopupMarkup(pid));return;}setTimeout(tick,300);};setTimeout(tick,Math.max(delay,800));}
function maybeVariantPageLoadFallback(){if(!variantCtx||!variantCtx.active){return;}Object.keys(popupData).forEach(function(key){var m=popupData[key];if(!m||m.rwgc_show!==true||!m.page_load_fallback){return;}var pid=parseInt(key,10);if(!pid){return;}tryPageLoadFallbackFor(pid,m);});}
function patchRuntime(){patchPopupDocuments();patchModule();patchFinalShowPopup();bindPopupEvents();installCloseCapture();}
patchRuntime();var tick=0;var patchIv=setInterval(function(){patchRuntime();tick++;if(tick>=120){clearInterval(patchIv);}},250);
function bindVariantFallback(){var run=function(){bindLatePopupDocuments();maybeVariantPageLoadFallback();};if(window.jQuery){jQuery(window).on('elementor/frontend/init',function(){setTimeout(run,200);setTimeout(run,800);});}window.addEventListener('load',function(){setTimeout(run,400);setTimeout(run,1200);},{once:true});}
bindVariantFallback();
window.rwgcPopupGeoBlocked=blocked;window.popupData=popupData;window.rwgcOpenAllowedPopup=openAllowedPopup;
})();
JS;

		return str_replace(
			array(
				'__USER_COUNTRY__',
				'__POPUP_DATA__',
				'__BLOCKED__',
				'__VARIANT_CTX__',
				'__DEBUG_FLAG__',
			),
			array(
				wp_json_encode( $visitor ),
				$popup_data,
				$blocked_data,
				$variant_data,
				$debug_flag,
			),
			$template
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function get_variant_route_context_for_script() {
		$ctx = array(
			'active'  => false,
			'page_id' => 0,
			'version' => '',
			'url'     => '',
		);

		if ( ! class_exists( 'RWGC_Page_Version_Routing', false ) ) {
			return $ctx;
		}

		if ( ! RWGC_Page_Version_Routing::is_page_version_request() ) {
			return $ctx;
		}

		$page_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		$version = RWGC_Page_Version_Routing::get_active_version();
		$url     = function_exists( 'home_url' ) ? (string) wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) : '';

		$ctx['active']  = true;
		$ctx['page_id'] = $page_id;
		$ctx['version'] = $version;
		$ctx['url']     = $url;

		return $ctx;
	}

	/**
	 * @param int $popup_id Popup template ID.
	 * @return bool
	 */
	private static function popup_has_page_load_trigger( $popup_id ) {
		$popup_id = absint( $popup_id );
		if ( $popup_id <= 0 ) {
			return false;
		}

		$triggers = self::get_popup_display_triggers( $popup_id );
		if ( empty( $triggers ) ) {
			return false;
		}

		// Elementor Pro stores On Page Load as a switcher: triggers.page_load = "yes".
		if ( ! empty( $triggers['page_load'] ) && 'yes' === (string) $triggers['page_load'] ) {
			return true;
		}

		// Older / exported nested shape: triggers.page_load = { delay: N }.
		return isset( $triggers['page_load'] ) && is_array( $triggers['page_load'] );
	}

	/**
	 * @param int $popup_id Popup template ID.
	 * @return float Seconds.
	 */
	private static function get_page_load_delay_seconds( $popup_id ) {
		$popup_id = absint( $popup_id );
		if ( $popup_id <= 0 ) {
			return 0.0;
		}

		$triggers = self::get_popup_display_triggers( $popup_id );
		if ( empty( $triggers ) ) {
			return 0.0;
		}

		if ( isset( $triggers['page_load'] ) && is_array( $triggers['page_load'] ) && isset( $triggers['page_load']['delay'] ) ) {
			return max( 0.0, (float) $triggers['page_load']['delay'] );
		}

		if ( isset( $triggers['page_load_delay'] ) ) {
			return max( 0.0, (float) $triggers['page_load_delay'] );
		}

		return 0.0;
	}

	/**
	 * @param int $popup_id Popup template ID.
	 * @return array<string, mixed>
	 */
	private static function get_popup_display_triggers( $popup_id ) {
		$popup_id = absint( $popup_id );
		if ( $popup_id <= 0 ) {
			return array();
		}

		if ( isset( self::$triggers_cache[ $popup_id ] ) ) {
			return self::$triggers_cache[ $popup_id ];
		}

		self::$triggers_cache[ $popup_id ] = self::resolve_popup_display_triggers( $popup_id );

		return self::$triggers_cache[ $popup_id ];
	}

	/**
	 * Resolve a popup's display triggers (uncached). Use {@see get_popup_display_triggers()}.
	 *
	 * @param int $popup_id Popup template ID.
	 * @return array<string, mixed>
	 */
	private static function resolve_popup_display_triggers( $popup_id ) {
		if ( did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Modules\Popup\Module', false ) && class_exists( '\Elementor\Plugin', false ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $popup_id );
			if ( $document && method_exists( $document, 'get_display_settings' ) ) {
				$display = $document->get_display_settings();
				if ( is_array( $display ) && ! empty( $display['triggers'] ) && is_object( $display['triggers'] ) && method_exists( $display['triggers'], 'get_settings' ) ) {
					$settings = $display['triggers']->get_settings();
					if ( is_array( $settings ) ) {
						return $settings;
					}
				}
			}
		}

		$display = get_post_meta( $popup_id, '_elementor_popup_display_settings', true );
		if ( ! is_array( $display ) || empty( $display['triggers'] ) || ! is_array( $display['triggers'] ) ) {
			return array();
		}

		return $display['triggers'];
	}

	/**
	 * Merged Elementor popup page settings + linked legacy geo_rule for surface evaluation.
	 *
	 * @param int $popup_id Popup template ID.
	 * @return array<string, mixed>|null
	 */
	private static function get_popup_targeting_settings( $popup_id ) {
		$popup_id = absint( $popup_id );
		if ( ! $popup_id ) {
			return null;
		}

		if ( array_key_exists( $popup_id, self::$settings_cache ) ) {
			return self::$settings_cache[ $popup_id ];
		}

		self::$settings_cache[ $popup_id ] = self::resolve_popup_targeting_settings( $popup_id );

		return self::$settings_cache[ $popup_id ];
	}

	/**
	 * Merged Elementor popup page settings + linked legacy geo_rule (uncached).
	 *
	 * @param int $popup_id Popup template ID.
	 * @return array<string, mixed>|null
	 */
	private static function resolve_popup_targeting_settings( $popup_id ) {
		$settings = array();
		$page     = self::get_popup_page_geo_settings( $popup_id );
		if ( is_array( $page ) && ! empty( $page['enabled'] ) ) {
			$settings = $page;
		}

		if ( post_type_exists( 'geo_rule' ) ) {
			$rules = get_posts(
				array(
					'post_type'      => 'geo_rule',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'   => self::META_PREFIX . 'target_type',
							'value' => 'popup',
						),
						array(
							'key'   => self::META_PREFIX . 'target_id',
							'value' => (string) $popup_id,
						),
						array(
							'key'   => self::META_PREFIX . 'active',
							'value' => '1',
						),
					),
				)
			);

			if ( ! empty( $rules ) && ( $rules[0] instanceof WP_Post ) ) {
				$rule_id = (int) $rules[0]->ID;
				$portable = get_post_meta( $rule_id, self::META_PREFIX . 'portable_targeting', true );
				if ( is_string( $portable ) && '' !== trim( $portable ) ) {
					$settings['egp_enable_geo_targeting']        = 'yes';
					$settings['egp_use_portable_geo_targeting']  = 'yes';
					$settings['egp_portable_geo_targeting']        = $portable;
					$portable_mode = self::rule_portable_visibility_mode( $rule_id );
					if ( null !== $portable_mode ) {
						$settings['rwgc_visibility_mode'] = $portable_mode;
					}
				} else {
					$rule_countries = get_post_meta( $rule_id, self::META_PREFIX . 'countries', true );
					if ( is_array( $rule_countries ) && ! empty( $rule_countries ) ) {
						$settings['egp_enable_geo_targeting'] = 'yes';
						$existing                         = isset( $settings['egp_countries'] ) && is_array( $settings['egp_countries'] ) ? $settings['egp_countries'] : array();
						$settings['egp_countries']        = array_values( array_unique( array_merge( $existing, $rule_countries ) ) );
					}
				}
			}
		}

		if ( empty( $settings ) || ! class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) || ! RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
			return null;
		}

		return $settings;
	}

	/**
	 * @param array<int|string> $countries Country codes.
	 * @param string              $visitor Visitor ISO code.
	 * @return bool
	 */
	private static function countries_match_visitor( array $countries, $visitor ) {
		$visitor = strtoupper( sanitize_text_field( $visitor ) );
		if ( '' === $visitor ) {
			return false;
		}
		$normalized = self::normalize_country_list( $countries );
		if ( empty( $normalized ) ) {
			return false;
		}
		return in_array( $visitor, $normalized, true );
	}

	/**
	 * @param mixed $countries Raw country list.
	 * @return array<int, string>
	 */
	private static function normalize_country_list( $countries ) {
		if ( ! is_array( $countries ) ) {
			return array();
		}
		$out = array();
		foreach ( $countries as $code ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			if ( 2 === strlen( $code ) ) {
				$out[] = $code;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $popup Popup arg.
	 * @return int
	 */
	private static function resolve_popup_id( $popup ) {
		if ( is_object( $popup ) && method_exists( $popup, 'get_id' ) ) {
			return (int) $popup->get_id();
		}
		if ( is_array( $popup ) && isset( $popup['id'] ) ) {
			return (int) $popup['id'];
		}
		if ( is_numeric( $popup ) ) {
			return (int) $popup;
		}
		return 0;
	}

	/**
	 * @param int $popup_id Popup template ID.
	 * @return array{enabled:bool,countries:array<int,string>,visibility_mode:string}|false
	 */
	private static function get_popup_page_geo_settings( $popup_id ) {
		$page_settings = get_post_meta( $popup_id, '_elementor_page_settings', true );
		if ( ! is_array( $page_settings ) ) {
			return false;
		}

		if ( ! self::page_settings_have_targeting( $page_settings ) ) {
			return false;
		}

		$country_on = self::page_settings_country_enabled( $page_settings );

		$use_portable = self::page_settings_visibility_enabled( $page_settings );

		$countries = array();
		if ( ! empty( $page_settings['egp_countries'] ) && is_array( $page_settings['egp_countries'] ) ) {
			$countries = $page_settings['egp_countries'];
		}

		$use_portable = $use_portable ? 'yes' : '';
		if ( ! empty( $page_settings['rwgc_enable_visibility_rules'] ) && 'yes' === (string) $page_settings['rwgc_enable_visibility_rules'] ) {
			$use_portable = 'yes';
		}

		$portable_raw = '';
		if ( ! empty( $page_settings['rwgc_portable_geo_targeting'] ) ) {
			$portable_raw = (string) $page_settings['rwgc_portable_geo_targeting'];
		} elseif ( ! empty( $page_settings['egp_portable_geo_targeting'] ) ) {
			$portable_raw = (string) $page_settings['egp_portable_geo_targeting'];
		}

		return array(
			'enabled'                       => true,
			'egp_enable_geo_targeting'        => $country_on ? 'yes' : '',
			'egp_countries'                   => $countries,
			'rwgc_enable_visibility_rules'    => ! empty( $page_settings['rwgc_enable_visibility_rules'] ) ? (string) $page_settings['rwgc_enable_visibility_rules'] : ( 'yes' === $use_portable ? 'yes' : '' ),
			'rwgc_country_visibility_mode'    => isset( $page_settings['rwgc_country_visibility_mode'] ) ? (string) $page_settings['rwgc_country_visibility_mode'] : ( isset( $page_settings['rwgc_visibility_mode'] ) ? (string) $page_settings['rwgc_visibility_mode'] : 'show_if' ),
			'rwgc_visibility_rules_mode'    => isset( $page_settings['rwgc_visibility_rules_mode'] ) ? (string) $page_settings['rwgc_visibility_rules_mode'] : ( isset( $page_settings['rwgc_visibility_mode'] ) ? (string) $page_settings['rwgc_visibility_mode'] : 'show_if' ),
			'rwgc_visibility_mode'            => isset( $page_settings['rwgc_visibility_mode'] ) ? (string) $page_settings['rwgc_visibility_mode'] : 'show_if',
			'egp_fallback_behavior'           => isset( $page_settings['egp_fallback_behavior'] ) ? sanitize_key( (string) $page_settings['egp_fallback_behavior'] ) : 'inherit',
			'rwgc_use_portable_geo_targeting' => $use_portable,
			'egp_use_portable_geo_targeting'  => $use_portable,
			'rwgc_portable_geo_targeting'     => $portable_raw,
			'egp_portable_geo_targeting'      => $portable_raw,
			'rwgc_visibility_rule_library'    => isset( $page_settings['rwgc_visibility_rule_library'] ) ? (string) $page_settings['rwgc_visibility_rule_library'] : '',
			'rwgc_applied_visibility_rule_id' => isset( $page_settings['rwgc_applied_visibility_rule_id'] ) ? (string) $page_settings['rwgc_applied_visibility_rule_id'] : '',
		);
	}

	/**
	 * @return array<int|string, array{id:int,title:string,countries:array<int,string>,visibility_mode:string}>
	 */
	private static function collect_popup_page_settings_map() {
		if ( null !== self::$page_settings_map_cache ) {
			return self::$page_settings_map_cache;
		}

		$popups = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_elementor_template_type',
						'value' => 'popup',
					),
				),
				'fields'         => 'ids',
			)
		);

		$out = array();
		foreach ( $popups as $popup_id ) {
			$popup_id = (int) $popup_id;
			$page_settings = get_post_meta( $popup_id, '_elementor_page_settings', true );
			if ( ! is_array( $page_settings ) || ! self::page_settings_have_targeting( $page_settings ) ) {
				continue;
			}
			$settings = self::get_popup_page_geo_settings( $popup_id );
			if ( ! $settings || empty( $settings['enabled'] ) ) {
				continue;
			}
			$countries = isset( $settings['egp_countries'] ) && is_array( $settings['egp_countries'] )
				? self::normalize_country_list( $settings['egp_countries'] )
				: array();
			$mode      = isset( $settings['rwgc_visibility_mode'] ) ? (string) $settings['rwgc_visibility_mode'] : 'show_if';

			$out[ $popup_id ] = array(
				'id'                => $popup_id,
				'title'             => get_the_title( $popup_id ),
				'countries'         => $countries,
				'visibility_mode'   => $mode,
				'source'            => 'page_settings',
				'uses_portable'     => ! empty( $settings['rwgc_use_portable_geo_targeting'] ) && 'yes' === (string) $settings['rwgc_use_portable_geo_targeting'],
			);
		}

		self::$page_settings_map_cache = $out;

		return $out;
	}

	/**
	 * @param int $rule_id geo_rule post ID.
	 * @return bool|null
	 */
	private static function rule_portable_should_show( $rule_id ) {
		$raw = get_post_meta( (int) $rule_id, self::META_PREFIX . 'portable_targeting', true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false )
			|| ! class_exists( 'RWGC_Rule_Evaluator', false )
			|| ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return null;
		}
		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
		if ( ! is_array( $set ) ) {
			return null;
		}
		$snapshot = RWGC_Context_Resolver::resolve_current();
		return RWGC_Rule_Evaluator::should_render_content( $set, $snapshot );
	}

	/**
	 * @param int $rule_id geo_rule post ID.
	 * @return string|null
	 */
	private static function rule_portable_visibility_mode( $rule_id ) {
		$raw = get_post_meta( (int) $rule_id, self::META_PREFIX . 'portable_targeting', true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return null;
		}
		$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $raw );
		if ( ! is_array( $set ) || empty( $set['mode'] ) ) {
			return null;
		}
		if ( function_exists( 'rwgc_normalize_visibility_mode' ) ) {
			return rwgc_normalize_visibility_mode( $set['mode'] );
		}
		return sanitize_key( (string) $set['mode'] );
	}

	/**
	 * Legacy-compatible fallback behavior when country cannot be detected.
	 *
	 * @param string $behavior Fallback behavior key.
	 * @return bool
	 */
	private static function resolve_unknown_country_fallback( $behavior ) {
		$behavior = sanitize_key( (string) $behavior );
		if ( '' === $behavior || 'inherit' === $behavior ) {
			$behavior = sanitize_key( (string) get_option( 'egp_fallback_behavior', 'show_to_all' ) );
		}

		switch ( $behavior ) {
			case 'show_to_none':
				return false;
			case 'show_default':
				return false;
			case 'show_to_all':
			default:
				return true;
		}
	}

	/**
	 * @return bool
	 */
	private static function is_debug_enabled() {
		return function_exists( 'rwgc_debug_targeting_enabled' ) && rwgc_debug_targeting_enabled();
	}

	/**
	 * @param int                  $popup_id Popup template ID.
	 * @param array<string, mixed> $settings Targeting settings.
	 * @param array<string, mixed> $result   Evaluation result from surface evaluator.
	 * @return void
	 */
	private static function debug_log_popup_decision( $popup_id, array $settings, array $result ) {
		if ( ! self::is_debug_enabled() || ! function_exists( 'error_log' ) ) {
			return;
		}

		$visitor = function_exists( 'rwgc_get_visitor_data' ) ? rwgc_get_visitor_data() : array();
		$cc      = isset( $visitor['country_code'] ) ? strtoupper( (string) $visitor['country_code'] ) : '';
		$cn      = isset( $visitor['country_name'] ) ? (string) $visitor['country_name'] : '';
		$city    = isset( $visitor['city'] ) ? (string) $visitor['city'] : '';
		$region  = isset( $visitor['region'] ) ? (string) $visitor['region'] : '';
		$ip      = isset( $visitor['ip'] ) ? (string) $visitor['ip'] : '';

		$should_render = isset( $result['should_render'] ) ? (bool) $result['should_render'] : true;
		$rules_match   = isset( $result['rules_match'] ) ? (bool) $result['rules_match'] : true;

		$snapshot = class_exists( 'RWGC_Context_Resolver', false ) ? RWGC_Context_Resolver::resolve_current() : null;
		$rule_id  = 0;
		if ( ! empty( $settings['rwgc_visibility_rule_library'] ) ) {
			$rule_id = absint( $settings['rwgc_visibility_rule_library'] );
		} elseif ( ! empty( $settings['rwgc_applied_visibility_rule_id'] ) ) {
			$rule_id = absint( $settings['rwgc_applied_visibility_rule_id'] );
		}

		$provenance = ( $rule_id > 0 && class_exists( 'RWGC_Variant_Rule_Applications', false ) )
			? RWGC_Variant_Rule_Applications::get_provenance( $rule_id )
			: array();

		$canonical_page_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;

		$payload = array(
			'popup_id'                 => (int) $popup_id,
			'popup_title'              => get_the_title( $popup_id ),
			'assigned_page'            => $canonical_page_id,
			'current_url'              => function_exists( 'home_url' ) ? (string) wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) : '',
			'canonical_page_id'        => $canonical_page_id,
			'detected_variant_key'     => $snapshot ? (string) $snapshot->get( 'page_version', '' ) : '',
			'detected_variant_page_id' => $snapshot ? (int) $snapshot->get( 'page_version_page_id', 0 ) : 0,
			'variant_route_active'     => $snapshot ? (bool) $snapshot->get( 'page_version_active', false ) : false,
			'detected_country'         => $cc . ( $cn ? ' (' . $cn . ')' : '' ),
			'detected_city'            => $city,
			'detected_region'          => $region,
			'detected_ip'              => $ip,
			'applied_rule_id'          => $rule_id > 0 ? (string) $rule_id : '',
			'rule_source_type'         => isset( $provenance['sourceType'] ) ? (string) $provenance['sourceType'] : '',
			'rule_source_page'         => isset( $provenance['sourcePageId'] ) ? (int) $provenance['sourcePageId'] : 0,
			'rule_source_variant'      => isset( $provenance['sourceVariant'] ) ? (string) $provenance['sourceVariant'] : '',
			'visibility_mode'          => isset( $result['visibility_mode'] ) ? (string) $result['visibility_mode'] : 'show_if',
			'applied_rule_source'      => isset( $result['rule_source'] ) ? (string) $result['rule_source'] : '',
			'rule_json'                => isset( $result['rule_json'] ) ? (string) $result['rule_json'] : '',
			'rule_match_result'        => $rules_match,
			'country_match'            => isset( $result['country_match'] ) ? (bool) $result['country_match'] : null,
			'portable_match'           => isset( $result['portable_match'] ) ? (bool) $result['portable_match'] : null,
			'elementor_page_load'      => self::popup_has_page_load_trigger( $popup_id ),
			'geo_allowed'              => $should_render,
			'popup_trigger_result'     => $should_render ? 'allow' : 'block',
			'final_decision'           => $should_render ? 'show' : 'suppress',
			'page_load_fallback'       => $should_render && self::popup_has_page_load_trigger( $popup_id ),
			'reason'                   => isset( $result['reason'] ) ? (string) $result['reason'] : '',
		);

		error_log( 'RWGC Popup Targeting Debug ' . wp_json_encode( $payload ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
