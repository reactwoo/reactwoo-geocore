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
		add_action( 'wp_footer', array( __CLASS__, 'print_popup_show_patch_script' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'print_popup_dom_guard_script' ), 99 );
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
	 * @param int $popup_id Popup template ID.
	 * @return bool|null True = show, false = hide, null = no geo targeting configured.
	 */
	public static function popup_should_display( $popup_id ) {
		$popup_id = absint( $popup_id );
		if ( ! $popup_id ) {
			return null;
		}

		$config = self::get_popup_geo_config( $popup_id );
		if ( null === $config ) {
			return null;
		}

		$visitor = strtoupper( (string) rwgc_get_visitor_country() );
		if ( '' === $visitor ) {
			$fallback_decision = self::resolve_unknown_country_fallback( isset( $config['fallback_behavior'] ) ? (string) $config['fallback_behavior'] : 'inherit' );
			self::debug_log_popup_decision(
				$popup_id,
				array(
					'visitor_country' => '',
					'match'           => false,
					'decision'        => $fallback_decision ? 'show' : 'hide',
					'mode'            => isset( $config['visibility_mode'] ) ? (string) $config['visibility_mode'] : 'show_if',
					'fallback'        => isset( $config['fallback_behavior'] ) ? (string) $config['fallback_behavior'] : 'inherit',
					'countries'       => isset( $config['countries'] ) ? $config['countries'] : array(),
					'source'          => isset( $config['source'] ) ? (string) $config['source'] : '',
					'reason'          => 'unknown_country_fallback',
				)
			);
			return $fallback_decision;
		}

		$matched = self::countries_match_visitor( $config['countries'], $visitor );
		$decision = false;

		if ( function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
			$decision = (bool) rwgc_visibility_mode_allows_render( $config['visibility_mode'], $matched );
		} else {
			$decision = (bool) $matched;
		}

		self::debug_log_popup_decision(
			$popup_id,
			array(
				'visitor_country' => $visitor,
				'match'           => (bool) $matched,
				'decision'        => $decision ? 'show' : 'hide',
				'mode'            => isset( $config['visibility_mode'] ) ? (string) $config['visibility_mode'] : 'show_if',
				'fallback'        => isset( $config['fallback_behavior'] ) ? (string) $config['fallback_behavior'] : 'inherit',
				'countries'       => isset( $config['countries'] ) ? $config['countries'] : array(),
				'source'          => isset( $config['source'] ) ? (string) $config['source'] : '',
				'reason'          => 'country_match',
			)
		);

		return $decision;
	}

	/**
	 * @return void
	 */
	public static function print_popup_show_patch_script() {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		$config_map = self::build_popup_config_map();
		if ( empty( $config_map ) ) {
			return;
		}

		$visitor = strtoupper( (string) rwgc_get_visitor_country() );
		$blocked   = array();

		foreach ( $config_map as $popup_id => $config ) {
			$decision = self::popup_should_display( (int) $popup_id );
			if ( false === $decision ) {
				$blocked[] = (int) $popup_id;
			}
		}

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
			. "setInterval(hidePopups,500);\n"
			. "})();"
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_popup_config_map() {
		$map = self::collect_popup_page_settings_map();
		if ( ! post_type_exists( 'geo_rule' ) ) {
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

		return $map;
	}

	/**
	 * @param array<int|string, array<string, mixed>> $map Popup config map.
	 * @param string $visitor Visitor country.
	 * @param int[] $blocked Popup IDs to force-hide.
	 * @return string
	 */
	private static function build_popup_runtime_script( $map, $visitor, $blocked ) {
		$user_country = wp_json_encode( $visitor );
		$popup_data   = wp_json_encode( $map );
		$blocked_data = wp_json_encode( array_values( $blocked ) );

		return "(function(){\n"
			. 'var userCountry=' . $user_country . ";\n"
			. 'var popupData=' . $popup_data . ";\n"
			. 'var blocked=' . $blocked_data . ";\n"
			. "function meta(pid){if(pid==null){return null;}var k=String(pid);return popupData[k]||popupData[pid]||null;}\n"
			. "function norm(v){if(v==null){return null;}if(typeof v==='number'){return v;}if(typeof v==='string'){var n=parseInt(v,10);return isNaN(n)?v:n;}if(typeof v==='object'){if(v.id!=null){return norm(v.id);}if(v.popup&&v.popup.id!=null){return norm(v.popup.id);}}return v;}\n"
			. "function popupShouldDisplay(m){var allowed=(m.countries||[]).map(function(c){return String(c).toUpperCase();});var matched=allowed.length>0&&allowed.indexOf(String(userCountry).toUpperCase())!==-1;var mode=(m.visibility_mode==='hide_if'||m.visibility_mode==='hide')?'hide_if':'show_if';return (mode==='hide_if')?!matched:matched;}\n"
			. "function shouldShowForPopup(pid){var m=meta(pid);if(!m){return true;}return popupShouldDisplay(m);}\n"
			. "function suppressPopup(pid){try{if(window.elementorProFrontend&&elementorProFrontend.modules&&elementorProFrontend.modules.popup){var mod=elementorProFrontend.modules.popup;if(typeof mod.closePopup==='function'){mod.closePopup({id:pid});return;}}}catch(e){}try{if(window.elementorFrontend&&elementorFrontend.documents&&elementorFrontend.documents.manager&&elementorFrontend.documents.manager.documents){var docs=elementorFrontend.documents.manager.documents;for(var dk in docs){if(!Object.prototype.hasOwnProperty.call(docs,dk)){continue;}var d=docs[dk];if(d&&typeof d.closePopup==='function'){d.closePopup({id:pid});}}}}catch(e2){}}\n"
			. "function apply(orig,scope,args){var pid=norm(args.length?args[0]:null);if(!pid||shouldShowForPopup(pid)){return orig.apply(scope,args);}return false;}\n"
			. "function patch(){if(window.__rwgcPopupGeoPatched){return true;}var mod=window.elementorProFrontend&&elementorProFrontend.modules&&elementorProFrontend.modules.popup;"
			. "if(mod&&typeof mod.showPopup==='function'&&!mod.__rwgcPopupGeoPatch){var o=mod.showPopup;mod.showPopup=function(){return apply(o,this,arguments);};if(typeof mod.triggerPopup==='function'){var t=mod.triggerPopup;mod.triggerPopup=function(){return apply(t,this,arguments);};}mod.__rwgcPopupGeoPatch=1;window.__rwgcPopupGeoPatched=1;return true;}"
			. "if(typeof elementorFrontend!=='undefined'){var docRoot=elementorFrontend.documents&&elementorFrontend.documents.manager&&elementorFrontend.documents.manager.documents&&elementorFrontend.documents.manager.documents[0];if(docRoot&&typeof docRoot.showPopup==='function'&&!docRoot.__rwgcPopupGeoPatch){var ds=docRoot.showPopup;docRoot.showPopup=function(){return apply(ds,this,arguments);};if(typeof docRoot.triggerPopup==='function'){var dt=docRoot.triggerPopup;docRoot.triggerPopup=function(){return apply(dt,this,arguments);};}docRoot.__rwgcPopupGeoPatch=1;window.__rwgcPopupGeoPatched=1;return true;}}"
			. "return false;}\n"
			. "if(window.jQuery&&window.jQuery(document)&&!window.__rwgcPopupGeoEventPatch){window.__rwgcPopupGeoEventPatch=1;window.jQuery(document).on('elementor/popup/show',function(evt,popupId){var pid=norm(popupId);if(!pid||shouldShowForPopup(pid)){return;}try{evt.preventDefault();evt.stopImmediatePropagation();}catch(e){}setTimeout(function(){suppressPopup(pid);},0);});}\n"
			. "var tries=0;(function retry(){if(patch()){return;}tries++;if(tries<80){setTimeout(retry,100);}})();\n"
			. "})();";
	}

	/**
	 * @param int $popup_id Popup template ID.
	 * @return array<string, mixed>|null
	 */
	private static function get_popup_geo_config( $popup_id ) {
		$config = null;

		$settings = self::get_popup_page_geo_settings( $popup_id );
		if ( $settings && ! empty( $settings['enabled'] ) ) {
			$config = array(
				'countries'       => self::normalize_country_list( $settings['countries'] ),
				'visibility_mode' => isset( $settings['visibility_mode'] ) ? (string) $settings['visibility_mode'] : 'show_if',
				'source'          => 'page_settings',
				'fallback_behavior' => isset( $settings['fallback_behavior'] ) ? (string) $settings['fallback_behavior'] : 'inherit',
			);
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
				$rule_id       = (int) $rules[0]->ID;
				$rule_mode     = 'show_if';
				$rule_countries = self::normalize_country_list( get_post_meta( $rule_id, self::META_PREFIX . 'countries', true ) );
				$portable_mode = self::rule_portable_visibility_mode( $rule_id );
				if ( null !== $portable_mode ) {
					$rule_mode = $portable_mode;
				}

				if ( null === $config ) {
					$config = array(
						'countries'       => $rule_countries,
						'visibility_mode' => $rule_mode,
						'source'          => 'geo_rule',
					);
				} else {
					$config['countries'] = array_values( array_unique( array_merge( $config['countries'], $rule_countries ) ) );
				}
			}
		}

		return $config;
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

		$enabled = false;
		if ( ! empty( $page_settings['egp_enable_geo_targeting'] ) && 'yes' === (string) $page_settings['egp_enable_geo_targeting'] ) {
			$enabled = true;
		} elseif ( ! empty( $page_settings['egp_geo_enabled'] ) && 'yes' === (string) $page_settings['egp_geo_enabled'] ) {
			$enabled = true;
		}

		if ( ! $enabled ) {
			return false;
		}

		$countries = array();
		if ( ! empty( $page_settings['egp_countries'] ) && is_array( $page_settings['egp_countries'] ) ) {
			$countries = $page_settings['egp_countries'];
		}

		return array(
			'enabled'        => true,
			'countries'      => $countries,
			'visibility_mode' => function_exists( 'rwgc_normalize_visibility_mode' )
				? rwgc_normalize_visibility_mode( isset( $page_settings['rwgc_visibility_mode'] ) ? $page_settings['rwgc_visibility_mode'] : ( isset( $page_settings['rwgc_geo_mode'] ) ? $page_settings['rwgc_geo_mode'] : 'show_if' ) )
				: 'show_if',
			'fallback_behavior' => isset( $page_settings['egp_fallback_behavior'] ) ? sanitize_key( (string) $page_settings['egp_fallback_behavior'] ) : 'inherit',
		);
	}

	/**
	 * @return array<int|string, array{id:int,title:string,countries:array<int,string>,visibility_mode:string}>
	 */
	private static function collect_popup_page_settings_map() {
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
			$settings = self::get_popup_page_geo_settings( $popup_id );
			if ( ! $settings || empty( $settings['enabled'] ) ) {
				continue;
			}
			$out[ $popup_id ] = array(
				'id'                => $popup_id,
				'title'             => get_the_title( $popup_id ),
				'countries'         => $settings['countries'],
				'visibility_mode' => $settings['visibility_mode'],
				'source'            => 'page_settings',
			);
		}

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
		return class_exists( 'RWGC_Settings', false ) && (bool) RWGC_Settings::get( 'debug_mode', 0 );
	}

	/**
	 * @param int                  $popup_id Popup template ID.
	 * @param array<string, mixed> $line     Structured payload.
	 * @return void
	 */
	private static function debug_log_popup_decision( $popup_id, array $line ) {
		if ( ! self::is_debug_enabled() || ! function_exists( 'error_log' ) ) {
			return;
		}
		$line['popup_id'] = (int) $popup_id;
		error_log( '[RWGC Popup Geo] ' . wp_json_encode( $line ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
