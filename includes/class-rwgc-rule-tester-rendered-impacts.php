<?php
/**
 * Rule Tester: discover product render sources and collect rendered impacts.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product source discovery + WooCommerce product-meta impact collector for the Rule Tester.
 */
class RWGC_Rule_Tester_Rendered_Impacts {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_rule_tester_collect_rendered_impacts', array( __CLASS__, 'collect_product_meta_impacts' ), 10, 4 );
	}

	/**
	 * @param array<string,mixed> $request  Tester request.
	 * @param int                 $rule_id  Selected visibility rule ID.
	 * @param array<string,mixed> $norm     Normalized tester payload.
	 * @param bool                $matched  Whether the selected rule matched.
	 * @return array{impacts:array<int,array<string,mixed>>,dynamic_query_detected:bool,note:string}
	 */
	public static function collect( array $request, $rule_id, array $norm, $matched ) {
		$rule_id = absint( $rule_id );
		$content = isset( $norm['content'] ) && is_array( $norm['content'] ) ? $norm['content'] : array();
		$context = isset( $norm['context'] ) && is_array( $norm['context'] ) ? $norm['context'] : array();

		$discovery = self::discover_product_sources( $content );
		$tester_context = array(
			'content'          => $content,
			'context'          => $context,
			'document_context' => isset( $norm['document_context'] ) && is_array( $norm['document_context'] ) ? $norm['document_context'] : array(),
			'rule_matched'     => (bool) $matched,
			'discovery'        => $discovery,
		);

		$impacts = apply_filters( 'rwgc_rule_tester_collect_rendered_impacts', array(), $tester_context, $rule_id, $content );

		$note = '';
		if ( ! empty( $discovery['dynamic_query_detected'] ) ) {
			$note = __( 'Some products are rendered dynamically by shortcode/widget. Open simulated preview to visually confirm final product visibility.', 'reactwoo-geocore' );
		}

		return array(
			'impacts'                  => is_array( $impacts ) ? array_values( $impacts ) : array(),
			'dynamic_query_detected'   => ! empty( $discovery['dynamic_query_detected'] ),
			'note'                     => $note,
		);
	}

	/**
	 * Collect impacts for products with Geo Core visibility rule meta.
	 *
	 * @param array<int,array<string,mixed>> $impacts         Existing impacts.
	 * @param array<string,mixed>            $tester_context  Tester context.
	 * @param int                            $rule_id         Selected rule ID.
	 * @param array<string,mixed>            $content         Content selector.
	 * @return array<int,array<string,mixed>>
	 */
	public static function collect_product_meta_impacts( $impacts, $tester_context, $rule_id, $content ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 || ! class_exists( 'RWGC_Product_Meta', false ) ) {
			return is_array( $impacts ) ? $impacts : array();
		}

		$discovery = isset( $tester_context['discovery'] ) && is_array( $tester_context['discovery'] )
			? $tester_context['discovery']
			: self::discover_product_sources( is_array( $content ) ? $content : array() );
		$sources   = isset( $discovery['sources'] ) && is_array( $discovery['sources'] ) ? $discovery['sources'] : array();
		$context   = isset( $tester_context['context'] ) && is_array( $tester_context['context'] ) ? $tester_context['context'] : array();
		$matched   = ! empty( $tester_context['rule_matched'] );

		$post = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
		$rule_label = $post instanceof WP_Post ? (string) $post->post_title : (string) $rule_id;

		$seen = array();
		foreach ( is_array( $impacts ) ? $impacts : array() as $row ) {
			if ( is_array( $row ) && ! empty( $row['product_id'] ) ) {
				$seen[ absint( $row['product_id'] ) ] = true;
			}
		}
		$out = is_array( $impacts ) ? $impacts : array();

		foreach ( $sources as $source_row ) {
			if ( ! is_array( $source_row ) ) {
				continue;
			}
			$product_id = absint( $source_row['product_id'] ?? 0 );
			if ( $product_id <= 0 || ! empty( $seen[ $product_id ] ) ) {
				continue;
			}
			$impact = self::product_meta_impact_row( $product_id, $rule_id, $rule_label, $matched, $source_row );
			if ( null === $impact ) {
				continue;
			}
			$out[]               = $impact;
			$seen[ $product_id ] = true;
		}

		if ( ! empty( $discovery['dynamic_query_detected'] ) ) {
			foreach ( self::products_linked_to_visibility_rule( $rule_id ) as $product_id ) {
				if ( ! empty( $seen[ $product_id ] ) ) {
					continue;
				}
				$impact = self::product_meta_impact_row(
					$product_id,
					$rule_id,
					$rule_label,
					$matched,
					array(
						'source'       => 'woocommerce_dynamic_grid',
						'source_label' => __( 'WooCommerce dynamic product grid', 'reactwoo-geocore' ),
					)
				);
				if ( null === $impact ) {
					continue;
				}
				$out[]               = $impact;
				$seen[ $product_id ] = true;
			}
		}

		return $out;
	}

	/**
	 * @param int                 $product_id   Product ID.
	 * @param int                 $rule_id      Visibility rule ID.
	 * @param string              $rule_label   Rule label.
	 * @param bool                $matched      Whether the rule matched.
	 * @param array<string,mixed> $source_row   Discovery source metadata.
	 * @return array<string,mixed>|null
	 */
	private static function product_meta_impact_row( $product_id, $rule_id, $rule_label, $matched, array $source_row ) {
		$product_id = absint( $product_id );
		$rule_id    = absint( $rule_id );
		if ( $product_id <= 0 || $rule_id <= 0 || ! class_exists( 'RWGC_Product_Meta', false ) ) {
			return null;
		}
		if ( ! RWGC_Product_Meta::has_geo_override( $product_id ) ) {
			return null;
		}
		$rule_ids = RWGC_Product_Meta::get_rule_ids( $product_id );
		if ( empty( $rule_ids ) || ! in_array( $rule_id, $rule_ids, true ) ) {
			return null;
		}

		$mode    = RWGC_Product_Meta::get_visibility_mode( $product_id );
		$visible = function_exists( 'rwgc_visibility_mode_allows_render' )
			? rwgc_visibility_mode_allows_render( $mode, $matched )
			: $matched;

		return array(
			'target_type'  => 'product',
			'source'       => sanitize_key( (string) ( $source_row['source'] ?? 'woocommerce_shortcode' ) ),
			'source_label' => (string) ( $source_row['source_label'] ?? __( 'WooCommerce shortcode', 'reactwoo-geocore' ) ),
			'product_id'   => $product_id,
			'product_name' => get_the_title( $product_id ) ?: (string) $product_id,
			'rule_id'      => $rule_id,
			'rule_label'   => $rule_label,
			'mode'         => $mode,
			'mode_label'   => self::mode_label( $mode ),
			'rule_matches' => $matched,
			'outcome'      => $visible ? 'visible' : 'hidden',
			'reason'       => self::product_visibility_reason( $mode, $matched, $visible ),
		);
	}

	/**
	 * Products with Geo Core product meta referencing a visibility rule.
	 *
	 * @param int $rule_id Visibility rule post ID.
	 * @return int[]
	 */
	private static function products_linked_to_visibility_rule( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 || ! class_exists( 'RWGC_Product_Meta', false ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'private' ),
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'meta_query'             => array(
					array(
						'key'     => RWGC_Product_Meta::META_RULE_IDS,
						'value'   => '"' . $rule_id . '"',
						'compare' => 'LIKE',
					),
				),
			)
		);

		$ids = array();
		foreach ( (array) $query->posts as $product_id ) {
			$product_id = absint( $product_id );
			if ( $product_id > 0 ) {
				$ids[] = $product_id;
			}
		}
		return $ids;
	}

	/**
	 * @param array<string,mixed> $content Content selector.
	 * @return array{sources:array<int,array<string,mixed>>,dynamic_query_detected:bool}
	 */
	public static function discover_product_sources( array $content ) {
		$type = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$id   = isset( $content['id'] ) ? absint( $content['id'] ) : 0;
		$out  = array(
			'sources'                  => array(),
			'dynamic_query_detected'   => false,
		);

		if ( $id <= 0 || ! in_array( $type, array( 'page', 'post', 'product' ), true ) ) {
			return $out;
		}

		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return $out;
		}

		$seen = array();
		self::append_sources_from_text( (string) $post->post_content, 'woocommerce_shortcode', __( 'WooCommerce shortcode', 'reactwoo-geocore' ), $out, $seen );

		$elementor_raw = (string) get_post_meta( $id, '_elementor_data', true );
		if ( '' !== trim( $elementor_raw ) ) {
			$data = json_decode( $elementor_raw, true );
			if ( is_array( $data ) ) {
				self::walk_elementor_for_products( $data, $out, $seen );
			}
		}

		/**
		 * Extend product source discovery for blocks/widgets.
		 *
		 * @param array{sources:array<int,array<string,mixed>>,dynamic_query_detected:bool} $out   Discovery result.
		 * @param int                                                                       $id    Post ID.
		 * @param string                                                                    $type  Content type.
		 */
		return apply_filters( 'rwgc_rule_tester_discover_product_sources', $out, $id, $type );
	}

	/**
	 * @param string              $text         Post content.
	 * @param string              $source       Source slug.
	 * @param string              $source_label Human label.
	 * @param array<string,mixed> $out          Discovery output (by ref).
	 * @param array<int,bool>     $seen         Seen product IDs.
	 * @return void
	 */
	private static function append_sources_from_text( $text, $source, $source_label, array &$out, array &$seen ) {
		$text = (string) $text;
		if ( '' === trim( $text ) ) {
			return;
		}

		$dynamic_tags = array(
			'products',
			'featured_products',
			'best_selling_products',
			'recent_products',
			'sale_products',
			'product_category',
			'top_rated_products',
		);
		foreach ( $dynamic_tags as $tag ) {
			if ( preg_match( '/\[' . preg_quote( $tag, '/' ) . '\b/i', $text ) ) {
				$out['dynamic_query_detected'] = true;
			}
		}

		if ( preg_match_all( '/\[product(?:_page)?\s+[^\]]*id=["\']?(\d+)["\']?/i', $text, $m ) ) {
			foreach ( (array) $m[1] as $pid ) {
				self::push_source( absint( $pid ), $source, $source_label, $out, $seen );
			}
		}
		if ( preg_match_all( '/\[products\s+[^\]]*ids=["\']?([0-9,\s]+)["\']?/i', $text, $m ) ) {
			foreach ( (array) $m[1] as $chunk ) {
				foreach ( preg_split( '/\s*,\s*/', (string) $chunk ) as $pid ) {
					self::push_source( absint( $pid ), $source, $source_label, $out, $seen );
				}
			}
		}

		if ( preg_match_all( '/<!--\s*wp:woocommerce\/product\s+(\{.*?\})\s*\/-->/s', $text, $m ) ) {
			foreach ( (array) $m[1] as $json ) {
				$attrs = json_decode( $json, true );
				if ( is_array( $attrs ) && ! empty( $attrs['productId'] ) ) {
					self::push_source( absint( $attrs['productId'] ), 'woocommerce_block', __( 'WooCommerce block', 'reactwoo-geocore' ), $out, $seen );
				}
			}
		}
	}

	/**
	 * @param array<int,mixed>    $elements Elementor nodes.
	 * @param array<string,mixed> $out      Discovery output.
	 * @param array<int,bool>     $seen     Seen IDs.
	 * @return void
	 */
	private static function walk_elementor_for_products( array $elements, array &$out, array &$seen ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$widget = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

			if ( in_array( $widget, array( 'woocommerce-product-title', 'woocommerce-product-images', 'woocommerce-product-price', 'woocommerce-product-add-to-cart', 'woocommerce-product-short-description', 'woocommerce-product-meta', 'woocommerce-product-content', 'woocommerce-product-data-tabs', 'woocommerce-product-related', 'woocommerce-product-upsell' ), true ) ) {
				$pid = self::elementor_product_id_from_settings( $settings );
				if ( $pid > 0 ) {
					self::push_source( $pid, 'elementor_product_widget', __( 'Elementor product widget', 'reactwoo-geocore' ), $out, $seen );
				}
			}
			if ( in_array( $widget, array( 'woocommerce-products', 'wc-products', 'products' ), true ) ) {
				$out['dynamic_query_detected'] = true;
				if ( ! empty( $settings['ids'] ) ) {
					$ids = is_array( $settings['ids'] ) ? $settings['ids'] : preg_split( '/\s*,\s*/', (string) $settings['ids'] );
					foreach ( (array) $ids as $pid ) {
						self::push_source( absint( $pid ), 'elementor_product_widget', __( 'Elementor product widget', 'reactwoo-geocore' ), $out, $seen );
					}
				}
			}
			if ( 'shortcode' === $widget ) {
				$shortcode = '';
				if ( ! empty( $settings['shortcode'] ) ) {
					$shortcode = (string) $settings['shortcode'];
				} elseif ( ! empty( $settings['editor'] ) ) {
					$shortcode = (string) $settings['editor'];
				}
				if ( '' !== trim( $shortcode ) ) {
					self::append_sources_from_text( $shortcode, 'elementor_shortcode', __( 'Elementor shortcode', 'reactwoo-geocore' ), $out, $seen );
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk_elementor_for_products( $element['elements'], $out, $seen );
			}
		}
	}

	/**
	 * @param array<string,mixed> $settings Elementor settings.
	 * @return int
	 */
	private static function elementor_product_id_from_settings( array $settings ) {
		foreach ( array( 'product_id', 'product' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return absint( $settings[ $key ] );
			}
		}
		return 0;
	}

	/**
	 * @param int                 $product_id   Product ID.
	 * @param string              $source       Source slug.
	 * @param string              $source_label Label.
	 * @param array<string,mixed> $out          Output.
	 * @param array<int,bool>     $seen         Seen map.
	 * @return void
	 */
	private static function push_source( $product_id, $source, $source_label, array &$out, array &$seen ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 || ! empty( $seen[ $product_id ] ) ) {
			return;
		}
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}
		$out['sources'][] = array(
			'product_id'   => $product_id,
			'source'       => sanitize_key( $source ),
			'source_label' => $source_label,
		);
		$seen[ $product_id ] = true;
	}

	/**
	 * @param string $mode show_if|hide_if.
	 * @return string
	 */
	private static function mode_label( $mode ) {
		$mode = function_exists( 'rwgc_normalize_visibility_mode' ) ? rwgc_normalize_visibility_mode( $mode ) : $mode;
		return 'hide_if' === $mode
			? __( 'Hide when rule matches', 'reactwoo-geocore' )
			: __( 'Show only when rule matches', 'reactwoo-geocore' );
	}

	/**
	 * @param string $mode     show_if|hide_if.
	 * @param bool   $matched  Rule matched.
	 * @param bool   $visible  Product visible in output.
	 * @return string
	 */
	private static function product_visibility_reason( $mode, $matched, $visible ) {
		$mode = function_exists( 'rwgc_normalize_visibility_mode' ) ? rwgc_normalize_visibility_mode( $mode ) : $mode;
		if ( $visible && 'show_if' === $mode ) {
			return __( 'The rule matched, so this show-on-match product is visible in the rendered output.', 'reactwoo-geocore' );
		}
		if ( ! $visible && 'show_if' === $mode ) {
			return __( 'This product was removed from the rendered shortcode output because the show-on-match rule did not match.', 'reactwoo-geocore' );
		}
		if ( ! $visible && 'hide_if' === $mode ) {
			return __( 'This product was removed from the rendered shortcode output by a geo product visibility rule.', 'reactwoo-geocore' );
		}
		return __( 'The rule did not match, so this hide-on-match product remains visible in the rendered output.', 'reactwoo-geocore' );
	}
}
