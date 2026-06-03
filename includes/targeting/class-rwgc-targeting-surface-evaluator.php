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
	 * Whether geo targeting is enabled in a settings array.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return bool
	 */
	public static function is_targeting_enabled( array $settings ) {
		if ( ! empty( $settings['egp_geo_enabled'] ) && 'yes' === (string) $settings['egp_geo_enabled'] ) {
			return true;
		}
		if ( ! empty( $settings['egp_enable_geo_targeting'] ) && 'yes' === (string) $settings['egp_enable_geo_targeting'] ) {
			return true;
		}
		return false;
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
	 * Visibility mode from settings (defaults to show_if).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return string show_if|hide_if
	 */
	public static function get_visibility_mode( array $settings ) {
		$mode = 'show_if';
		if ( isset( $settings['rwgc_visibility_mode'] ) ) {
			$mode = (string) $settings['rwgc_visibility_mode'];
		} elseif ( isset( $settings['rwgc_geo_mode'] ) ) {
			$mode = (string) $settings['rwgc_geo_mode'];
		}
		if ( function_exists( 'rwgc_normalize_visibility_mode' ) ) {
			return rwgc_normalize_visibility_mode( $mode );
		}
		return 'hide_if' === sanitize_key( $mode ) ? 'hide_if' : 'show_if';
	}

	/**
	 * Evaluate targeting for a settings array.
	 *
	 * Countries and portable rules combine with AND when both are configured.
	 *
	 * @param array<string, mixed> $settings Builder settings.
	 * @return array<string, mixed>
	 */
	public static function evaluate( array $settings ) {
		$result = array(
			'targeting_enabled' => self::is_targeting_enabled( $settings ),
			'rules_match'       => true,
			'should_render'     => true,
			'visibility_mode'   => self::get_visibility_mode( $settings ),
			'rule_source'       => '',
			'rule_json'         => '',
			'reason'            => 'no_targeting',
			'country_match'     => true,
			'portable_match'    => true,
		);

		if ( ! $result['targeting_enabled'] ) {
			return $result;
		}

		$mode            = $result['visibility_mode'];
		$countries       = self::parse_countries( $settings );
		$country_active  = ! empty( $countries );
		$portable_active = self::uses_portable_rules( $settings );
		$set             = null;

		if ( $country_active ) {
			$visitor = function_exists( 'rwgc_get_visitor_country' ) ? strtoupper( (string) rwgc_get_visitor_country() ) : '';
			if ( '' === $visitor ) {
				$result['country_match'] = false;
			} else {
				$result['country_match'] = in_array( $visitor, $countries, true );
			}
		}

		if ( $portable_active && class_exists( 'RWGC_Rule_Registry', false ) ) {
			$set = RWGC_Rule_Registry::resolve_rule_set_from_settings( $settings );
			if ( is_array( $set ) ) {
				$result['rule_source'] = ! empty( $settings['rwgc_visibility_rule_library'] ) || ! empty( $settings['rwgc_applied_visibility_rule_id'] )
					? 'library:' . (string) ( $settings['rwgc_visibility_rule_library'] ?? $settings['rwgc_applied_visibility_rule_id'] )
					: 'inline_portable';
				$encoded               = wp_json_encode( $set );
				$result['rule_json']   = is_string( $encoded ) ? $encoded : '';
				if ( class_exists( 'RWGC_Rule_Evaluator', false ) && class_exists( 'RWGC_Context_Resolver', false ) ) {
					$snapshot               = RWGC_Context_Resolver::resolve_current();
					$result['portable_match'] = RWGC_Rule_Evaluator::matches( $set, $snapshot );
				}
			} elseif ( $portable_active ) {
				// Portable mode on but no rule data yet — do not block (avoid suppressing everyone).
				$result['portable_match'] = true;
				$result['reason']         = 'portable_enabled_empty';
			}
		}

		if ( $country_active && $portable_active && is_array( $set ) ) {
			$result['rules_match'] = $result['country_match'] && $result['portable_match'];
			$result['reason']      = 'countries_and_portable';
		} elseif ( $portable_active && is_array( $set ) ) {
			$result['rules_match'] = $result['portable_match'];
			$result['reason']      = 'portable_rules';
		} elseif ( $country_active ) {
			$result['rules_match'] = $result['country_match'];
			$result['reason']      = 'country_list';
		} else {
			$result['rules_match'] = true;
			$result['reason']      = 'targeting_enabled_empty_rules';
		}

		if ( function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
			$result['should_render'] = rwgc_visibility_mode_allows_render( $mode, $result['rules_match'] );
		} else {
			$result['should_render'] = (bool) $result['rules_match'];
		}

		return $result;
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
