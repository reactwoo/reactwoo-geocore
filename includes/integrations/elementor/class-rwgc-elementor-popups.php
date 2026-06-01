<?php
/**
 * Elementor Pro popup geo targeting (legacy Geo Elementor data + geo_rule CPT).
 *
 * Runs when Geo Elementor is inactive so existing popup rules keep working.
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
		if ( class_exists( 'EGP_Geo_Rules', false ) ) {
			return;
		}
		if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
			return;
		}

		add_filter( 'elementor_pro/popup/should_show', array( __CLASS__, 'filter_popup_should_show' ), 15, 2 );
		add_action( 'wp_footer', array( __CLASS__, 'print_popup_show_patch_script' ), 25 );
	}

	/**
	 * @param bool $should_show Current decision.
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

		$rule_decision = self::evaluate_geo_rule_for_popup( $popup_id );
		if ( null !== $rule_decision ) {
			return $rule_decision;
		}

		$settings = self::get_popup_page_geo_settings( $popup_id );
		if ( ! $settings || empty( $settings['enabled'] ) ) {
			return (bool) $should_show;
		}

		if ( null !== $settings['portable_decision'] ) {
			return (bool) $settings['portable_decision'];
		}

		return self::visitor_matches_countries( $settings['countries'], $settings['mode'] );
	}

	/**
	 * Fallback patch for Elementor Pro popup module (same approach as legacy Geo Elementor).
	 *
	 * @return void
	 */
	public static function print_popup_show_patch_script() {
		if ( function_exists( 'rwgc_is_builder_edit_request' ) && rwgc_is_builder_edit_request() ) {
			return;
		}

		$popup_data = self::collect_popup_page_settings_map();
		if ( empty( $popup_data ) ) {
			return;
		}

		$fallback_popup_id = (string) get_option( 'egp_default_popup_id', '' );
		$fallback_behavior = (string) get_option( 'egp_fallback_behavior', 'show_to_all' );

		wp_print_inline_script_tag(
			'(function(){'
			. 'var popupData=' . wp_json_encode( $popup_data ) . ';'
			. 'var fallbackPopupId=' . wp_json_encode( $fallback_popup_id ) . ';'
			. 'var fallbackBehavior=' . wp_json_encode( $fallback_behavior ) . ';'
			. 'function meta(pid){if(pid==null)return null;var k=String(pid);return popupData[pid]||popupData[k]||null;}'
			. 'function norm(v){if(v==null)return null;if(typeof v==="number")return v;if(typeof v==="string"){var n=parseInt(v,10);return isNaN(n)?v:n;}if(typeof v==="object"){if(v.id!=null)return norm(v.id);if(v.popup&&v.popup.id!=null)return norm(v.popup.id);}return v;}'
			. 'function apply(orig,scope,args){var pid=norm(args.length?args[0]:null);var m=meta(pid);if(!m||typeof m.allowed==="undefined"){return orig.apply(scope,args);}'
			. 'if(m.allowed){return orig.apply(scope,args);}'
			. 'if(fallbackPopupId&&fallbackBehavior==="show_fallback"){var fb=parseInt(fallbackPopupId,10);var raw=args[0];if(typeof raw==="object"&&raw!==null){var next=Object.assign({},raw);if("id" in next)next.id=fb;if(raw.popup&&typeof raw.popup==="object"){next.popup=Object.assign({},raw.popup);next.popup.id=fb;}return orig.call(scope,next);}return orig.call(scope,fb);}'
			. 'return false;}'
			. 'function patch(){if(window.__rwgcPopupGeoPatched)return true;var mod=window.elementorProFrontend&&elementorProFrontend.modules&&elementorProFrontend.modules.popup;'
			. 'if(mod&&typeof mod.showPopup==="function"&&!mod.__rwgcPopupGeoPatch){var o=mod.showPopup;mod.showPopup=function(){return apply(o,this,arguments);};'
			. 'if(typeof mod.triggerPopup==="function"){var t=mod.triggerPopup;mod.triggerPopup=function(){return apply(t,this,arguments);};}'
			. 'mod.__rwgcPopupGeoPatch=1;window.__rwgcPopupGeoPatched=1;return true;}return false;}'
			. 'var tries=0;(function retry(){if(patch())return;tries++;if(tries<60)setTimeout(retry,100);})();'
			. '})();'
		);
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
	 * Active geo_rule CPT row for this popup template.
	 *
	 * @param int $popup_id Popup template post ID.
	 * @return bool|null True/false when a rule applies; null when none.
	 */
	private static function evaluate_geo_rule_for_popup( $popup_id ) {
		if ( ! post_type_exists( 'geo_rule' ) ) {
			return null;
		}

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

		if ( empty( $rules ) || ! ( $rules[0] instanceof WP_Post ) ) {
			return null;
		}

		$rule_id = (int) $rules[0]->ID;

		$portable = self::rule_portable_should_show( $rule_id );
		if ( null !== $portable ) {
			return $portable;
		}

		$countries = get_post_meta( $rule_id, self::META_PREFIX . 'countries', true );
		if ( ! is_array( $countries ) || empty( $countries ) ) {
			return false;
		}

		return self::visitor_matches_countries( $countries );
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
	 * @param int $popup_id Popup template ID.
	 * @return array{enabled:bool,countries:array<int,string>,mode:string,portable_decision:bool|null}|false
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

		$countries = self::parse_countries_from_settings( $page_settings );

		return array(
			'enabled'           => true,
			'countries'         => $countries,
			'mode'              => self::normalize_mode( isset( $page_settings['rwgc_geo_mode'] ) ? $page_settings['rwgc_geo_mode'] : 'show' ),
			'portable_decision' => self::page_settings_portable_should_show( $page_settings ),
		);
	}

	/**
	 * @return array<int|string, array{id:int,title:string,countries:array<int,string>,mode:string,allowed:bool}>
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
			$allowed = null !== $settings['portable_decision']
				? (bool) $settings['portable_decision']
				: self::visitor_matches_countries( $settings['countries'], $settings['mode'] );

			$out[ $popup_id ] = array(
				'id'        => $popup_id,
				'title'     => get_the_title( $popup_id ),
				'countries' => $settings['countries'],
				'mode'      => $settings['mode'],
				'allowed'   => $allowed,
			);
		}

		return $out;
	}

	/**
	 * @param array<int, string> $countries Country codes.
	 * @param string             $mode      show|hide.
	 * @return bool
	 */
	private static function visitor_matches_countries( array $countries, $mode = 'show' ) {
		$mode    = self::normalize_mode( $mode );
		$visitor = strtoupper( (string) rwgc_get_visitor_country() );
		if ( '' === $visitor ) {
			return 'hide' === $mode;
		}
		$normalized = array();
		foreach ( $countries as $code ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			if ( 2 === strlen( $code ) ) {
				$normalized[] = $code;
			}
		}
		if ( empty( $normalized ) ) {
			return 'hide' === $mode;
		}
		$matches = in_array( $visitor, $normalized, true );
		return 'hide' === $mode ? ! $matches : $matches;
	}

	/**
	 * @param array<string, mixed> $settings Elementor page settings.
	 * @return array<int, string>
	 */
	private static function parse_countries_from_settings( array $settings ) {
		$raw = isset( $settings['egp_countries'] ) ? $settings['egp_countries'] : '';
		if ( is_array( $raw ) ) {
			$list = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$list = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
			$list = is_array( $list ) ? $list : array();
		} else {
			$list = array();
		}

		$out = array();
		foreach ( $list as $code ) {
			$code = strtoupper( sanitize_text_field( (string) $code ) );
			if ( 2 === strlen( $code ) ) {
				$out[] = $code;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array<string, mixed> $settings Elementor page settings.
	 * @return bool|null
	 */
	private static function page_settings_portable_should_show( array $settings ) {
		$enabled = false;
		if ( ! empty( $settings['rwgc_use_portable_geo_targeting'] ) && 'yes' === (string) $settings['rwgc_use_portable_geo_targeting'] ) {
			$enabled = true;
		} elseif ( ! empty( $settings['egp_use_portable_geo_targeting'] ) && 'yes' === (string) $settings['egp_use_portable_geo_targeting'] ) {
			$enabled = true;
		}
		if ( ! $enabled ) {
			return null;
		}

		$raw = '';
		if ( isset( $settings['rwgc_portable_geo_targeting'] ) ) {
			$raw = wp_unslash( (string) $settings['rwgc_portable_geo_targeting'] );
		} elseif ( isset( $settings['egp_portable_geo_targeting'] ) ) {
			$raw = wp_unslash( (string) $settings['egp_portable_geo_targeting'] );
		}

		if ( '' === trim( (string) $raw )
			|| ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false )
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
	 * @param mixed $mode Raw mode.
	 * @return string
	 */
	private static function normalize_mode( $mode ) {
		return 'hide' === sanitize_key( (string) $mode ) ? 'hide' : 'show';
	}
}
