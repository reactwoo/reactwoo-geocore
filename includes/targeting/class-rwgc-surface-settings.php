<?php
/**
 * Normalize geo targeting settings for {@see RWGC_Targeting_Surface_Evaluator}.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps legacy builder keys to the split country + visibility rules schema.
 */
class RWGC_Surface_Settings {

	/**
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $settings ) {
		$out = $settings;

		if ( empty( $out['egp_enable_geo_targeting'] ) && ! empty( $out['egp_geo_enabled'] ) && 'yes' === (string) $out['egp_geo_enabled'] ) {
			$out['egp_enable_geo_targeting'] = 'yes';
		}

		if ( empty( $out['rwgc_country_visibility_mode'] ) ) {
			if ( ! empty( $out['rwgc_visibility_mode'] )
				&& ( empty( $out['rwgc_enable_visibility_rules'] ) || 'yes' !== (string) $out['rwgc_enable_visibility_rules'] )
				&& empty( $out['rwgc_use_portable_geo_targeting'] )
				&& empty( $out['egp_use_portable_geo_targeting'] ) ) {
				$out['rwgc_country_visibility_mode'] = (string) $out['rwgc_visibility_mode'];
			} elseif ( ! empty( $out['rwgc_geo_mode'] ) ) {
				$out['rwgc_country_visibility_mode'] = (string) $out['rwgc_geo_mode'];
			}
		}

		if ( empty( $out['rwgc_enable_visibility_rules'] ) ) {
			if ( ! empty( $out['rwgc_use_portable_geo_targeting'] ) && 'yes' === (string) $out['rwgc_use_portable_geo_targeting'] ) {
				$out['rwgc_enable_visibility_rules'] = 'yes';
			} elseif ( ! empty( $out['egp_use_portable_geo_targeting'] ) && 'yes' === (string) $out['egp_use_portable_geo_targeting'] ) {
				$out['rwgc_enable_visibility_rules'] = 'yes';
			}
		}

		if ( empty( $out['rwgc_portable_geo_targeting'] ) && ! empty( $out['egp_portable_geo_targeting'] ) ) {
			$out['rwgc_portable_geo_targeting'] = (string) $out['egp_portable_geo_targeting'];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, mixed>
	 */
	public static function from_block_attributes( array $attributes ) {
		$has_country_visibility_mode = array_key_exists( 'countryVisibilityMode', $attributes );
		$attrs = wp_parse_args(
			$attributes,
			array(
				'enableCountryTargeting'   => false,
				'countryVisibilityMode'  => 'show_if',
				'showCountries'          => array(),
				'hideCountries'          => array(),
				'enableVisibilityRules'  => false,
				'visibilityRulesMode'    => 'show_if',
				'portableTargeting'      => '',
				'appliedVisibilityRuleId' => '',
				'visibilityRuleLibrary'  => '',
				'mode'                   => 'show',
				'usePortableTargeting'   => false,
			)
		);

		$show_countries = is_array( $attrs['showCountries'] ) ? $attrs['showCountries'] : array();
		$hide_countries = is_array( $attrs['hideCountries'] ) ? $attrs['hideCountries'] : array();
		$legacy_portable_on = ! empty( $attrs['usePortableTargeting'] ) || '' !== trim( (string) $attrs['portableTargeting'] );

		$country_on    = ! empty( $attrs['enableCountryTargeting'] );
		$visibility_on = ! empty( $attrs['enableVisibilityRules'] );

		if ( ! $country_on && ! $visibility_on ) {
			if ( $legacy_portable_on ) {
				$visibility_on = true;
			} elseif ( ! empty( $show_countries ) || ! empty( $hide_countries ) ) {
				$country_on = true;
			}
		}

		$country_mode = self::normalize_mode_value( $has_country_visibility_mode ? $attrs['countryVisibilityMode'] : ( $attrs['mode'] ?? 'show_if' ) );
		$countries    = $show_countries;
		if ( $country_on && empty( $show_countries ) && ! empty( $hide_countries ) ) {
			$countries    = $hide_countries;
			$country_mode = 'hide_if';
		}

		$settings = array(
			'egp_enable_geo_targeting'        => $country_on ? 'yes' : '',
			'rwgc_country_visibility_mode'    => $country_mode,
			'egp_countries'                   => $countries,
			'rwgc_enable_visibility_rules'    => $visibility_on ? 'yes' : '',
			'rwgc_visibility_rules_mode'      => self::normalize_mode_value( $attrs['visibilityRulesMode'] ?? 'show_if' ),
			'rwgc_portable_geo_targeting'     => (string) ( $attrs['portableTargeting'] ?? '' ),
			'rwgc_applied_visibility_rule_id' => (string) ( $attrs['appliedVisibilityRuleId'] ?? '' ),
			'rwgc_visibility_rule_library'    => (string) ( $attrs['visibilityRuleLibrary'] ?? '' ),
			'rwgc_use_portable_geo_targeting' => $visibility_on ? 'yes' : '',
		);

		return self::normalize( $settings );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function from_post_meta( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array();
		}

		$legacy_enabled = 'yes' === (string) get_post_meta( $post_id, '_rwgc_post_geo_enabled', true );
		$country_on     = 'yes' === (string) get_post_meta( $post_id, '_rwgc_post_country_enabled', true );
		$visibility_on  = 'yes' === (string) get_post_meta( $post_id, '_rwgc_post_visibility_rules_enabled', true );

		if ( ! $country_on && ! $visibility_on && $legacy_enabled ) {
			$use_portable = 'yes' === (string) get_post_meta( $post_id, '_rwgc_post_use_portable_targeting', true );
			if ( $use_portable ) {
				$visibility_on = true;
			} else {
				$country_on = true;
			}
		}

		$mode = (string) get_post_meta( $post_id, '_rwgc_post_geo_mode', true );
		if ( '' === (string) get_post_meta( $post_id, '_rwgc_post_country_visibility_mode', true ) && '' !== $mode ) {
			$country_mode = $mode;
		} else {
			$country_mode = (string) get_post_meta( $post_id, '_rwgc_post_country_visibility_mode', true );
		}

		$visibility_mode = (string) get_post_meta( $post_id, '_rwgc_post_visibility_rules_mode', true );
		if ( '' === $visibility_mode ) {
			$visibility_mode = $mode;
		}

		$settings = array(
			'egp_enable_geo_targeting'        => $country_on ? 'yes' : '',
			'rwgc_country_visibility_mode'    => self::normalize_mode_value( $country_mode ),
			'egp_countries'                   => get_post_meta( $post_id, '_rwgc_post_geo_countries', true ),
			'rwgc_enable_visibility_rules'    => $visibility_on ? 'yes' : '',
			'rwgc_visibility_rules_mode'      => self::normalize_mode_value( $visibility_mode ),
			'rwgc_use_portable_geo_targeting' => 'yes' === (string) get_post_meta( $post_id, '_rwgc_post_use_portable_targeting', true ) ? 'yes' : '',
			'rwgc_portable_geo_targeting'     => (string) get_post_meta( $post_id, '_rwgc_post_portable_targeting', true ),
			'rwgc_applied_visibility_rule_id' => (string) get_post_meta( $post_id, '_rwgc_post_applied_visibility_rule_id', true ),
			'rwgc_visibility_rule_library'    => (string) get_post_meta( $post_id, '_rwgc_post_visibility_rule_library', true ),
		);

		return self::normalize( $settings );
	}

