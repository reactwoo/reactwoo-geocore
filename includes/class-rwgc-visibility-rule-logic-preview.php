<?php
/**
 * Human-readable numbered logic preview for visibility rules.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds explicit AND/OR logic copy for the rule editor preview panel.
 */
class RWGC_Visibility_Rule_Logic_Preview {

	/**
	 * @param array<string,mixed>|null $set          Portable rule set (decoded or sanitized).
	 * @param string                   $target_label Popup / surface label.
	 * @return array{intro:string,lines:array<int,array<string,mixed>>}
	 */
	public static function build( $set, $target_label = '' ) {
		$target_label = trim( (string) $target_label );
		$intro        = '' !== $target_label
			? sprintf(
				/* translators: %s: popup or surface label */
				__( 'This rule shows %s when all of these are true:', 'reactwoo-geocore' ),
				$target_label
			)
			: __( 'This rule matches when all of these are true:', 'reactwoo-geocore' );

		$lines = array();
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return array(
				'intro' => $intro,
				'lines' => array(),
			);
		}

		$index = 1;
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$built = self::condition_line( $cond );
			if ( null === $built ) {
				continue;
			}
			$built['number'] = $index++;
			$lines[]         = $built;
		}

		return array(
			'intro' => $intro,
			'lines' => $lines,
		);
	}

	/**
	 * Compact bullet list for the rule tester modal (no intro/numbers).
	 *
	 * @param array<string,mixed>|null $set Portable rule set.
	 * @return array<int,array{text:string,children:array<int,string>}>
	 */
	public static function build_compact( $set ) {
		$items = array();
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $items;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$built = self::compact_condition_line( $cond );
			if ( null === $built ) {
				continue;
			}
			$items[] = $built;
		}
		return $items;
	}

	/**
	 * @param array<int,array<string,mixed>> $conds Branch conditions.
	 * @return bool
	 */
	public static function is_google_ads_branch( array $conds ) {
		return self::branch_is_google_ads( $conds );
	}

	/**
	 * @param array<string,mixed> $cond Condition row.
	 * @return array<string,mixed>|null
	 */
	private static function condition_line( array $cond ) {
		$type = (string) ( $cond['type'] ?? '' );
		$op   = (string) ( $cond['operator'] ?? '' );
		$val  = $cond['value'] ?? array();

		if ( 'country' === $type && in_array( $op, array( 'in', 'is' ), true ) ) {
			return array(
				'text'     => sprintf(
					__( 'Visitor country is %s.', 'reactwoo-geocore' ),
					self::or_list( self::country_labels( $val ) )
				),
				'children' => array(),
			);
		}
		if ( 'country' === $type && in_array( $op, array( 'not_in', 'is_not' ), true ) ) {
			return array(
				'text'     => sprintf(
					__( 'Visitor is not from %s.', 'reactwoo-geocore' ),
					self::or_list( self::country_labels( $val ) )
				),
				'children' => array(),
			);
		}
		if ( in_array( $type, array( 'device', 'device_type' ), true ) ) {
			$labels = array_map( 'ucfirst', self::string_list( $val ) );
			return array(
				'text'     => sprintf( __( 'Device is %s.', 'reactwoo-geocore' ), self::or_list( $labels ) ),
				'children' => array(),
			);
		}
		if ( 'page_type' === $type ) {
			$labels = array();
			foreach ( self::string_list( $val ) as $slug ) {
				$labels[] = self::page_type_label( $slug );
			}
			return array(
				'text'     => sprintf( __( 'Page type is %s.', 'reactwoo-geocore' ), self::or_list( $labels ) ),
				'children' => array(),
			);
		}
		if ( 'condition_group' === $type && is_array( $val ) ) {
			$children = array();
			foreach ( (array) ( $val['branches'] ?? array() ) as $branch ) {
				if ( ! is_array( $branch ) ) {
					continue;
				}
				$child = self::branch_preview_line( $branch );
				if ( '' !== $child ) {
					$children[] = $child;
				}
			}
			if ( empty( $children ) ) {
				return null;
			}
			return array(
				'text'     => __( 'Traffic matches either:', 'reactwoo-geocore' ),
				'children' => $children,
			);
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $cond Condition row.
	 * @return array{text:string,children:array<int,string>}|null
	 */
	private static function compact_condition_line( array $cond ) {
		$type = (string) ( $cond['type'] ?? '' );
		$op   = (string) ( $cond['operator'] ?? '' );
		$val  = $cond['value'] ?? array();

		if ( 'country' === $type && in_array( $op, array( 'in', 'is' ), true ) ) {
			return array(
				'text'     => sprintf(
					__( 'Visitor country is any of %s', 'reactwoo-geocore' ),
					self::or_list_upper( self::country_labels( $val ) )
				),
				'children' => array(),
			);
		}
		if ( 'country' === $type && in_array( $op, array( 'not_in', 'is_not' ), true ) ) {
			return array(
				'text'     => sprintf(
					__( 'Visitor country is not any of %s', 'reactwoo-geocore' ),
					self::or_list_upper( self::country_labels( $val ) )
				),
				'children' => array(),
			);
		}
		if ( in_array( $type, array( 'device', 'device_type' ), true ) ) {
			$labels = array_map( 'ucfirst', self::string_list( $val ) );
			return array(
				'text'     => sprintf( __( 'Device is %s', 'reactwoo-geocore' ), self::or_list_upper( $labels ) ),
				'children' => array(),
			);
		}
		if ( 'page_type' === $type ) {
			$labels = array();
			foreach ( self::string_list( $val ) as $slug ) {
				$labels[] = self::page_type_label( $slug );
			}
			return array(
				'text'     => sprintf( __( 'Page type is %s', 'reactwoo-geocore' ), self::or_list_upper( $labels ) ),
				'children' => array(),
			);
		}
		if ( 'condition_group' === $type && is_array( $val ) ) {
			$children = array();
			foreach ( (array) ( $val['branches'] ?? array() ) as $branch ) {
				if ( ! is_array( $branch ) ) {
					continue;
				}
				$child = self::branch_preview_line( $branch );
				if ( '' !== $child ) {
					$children[] = $child;
				}
			}
			if ( empty( $children ) ) {
				return null;
			}
			return array(
				'text'     => __( 'Traffic matches any:', 'reactwoo-geocore' ),
				'children' => $children,
			);
		}

		return null;
	}

	/**
	 * @param array<int,string> $labels Labels.
	 * @return string
	 */
	private static function or_list_upper( array $labels ) {
		$labels = array_values( array_filter( $labels ) );
		if ( empty( $labels ) ) {
			return '';
		}
		if ( 1 === count( $labels ) ) {
			return $labels[0];
		}
		return implode( ' OR ', $labels );
	}

	/**
	 * @param array<string,mixed> $branch Group branch.
	 * @return string
	 */
	private static function branch_preview_line( array $branch ) {
		$label = trim( (string) ( $branch['label'] ?? '' ) );
		$conds = (array) ( $branch['conditions'] ?? array() );
		if ( self::branch_is_google_ads( $conds ) ) {
			return __( 'Google Ads standard UTM: utm_source=google and utm_medium=cpc', 'reactwoo-geocore' );
		}
		foreach ( $conds as $cond ) {
			if ( ! is_array( $cond ) || 'request_uri' !== (string) ( $cond['type'] ?? '' ) ) {
				continue;
			}
			$path = self::string_list( $cond['value'] ?? array() );
			$path = ! empty( $path[0] ) ? $path[0] : '';
			if ( '' !== $path ) {
				return sprintf( __( 'URL contains %s', 'reactwoo-geocore' ), $path );
			}
		}
		return '' !== $label ? $label : '';
	}

	/**
	 * @param array<int,array<string,mixed>> $conds Branch conditions.
	 * @return bool
	 */
	private static function branch_is_google_ads( array $conds ) {
		$has_source = false;
		$has_medium = false;
		foreach ( $conds as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$type = (string) ( $cond['type'] ?? '' );
			$vals = self::string_list( $cond['value'] ?? array() );
			if ( 'utm_source' === $type && in_array( 'google', array_map( 'strtolower', $vals ), true ) ) {
				$has_source = true;
			}
			if ( 'utm_medium' === $type && in_array( 'cpc', array_map( 'strtolower', $vals ), true ) ) {
				$has_medium = true;
			}
		}
		return $has_source && $has_medium;
	}

	/**
	 * @param array<int,string> $labels Labels.
	 * @return string
	 */
	private static function or_list( array $labels ) {
		$labels = array_values( array_filter( $labels ) );
		if ( empty( $labels ) ) {
			return '';
		}
		if ( 1 === count( $labels ) ) {
			return $labels[0];
		}
		return implode( ' ' . __( 'or', 'reactwoo-geocore' ) . ' ', $labels );
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
	private static function country_labels( $val ) {
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
}
