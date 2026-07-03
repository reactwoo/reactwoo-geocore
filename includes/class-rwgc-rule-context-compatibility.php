<?php
/**
 * Portable rule ↔ document/content compatibility (read-only metadata).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determines whether a portable rule's content constraints fit a builder/document context.
 */
class RWGC_Rule_Context_Compatibility {

	/**
	 * @param array<string,mixed>|null $rule_set Portable rule set (decoded).
	 * @param array<string,mixed>      $context  Document/content context.
	 * @return array<string,mixed>
	 */
	public static function evaluate( $rule_set, array $context ) {
		$constraints = self::extract_constraints( $rule_set );
		$actual      = self::normalize_context( $context );

		if ( empty( $constraints['page_types'] ) && empty( $constraints['uri_fragments'] ) ) {
			return array(
				'status'           => 'compatible',
				'reasons'          => array(),
				'scope_summary'    => self::scope_summary( $rule_set ),
				'required_context' => array(),
				'actual_context'   => $actual,
			);
		}

		$reasons = array();
		$status  = 'compatible';

		if ( ! empty( $constraints['page_types'] ) && '' !== (string) ( $actual['page_type'] ?? '' ) ) {
			$required = array_map( 'sanitize_key', $constraints['page_types'] );
			$actual_pt = sanitize_key( (string) $actual['page_type'] );
			if ( ! in_array( $actual_pt, $required, true ) ) {
				$status    = 'incompatible';
				$reasons[] = sprintf(
					/* translators: 1: required page types label, 2: actual page type label */
					__( 'Rule requires %1$s but current context is %2$s.', 'reactwoo-geocore' ),
					self::page_types_label( $required ),
					self::page_type_label( $actual_pt )
				);
			}
		}

		if ( ! empty( $constraints['uri_fragments'] ) ) {
			$uri = strtolower( (string) ( $actual['request_uri'] ?? '' ) );
			if ( '' === $uri ) {
				if ( 'incompatible' !== $status ) {
					$status = 'warning';
				}
				$reasons[] = sprintf(
					/* translators: %s: URL fragment */
					__( 'Rule expects URL containing %s; current URL is unknown here.', 'reactwoo-geocore' ),
					implode( ', ', $constraints['uri_fragments'] )
				);
			} else {
				$matched_uri = false;
				foreach ( $constraints['uri_fragments'] as $fragment ) {
					if ( false !== strpos( $uri, strtolower( (string) $fragment ) ) ) {
						$matched_uri = true;
						break;
					}
				}
				if ( ! $matched_uri ) {
					if ( 'incompatible' !== $status ) {
						$status = 'warning';
					}
					$reasons[] = sprintf(
						/* translators: 1: URL path, 2: expected fragment */
						__( 'Current URL %1$s does not contain %2$s.', 'reactwoo-geocore' ),
						$uri,
						implode( ', ', $constraints['uri_fragments'] )
					);
				}
			}
		}

		return array(
			'status'           => $status,
			'reasons'          => $reasons,
			'scope_summary'    => self::scope_summary( $rule_set ),
			'required_context' => array(
				'page_type'    => ! empty( $constraints['page_types'] ) ? sanitize_key( (string) $constraints['page_types'][0] ) : '',
				'page_types'   => $constraints['page_types'],
				'uri_contains' => $constraints['uri_fragments'],
			),
			'actual_context'   => $actual,
		);
	}

