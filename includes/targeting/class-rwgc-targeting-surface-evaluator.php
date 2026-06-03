<?php
/**
 * Shared geo targeting evaluation for Elementor, Gutenberg, popups, and REST.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates saved builder settings (countries, portable JSON, library rule id).
 */
class RWGC_Targeting_Surface_Evaluator {

	/**
	 * Whether country targeting layer is enabled.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function is_country_targeting_enabled( array $settings ) {
		if ( ! empty( $settings['egp_enable_geo_targeting'] ) && 'yes' === (string) $settings['egp_enable_geo_targeting'] ) {
			return true;
		}
		if ( ! empty( $settings['egp_geo_enabled'] ) && 'yes' === (string) $settings['egp_geo_enabled'] ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether visibility rules (portable / library) layer is enabled.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function is_visibility_rules_enabled( array $settings ) {
		if ( ! empty( $settings['rwgc_enable_visibility_rules'] ) && 'yes' === (string) $settings['rwgc_enable_visibility_rules'] ) {
			return true;
		}
		if ( self::uses_portable_rules( $settings ) ) {
			return true;
		}
		return self::has_resolved_portable_config( $settings );
	}

	/**
	 * Whether any targeting layer is active for this surface.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function is_surface_active( array $settings ) {
		return self::is_country_targeting_enabled( $settings ) || self::is_visibility_rules_enabled( $settings );
	}

	/**
	 * @deprecated Use is_surface_active().
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function is_targeting_enabled( array $settings ) {
		return self::is_surface_active( $settings );
	}

	/**
	 * Whether portable rule builder mode is active.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function uses_portable_rules( array $settings ) {
		if ( ! empty( $settings['egp_use_portable_geo_targeting'] ) && 'yes' === (string) $settings['egp_use_portable_geo_targeting'] ) {
			return true;
		}
		if ( ! empty( $settings['rwgc_use_portable_geo_targeting'] ) && 'yes' === (string) $settings['rwgc_use_portable_geo_targeting'] ) {
			return true;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	private static function has_resolved_portable_config( array $settings ) {
		if ( ! class_exists( 'RWGC_Rule_Registry', false ) ) {
			return false;
		}
		if ( '' !== trim( (string) ( $settings['rwgc_applied_visibility_rule_id'] ?? '' ) ) ) {
			return true;
		}
		if ( '' !== trim( (string) ( $settings['rwgc_visibility_rule_library'] ?? '' ) ) ) {
			return true;
		}
		foreach ( array( 'egp_portable_geo_targeting', 'rwgc_portable_geo_targeting' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) && '' !== trim( (string) $settings[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Country layer visibility mode (defaults to show_if).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	public static function get_country_visibility_mode( array $settings ) {
		$mode = 'show_if';
		if ( isset( $settings['rwgc_country_visibility_mode'] ) ) {
			$mode = (string) $settings['rwgc_country_visibility_mode'];
		} elseif ( isset( $settings['rwgc_visibility_mode'] ) && ! self::is_visibility_rules_enabled( $settings ) ) {
			$mode = (string) $settings['rwgc_visibility_mode'];
		} elseif ( isset( $settings['rwgc_geo_mode'] ) ) {
			$mode = (string) $settings['rwgc_geo_mode'];
		}
		return self::normalize_mode( $mode );
	}

	/**
	 * Visibility rules layer mode (defaults to show_if).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	public static function get_visibility_rules_mode( array $settings ) {
		$mode = 'show_if';
		if ( isset( $settings['rwgc_visibility_rules_mode'] ) ) {
			$mode = (string) $settings['rwgc_visibility_rules_mode'];
		} elseif ( isset( $settings['rwgc_visibility_mode'] ) ) {
			$mode = (string) $settings['rwgc_visibility_mode'];
		} elseif ( isset( $settings['rwgc_geo_mode'] ) ) {
			$mode = (string) $settings['rwgc_geo_mode'];
		}
		return self::normalize_mode( $mode );
	}

	/**
	 * @param string $mode Raw mode.
	 * @return string
	 */
	private static function normalize_mode( $mode ) {
		if ( function_exists( 'rwgc_normalize_visibility_mode' ) ) {
			return rwgc_normalize_visibility_mode( $mode );
		}
		return 'hide_if' === sanitize_key( (string) $mode ) ? 'hide_if' : 'show_if';
	}

