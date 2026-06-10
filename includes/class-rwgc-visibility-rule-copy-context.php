<?php
/**
 * Structured targeting context for Geo AI copy adaptation (portable rule → brief).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds marketer- and model-facing targeting summaries from visibility rules.
 */
class RWGC_Visibility_Rule_Copy_Context {

	/**
	 * @param int $rule_id Visibility rule post ID.
	 * @return array<string, mixed>
	 */
	public static function from_rule_id( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 || ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return self::empty_context();
		}
		$post = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
		if ( ! $post ) {
			return self::empty_context();
		}
		$set = RWGC_Visibility_Rule_Repository::get_rule_set( $rule_id );
		$ctx = self::from_rule_set( $set );
		$ctx['rule_id']    = $rule_id;
		$ctx['rule_title'] = get_the_title( $post );
		return $ctx;
	}

	/**
	 * Synthetic context when only country codes were chosen (no library rule yet).
	 *
	 * @param array<int, string> $country_codes ISO2 list.
	 * @return array<string, mixed>
	 */
	public static function from_country_codes( array $country_codes ) {
		$codes = array();
		foreach ( $country_codes as $code ) {
			$iso = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
			if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
				$codes[] = $iso;
			}
		}
		$codes = array_values( array_unique( $codes ) );
		if ( empty( $codes ) ) {
			return self::empty_context();
		}
		$set = array(
			'rules' => array(
				array(
					'conditions' => array(
						array(
							'type'     => 'country',
							'operator' => 'in',
							'value'    => $codes,
						),
					),
				),
			),
		);
		$ctx = self::from_rule_set( $set );
		$ctx['rule_id']    = 0;
		$ctx['rule_title'] = '';
		return $ctx;
	}

	/**
	 * @param int $page_id Master or variant page ID.
	 * @return array<string, mixed>
	 */
	public static function from_page_experience_meta( $page_id ) {
		$page_id = absint( $page_id );
		if ( $page_id <= 0 ) {
			return self::empty_context();
		}
		$rule_id = (int) get_post_meta( $page_id, '_rwgc_experience_visibility_rule_id', true );
		if ( $rule_id > 0 ) {
			return self::from_rule_id( $rule_id );
		}
		return self::empty_context();
	}

	/**
	 * @param array<string, mixed>|null $set Portable rule set.
	 * @return array<string, mixed>
	 */
	public static function from_rule_set( $set ) {
		$out = self::empty_context();
		if ( ! is_array( $set ) || empty( $set['rules'] ) || ! is_array( $set['rules'] ) ) {
			return $out;
		}

		$lines      = array();
		$conditions = array();
		$geo_codes  = array();
		$devices    = array();
		$campaigns  = array();

		foreach ( $set['rules'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
				continue;
			}
			foreach ( $rule['conditions'] as $cond ) {
				if ( ! is_array( $cond ) ) {
					continue;
				}
				$parsed = self::parse_condition( $cond );
				if ( '' === $parsed['label'] ) {
					continue;
				}
				$lines[]      = $parsed['label'];
				$conditions[] = $parsed;
				if ( ! empty( $parsed['geo_codes'] ) ) {
					$geo_codes = array_merge( $geo_codes, $parsed['geo_codes'] );
				}
				if ( ! empty( $parsed['devices'] ) ) {
					$devices = array_merge( $devices, $parsed['devices'] );
				}
				if ( ! empty( $parsed['campaigns'] ) ) {
					$campaigns = array_merge( $campaigns, $parsed['campaigns'] );
				}
			}
		}

		$geo_codes = array_values( array_unique( $geo_codes ) );
		$devices   = array_values( array_unique( $devices ) );
		$campaigns = array_values( array_unique( array_filter( $campaigns ) ) );
		$lines     = array_values( array_unique( array_filter( $lines ) ) );

		$out['summary']       = implode( ' · ', $lines );
		$out['conditions']    = $conditions;
		$out['geo_codes']     = $geo_codes;
		$out['device_types']  = $devices;
		$out['campaigns']     = $campaigns;
		$out['primary_geo']   = ! empty( $geo_codes ) ? $geo_codes[0] : '';
		$out['adapt_brief']   = self::build_adapt_brief( $lines, $geo_codes, $devices, $campaigns );

		/**
		 * Filter structured targeting context before Geo AI copy workflows consume it.
		 *
		 * @param array<string, mixed>     $out Context.
		 * @param array<string, mixed>|null $set Source rule set.
		 */
		return apply_filters( 'rwgc_visibility_rule_copy_context', $out, $set );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function empty_context() {
		return array(
			'rule_id'       => 0,
			'rule_title'    => '',
			'summary'       => '',
			'adapt_brief'   => '',
			'conditions'    => array(),
			'geo_codes'     => array(),
			'device_types'  => array(),
			'campaigns'     => array(),
			'primary_geo'   => '',
		);
	}

	/**
	 * @param array<string, mixed> $cond Raw condition row.
	 * @return array<string, mixed>
	 */
	private static function parse_condition( array $cond ) {
		$type = isset( $cond['type'] ) ? sanitize_key( (string) $cond['type'] ) : '';
		$op   = isset( $cond['operator'] ) ? sanitize_key( (string) $cond['operator'] ) : 'in';
		$val  = isset( $cond['value'] ) ? $cond['value'] : '';

		$parsed = array(
			'type'      => $type,
			'operator'  => $op,
			'value'     => $val,
			'label'     => '',
			'geo_codes' => array(),
			'devices'   => array(),
			'campaigns' => array(),
		);

		if ( 'country' === $type ) {
			$codes = is_array( $val ) ? $val : array( $val );
			$labels = array();
			foreach ( $codes as $code ) {
				$iso = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
				if ( ! preg_match( '/^[A-Z]{2}$/', $iso ) ) {
					continue;
				}
				$parsed['geo_codes'][] = $iso;
				$labels[]              = self::country_label( $iso );
			}
			if ( ! empty( $labels ) ) {
				$parsed['label'] = sprintf(
					/* translators: %s: country list */
					__( 'Country: %s', 'reactwoo-geocore' ),
					implode( ', ', $labels )
				);
			}
		} elseif ( in_array( $type, array( 'device', 'device_type' ), true ) ) {
			$devices = is_array( $val ) ? $val : array( $val );
			foreach ( $devices as $d ) {
				$d = strtolower( sanitize_key( (string) $d ) );
				if ( '' !== $d ) {
					$parsed['devices'][] = $d;
				}
			}
			if ( ! empty( $parsed['devices'] ) ) {
				$parsed['label'] = sprintf(
					/* translators: %s: device types */
					__( 'Device: %s', 'reactwoo-geocore' ),
					implode( ', ', $parsed['devices'] )
				);
			}
		} elseif ( in_array( $type, array( 'campaign', 'utm_campaign' ), true ) ) {
			$camps = is_array( $val ) ? $val : array( $val );
			foreach ( $camps as $c ) {
				$c = sanitize_text_field( (string) $c );
				if ( '' !== $c ) {
					$parsed['campaigns'][] = $c;
				}
			}
			if ( ! empty( $parsed['campaigns'] ) ) {
				$parsed['label'] = sprintf(
					/* translators: %s: campaign names */
					__( 'Campaign: %s', 'reactwoo-geocore' ),
					implode( ', ', $parsed['campaigns'] )
				);
			}
		} elseif ( in_array( $type, array( 'source', 'medium', 'term', 'content' ), true ) ) {
			$text = is_array( $val ) ? implode( ', ', array_map( 'strval', $val ) ) : sanitize_text_field( (string) $val );
			if ( '' !== $text ) {
				$parsed['label'] = ucfirst( $type ) . ': ' . $text;
			}
		} elseif ( 'audience' === $type || false !== strpos( $type, 'audience' ) ) {
			$text = is_array( $val ) ? implode( ', ', array_map( 'strval', $val ) ) : sanitize_text_field( (string) $val );
			if ( '' !== $text ) {
				$parsed['label'] = sprintf(
					/* translators: %s: audience label */
					__( 'Audience: %s', 'reactwoo-geocore' ),
					$text
				);
			}
		} elseif ( '' !== $type ) {
			$text = is_array( $val ) ? wp_json_encode( $val ) : sanitize_text_field( (string) $val );
			if ( is_string( $text ) && '' !== $text ) {
				$parsed['label'] = ucfirst( str_replace( '_', ' ', $type ) ) . ': ' . $text;
			}
		}

		return $parsed;
	}

	/**
	 * @param string $iso2 ISO2 code.
	 * @return string
	 */
	private static function country_label( $iso2 ) {
		$iso2 = strtoupper( substr( sanitize_text_field( (string) $iso2 ), 0, 2 ) );
		if ( class_exists( 'RWGC_Countries', false ) ) {
			$opts = RWGC_Countries::get_options();
			if ( isset( $opts[ $iso2 ] ) ) {
				return (string) $opts[ $iso2 ] . ' (' . $iso2 . ')';
			}
		}
		return $iso2;
	}

	/**
	 * @param array<int, string> $lines     Human condition lines.
	 * @param array<int, string> $geo_codes ISO2 codes.
	 * @param array<int, string> $devices   Device slugs.
	 * @param array<int, string> $campaigns Campaign names.
	 * @return string
	 */
	private static function build_adapt_brief( array $lines, array $geo_codes, array $devices, array $campaigns ) {
		$parts = array();
		if ( ! empty( $lines ) ) {
			$parts[] = sprintf(
				/* translators: %s: targeting summary */
				__( 'Adapt this page for visitors matching: %s.', 'reactwoo-geocore' ),
				implode( '; ', $lines )
			);
		}
		if ( ! empty( $geo_codes ) ) {
			$geo_labels = array_map( array( __CLASS__, 'country_label' ), array_slice( $geo_codes, 0, 6 ) );
			$parts[]    = sprintf(
				/* translators: %s: country names */
				__( 'Use locale-appropriate spelling, currency hints, and cultural tone for %s.', 'reactwoo-geocore' ),
				implode( ', ', $geo_labels )
			);
		}
		if ( in_array( 'mobile', $devices, true ) || in_array( 'tablet', $devices, true ) ) {
			$parts[] = __( 'Keep headlines short and scannable for mobile screens.', 'reactwoo-geocore' );
		}
		if ( ! empty( $campaigns ) ) {
			$parts[] = sprintf(
				/* translators: %s: campaign name */
				__( 'Align the message with campaign “%s”.', 'reactwoo-geocore' ),
				$campaigns[0]
			);
		}
		return implode( ' ', $parts );
	}
}
