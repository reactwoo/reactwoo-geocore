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

	const VERSION = 1;

	/** @var string[] */
	const PRO_CONDITION_TYPES = array( 'campaign', 'audience', 'time' );

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
		if ( in_array( $type, self::PRO_CONDITION_TYPES, true ) ) {
			return true;
		}
		return 0 === strpos( $type, 'reactwoo:' );
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
			'mode'           => self::sanitize_mode( isset( $data['mode'] ) ? $data['mode'] : 'show' ),
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
		return 'hide' === $m ? 'hide' : 'show';
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
}
