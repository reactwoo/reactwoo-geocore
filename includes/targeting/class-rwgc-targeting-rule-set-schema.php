<?php
/**
 * Portable targeting rule-set schema (JSON) — sanitize and version.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes rule documents shared by Elementor, blocks, and future admin UIs.
 */
class RWGC_Targeting_Rule_Set_Schema {

	const VERSION = 2;

	/**
	 * Condition types available without GeoCore Pro (engine-level; UI may still emphasise country-only).
	 *
	 * @var string[]
	 */
	const FREE_CONDITION_TYPES = array(
		'country',
		'country_group',
		'language',
		'locale',
		'device',
		'device_type',
		'time_of_day',
		'day_of_week',
		'logged_in',
		'page_version_url',
		'page_type',
		'request_uri',
		'condition_group',
	);

	/** @var string[] */
	const PRO_CONDITION_TYPES = array(
		'campaign',
		'utm_campaign',
		'utm_source',
		'utm_medium',
		'audience',
		'time',
		'day',
		'date',
		'weather_facet',
		'weather_condition',
		'temperature',
		'precipitation_probability',
		'wind_speed',
		'humidity',
		'source',
		'medium',
		'gclid',
		'content',
		'term',
		'profile_id',
	);

	/**
	 * Whether GeoCore Pro is active for advanced condition types.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		return (bool) apply_filters( 'rwgc_pro_enabled', false );
	}

	/**
	 * Sanitize a condition type slug (allows `reactwoo:*` — {@see sanitize_key()} strips colons).
	 *
	 * @param mixed $raw Raw type from JSON.
	 * @return string Empty when invalid.
	 */
	public static function sanitize_condition_type_string( $raw ) {
		$s = strtolower( trim( (string) $raw ) );
		if ( '' === $s || strlen( $s ) > 120 ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9_.:*-]+$/', $s ) ) {
			return '';
		}
		return $s;
	}

	/**
	 * Types only available when Pro is active (includes `reactwoo:` audience tokens).
	 *
	 * @param string $type Sanitized condition type.
	 * @return bool
	 */
	public static function is_pro_gated_condition_type( $type ) {
		if ( in_array( $type, self::FREE_CONDITION_TYPES, true ) ) {
			return false;
		}
		if ( in_array( $type, self::PRO_CONDITION_TYPES, true ) ) {
			return true;
		}
		if ( 0 === strpos( $type, 'reactwoo:' ) ) {
			return true;
		}
		/**
		 * Whether a condition type requires GeoCore Pro to persist in sanitized rule JSON.
		 *
		 * @param bool   $gated Default true for unknown extension types unless opted-in via filter.
		 * @param string $type  Sanitized type slug.
		 */
		return (bool) apply_filters( 'rwgc_rule_condition_requires_pro', true, $type );
	}

	/**
	 * Labels and hints for builder UIs (choices are augmented server-side per site).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_rule_target_type_definitions() {
		$defs = array(
			'country'       => array(
				'label'       => __( 'Country', 'reactwoo-geocore' ),
				'pro'         => false,
				'description' => __( 'ISO country codes. Leave empty to match all countries.', 'reactwoo-geocore' ),
			),
			'country_group' => array(
				'label'       => __( 'Country group', 'reactwoo-geocore' ),
				'pro'         => false,
				'description' => __( 'Named country lists. Leave empty to match all visitors.', 'reactwoo-geocore' ),
			),
			'page_version_url' => array(
				'label'       => __( 'Page Version URL', 'reactwoo-geocore' ),
				'pro'         => false,
				'description' => __( 'Show content only on a branded page version URL (`/page/_gc/version-name`).', 'reactwoo-geocore' ),
			),
			'page_type'        => array(
				'label'       => __( 'Page type', 'reactwoo-geocore' ),
				'pro'         => false,
				'description' => __( 'WooCommerce / site page context such as product or category pages.', 'reactwoo-geocore' ),
			),
			'request_uri'      => array(
				'label'       => __( 'Request URL', 'reactwoo-geocore' ),
				'pro'         => false,
				'description' => __( 'Match the current request path or URL fragment.', 'reactwoo-geocore' ),
			),
			'condition_group'  => array(
				'label'       => __( 'Condition group', 'reactwoo-geocore' ),
				'pro'         => false,
				'description' => __( 'Nested match-all / match-any branches (for example Google Ads OR URL contains).', 'reactwoo-geocore' ),
				'hidden'      => true,
			),
			'campaign'      => array(
				'label'       => __( 'Campaign', 'reactwoo-geocore' ),
				'pro'         => true,
				'description' => __( 'UTM / Ads campaign context. Empty means all campaigns.', 'reactwoo-geocore' ),
			),
			'audience'      => array(
				'label'       => __( 'Audience', 'reactwoo-geocore' ),
				'pro'         => true,
				'description' => __( 'Analytics audiences. Empty means all audiences.', 'reactwoo-geocore' ),
			),
			'time'          => array(
				'label'       => __( 'Time window', 'reactwoo-geocore' ),
				'pro'         => true,
				'description' => __( 'Clock-based windows using your selected timezone strategy.', 'reactwoo-geocore' ),
			),
			'weather_facet' => array(
				'label'       => __( 'Shopping weather', 'reactwoo-geocore' ),
				'pro'         => true,
				'description' => __( 'Wet, dry, hot, cold, windy, sunny — derived from your weather provider (GeoCore Pro).', 'reactwoo-geocore' ),
			),
			'weather_condition' => array(
				'label'       => __( 'Weather (legacy)', 'reactwoo-geocore' ),
				'pro'         => true,
				'description' => __( 'Deprecated technical condition slug; prefer Shopping weather.', 'reactwoo-geocore' ),
				'hidden'      => true,
			),
		);

		/**
		 * Portable targeting vocabulary for builders (merge or replace entries).
		 *
		 * @param array<string, array<string, mixed>> $defs Keys are condition type slugs.
		 */
		return apply_filters( 'rwgc_rule_target_types', $defs );
	}

	/**
	 * Decode JSON string or accept array.
	 *
	 * @param mixed $raw JSON string or array.
	 * @return array<string, mixed>|null
	 */
	public static function parse( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Normalize and validate a rule set; drops unknown keys and Pro-only conditions when Pro is off.
	 *
	 * @param mixed $raw Raw input.
	 * @return array<string, mixed>|null Null when unusable (empty rules after sanitize).
	 */
	public static function sanitize( $raw ) {
		$data = self::parse( $raw );
		if ( null === $data ) {
			return null;
		}

		$pro = self::is_pro_active();

		$out = array(
			'schema_version' => self::VERSION,
			'enabled'        => ! empty( $data['enabled'] ),
			'mode'           => self::sanitize_mode( isset( $data['mode'] ) ? $data['mode'] : 'show_if' ),
			'match'          => self::sanitize_rule_match( isset( $data['match'] ) ? $data['match'] : 'any' ),
			'rules'          => array(),
		);

		$rules = isset( $data['rules'] ) && is_array( $data['rules'] ) ? $data['rules'] : array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$san_rule = self::sanitize_rule( $rule, $pro );
			if ( null !== $san_rule ) {
				$out['rules'][] = $san_rule;
			}
		}

		if ( empty( $out['rules'] ) ) {
			return null;
		}

		return $out;
	}

	/**
	 * @param mixed $mode Raw mode.
	 * @return string show|hide
	 */
	public static function sanitize_mode( $mode ) {
		$m = sanitize_key( (string) $mode );
		if ( in_array( $m, array( 'hide_if', 'hide' ), true ) ) {
			return 'hide_if';
		}
		return 'show_if';
	}

	/**
	 * @param mixed $match Raw match.
	 * @return string any|all
	 */
	public static function sanitize_rule_match( $match ) {
		$m = sanitize_key( (string) $match );
		return 'all' === $m ? 'all' : 'any';
	}

	/**
	 * @param array<string, mixed> $rule Raw rule.
	 * @param bool                 $pro   Pro active.
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_rule( array $rule, $pro ) {
		$id = isset( $rule['id'] ) ? sanitize_key( (string) $rule['id'] ) : '';
		if ( '' === $id ) {
			$id = 'rule_' . wp_generate_password( 8, false, false );
		}

		$label = isset( $rule['label'] ) ? sanitize_text_field( (string) $rule['label'] ) : '';

		$conditions_out = array();
		$conds          = isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
		$had_any_cond   = false;
		foreach ( $conds as $c ) {
			if ( is_array( $c ) && ! empty( $c['type'] ) ) {
				$had_any_cond = true;
			}
		}
		foreach ( $conds as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$type = self::sanitize_condition_type_string( isset( $c['type'] ) ? $c['type'] : '' );
			if ( '' === $type ) {
				continue;
			}
			if ( ! $pro && self::is_pro_gated_condition_type( $type ) ) {
				continue;
			}
			$op = isset( $c['operator'] ) ? sanitize_key( (string) $c['operator'] ) : 'in';
			if ( 'page_version_url' === $type ) {
				if ( ! in_array( $op, array( 'equals', 'is', 'is_not' ), true ) ) {
					$op = 'equals';
				}
				if ( ! class_exists( 'RWGC_Page_Version', false ) ) {
					continue;
				}
				$pv_val = RWGC_Page_Version::sanitize_condition_value( isset( $c['value'] ) ? $c['value'] : array() );
				if ( null === $pv_val ) {
					continue;
				}
				$conditions_out[] = array(
					'type'     => $type,
					'operator' => $op,
					'value'    => $pv_val,
				);
				continue;
			}
			if ( 'condition_group' === $type ) {
				$group_val = self::sanitize_condition_group_value( isset( $c['value'] ) ? $c['value'] : array(), $pro );
				if ( null === $group_val ) {
					continue;
				}
				$conditions_out[] = array(
					'type'     => $type,
					'operator' => 'match',
					'value'    => $group_val,
				);
				continue;
			}
			if ( ! RWGC_Target_Operators::is_valid( $op ) ) {
				$op = 'in';
			}
			$conditions_out[] = array(
				'type'     => $type,
				'operator' => $op,
				'value'    => isset( $c['value'] ) ? $c['value'] : array(),
			);
		}

		if ( $had_any_cond && empty( $conditions_out ) ) {
			return null;
		}

		return array(
			'id'         => $id,
			'label'      => $label,
			'match'      => self::sanitize_rule_match( isset( $rule['match'] ) ? $rule['match'] : 'all' ),
			'conditions' => $conditions_out,
		);
	}

	/**
	 * Sanitize nested OR/AND group payload used by Geo Assistant exports.
	 *
	 * @param mixed $raw Raw group value.
	 * @param bool  $pro Pro active.
	 * @return array<string,mixed>|null
	 */
	private static function sanitize_condition_group_value( $raw, $pro ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}
		$branches_out = array();
		$branches     = isset( $raw['branches'] ) && is_array( $raw['branches'] ) ? $raw['branches'] : array();
		foreach ( $branches as $branch ) {
			if ( ! is_array( $branch ) ) {
				continue;
			}
			$conds = self::sanitize_branch_conditions( $branch, $pro );
			if ( empty( $conds ) ) {
				continue;
			}
			$branches_out[] = array(
				'label'      => sanitize_text_field( (string) ( $branch['label'] ?? '' ) ),
				'match'      => self::sanitize_rule_match( isset( $branch['match'] ) ? $branch['match'] : 'all' ),
				'conditions' => $conds,
			);
		}
		if ( count( $branches_out ) < 2 ) {
			return null;
		}
		return array(
			'match'    => self::sanitize_rule_match( isset( $raw['match'] ) ? $raw['match'] : 'any' ),
			'label'    => sanitize_text_field( (string) ( $raw['label'] ?? '' ) ),
			'branches' => $branches_out,
		);
	}

	/**
	 * @param array<string,mixed> $branch Branch row.
	 * @param bool                $pro    Pro active.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitize_branch_conditions( array $branch, $pro ) {
		$conds = isset( $branch['conditions'] ) && is_array( $branch['conditions'] ) ? $branch['conditions'] : array();
		$out   = array();
		foreach ( $conds as $c ) {
			if ( ! is_array( $c ) || empty( $c['type'] ) ) {
				continue;
			}
			$type = self::sanitize_condition_type_string( $c['type'] );
			if ( '' === $type || ( ! $pro && self::is_pro_gated_condition_type( $type ) ) ) {
				continue;
			}
			$op = isset( $c['operator'] ) ? sanitize_key( (string) $c['operator'] ) : 'in';
			if ( ! RWGC_Target_Operators::is_valid( $op ) ) {
				$op = 'in';
			}
			$out[] = array(
				'type'     => $type,
				'operator' => $op,
				'value'    => isset( $c['value'] ) ? $c['value'] : array(),
			);
		}
		return $out;
	}

	/**
	 * Authoring payload for portable JSON (Elementor, Geo Content block, Targeting admin).
	 *
	 * GeoCore Pro extends `audiences` / `campaigns` from synced Google entities.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_editor_context() {
		/**
		 * Structured choice lists for dynamic builder controls (campaign pickers, presets, etc.).
		 *
		 * @param array<string, mixed> $choices Arbitrary keyed lists.
		 */
		$choices = apply_filters( 'rwgc_rule_condition_choices', array() );

		$library = array();
		if ( class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			$library = RWGC_Visibility_Rule_Repository::get_library_picker_rows();
		}

		$weather_connected = (bool) apply_filters( 'rwgc_weather_targets_configured', false );

		$base = array(
			'pro'                  => self::is_pro_active(),
			'weather_connected'    => $weather_connected,
			'weather_facets'       => array(),
			'site_url'             => home_url( '/' ),
			'advanced_targeting'   => function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled(),
			'visibility_library'   => $library,
			'audiences'            => array(),
			'campaigns'            => array(),
			'countries'            => self::build_country_choice_rows(),
			'help_urls'           => array(
				'geocore_targeting' => admin_url( 'admin.php?page=rwgc-target-types' ),
			),
			'rule_target_types'   => self::get_rule_target_type_definitions(),
			'rule_condition_choices' => is_array( $choices ) ? $choices : array(),
			'upgrade_message'     => __( 'Advanced targeting is available in GeoCore Pro.', 'reactwoo-geocore' ),
			'ui_surfaces'         => array(
				'elementor' => __( 'Elementor → page or popup → Advanced → Geo Visibility → enable geo → turn on “Use advanced visibility rules”.', 'reactwoo-geocore' ),
				'block'     => __( 'Block editor → Geo Content block → sidebar → Advanced visibility rules.', 'reactwoo-geocore' ),
				'geo_rule'  => __( 'Targeting → Visibility rules library, or legacy Geo Elementor → Geo Rules while migrating.', 'reactwoo-geocore' ),
			),
		);
		$base['help_urls'] = apply_filters( 'rwgc_rule_builder_help_urls', $base['help_urls'] );
		/**
		 * Extend portable-rule authoring data (synced audiences, campaigns, etc.).
		 *
		 * @param array<string, mixed> $base Default: pro (bool), audiences[], campaigns[], ui_surfaces.
		 *                                     Audience rows: `id`, `name`. Campaign rows: `id`, `name`.
		 */
		return apply_filters( 'rwgc_portable_targeting_editor_context', $base );
	}

	/**
	 * ISO2 rows for rule-builder pickers (code + label).
	 *
	 * @return array<int, array{code:string,label:string}>
	 */
	private static function build_country_choice_rows() {
		if ( ! class_exists( 'RWGC_Countries', false ) ) {
			return array();
		}
		$opts = RWGC_Countries::get_options();
		if ( ! is_array( $opts ) ) {
			return array();
		}
		$out = array();
		foreach ( $opts as $code => $label ) {
			$c = strtoupper( sanitize_text_field( (string) $code ) );
			if ( ! preg_match( '/^[A-Z]{2}$/', $c ) ) {
				continue;
			}
			$out[] = array(
				'code'  => $c,
				'label' => sanitize_text_field( (string) $label ),
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( (string) $a['label'], (string) $b['label'] );
			}
		);
		return $out;
	}
}