	/**
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return array<string, mixed>
	 */
	public static function from_shortcode_atts( array $atts ) {
		$country_on    = ! empty( $atts['country_on'] );
		$visibility_on = ! empty( $atts['visibility_on'] );
		$rule_id       = isset( $atts['rule'] ) ? trim( (string) $atts['rule'] ) : '';
		if ( '' === $rule_id && isset( $atts['visibility_rule'] ) ) {
			$rule_id = trim( (string) $atts['visibility_rule'] );
		}

		if ( ! $visibility_on && '' !== $rule_id ) {
			$visibility_on = true;
		}

		if ( ! $country_on && ! empty( $atts['country'] ) ) {
			$country_on = true;
		}

		$countries = array();
		if ( ! empty( $atts['country'] ) ) {
			$countries = array_filter( array_map( 'trim', explode( ',', strtoupper( (string) $atts['country'] ) ) ) );
		}

		$settings = array(
			'egp_enable_geo_targeting'        => $country_on ? 'yes' : '',
			'rwgc_country_visibility_mode'    => self::normalize_mode_value( $atts['country_mode'] ?? 'show_if' ),
			'egp_countries'                   => $countries,
			'rwgc_enable_visibility_rules'    => $visibility_on ? 'yes' : '',
			'rwgc_visibility_rules_mode'      => self::normalize_mode_value( $atts['mode'] ?? 'show_if' ),
			'rwgc_visibility_rule_library'    => $rule_id,
			'rwgc_applied_visibility_rule_id' => $rule_id,
			'rwgc_portable_geo_targeting'     => isset( $atts['portable'] ) ? (string) $atts['portable'] : '',
		);

		return self::normalize( $settings );
	}

	/**
	 * @param mixed $mode Raw mode.
	 * @return string
	 */
	private static function normalize_mode_value( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( 'hide' === $mode || 'hide_if' === $mode ) {
			return 'hide_if';
		}
		return 'show_if';
	}
}
