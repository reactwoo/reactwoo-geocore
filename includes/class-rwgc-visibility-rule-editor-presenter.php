<?php
/**
 * Sidebar summary + validation for the visibility rule editor screen.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds human-readable rule editor panels from portable JSON + assistant meta.
 */
class RWGC_Visibility_Rule_Editor_Presenter {

	/**
	 * @param int    $post_id      Rule post ID.
	 * @param string $title        Rule title.
	 * @param string $status       publish|draft.
	 * @param string $portable_raw Portable JSON.
	 * @return array<string,mixed>
	 */
	public static function build( $post_id, $title, $status, $portable_raw ) {
		$post_id = absint( $post_id );
		$set     = null;
		if ( class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			$set = RWGC_Targeting_Rule_Set_Schema::sanitize( $portable_raw );
		}
		$assistant = self::assistant_meta( $post_id );
		$target    = self::target_label( $title, $assistant );
		$chips     = self::summary_chips( $set );
		$summary   = self::summary_sentence( $target, $chips );
		$warnings  = self::validation_warnings( $set, $portable_raw );

		return array(
			'target_label' => $target,
			'summary'      => $summary,
			'chips'        => $chips,
			'status_label' => 'publish' === $status ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
			'status_slug'  => $status,
			'valid'        => empty( $warnings ),
			'warnings'     => $warnings,
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private static function assistant_meta( $post_id ) {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return array();
		}
		$raw = get_post_meta( $post_id, '_rwga_assistant_source', true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param string               $title     Rule title.
	 * @param array<string,mixed>  $assistant Assistant meta.
	 * @return string
	 */
	private static function target_label( $title, array $assistant ) {
		$label = trim( (string) ( $assistant['target_label'] ?? '' ) );
		if ( '' !== $label ) {
			return $label;
		}
		if ( preg_match( '/—\s*(.+)$/u', (string) $title, $m ) ) {
			return trim( (string) $m[1] );
		}
		return '';
	}

	/**
	 * @param array<string,mixed>|null $set Portable rule set.
	 * @return array<string,array<int,string>>
	 */
	private static function summary_chips( $set ) {
		$out = array(
			'include'  => array(),
			'exclude'  => array(),
			'device'   => array(),
			'page'     => array(),
			'traffic'  => array(),
		);
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $out;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$type = (string) ( $cond['type'] ?? '' );
			$op   = (string) ( $cond['operator'] ?? '' );
			$val  = $cond['value'] ?? array();
			if ( 'country' === $type && in_array( $op, array( 'in', 'is' ), true ) ) {
				$out['include'] = array_merge( $out['include'], self::country_chip_labels( $val ) );
			} elseif ( 'country' === $type && in_array( $op, array( 'not_in', 'is_not' ), true ) ) {
				$out['exclude'] = array_merge( $out['exclude'], self::country_chip_labels( $val ) );
			} elseif ( in_array( $type, array( 'device', 'device_type' ), true ) ) {
				$out['device'] = array_merge( $out['device'], self::string_list( $val ) );
			} elseif ( 'page_type' === $type ) {
				foreach ( self::string_list( $val ) as $page ) {
					$out['page'][] = self::page_type_label( $page );
				}
			} elseif ( 'condition_group' === $type && is_array( $val ) ) {
				foreach ( (array) ( $val['branches'] ?? array() ) as $branch ) {
					if ( ! is_array( $branch ) ) {
						continue;
					}
					$label = trim( (string) ( $branch['label'] ?? '' ) );
					if ( '' !== $label ) {
						$out['traffic'][] = self::traffic_chip_label( $label );
					}
				}
			}
		}
		foreach ( $out as $key => $list ) {
			$out[ $key ] = array_values( array_unique( array_filter( $list ) ) );
		}
		return $out;
	}

	/**
	 * @param mixed $val Values.
	 * @return array<int,string>
	 */
	private static function string_list( $val ) {
		if ( ! is_array( $val ) ) {
			$val = array( $val );
		}
		$out = array();
		foreach ( $val as $item ) {
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * @param mixed $val ISO codes.
	 * @return array<int,string>
	 */
	private static function country_chip_labels( $val ) {
		$labels = array();
		foreach ( self::string_list( $val ) as $code ) {
			$labels[] = self::country_label( $code );
		}
		return $labels;
	}

	/**
	 * @param string $code ISO2.
	 * @return string
	 */
	private static function country_label( $code ) {
		$code = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
		if ( class_exists( 'RWGC_Countries', false ) ) {
			$opts = RWGC_Countries::get_options();
			if ( isset( $opts[ $code ] ) ) {
				return (string) $opts[ $code ];
			}
		}
		return $code;
	}

	/**
	 * @param string $slug Page type slug.
	 * @return string
	 */
	private static function page_type_label( $slug ) {
		$map = array(
			'product'  => __( 'Product pages', 'reactwoo-geocore' ),
			'category' => __( 'Category pages', 'reactwoo-geocore' ),
			'homepage' => __( 'Homepage', 'reactwoo-geocore' ),
			'shop'     => __( 'Shop', 'reactwoo-geocore' ),
			'cart'     => __( 'Cart', 'reactwoo-geocore' ),
			'checkout' => __( 'Checkout', 'reactwoo-geocore' ),
		);
		$slug = sanitize_key( (string) $slug );
		return $map[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) );
	}

	/**
	 * @param string $label Branch label from stored group.
	 * @return string
	 */
	private static function traffic_chip_label( $label ) {
		$label = trim( (string) $label );
		if ( preg_match( '/google\s+ads/i', $label ) ) {
			return __( 'Google Ads', 'reactwoo-geocore' );
		}
		if ( preg_match( '/url\s+contains/i', $label ) ) {
			return $label;
		}
		return $label;
	}

	/**
	 * @param string                    $target Target label.
	 * @param array<string,array<int,string>> $chips Chips.
	 * @return string
	 */
	private static function summary_sentence( $target, array $chips ) {
		if ( '' !== $target && ! empty( $chips['include'] ) && ! empty( $chips['device'] ) && ! empty( $chips['page'] ) ) {
			$sentence = sprintf(
				/* translators: 1: target, 2: device list, 3: page list, 4: include countries, 5: exclude clause, 6: traffic clause */
				__( 'This rule shows %1$s to %2$s visitors on %3$s from %4$s%5$s%6$s.', 'reactwoo-geocore' ),
				$target,
				strtolower( implode( ', ', $chips['device'] ) ),
				strtolower( implode( ', ', $chips['page'] ) ),
				implode( ' ' . __( 'or', 'reactwoo-geocore' ) . ' ', $chips['include'] ),
				! empty( $chips['exclude'] )
					? ', ' . sprintf(
						__( 'excluding %s', 'reactwoo-geocore' ),
						implode( ' ' . __( 'and', 'reactwoo-geocore' ) . ' ', $chips['exclude'] )
					)
					: '',
				! empty( $chips['traffic'] )
					? ', ' . sprintf(
						__( 'when they came from %s', 'reactwoo-geocore' ),
						implode( ' ' . __( 'or', 'reactwoo-geocore' ) . ' ', $chips['traffic'] )
					)
					: ''
			);
			return $sentence;
		}

		$parts = array();
		if ( '' !== $target ) {
			$parts[] = sprintf(
				/* translators: %s: popup or surface label */
				__( 'This rule targets %s.', 'reactwoo-geocore' ),
				$target
			);
		}
		if ( ! empty( $chips['include'] ) ) {
			$parts[] = sprintf(
				__( 'Includes visitors from %s.', 'reactwoo-geocore' ),
				implode( ' ' . __( 'or', 'reactwoo-geocore' ) . ' ', $chips['include'] )
			);
		}
		if ( ! empty( $chips['exclude'] ) ) {
			$parts[] = sprintf(
				__( 'Excludes visitors from %s.', 'reactwoo-geocore' ),
				implode( ' ' . __( 'or', 'reactwoo-geocore' ) . ' ', $chips['exclude'] )
			);
		}
		if ( ! empty( $chips['device'] ) ) {
			$parts[] = sprintf( __( 'Device: %s.', 'reactwoo-geocore' ), implode( ', ', $chips['device'] ) );
		}
		if ( ! empty( $chips['page'] ) ) {
			$parts[] = sprintf( __( 'Page: %s.', 'reactwoo-geocore' ), implode( ', ', $chips['page'] ) );
		}
		if ( ! empty( $chips['traffic'] ) ) {
			$parts[] = sprintf(
				__( 'Traffic: %s.', 'reactwoo-geocore' ),
				implode( ' ' . __( 'OR', 'reactwoo-geocore' ) . ' ', $chips['traffic'] )
			);
		}
		if ( empty( $parts ) ) {
			return __( 'Add conditions to define who should match this rule.', 'reactwoo-geocore' );
		}
		return implode( ' ', $parts );
	}

	/**
	 * @param array<string,mixed>|null $set          Sanitized set.
	 * @param string                   $portable_raw Raw JSON.
	 * @return array<int,string>
	 */
	private static function validation_warnings( $set, $portable_raw ) {
		$warnings = array();
		if ( null === $set ) {
			$warnings[] = __( 'Missing or invalid stored rule data.', 'reactwoo-geocore' );
			return $warnings;
		}
		$conds = (array) ( $set['rules'][0]['conditions'] ?? array() );
		if ( empty( $conds ) ) {
			$warnings[] = __( 'Missing condition', 'reactwoo-geocore' );
		}
		$raw = is_string( $portable_raw ) ? json_decode( $portable_raw, true ) : null;
		if ( is_array( $raw ) && ! empty( $raw['rules'][0]['conditions'] ) && count( $raw['rules'][0]['conditions'] ) > count( $conds ) ) {
			$warnings[] = __( 'This rule contains advanced logic. Some conditions are shown in read-only mode.', 'reactwoo-geocore' );
		}
		return $warnings;
	}
}