	/**
	 * @param array<string,mixed>|null $rule_set Rule set.
	 * @return string
	 */
	public static function scope_summary( $rule_set ) {
		$constraints = self::extract_constraints( $rule_set );
		if ( empty( $constraints['page_types'] ) && empty( $constraints['uri_fragments'] ) ) {
			return __( 'Any content', 'reactwoo-geocore' );
		}
		$parts = array();
		if ( ! empty( $constraints['page_types'] ) ) {
			$parts[] = self::page_types_label( $constraints['page_types'] );
		}
		if ( ! empty( $constraints['uri_fragments'] ) ) {
			foreach ( $constraints['uri_fragments'] as $frag ) {
				$parts[] = sprintf(
					/* translators: %s: URL path fragment */
					__( 'URL contains %s', 'reactwoo-geocore' ),
					$frag
				);
			}
		}
		return implode( '; ', $parts );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function document_context_from_post( $post_id ) {
		$post_id = absint( $post_id );
		$context = array(
			'post_id'       => $post_id,
			'post_type'     => '',
			'page_type'     => '',
			'request_uri'   => '',
			'document_type' => 'page',
		);
		if ( $post_id <= 0 ) {
			return $context;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return $context;
		}
		$context['post_type'] = (string) $post->post_type;
		if ( class_exists( 'RWGC_Visibility_Rule_Tester', false ) ) {
			$context['page_type'] = RWGC_Visibility_Rule_Tester::page_type_for_post_public( $post );
		} else {
			$context['page_type'] = self::infer_page_type_for_post( $post );
		}
		$link = get_permalink( $post );
		if ( is_string( $link ) && '' !== $link ) {
			$path = wp_parse_url( $link, PHP_URL_PATH );
			$context['request_uri'] = is_string( $path ) ? $path : '';
		}
		if ( 'elementor_library' === $post->post_type ) {
			$context['document_type'] = 'elementor_template';
			$template_type            = (string) get_post_meta( $post_id, '_elementor_template_type', true );
			if ( 'popup' === $template_type ) {
				$context['document_type'] = 'elementor_popup';
			}
		}
		return $context;
	}

	/**
	 * @param array<string,mixed> $context Raw context.
	 * @return array<string,mixed>
	 */
	private static function normalize_context( array $context ) {
		return array(
			'post_id'       => isset( $context['post_id'] ) ? absint( $context['post_id'] ) : 0,
			'post_type'     => isset( $context['post_type'] ) ? sanitize_key( (string) $context['post_type'] ) : '',
			'page_type'     => isset( $context['page_type'] ) ? sanitize_key( (string) $context['page_type'] ) : '',
			'request_uri'   => isset( $context['request_uri'] ) ? (string) $context['request_uri'] : '',
			'document_type' => isset( $context['document_type'] ) ? sanitize_key( (string) $context['document_type'] ) : '',
		);
	}

	/**
	 * @param array<string,mixed>|null $rule_set Rule set.
	 * @return array{page_types:array<int,string>,uri_fragments:array<int,string>}
	 */
	private static function extract_constraints( $rule_set ) {
		$page_types    = array();
		$uri_fragments = array();
		if ( ! is_array( $rule_set ) || empty( $rule_set['rules'][0]['conditions'] ) ) {
			return array(
				'page_types'    => $page_types,
				'uri_fragments' => $uri_fragments,
			);
		}
		foreach ( (array) $rule_set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$type = (string) ( $cond['type'] ?? '' );
			$op   = (string) ( $cond['operator'] ?? '' );
			$val  = $cond['value'] ?? array();
			if ( 'page_type' === $type && in_array( $op, array( 'in', 'is' ), true ) ) {
				foreach ( self::string_list( $val ) as $slug ) {
					$page_types[] = sanitize_key( $slug );
				}
			}
			if ( 'request_uri' === $type && in_array( $op, array( 'contains', 'in', 'is' ), true ) ) {
				foreach ( self::string_list( $val ) as $frag ) {
					$uri_fragments[] = $frag;
				}
			}
		}
		return array(
			'page_types'    => array_values( array_unique( array_filter( $page_types ) ) ),
			'uri_fragments' => array_values( array_unique( array_filter( $uri_fragments ) ) ),
		);
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
	 * @param array<int,string> $slugs Page type slugs.
	 * @return string
	 */
	private static function page_types_label( array $slugs ) {
		$labels = array();
		foreach ( $slugs as $slug ) {
			$labels[] = self::page_type_label( $slug );
		}
		return implode( ' ' . __( 'or', 'reactwoo-geocore' ) . ' ', $labels );
	}

	/**
	 * @param string $slug Page type slug.
	 * @return string
	 */
	public static function page_type_label( $slug ) {
		$map = array(
			'product'  => __( 'Product pages', 'reactwoo-geocore' ),
			'category' => __( 'Product category pages', 'reactwoo-geocore' ),
			'homepage' => __( 'Homepage', 'reactwoo-geocore' ),
			'shop'     => __( 'Shop', 'reactwoo-geocore' ),
			'cart'     => __( 'Cart', 'reactwoo-geocore' ),
			'checkout' => __( 'Checkout', 'reactwoo-geocore' ),
			'page'     => __( 'Pages', 'reactwoo-geocore' ),
			'post'     => __( 'Posts', 'reactwoo-geocore' ),
			'search'   => __( 'Search', 'reactwoo-geocore' ),
			'other'    => __( 'Other', 'reactwoo-geocore' ),
		);
		$slug = sanitize_key( (string) $slug );
		return $map[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) );
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function infer_page_type_for_post( WP_Post $post ) {
		if ( 'product' === $post->post_type ) {
			return 'product';
		}
		if ( 'page' === $post->post_type ) {
			if ( function_exists( 'wc_get_page_id' ) && (int) wc_get_page_id( 'shop' ) === (int) $post->ID ) {
				return 'shop';
			}
			if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
				return 'homepage';
			}
			return 'page';
		}
		if ( 'post' === $post->post_type ) {
			return 'post';
		}
		return 'other';
	}
}
