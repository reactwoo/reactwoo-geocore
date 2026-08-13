<?php
/**
 * Default PHP HTML renderer for ReactWoo components.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Namespace: rw- / data-rw-component. No Shadow DOM.
 */
final class RWGC_Php_Html_Component_Renderer implements RWGC_Component_Renderer_Interface {

	/**
	 * @return string
	 */
	public function id() {
		return 'php_html';
	}

	/**
	 * @param RWGC_Component_Definition $definition Definition.
	 * @param array<string, mixed>      $props Props.
	 * @param array<string, mixed>      $context Context.
	 * @return string
	 */
	public function render( RWGC_Component_Definition $definition, array $props, array $context = array() ) {
		$type = $definition->type();
		switch ( $type ) {
			case 'hero':
				return self::render_hero( $props );
			case 'cta':
				return self::render_cta( $props );
			case 'promotion_banner':
				return self::render_promotion_banner( $props );
			case 'notice':
				return self::render_notice( $props );
			case 'product_rail':
				return self::render_product_rail( $props );
			case 'popup':
				return self::render_popup( $props );
			default:
				return '';
		}
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @return string
	 */
	private static function render_hero( array $props ) {
		$headline = self::text( $props, 'headline', '' );
		$sub      = self::text( $props, 'subheadline', '' );
		$cta_label = self::text( $props, 'cta_label', '' );
		$cta_url   = self::url( $props, 'cta_url', '#' );
		$image     = self::url( $props, 'image_url', '' );

		$inner  = '';
		if ( '' !== $headline ) {
			$inner .= '<h2 class="rw-hero__headline">' . esc_html( $headline ) . '</h2>';
		}
		if ( '' !== $sub ) {
			$inner .= '<p class="rw-hero__subheadline">' . esc_html( $sub ) . '</p>';
		}
		if ( '' !== $cta_label ) {
			$inner .= '<p class="rw-hero__actions"><a class="rw-hero__cta" href="' . esc_url( $cta_url ) . '">' . esc_html( $cta_label ) . '</a></p>';
		}
		$media = '';
		if ( '' !== $image ) {
			$media = '<div class="rw-hero__media"><img class="rw-hero__image" src="' . esc_url( $image ) . '" alt="" loading="lazy" /></div>';
		}

		return self::wrap(
			'hero',
			'<div class="rw-hero__content">' . $inner . '</div>' . $media,
			array( 'role' => 'region', 'aria-label' => $headline !== '' ? $headline : 'Hero' ),
			$props
		);
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @return string
	 */
	private static function render_cta( array $props ) {
		$label = self::text( $props, 'label', 'Learn more' );
		$url   = self::url( $props, 'url', '#' );
		return self::wrap(
			'cta',
			'<a class="rw-cta__link" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>',
			array(),
			$props
		);
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @return string
	 */
	private static function render_promotion_banner( array $props ) {
		$text = self::text( $props, 'text', '' );
		$url  = self::url( $props, 'url', '' );
		$body = '<p class="rw-promotion-banner__text">' . esc_html( $text ) . '</p>';
		if ( '' !== $url ) {
			$body = '<a class="rw-promotion-banner__link" href="' . esc_url( $url ) . '">' . $body . '</a>';
		}
		return self::wrap( 'promotion_banner', $body, array( 'role' => 'complementary' ), $props );
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @return string
	 */
	private static function render_notice( array $props ) {
		$text = self::text( $props, 'text', '' );
		$tone = self::text( $props, 'tone', 'info' );
		if ( ! in_array( $tone, array( 'info', 'success', 'warning', 'error' ), true ) ) {
			$tone = 'info';
		}
		return self::wrap(
			'notice',
			'<p class="rw-notice__text">' . esc_html( $text ) . '</p>',
			array(
				'role'         => 'status',
				'data-rw-tone' => $tone,
			),
			$props
		);
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @return string
	 */
	private static function render_product_rail( array $props ) {
		$title = self::text( $props, 'title', '' );
		$items = isset( $props['items'] ) && is_array( $props['items'] ) ? $props['items'] : array();
		$list  = '';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$name = self::text( $item, 'name', '' );
			$url  = self::url( $item, 'url', '#' );
			if ( '' === $name ) {
				continue;
			}
			$list .= '<li class="rw-product-rail__item"><a class="rw-product-rail__link" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
		}
		$heading = '' !== $title ? '<h3 class="rw-product-rail__title">' . esc_html( $title ) . '</h3>' : '';
		$body    = $heading . ( '' !== $list ? '<ul class="rw-product-rail__list">' . $list . '</ul>' : '' );
		return self::wrap( 'product_rail', $body, array(), $props );
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @return string
	 */
	private static function render_popup( array $props ) {
		$title   = self::text( $props, 'title', '' );
		$content = self::text( $props, 'content', '' );
		// Works without JS: details/summary disclosure (slide-in behaviour can enhance later).
		$summary = '' !== $title ? $title : 'Notice';
		$body    = '<details class="rw-popup__panel"><summary class="rw-popup__summary">' . esc_html( $summary ) . '</summary>';
		$body   .= '<div class="rw-popup__content">' . esc_html( $content ) . '</div></details>';
		return self::wrap( 'popup', $body, array(), $props );
	}

	/**
	 * @param string               $type Type.
	 * @param string               $inner Inner HTML.
	 * @param array<string, string> $attrs Extra attributes.
	 * @return string
	 */
	private static function wrap( $type, $inner, array $attrs = array(), array $props = array() ) {
		$class = 'rw-component rw-component--' . str_replace( '_', '-', (string) $type );
		$pres  = self::presentation_attrs( $props );
		$attr  = ' class="' . esc_attr( $class ) . '" data-rw-component="' . esc_attr( (string) $type ) . '"';
		foreach ( $pres as $k => $v ) {
			$attr .= ' ' . esc_attr( (string) $k ) . '="' . esc_attr( (string) $v ) . '"';
		}
		foreach ( $attrs as $k => $v ) {
			$attr .= ' ' . esc_attr( (string) $k ) . '="' . esc_attr( (string) $v ) . '"';
		}
		return '<div' . $attr . '>' . $inner . '</div>';
	}

	/**
	 * Constrained presentation props from the Cloud Component Editor (WP13).
	 *
	 * @param array<string, mixed> $props Props (may include presentation keys).
	 * @return array<string, string>
	 */
	private static function presentation_attrs( array $props ) {
		$map = array(
			'layout'      => array( 'stacked', 'split' ),
			'align'       => array( 'start', 'center', 'end' ),
			'spacing'     => array( 'compact', 'comfortable', 'spacious' ),
			'color'       => array( 'text', 'accent', 'surface' ),
			'typography'  => array( 'sans', 'display' ),
			'shape'       => array( 'default', 'pill', 'square' ),
			'responsive'  => array( 'default', 'stack_mobile' ),
		);
		$out = array();
		foreach ( $map as $key => $allowed ) {
			$raw = isset( $props[ $key ] ) ? (string) $props[ $key ] : '';
			if ( in_array( $raw, $allowed, true ) ) {
				$out[ 'data-rw-' . $key ] = $raw;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @param string               $key Key.
	 * @param string               $default Default.
	 * @return string
	 */
	private static function text( array $props, $key, $default ) {
		return isset( $props[ $key ] ) ? trim( (string) $props[ $key ] ) : $default;
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @param string               $key Key.
	 * @param string               $default Default.
	 * @return string
	 */
	private static function url( array $props, $key, $default ) {
		$v = isset( $props[ $key ] ) ? trim( (string) $props[ $key ] ) : $default;
		return $v;
	}
}