	/**
	 * Evaluate targeting for a settings array.
	 *
	 * @param array<string, mixed> $settings Builder settings.
	 * @return array<string, mixed>
	 */
	public static function evaluate( array $settings ) {
		if ( class_exists( 'RWGC_Surface_Settings', false ) ) {
			$settings = RWGC_Surface_Settings::normalize( $settings );
		}

		$country_on    = self::is_country_targeting_enabled( $settings );
		$visibility_on = self::is_visibility_rules_enabled( $settings );

		$result = array(
			'targeting_enabled'  => $country_on || $visibility_on,
			'rules_match'        => true,
			'should_render'      => true,
			'visibility_mode'    => self::get_visibility_rules_mode( $settings ),
			'rule_source'        => '',
			'rule_json'          => '',
			'reason'             => 'no_targeting',
			'country_match'      => true,
			'portable_match'     => true,
			'country_layer_on'   => $country_on,
			'visibility_layer_on' => $visibility_on,
		);

		if ( ! $result['targeting_enabled'] ) {
			return $result;
		}

		$should_render = true;

		if ( $country_on ) {
			$countries     = self::parse_countries( $settings );
			$country_match = true;
			if ( ! empty( $countries ) ) {
				$visitor = function_exists( 'rwgc_get_visitor_country' ) ? strtoupper( (string) rwgc_get_visitor_country() ) : '';
				$country_match = '' !== $visitor && in_array( $visitor, $countries, true );
			}
			$result['country_match'] = $country_match;
			$country_mode            = self::get_country_visibility_mode( $settings );
			$country_show            = function_exists( 'rwgc_visibility_mode_allows_render' )
				? rwgc_visibility_mode_allows_render( $country_mode, $country_match )
				: $country_match;
			$should_render           = $should_render && $country_show;
			$result['reason']        = 'country_layer';
		}

		if ( $visibility_on ) {
			$set             = null;
			$portable_match  = true;
			if ( class_exists( 'RWGC_Rule_Registry', false ) ) {
				$set = RWGC_Rule_Registry::resolve_rule_set_from_settings( $settings );
			}
			if ( is_array( $set ) ) {
				$result['rule_source'] = ! empty( $settings['rwgc_visibility_rule_library'] ) || ! empty( $settings['rwgc_applied_visibility_rule_id'] )
					? 'library:' . (string) ( $settings['rwgc_visibility_rule_library'] ?? $settings['rwgc_applied_visibility_rule_id'] )
					: 'inline_portable';
				$encoded               = wp_json_encode( $set );
				$result['rule_json']   = is_string( $encoded ) ? $encoded : '';
				if ( class_exists( 'RWGC_Rule_Evaluator', false ) && class_exists( 'RWGC_Context_Resolver', false ) ) {
					$snapshot         = RWGC_Context_Resolver::resolve_current();
					$portable_match   = RWGC_Rule_Evaluator::matches( $set, $snapshot );
				}
			} elseif ( self::uses_portable_rules( $settings ) || self::has_resolved_portable_config( $settings ) ) {
				$portable_match = true;
				$result['reason'] = 'visibility_rules_empty';
			}
			$result['portable_match'] = $portable_match;
			$rules_mode               = self::get_visibility_rules_mode( $settings );
			$visibility_show          = function_exists( 'rwgc_visibility_mode_allows_render' )
				? rwgc_visibility_mode_allows_render( $rules_mode, $portable_match )
				: $portable_match;
			$should_render            = $should_render && $visibility_show;
			$result['reason']         = $country_on ? 'country_and_visibility_rules' : 'visibility_rules';
		}

		$rules_match = true;
		if ( $country_on && ! empty( self::parse_countries( $settings ) ) ) {
			$rules_match = $rules_match && $result['country_match'];
		}
		if ( $visibility_on && isset( $set ) && is_array( $set ) ) {
			$rules_match = $rules_match && $result['portable_match'];
		}

		$result['rules_match']   = $rules_match;
		$result['should_render'] = $should_render;

		return $result;
	}

	/**
	 * Primary evaluation reason for popup country fallback checks.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	public static function get_primary_evaluation_reason( array $settings ) {
		if ( self::is_visibility_rules_enabled( $settings ) ) {
			return 'visibility_rules';
		}
		if ( self::is_country_targeting_enabled( $settings ) && ! empty( self::parse_countries( $settings ) ) ) {
			return 'country_list';
		}
		return 'targeting_enabled_empty_rules';
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 * @return array<int, string>
	 */
	public static function parse_countries( array $settings ) {
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
}

if ( ! function_exists( 'rwgc_debug_targeting_enabled' ) ) {
	/**
	 * @return bool
	 */
	function rwgc_debug_targeting_enabled() {
		if ( defined( 'RWGC_DEBUG_TARGETING' ) && RWGC_DEBUG_TARGETING ) {
			return true;
		}
		return class_exists( 'RWGC_Settings', false ) && (bool) RWGC_Settings::get( 'debug_mode', 0 );
	}
}
