<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rwgc_is_wordpress_org_distribution' ) ) {
	/**
	 * Whether this copy is the WordPress.org package.
	 *
	 * The ReactWoo release channel leaves RWGC_DISTRIBUTION as reactwoo and keeps
	 * the api.reactwoo.com updater. The WordPress.org zip sets the constant to
	 * wporg and does not ship that updater. Pro add-ons update themselves.
	 *
	 * @return bool
	 */
	function rwgc_is_wordpress_org_distribution() {
		return defined( 'RWGC_DISTRIBUTION' ) && 'wporg' === RWGC_DISTRIBUTION;
	}
}

if ( ! function_exists( 'rwgc_kses_post_html' ) ) {
	/**
	 * Late-escape HTML returned by shortcodes and block render callbacks.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	function rwgc_kses_post_html( $html ) {
		$html = (string) $html;
		if ( function_exists( 'wp_kses_post' ) ) {
			return wp_kses_post( $html );
		}
		return $html;
	}
}

if ( ! function_exists( 'rwgc_geo_content_allowed_html' ) ) {
	/**
	 * Allowlist for Geo Content output (block, shortcode, slots, and popups).
	 *
	 * Starts from the post allowlist, then adds the embed and form tags that
	 * wp_kses_post() would strip. Script tags, on* handlers, and srcdoc are
	 * removed after the rwgc_geo_content_allowed_html filter.
	 *
	 * @return array<string, array<string, bool>>
	 */
	function rwgc_geo_content_allowed_html() {
		$allowed = function_exists( 'wp_kses_allowed_html' ) ? wp_kses_allowed_html( 'post' ) : array();
		if ( ! is_array( $allowed ) ) {
			$allowed = array();
		}
		if ( ! $allowed ) {
			$allowed = rwgc_geo_content_post_allowlist_fallback();
		}

		$iframe = array(
			'src'             => true,
			'width'           => true,
			'height'          => true,
			'title'           => true,
			'allow'           => true,
			'allowfullscreen' => true,
			'frameborder'     => true,
			'loading'         => true,
			'referrerpolicy'  => true,
			'style'           => true,
			'class'           => true,
			'id'              => true,
		);
		$form   = array(
			'action' => true,
			'method' => true,
			'class'  => true,
			'id'     => true,
			'target' => true,
		);
		$control = array(
			'type'        => true,
			'name'        => true,
			'value'       => true,
			'placeholder' => true,
			'required'    => true,
			'checked'     => true,
			'selected'    => true,
			'disabled'    => true,
			'for'         => true,
			'class'       => true,
			'id'          => true,
		);
		$group = array(
			'class' => true,
			'id'    => true,
		);

		$allowed['iframe']   = $iframe;
		$allowed['form']     = $form;
		$allowed['input']    = isset( $allowed['input'] ) && is_array( $allowed['input'] ) ? array_merge( $allowed['input'], $control ) : $control;
		$allowed['select']   = isset( $allowed['select'] ) && is_array( $allowed['select'] ) ? array_merge( $allowed['select'], $control ) : $control;
		$allowed['option']   = isset( $allowed['option'] ) && is_array( $allowed['option'] ) ? array_merge( $allowed['option'], $control ) : $control;
		$allowed['textarea'] = isset( $allowed['textarea'] ) && is_array( $allowed['textarea'] ) ? array_merge( $allowed['textarea'], $control ) : $control;
		$allowed['button']   = isset( $allowed['button'] ) && is_array( $allowed['button'] ) ? array_merge( $allowed['button'], $control ) : $control;
		$allowed['label']    = isset( $allowed['label'] ) && is_array( $allowed['label'] ) ? array_merge( $allowed['label'], $control ) : $control;
		$allowed['fieldset'] = isset( $allowed['fieldset'] ) && is_array( $allowed['fieldset'] ) ? array_merge( $allowed['fieldset'], $group ) : $group;
		$allowed['legend']   = isset( $allowed['legend'] ) && is_array( $allowed['legend'] ) ? array_merge( $allowed['legend'], $group ) : $group;

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'rwgc_geo_content_allowed_html', $allowed );
			if ( is_array( $filtered ) ) {
				$allowed = $filtered;
			}
		}

		return rwgc_geo_content_strip_unsafe_allowlist( $allowed );
	}
}

if ( ! function_exists( 'rwgc_geo_content_post_allowlist_fallback' ) ) {
	/**
	 * Post-sized allowlist used when WordPress kses is not loaded (tests).
	 *
	 * @return array<string, array<string, bool>>
	 */
	function rwgc_geo_content_post_allowlist_fallback() {
		$common = array(
			'class' => true,
			'id'    => true,
			'style' => true,
			'title' => true,
		);
		$tags   = array( 'p', 'div', 'span', 'br', 'hr', 'strong', 'em', 'b', 'i', 'u', 'ul', 'ol', 'li', 'a', 'img', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td' );
		$allowed = array();
		foreach ( $tags as $tag ) {
			$allowed[ $tag ] = $common;
		}
		$allowed['a']['href']     = true;
		$allowed['a']['target']   = true;
		$allowed['a']['rel']      = true;
		$allowed['img']['src']    = true;
		$allowed['img']['alt']    = true;
		$allowed['img']['width']  = true;
		$allowed['img']['height'] = true;
		return $allowed;
	}
}

if ( ! function_exists( 'rwgc_geo_content_strip_unsafe_allowlist' ) ) {
	/**
	 * Drop script and event-handler attributes from a kses allowlist.
	 *
	 * @param mixed $allowed Allowlist.
	 * @return array<string, array<string, bool>>
	 */
	function rwgc_geo_content_strip_unsafe_allowlist( $allowed ) {
		if ( ! is_array( $allowed ) ) {
			return array();
		}
		foreach ( array_keys( $allowed ) as $tag ) {
			if ( 'script' === strtolower( (string) $tag ) ) {
				unset( $allowed[ $tag ] );
			}
		}
		foreach ( $allowed as $tag => $attrs ) {
			if ( ! is_array( $attrs ) ) {
				$allowed[ $tag ] = array();
				continue;
			}
			foreach ( array_keys( $attrs ) as $attr ) {
				$name = strtolower( (string) $attr );
				if ( str_starts_with( $name, 'on' ) || 'srcdoc' === $name ) {
					unset( $allowed[ $tag ][ $attr ] );
				}
			}
		}
		return $allowed;
	}
}

if ( ! function_exists( 'rwgc_geo_content_allow_wildcard_attrs' ) ) {
	/**
	 * Allow aria-* and data-* on form controls present in this fragment.
	 *
	 * wp_kses matches attribute names exactly, so each safe wildcard attribute
	 * in the fragment is added before escaping.
	 *
	 * @param string                               $html    HTML.
	 * @param array<string, array<string, bool>> $allowed Allowlist.
	 * @return array<string, array<string, bool>>
	 */
	function rwgc_geo_content_allow_wildcard_attrs( $html, array $allowed ) {
		$tags = array( 'input', 'select', 'option', 'textarea', 'button', 'label' );
		if ( ! preg_match_all( '/<(' . implode( '|', $tags ) . ')\b([^>]*)>/i', (string) $html, $matches, PREG_SET_ORDER ) ) {
			return $allowed;
		}
		foreach ( $matches as $match ) {
			$tag = strtolower( $match[1] );
			if ( ! isset( $allowed[ $tag ] ) || ! is_array( $allowed[ $tag ] ) ) {
				continue;
			}
			if ( ! preg_match_all( '/(?:\s|^)((?:aria|data)-[a-zA-Z0-9_:\-]+)\s*=/', $match[2], $attrs ) ) {
				continue;
			}
			foreach ( $attrs[1] as $attr ) {
				$name = strtolower( (string) $attr );
				if ( str_starts_with( $name, 'on' ) ) {
					continue;
				}
				$allowed[ $tag ][ $name ] = true;
			}
		}
		return $allowed;
	}
}

if ( ! function_exists( 'rwgc_kses_geo_content_html' ) ) {
	/**
	 * Escape Geo Content inner HTML, keeping https embeds and simple forms.
	 *
	 * @param string                                    $html    HTML.
	 * @param array<string, array<string, bool>>|null $allowed Allowlist. Null builds the Geo Content allowlist.
	 * @return string
	 */
	function rwgc_kses_geo_content_html( $html, $allowed = null ) {
		$html = (string) $html;
		if ( ! is_array( $allowed ) ) {
			$allowed = rwgc_geo_content_allowed_html();
		}
		$allowed = rwgc_geo_content_allow_wildcard_attrs( $html, $allowed );
		$allowed = rwgc_geo_content_strip_unsafe_allowlist( $allowed );
		if ( function_exists( 'wp_kses' ) ) {
			$html = wp_kses( $html, $allowed );
		} else {
			$html = rwgc_geo_content_kses_fallback( $html, $allowed );
		}
		return rwgc_geo_content_enforce_embed_safety( $html );
	}
}

if ( ! function_exists( 'rwgc_geo_content_kses_fallback' ) ) {
	/**
	 * Allowlist escape used when wp_kses() is not loaded.
	 *
	 * @param string                               $html    HTML.
	 * @param array<string, array<string, bool>> $allowed Allowlist.
	 * @return string
	 */
	function rwgc_geo_content_kses_fallback( $html, array $allowed ) {
		$html = preg_replace( '#<script\b[^>]*>.*?</script\s*>#is', '', (string) $html );
		$html = preg_replace( '#<script\b[^>]*/?\s*>#i', '', (string) $html );
		$html = preg_replace_callback(
			'#<\s*(/?)\s*([a-zA-Z0-9]+)\b([^<>]*)>#',
			static function ( $matches ) use ( $allowed ) {
				$tag = strtolower( $matches[2] );
				if ( ! isset( $allowed[ $tag ] ) || ! is_array( $allowed[ $tag ] ) ) {
					return '';
				}
				if ( '/' === $matches[1] ) {
					return '</' . $tag . '>';
				}
				$raw          = (string) $matches[3];
				$self_closing = (bool) preg_match( '#/\s*$#', $raw );
				if ( $self_closing ) {
					$raw = (string) preg_replace( '#/\s*$#', '', $raw );
				}
				$attrs = rwgc_geo_content_filter_attributes( $raw, $allowed[ $tag ] );
				return '<' . $tag . $attrs . ( $self_closing ? ' /' : '' ) . '>';
			},
			(string) $html
		);
		return is_string( $html ) ? $html : '';
	}
}

if ( ! function_exists( 'rwgc_geo_content_filter_attributes' ) ) {
	/**
	 * Keep attributes that are on the tag allowlist and are not event handlers.
	 *
	 * @param string              $raw     Raw attribute string.
	 * @param array<string, bool> $allowed Allowed attributes.
	 * @return string
	 */
	function rwgc_geo_content_filter_attributes( $raw, array $allowed ) {
		if ( ! preg_match_all( '/([^\s=\/\'"<>]+)\s*(?:=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/', (string) $raw, $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL ) ) {
			return '';
		}
		$out = '';
		foreach ( $found as $attr ) {
			$name = strtolower( (string) $attr[1] );
			if ( str_starts_with( $name, 'on' ) || ! isset( $allowed[ $name ] ) || ! $allowed[ $name ] ) {
				continue;
			}
			$full = (string) $attr[0];
			if ( ! str_contains( $full, '=' ) ) {
				$out .= ' ' . $name;
				continue;
			}
			if ( null !== $attr[2] ) {
				$value = $attr[2];
			} elseif ( null !== $attr[3] ) {
				$value = $attr[3];
			} else {
				$value = (string) $attr[4];
			}
			$out .= ' ' . $name . '="' . $value . '"';
		}
		return $out;
	}
}

if ( ! function_exists( 'rwgc_geo_content_is_https_url' ) ) {
	/**
	 * Whether an iframe src is an https URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	function rwgc_geo_content_is_https_url( $url ) {
		$url = html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$url = trim( $url );
		$url = preg_replace( '/^[\x00-\x20]+/', '', $url );
		return is_string( $url ) && 1 === preg_match( '#^https://#i', $url );
	}
}

if ( ! function_exists( 'rwgc_geo_content_is_safe_form_action' ) ) {
	/**
	 * Whether a form action is relative or an http(s) URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	function rwgc_geo_content_is_safe_form_action( $url ) {
		$url = html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$url = trim( (string) $url );
		$url = preg_replace( '/^[\x00-\x20]+/', '', $url );
		if ( ! is_string( $url ) || '' === $url ) {
			return true;
		}
		if ( ! preg_match( '#^([a-z][a-z0-9+.\-]*):#i', $url, $matches ) ) {
			return true;
		}
		$scheme = strtolower( $matches[1] );
		return 'https' === $scheme || 'http' === $scheme;
	}
}

if ( ! function_exists( 'rwgc_geo_content_strip_unsafe_form_action' ) ) {
	/**
	 * Remove a javascript: (or other non-http) form action.
	 *
	 * @param string $attrs Raw attribute string.
	 * @return string
	 */
	function rwgc_geo_content_strip_unsafe_form_action( $attrs ) {
		$attrs = preg_replace_callback(
			'/\saction\s*=\s*([\'"])(.*?)\1/i',
			static function ( $action ) {
				return rwgc_geo_content_is_safe_form_action( $action[2] ) ? $action[0] : '';
			},
			(string) $attrs
		);
		$attrs = preg_replace_callback(
			'/\saction\s*=\s*([^\s\'">]+)/i',
			static function ( $action ) {
				return rwgc_geo_content_is_safe_form_action( $action[1] ) ? $action[0] : '';
			},
			(string) $attrs
		);
		return is_string( $attrs ) ? $attrs : '';
	}
}

if ( ! function_exists( 'rwgc_geo_content_enforce_embed_safety' ) ) {
	/**
	 * Strip script, event handlers, non-https iframe src values, and unsafe form actions.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	function rwgc_geo_content_enforce_embed_safety( $html ) {
		$html = preg_replace( '#<script\b[^>]*>.*?</script\s*>#is', '', (string) $html );
		$html = preg_replace( '#<script\b[^>]*/?\s*>#i', '', (string) $html );
		$html = preg_replace( '/\s+on[a-zA-Z0-9]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $html );
		$html = preg_replace( '/\ssrcdoc\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string) $html );
		$html = rwgc_geo_content_strip_unsafe_hrefs( (string) $html );
		$html = preg_replace_callback(
			'#<form\b([^>]*)>#i',
			static function ( $matches ) {
				$attrs = rwgc_geo_content_strip_unsafe_form_action( (string) $matches[1] );
				return '<form' . $attrs . '>';
			},
			(string) $html
		);
		$html = preg_replace_callback(
			'#<iframe\b([^>]*)>(.*?)</iframe\s*>|<iframe\b([^>]*)/?\s*>#is',
			static function ( $matches ) {
				$attrs = ( isset( $matches[3] ) && '' !== $matches[3] ) ? $matches[3] : (string) $matches[1];
				$src   = rwgc_geo_content_attribute_value( $attrs, 'src' );
				if ( ! rwgc_geo_content_is_https_url( $src ) ) {
					return '';
				}
				return $matches[0];
			},
			(string) $html
		);
		return is_string( $html ) ? $html : '';
	}
}

if ( ! function_exists( 'rwgc_geo_content_attribute_value' ) ) {
	/**
	 * Read one attribute value from a raw attribute string.
	 *
	 * @param string $attrs Raw attributes.
	 * @param string $name  Attribute name.
	 * @return string
	 */
	function rwgc_geo_content_attribute_value( $attrs, $name ) {
		$name = preg_quote( (string) $name, '/' );
		if ( preg_match( '/\s' . $name . '\s*=\s*([\'"])(.*?)\1/is', (string) $attrs, $matches ) ) {
			return (string) $matches[2];
		}
		if ( preg_match( '/\s' . $name . '\s*=\s*([^\s\'">]+)/i', (string) $attrs, $matches ) ) {
			return (string) $matches[1];
		}
		return '';
	}
}

if ( ! function_exists( 'rwgc_geo_content_is_safe_href' ) ) {
	/**
	 * Whether an href is relative or a non-script URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	function rwgc_geo_content_is_safe_href( $url ) {
		$url = html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$url = trim( $url );
		$url = preg_replace( '/[\x00-\x20]+/', '', (string) $url );
		if ( ! is_string( $url ) || '' === $url ) {
			return true;
		}
		if ( ! preg_match( '#^([a-z][a-z0-9+.\-]*):#i', $url, $matches ) ) {
			return true;
		}
		$scheme = strtolower( $matches[1] );
		return in_array( $scheme, array( 'http', 'https', 'mailto', 'tel' ), true );
	}
}

if ( ! function_exists( 'rwgc_geo_content_strip_unsafe_hrefs' ) ) {
	/**
	 * Remove javascript: and data: href attributes.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	function rwgc_geo_content_strip_unsafe_hrefs( $html ) {
		$html = preg_replace_callback(
			'/\shref\s*=\s*([\'"])(.*?)\1/i',
			static function ( $href ) {
				return rwgc_geo_content_is_safe_href( $href[2] ) ? $href[0] : '';
			},
			(string) $html
		);
		$html = preg_replace_callback(
			'/\shref\s*=\s*([^\s\'">]+)/i',
			static function ( $href ) {
				return rwgc_geo_content_is_safe_href( $href[1] ) ? $href[0] : '';
			},
			(string) $html
		);
		return is_string( $html ) ? $html : '';
	}
}

if ( ! function_exists( 'rwgc_kses_rendered_html' ) ) {
	/**
	 * Escape Elementor slot and popup HTML with the Geo Content allowlist.
	 *
	 * Builder attributes stay. The rwgc_geo_content_allowed_html filter still runs,
	 * and script, event handlers, and srcdoc are removed after it.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	function rwgc_kses_rendered_html( $html ) {
		return rwgc_kses_geo_content_html( $html, rwgc_geo_content_merge_builder_allowlist( rwgc_geo_content_allowed_html() ) );
	}
}

if ( ! function_exists( 'rwgc_geo_content_merge_builder_allowlist' ) ) {
	/**
	 * Add Elementor and slot attributes on top of the Geo Content allowlist.
	 *
	 * Script, event handlers, and srcdoc are removed again after the merge.
	 *
	 * @param array<string, array<string, bool>> $allowed Allowlist.
	 * @return array<string, array<string, bool>>
	 */
	function rwgc_geo_content_merge_builder_allowlist( array $allowed ) {
		$attrs = array(
			'class'                        => true,
			'id'                           => true,
			'style'                        => true,
			'role'                         => true,
			'tabindex'                     => true,
			'hidden'                       => true,
			'aria-hidden'                  => true,
			'aria-label'                   => true,
			'aria-modal'                   => true,
			'aria-labelledby'              => true,
			'aria-describedby'             => true,
			'data-id'                      => true,
			'data-element_type'            => true,
			'data-widget_type'             => true,
			'data-settings'                => true,
			'data-model-cid'               => true,
			'data-elementor-id'            => true,
			'data-elementor-type'          => true,
			'data-elementor-settings'      => true,
			'data-elementor-open-lightbox' => true,
			'data-elementor-lightbox'      => true,
			'data-rwgc-slot'               => true,
			'data-reactwoo-slot-mode'      => true,
		);
		foreach ( $allowed as $tag => $tag_attrs ) {
			$allowed[ $tag ] = array_merge( is_array( $tag_attrs ) ? $tag_attrs : array(), $attrs );
		}
		$extra_tags = array( 'svg', 'path', 'use', 'g', 'circle', 'polygon', 'polyline', 'line', 'rect', 'source', 'picture', 'nav', 'section', 'header', 'footer', 'main' );
		foreach ( $extra_tags as $tag ) {
			$existing        = ( isset( $allowed[ $tag ] ) && is_array( $allowed[ $tag ] ) ) ? $allowed[ $tag ] : array();
			$allowed[ $tag ] = array_merge( $existing, $attrs );
		}
		$allowed['svg']  = array_merge( $allowed['svg'], array( 'xmlns' => true, 'viewbox' => true, 'viewBox' => true, 'fill' => true, 'stroke' => true, 'width' => true, 'height' => true ) );
		$allowed['path'] = array_merge( $allowed['path'], array( 'd' => true, 'fill' => true, 'stroke' => true ) );
		return rwgc_geo_content_strip_unsafe_allowlist( $allowed );
	}
}

if ( ! function_exists( 'rwgc_page_variants_available' ) ) {
	/**
	 * Whether an entitled Geo Core Pro runtime can create and edit page versions.
	 *
	 * Core does not turn this on. Pro returns true for a valid standalone licence
	 * or a Decision Cloud personalisation grant. There is no numeric cap.
	 *
	 * @return bool
	 */
	function rwgc_page_variants_available() {
		return (bool) apply_filters( 'rwgc_page_variants_available', false );
	}
}

if ( ! function_exists( 'rwgc_is_geo_core_active' ) ) {
	/**
	 * Whether ReactWoo Geo Core is loaded (for satellite plugin guards).
	 *
	 * @return bool
	 */
	function rwgc_is_geo_core_active() {
		$active = class_exists( 'RWGC_Plugin', false );
		/**
		 * Filter Geo Core active detection (e.g. tests).
		 *
		 * @param bool $active Whether the main plugin class is present.
		 */
		return (bool) apply_filters( 'rwgc_is_geo_core_active', $active );
	}
}

if ( ! function_exists( 'rwgc_is_ready' ) ) {
	/**
	 * Whether Geo Core is ready for use.
	 *
	 * @return bool
	 */
	function rwgc_is_ready() {
		return RWGC_API::is_ready();
	}
}

if ( ! function_exists( 'rwgc_is_pro_enabled' ) ) {
	/**
	 * Whether a Pro runtime extension is active for this site.
	 *
	 * Geo Core defaults to false; GeoCore Pro can return true through the filter.
	 *
	 * @return bool
	 */
	function rwgc_is_pro_enabled() {
		return (bool) apply_filters( 'rwgc_pro_enabled', false );
	}
}

if ( ! function_exists( 'rwgc_get_suite_capability_map' ) ) {
	/**
	 * Installed/licensed suite products for capability-aware AI and admin UX.
	 *
	 * @return array<string, mixed>
	 */
	function rwgc_get_suite_capability_map() {
		if ( class_exists( 'RWGC_Suite_Capability_Map', false ) ) {
			return RWGC_Suite_Capability_Map::get_map();
		}
		return array(
			'geocore_active'           => true,
			'geocore_version'          => defined( 'RWGC_VERSION' ) ? (string) RWGC_VERSION : '',
			'local_fallback_available' => true,
		);
	}
}

if ( ! function_exists( 'rwgc_ux_opportunity_review_admin_url' ) ) {
	/**
	 * Admin URL for Geo AI UX opportunity review (when Geo AI is active).
	 *
	 * @param array<string, scalar> $args Query args: page_id, product_id, variant_page_id, rule_id, source.
	 * @return string
	 */
	function rwgc_ux_opportunity_review_admin_url( array $args = array() ) {
		if ( class_exists( 'RWGO_Optimise_Hub', false ) && class_exists( 'RWGO_AI_Module', false ) && RWGO_AI_Module::uses_optimise_hub() ) {
			unset( $args['page'] );
			if ( ! isset( $args['source'] ) ) {
				$args['source'] = 'insights';
			}
			return RWGO_Optimise_Hub::tab_url( 'ai-review', $args );
		}
		$base = array(
			'page'   => 'rwga-ux-opportunity-review',
			'source' => 'insights',
		);
		return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
	}
}

if ( ! function_exists( 'rwgc_is_geo_ai_active' ) ) {
	/**
	 * Whether the Geo AI satellite plugin is installed and active.
	 *
	 * @return bool
	 */
	function rwgc_is_geo_ai_active() {
		if ( ! class_exists( 'RWGA_Plugin', false ) ) {
			return false;
		}
		$map = function_exists( 'rwgc_get_suite_capability_map' ) ? rwgc_get_suite_capability_map() : array();
		if ( ! empty( $map['geo_ai_active'] ) ) {
			return true;
		}
		if ( ! empty( $map['optimise']['ai_review'] ) ) {
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'rwgc_can_link_ux_opportunity_review' ) ) {
	/**
	 * Whether the current user may open Geo AI UX opportunity review screens.
	 *
	 * @return bool
	 */
	function rwgc_can_link_ux_opportunity_review() {
		if ( ! rwgc_is_geo_ai_active() || ! function_exists( 'rwgc_ux_opportunity_review_admin_url' ) ) {
			return false;
		}
		if ( ! class_exists( 'RWGA_Capabilities', false ) ) {
			return false;
		}
		return current_user_can( RWGA_Capabilities::CAP_VIEW_REPORTS );
	}
}

if ( ! function_exists( 'rwgc_advanced_targeting_enabled' ) ) {
	/**
	 * Whether the included country rule builder is shown.
	 *
	 * This plugin evaluates country and country-group conditions. The filter defaults to true.
	 *
	 * @return bool
	 */
	function rwgc_advanced_targeting_enabled() {
		return (bool) apply_filters( 'rwgc_advanced_targeting_enabled', true );
	}
}

if ( ! function_exists( 'rwgc_normalize_visibility_mode' ) ) {
	/**
	 * Normalize visibility mode to canonical values.
	 *
	 * Backward compatible aliases:
	 * - show => show_if
	 * - hide => hide_if
	 *
	 * @param mixed $mode Raw mode.
	 * @return string
	 */
	function rwgc_normalize_visibility_mode( $mode ) {
		$raw = sanitize_key( (string) $mode );
		if ( in_array( $raw, array( 'hide_if', 'hide', 'restrict', 'suppress' ), true ) ) {
			return 'hide_if';
		}
		return 'show_if';
	}
}

if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
	/**
	 * Determine whether content should render for a mode + match result.
	 *
	 * @param mixed $mode    Raw visibility mode.
	 * @param bool  $matched Whether rules matched.
	 * @return bool
	 */
	function rwgc_visibility_mode_allows_render( $mode, $matched ) {
		$normalized = rwgc_normalize_visibility_mode( $mode );
		if ( 'hide_if' === $normalized ) {
			return ! $matched;
		}
		return (bool) $matched;
	}
}

if ( ! function_exists( 'rwgc_get_portable_targeting_editor_context' ) ) {
	/**
	 * Portable JSON authoring context (audiences, campaigns, Pro flag) for admin and editors.
	 *
	 * @return array<string, mixed>
	 */
	function rwgc_get_portable_targeting_editor_context() {
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return array(
				'pro'       => (bool) apply_filters( 'rwgc_pro_enabled', false ),
				'audiences' => array(),
				'campaigns' => array(),
			);
		}
		return RWGC_Targeting_Rule_Set_Schema::get_editor_context();
	}
}

if ( ! function_exists( 'rwgc_get_visibility_rule_set' ) ) {
	/**
	 * Sanitized portable rule set from the Core visibility rules library CPT.
	 *
	 * @param int $post_id Library post ID ({@see RWGC_Visibility_Rule_CPT::POST_TYPE}).
	 * @return array<string, mixed>|null
	 */
	function rwgc_get_visibility_rule_set( $post_id ) {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return null;
		}
		return RWGC_Visibility_Rule_Repository::get_rule_set( $post_id );
	}
}

if ( ! function_exists( 'rwgc_get_settings_providers' ) ) {
	/**
	 * Active settings providers and their routes (platform shell).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function rwgc_get_settings_providers() {
		if ( ! class_exists( 'RWGC_Admin_Settings_Nav', false ) ) {
			return array();
		}
		return RWGC_Admin_Settings_Nav::get_active_providers();
	}
}

if ( ! function_exists( 'rwgc_get_platform_sync_status' ) ) {
	/**
	 * Shell sync pill snapshot (label, hint, variant, url).
	 *
	 * @return array<string, string>
	 */
	function rwgc_get_platform_sync_status() {
		if ( ! class_exists( 'RWGC_Platform_Sync_Status', false ) ) {
			return array();
		}
		return RWGC_Platform_Sync_Status::get_snapshot();
	}
}

if ( ! function_exists( 'rwgc_get_platform_integrations' ) ) {
	/**
	 * Integration connection rows for the Integrations hub.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	function rwgc_get_platform_integrations() {
		if ( ! class_exists( 'RWGC_Platform_Integrations', false ) ) {
			return array();
		}
		return RWGC_Platform_Integrations::get_items();
	}
}

if ( ! function_exists( 'rwgc_get_setup_progress' ) ) {
	/**
	 * Platform onboarding checklist for Overview (steps, completed, total, percent).
	 *
	 * @return array{steps: array<int, array<string, mixed>>, completed: int, total: int, percent: int}
	 */
	function rwgc_get_setup_progress() {
		if ( ! class_exists( 'RWGC_Onboarding', false ) ) {
			return array(
				'steps'     => array(),
				'completed' => 0,
				'total'     => 0,
				'percent'   => 0,
			);
		}
		return RWGC_Onboarding::get_setup_progress();
	}
}

if ( ! function_exists( 'rwgc_get_maxmind_admin_url' ) ) {
	/**
	 * Admin URL for MaxMind credentials and GeoLite2 country database management.
	 *
	 * @return string
	 */
	function rwgc_get_maxmind_admin_url() {
		$slug = 'rwgc-integrations-maxmind';
		if ( function_exists( 'rwgc_app_url' ) && function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell() ) {
			return rwgc_app_url( 'integrations', $slug );
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
	}
}

if ( ! function_exists( 'rwgc_get_visitor_data' ) ) {
	/**
	 * Get full visitor geo payload.
	 *
	 * @return array
	 */
	function rwgc_get_visitor_data() {
		return RWGC_API::get_visitor_data();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * Get visitor country code.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return RWGC_API::get_visitor_country();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country_name' ) ) {
	/**
	 * Get visitor country name.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_country_name() {
		return RWGC_API::get_visitor_country_name();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_region' ) ) {
	/**
	 * Get visitor region (informational payload).
	 *
	 * Geo Core does not use region for built-in page routing; routing is country-based.
	 * Region may still appear in REST/shortcodes/admin preview.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_region() {
		return RWGC_API::get_visitor_region();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_city' ) ) {
	/**
	 * Get visitor city string from the resolved geo payload (e.g. local MaxMind City DB or filtered API data).
	 *
	 * Geo Core uses country for visibility. City on the visitor payload is informational.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_city() {
		return RWGC_API::get_visitor_city();
	}
}

if ( ! function_exists( 'rwgc_get_visitor_currency' ) ) {
	/**
	 * Get visitor currency.
	 *
	 * @return string
	 */
	function rwgc_get_visitor_currency() {
		return RWGC_API::get_visitor_currency();
	}
}

if ( ! function_exists( 'rwgc_get_currency_for_country' ) ) {
	/**
	 * Get suggested currency for a country.
	 *
	 * @param string $country_code ISO2.
	 * @return string
	 */
	function rwgc_get_currency_for_country( $country_code ) {
		return RWGC_API::get_currency_for_country( $country_code );
	}
}

if ( ! function_exists( 'rwgc_has_country' ) ) {
	/**
	 * Whether a country is present in the mapping.
	 *
	 * @param string $country_code ISO2.
	 * @return bool
	 */
	function rwgc_has_country( $country_code ) {
		return RWGC_API::has_country( $country_code );
	}
}

if ( ! function_exists( 'rwgc_get_page_route_bundle' ) ) {
	/**
	 * Legacy page meta as a canonical default + variants bundle (after filters).
	 *
	 * Requires `rwgc_loaded` (plugins_loaded priority 5). Returns null if the engine is not booted.
	 *
	 * @param int        $page_id Page ID.
	 * @param array|null $config  Optional preloaded config array.
	 * @return RWGC_Page_Route_Bundle|null
	 */
	function rwgc_get_page_route_bundle( $page_id, $config = null ) {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return null;
		}
		return RWGC_Routing::get_page_route_bundle( $page_id, $config );
	}
}

if ( ! function_exists( 'rwgc_get_page_route_decision' ) ) {
	/**
	 * Resolved redirect decision for a page (same filters as front-end routing).
	 *
	 * @param int               $page_id Page ID.
	 * @param RWGC_Context|null $context Context or null for visitor.
	 * @param array|null        $config  Optional preloaded config.
	 * @return array|null Keys: target_page_id, reason, page_id, country, variant_id.
	 */
	function rwgc_get_page_route_decision( $page_id, $context = null, $config = null ) {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return null;
		}
		return RWGC_Routing::get_route_decision_for_page( $page_id, $context, $config );
	}
}

if ( ! function_exists( 'rwgc_ai_request_variant_draft' ) ) {
	/**
	 * Request a draft geo variant from api.reactwoo.com (does not save or publish).
	 *
	 * @param int                  $page_id     Page ID.
	 * @param array<string, mixed> $context     Optional context (e.g. country_iso2).
	 * @param string               $instructions Optional instructions.
	 * @return array<string, mixed>|\WP_Error
	 */
	function rwgc_ai_request_variant_draft( $page_id, $context = array(), $instructions = '' ) {
		if ( ! class_exists( 'RWGC_AI_Orchestrator', false ) ) {
			return new WP_Error( 'rwgc_not_loaded', __( 'Geo Core is not loaded.', 'reactwoo-geocore' ) );
		}
		return RWGC_AI_Orchestrator::request_variant_draft( $page_id, $context, $instructions );
	}
}

if ( ! function_exists( 'rwgc_platform_clear_api_token' ) ) {
	/**
	 * Clear cached JWT for api.reactwoo.com (e.g. after license change).
	 *
	 * @return void
	 */
	function rwgc_platform_clear_api_token() {
		if ( class_exists( 'RWGC_Platform_Client', false ) ) {
			RWGC_Platform_Client::clear_token_cache();
		}
	}
}

if ( ! function_exists( 'rwgc_context_snapshot' ) ) {
	/**
	 * Portable context array for events (see RWGC_Context::to_snapshot()).
	 *
	 * @param RWGC_Context|null $context Context.
	 * @return array<string, mixed>
	 */
	function rwgc_context_snapshot( $context ) {
		if ( $context instanceof RWGC_Context ) {
			return $context->to_snapshot();
		}
		return array();
	}
}

if ( ! function_exists( 'rwgc_emit_geo_event' ) ) {
	/**
	 * Emit a Geo Core event via `rwgc_geo_event` filter and action.
	 *
	 * @param RWGC_Event $event Event envelope.
	 * @return void
	 */
	function rwgc_emit_geo_event( $event ) {
		if ( ! class_exists( 'RWGC_Events', false ) || ! $event instanceof RWGC_Event ) {
			return;
		}
		RWGC_Events::emit( $event );
	}
}

if ( ! function_exists( 'rwgc_build_ai_snapshot' ) ) {
	/**
	 * Build a compact site intelligence snapshot for Geo AI cloud workflows.
	 *
	 * Does not include page content, Elementor JSON, or personal data.
	 *
	 * @param array<string, mixed> $context Optional builder context.
	 * @return array<string, mixed>
	 */
	function rwgc_build_ai_snapshot( array $context = array() ) {
		if ( ! class_exists( 'RWGC_AI_Snapshot_Builder', false ) ) {
			return array();
		}
		return RWGC_AI_Snapshot_Builder::build( $context );
	}
}

if ( ! function_exists( 'rwgc_get_ai_snapshot_hash' ) ) {
	/**
	 * SHA-256 hash of the current site intelligence snapshot.
	 *
	 * @return string
	 */
	function rwgc_get_ai_snapshot_hash() {
		if ( ! class_exists( 'RWGC_AI_Snapshot_Builder', false ) ) {
			return '';
		}
		$payload = RWGC_AI_Snapshot_Builder::build();
		return isset( $payload['snapshot_hash'] ) ? (string) $payload['snapshot_hash'] : '';
	}
}

if ( ! function_exists( 'rwgc_get_geo_event_types' ) ) {
	/**
	 * Known `event_type` slugs for Geo Core events (see RWGC_Event::known_event_types()).
	 *
	 * @return string[]
	 */
	function rwgc_get_geo_event_types() {
		if ( ! class_exists( 'RWGC_Event', false ) ) {
			return array();
		}
		return RWGC_Event::known_event_types();
	}
}

if ( ! function_exists( 'rwgc_get_rest_v1_url' ) ) {
	/**
	 * Public URL for a `reactwoo-geocore/v1` REST route, or empty string if REST is disabled or unavailable.
	 *
	 * @param string $endpoint Route after `v1/` (e.g. `location`, `capabilities`, `ai/variant-draft`).
	 * @return string
	 */
	function rwgc_get_rest_v1_url( $endpoint ) {
		if ( ! function_exists( 'rest_url' ) ) {
			return '';
		}
		if ( class_exists( 'RWGC_Settings', false ) && ! RWGC_Settings::get( 'rest_enabled', 1 ) ) {
			return '';
		}
		$endpoint = trim( preg_replace( '#[^a-z0-9/\-_]#i', '', (string) $endpoint ), '/' );
		if ( '' === $endpoint ) {
			return '';
		}
		$url = rest_url( 'reactwoo-geocore/v1/' . $endpoint );
		/**
		 * Filter Geo Core REST v1 URL (proxies, subdirectory installs, tests).
		 *
		 * @param string $url      Full REST URL.
		 * @param string $endpoint Sanitized route segment(s) after `v1/`.
		 */
		return (string) apply_filters( 'rwgc_rest_v1_url', $url, $endpoint );
	}
}

if ( ! function_exists( 'rwgc_get_rest_location_url' ) ) {
	/**
	 * Public URL for `GET …/location` (visitor geo), or empty string when REST is off.
	 *
	 * @return string
	 */
	function rwgc_get_rest_location_url() {
		return rwgc_get_rest_v1_url( 'location' );
	}
}

if ( ! function_exists( 'rwgc_get_rest_capabilities_url' ) ) {
	/**
	 * Public URL for REST discovery (`GET …/capabilities`), or empty string if REST is disabled or unavailable.
	 *
	 * @return string
	 */
	function rwgc_get_rest_capabilities_url() {
		return rwgc_get_rest_v1_url( 'capabilities' );
	}
}

if ( ! function_exists( 'rwgc_is_woocommerce_active' ) ) {
	/**
	 * Whether WooCommerce is loaded (for Geo Commerce and other satellite plugins).
	 *
	 * @return bool
	 */
	function rwgc_is_woocommerce_active() {
		$active = class_exists( 'WooCommerce', false );
		/**
		 * Filter WooCommerce active detection (e.g. tests or custom stacks).
		 *
		 * @param bool $active Whether the WooCommerce class exists.
		 */
		return (bool) apply_filters( 'rwgc_is_woocommerce_active', $active );
	}
}

if ( ! function_exists( 'rwgc_get_suite_handoff_request_context' ) ) {
	/**
	 * Parse Geo Suite workflow handoff query args (`rwgc_handoff`, `rwgc_from`, `rwgc_launcher`, `rwgc_variant_page_id`).
	 * Satellites use this on their admin screens to show context after deep links from Suite Home / Getting Started / next steps.
	 *
	 * @return array{active: bool, from: string, launcher: string, variant_page_id: int, master_page_id: int, geo_target: string}
	 */
	function rwgc_get_suite_handoff_request_context() {
		$out = array(
			'active'              => false,
			'from'                => '',
			'launcher'            => '',
			'variant_page_id'     => 0,
			'master_page_id'      => 0,
			'geo_target'          => '',
			'visibility_rule_id'  => 0,
			'targeting_summary'   => '',
		);
		if ( ! is_admin() ) {
			return $out;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request context for UX.
		if ( ! isset( $_GET['rwgc_handoff'] ) || '1' !== sanitize_text_field( wp_unslash( $_GET['rwgc_handoff'] ) ) ) {
			return $out;
		}
		$out['active'] = true;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_from'] ) ) {
			$out['from'] = sanitize_key( wp_unslash( (string) $_GET['rwgc_from'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_launcher'] ) ) {
			$out['launcher'] = sanitize_key( wp_unslash( (string) $_GET['rwgc_launcher'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_variant_page_id'] ) ) {
			$out['variant_page_id'] = absint( wp_unslash( $_GET['rwgc_variant_page_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_master_page_id'] ) ) {
			$out['master_page_id'] = absint( wp_unslash( $_GET['rwgc_master_page_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwga_geo_target'] ) ) {
			$out['geo_target'] = strtoupper( substr( sanitize_text_field( wp_unslash( (string) $_GET['rwga_geo_target'] ) ), 0, 2 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rwgc_visibility_rule_id'] ) ) {
			$out['visibility_rule_id'] = absint( wp_unslash( $_GET['rwgc_visibility_rule_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
		}
		if ( $out['visibility_rule_id'] > 0 && function_exists( 'rwgc_get_visibility_rule_copy_context' ) ) {
			$tc = rwgc_get_visibility_rule_copy_context( $out['visibility_rule_id'] );
			if ( is_array( $tc ) && ! empty( $tc['summary'] ) ) {
				$out['targeting_summary'] = (string) $tc['summary'];
			}
		}
		/**
		 * Filter parsed suite handoff request context (tests or custom entry points).
		 *
		 * @param array{active: bool, from: string, launcher: string, variant_page_id: int, master_page_id: int, geo_target: string, visibility_rule_id: int, targeting_summary: string} $out Parsed values.
		 */
		$filtered = apply_filters( 'rwgc_suite_handoff_request_context', $out );
		return is_array( $filtered ) ? array_merge( $out, $filtered ) : $out;
	}
}

if ( ! function_exists( 'rwgc_get_visibility_rule_copy_context' ) ) {
	/**
	 * Structured targeting context for Geo AI copy adaptation from a visibility rule ID.
	 *
	 * @param int $rule_id Visibility rule post ID.
	 * @return array<string, mixed>
	 */
	function rwgc_get_visibility_rule_copy_context( $rule_id ) {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Copy_Context', false ) ) {
			return array();
		}
		return RWGC_Visibility_Rule_Copy_Context::from_rule_id( absint( $rule_id ) );
	}
}

if ( ! function_exists( 'rwgc_get_page_experience_copy_context' ) ) {
	/**
	 * Targeting context from experience builder meta on a page.
	 *
	 * @param int $page_id Page ID.
	 * @return array<string, mixed>
	 */
	function rwgc_get_page_experience_copy_context( $page_id ) {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Copy_Context', false ) ) {
			return array();
		}
		return RWGC_Visibility_Rule_Copy_Context::from_page_experience_meta( absint( $page_id ) );
	}
}

if ( ! function_exists( 'rwgc_get_country_codes_copy_context' ) ) {
	/**
	 * Synthetic targeting context from ISO2 country codes (selected countries path).
	 *
	 * @param array<int, string> $country_codes ISO2 codes.
	 * @return array<string, mixed>
	 */
	function rwgc_get_country_codes_copy_context( array $country_codes ) {
		if ( ! class_exists( 'RWGC_Visibility_Rule_Copy_Context', false ) ) {
			return array();
		}
		return RWGC_Visibility_Rule_Copy_Context::from_country_codes( $country_codes );
	}
}

if ( ! function_exists( 'rwgc_is_builder_edit_request' ) ) {
	/**
	 * Whether the current request is an Elementor (or future builder) edit/preview surface that should skip geo routing.
	 *
	 * Page-version routing does not own this check. Country visibility uses it in Core.
	 *
	 * @param int|null $post_id Optional document ID for capability checks.
	 * @return bool
	 */
	function rwgc_is_builder_edit_request( $post_id = null ) {
		if ( ! class_exists( 'RWGC_Builder_Request', false ) ) {
			return false;
		}
		return RWGC_Builder_Request::is_edit_request( $post_id );
	}
}

if ( ! function_exists( 'rwgc_is_builder_context' ) ) {
	/**
	 * Alias for builder/editor detection used by other plugins.
	 * Debug mode does not change the return value.
	 *
	 * @param int|null $post_id Optional document ID for capability checks.
	 * @return bool
	 */
	function rwgc_is_builder_context( $post_id = null ) {
		return rwgc_is_builder_edit_request( $post_id );
	}
}

if ( ! function_exists( 'rwgc_get_target_types' ) ) {
	/**
	 * All registered target type definitions (suite targeting vocabulary).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function rwgc_get_target_types() {
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			return array();
		}
		RWGC_Target_Registry::init();
		return RWGC_Target_Registry::instance()->get_target_types();
	}
}

if ( ! function_exists( 'rwgc_get_available_target_types' ) ) {
	/**
	 * Target types that pass availability checks for this site/request.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	function rwgc_get_available_target_types() {
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			return array();
		}
		RWGC_Target_Registry::init();
		return RWGC_Target_Registry::instance()->get_available_target_types();
	}
}

if ( ! function_exists( 'rwgc_get_target_definition' ) ) {
	/**
	 * Single target definition by key.
	 *
	 * @param string $key Target key.
	 * @return array<string, mixed>|null
	 */
	function rwgc_get_target_definition( $key ) {
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			return null;
		}
		RWGC_Target_Registry::init();
		return RWGC_Target_Registry::instance()->get_target_type( (string) $key );
	}
}

if ( ! function_exists( 'rwgc_get_context_snapshot' ) ) {
	/**
	 * Normalised context for the current visitor/request (array shape).
	 *
	 * @return array<string, mixed>
	 */
	function rwgc_get_context_snapshot() {
		if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return array();
		}
		$flat = RWGC_Context_Resolver::resolve_current()->to_array();
		if ( class_exists( 'RWGC_Context_Snapshot_Formatter', false ) ) {
			return RWGC_Context_Snapshot_Formatter::enrich( $flat );
		}
		return $flat;
	}
}

if ( ! function_exists( 'rwgc_get_context_value' ) ) {
	/**
	 * One resolved value from the current context snapshot.
	 *
	 * @param string $key Target key.
	 * @return mixed|null
	 */
	function rwgc_get_context_value( $key ) {
		if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return null;
		}
		return RWGC_Context_Resolver::resolve_target_value( (string) $key );
	}
}

if ( ! function_exists( 'rwgc_resolve_preview_context' ) ) {
	/**
	 * Simulated context for admin previews (overrides merged when supported).
	 *
	 * @param array<string, mixed> $overrides Keyed overrides.
	 * @return array<string, mixed>
	 */
	function rwgc_resolve_preview_context( $overrides = array() ) {
		if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return is_array( $overrides ) ? $overrides : array();
		}
		return RWGC_Context_Resolver::resolve_for_preview( is_array( $overrides ) ? $overrides : array() )->to_array();
	}
}

if ( ! function_exists( 'rwgc_is_target_available' ) ) {
	/**
	 * Whether a target key is available for rule building on this site.
	 *
	 * @param string $key Target key.
	 * @return bool
	 */
	function rwgc_is_target_available( $key ) {
		$def = rwgc_get_target_definition( $key );
		if ( null === $def || ! class_exists( 'RWGC_Target_Availability', false ) ) {
			return false;
		}
		return RWGC_Target_Availability::is_available( $def );
	}
}

if ( ! function_exists( 'rwgc_register_module' ) ) {
	/**
	 * Register or replace a Geo Suite module row (alias for rwgc_register_modules).
	 *
	 * @param array<string, mixed> $module Module row: id, label, description, optional active, admin_url, install_url, is_active_callback, plugin_files.
	 * @return void
	 */
	function rwgc_register_module( array $module ) {
		if ( empty( $module['id'] ) || ! is_string( $module['id'] ) ) {
			return;
		}
		$id = $module['id'];
		add_filter(
			'rwgc_register_modules',
			static function ( $modules ) use ( $module, $id ) {
				if ( ! is_array( $modules ) ) {
					$modules = array();
				}
				$replaced = false;
				foreach ( $modules as $i => $row ) {
					if ( is_array( $row ) && isset( $row['id'] ) && (string) $row['id'] === (string) $id ) {
						$modules[ $i ] = array_merge( $row, $module );
						$replaced      = true;
						break;
					}
				}
				if ( ! $replaced ) {
					$modules[] = $module;
				}
				return $modules;
			}
		);
	}
}

if ( ! function_exists( 'rwgc_register_dashboard_card' ) ) {
	/**
	 * Register a Geo Core dashboard summary card (fires on rwgc_dashboard_satellite_panels).
	 *
	 * @param callable $callback Echoes card markup (same pattern as satellite summary cards).
	 * @return void
	 */
	function rwgc_register_dashboard_card( $callback ) {
		if ( is_callable( $callback ) ) {
			add_action( 'rwgc_dashboard_satellite_panels', $callback, 10, 0 );
		}
	}
}

if ( ! function_exists( 'rwgc_register_app_route' ) ) {
	/**
	 * Register an in-app route (app shell section nav; wp-admin submenu optional).
	 *
	 * @param array<string, mixed> $args section, route, menu_slug, label, callback; optional register_wp_submenu (default false), show_in_wp_sidebar (legacy alias).
	 * @return string|false Hook suffix.
	 */
	function rwgc_register_app_route( array $args ) {
		if ( empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}
		$slug = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug ) {
			return false;
		}

		if ( ! isset( $args['show_in_wp_sidebar'] ) ) {
			$args['show_in_wp_sidebar'] = false;
		}

		if ( class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			RWGC_Admin_Route_Registry::register_route( $args );
		}

		$page_title = isset( $args['page_title'] ) ? (string) $args['page_title'] : ( isset( $args['label'] ) ? (string) $args['label'] : $slug );
		$menu_title = isset( $args['menu_title'] ) ? (string) $args['menu_title'] : ( isset( $args['label'] ) ? (string) $args['label'] : $slug );

		$register_wp_submenu = class_exists( 'RWGC_Admin_Route_Registry', false )
			? RWGC_Admin_Route_Registry::resolve_register_wp_submenu( $args )
			: ! empty( $args['register_wp_submenu'] ) || ! empty( $args['show_in_wp_sidebar'] );

		$merged = array_merge(
			$args,
			array(
				'page_title' => $page_title,
				'menu_title' => $menu_title,
				'menu_slug'  => $slug,
			)
		);

		if ( $register_wp_submenu ) {
			return rwgc_register_admin_submenu( $merged );
		}

		$parent_slug = function_exists( 'rwgc_admin_menu_parent' ) ? rwgc_admin_menu_parent() : 'rwgc-dashboard';
		if ( $slug === $parent_slug ) {
			return false;
		}

		if ( class_exists( 'RWGC_Admin_Platform', false ) ) {
			return RWGC_Admin_Platform::register_shell_only_page( $merged );
		}

		return rwgc_register_admin_submenu( $merged );
	}
}

if ( ! function_exists( 'rwgc_app_url' ) ) {
	/**
	 * Admin URL for a goal section route in the ReactWoo Geo app shell.
	 *
	 * @param string $section_id Goal section id (overview, targeting, commerce, …).
	 * @param string $menu_slug  Optional menu slug.
	 * @return string
	 */
	function rwgc_app_url( $section_id, $menu_slug = '' ) {
		if ( class_exists( 'RWGC_Admin_Route_Registry', false ) ) {
			return RWGC_Admin_Route_Registry::get_url( $section_id, $menu_slug );
		}
		$menu_slug = sanitize_key( (string) $menu_slug );
		if ( '' === $menu_slug ) {
			$menu_slug = 'rwgc-dashboard';
		}
		return admin_url( 'admin.php?page=' . rawurlencode( $menu_slug ) );
	}
}

if ( ! function_exists( 'rwgc_register_admin_submenu' ) ) {
	/**
	 * Register a wp-admin submenu under the ReactWoo Geo hub (hidden from sidebar by default).
	 *
	 * @param array<string, mixed> $args page_title, menu_title, capability, menu_slug, callback; optional position.
	 * @return string|false Hook suffix from add_submenu_page.
	 */
	function rwgc_register_admin_submenu( array $args ) {
		$default_cap = 'manage_options';
		if ( class_exists( 'RWGC_Admin', false ) ) {
			$default_cap = RWGC_Admin::required_capability();
		}
		if ( empty( $args['capability'] ) ) {
			$args['capability'] = $default_cap;
		}
		if ( class_exists( 'RWGC_Admin_Platform', false ) ) {
			return RWGC_Admin_Platform::register_submenu( $args );
		}
		$parent = 'rwgc-dashboard';
		$slug   = isset( $args['menu_slug'] ) ? sanitize_key( (string) $args['menu_slug'] ) : '';
		if ( '' === $slug || empty( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
			return false;
		}
		return add_submenu_page(
			$parent,
			isset( $args['page_title'] ) ? (string) $args['page_title'] : '',
			isset( $args['menu_title'] ) ? (string) $args['menu_title'] : '',
			(string) $args['capability'],
			$slug,
			$args['callback'],
			isset( $args['position'] ) ? $args['position'] : null
		);
	}
}

if ( ! function_exists( 'rwgc_get_setup_progress' ) ) {
	/**
	 * Platform onboarding checklist progress for Overview.
	 *
	 * @return array{steps: array<int, array<string, mixed>>, completed: int, total: int, percent: int}
	 */
	function rwgc_get_setup_progress() {
		return class_exists( 'RWGC_Onboarding', false )
			? RWGC_Onboarding::get_setup_progress()
			: array(
				'steps'     => array(),
				'completed' => 0,
				'total'     => 0,
				'percent'   => 0,
			);
	}
}

if ( ! function_exists( 'rwgc_uses_platform_shell' ) ) {
	/**
	 * Whether the ReactWoo Geo unified admin app shell is active on this request.
	 *
	 * @return bool
	 */
	function rwgc_uses_platform_shell() {
		return class_exists( 'RWGC_Admin_App_Shell', false ) && RWGC_Admin_App_Shell::should_render();
	}
}

if ( ! function_exists( 'rwgc_admin_menu_parent' ) ) {
	/**
	 * Geo Core hub parent menu slug for satellite submenus.
	 *
	 * @return string
	 */
	function rwgc_admin_menu_parent() {
		return class_exists( 'RWGC_Admin_Platform', false )
			? RWGC_Admin_Platform::menu_parent()
			: 'rwgc-dashboard';
	}
}

if ( ! function_exists( 'rwgc_render_inner_nav' ) ) {
	/**
	 * Render horizontal section nav (shared Geo Core / satellite shell).
	 *
	 * @param array<string, string|array{label:string,url?:string}> $items   Slug => label or array with label + optional url.
	 * @param string                                                  $current Active admin page slug.
	 * @param array<string, mixed>                                      $args    Optional: filter, aria_label, show_hub_breadcrumb, hub_extension_label.
	 * @return void
	 */
	function rwgc_render_inner_nav( array $items, $current, $args = array() ) {
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_inner_nav( $items, (string) $current, $args );
			return;
		}
		$filter = isset( $args['filter'] ) ? (string) $args['filter'] : '';
		if ( '' !== $filter ) {
			$items = apply_filters( $filter, $items, $current ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook name is passed in by the caller.
		}
		$aria = isset( $args['aria_label'] ) ? (string) $args['aria_label'] : __( 'Section navigation', 'reactwoo-geocore' );
		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr( $aria ) . '">';
		foreach ( $items as $slug => $label ) {
			if ( is_array( $label ) ) {
				$label = isset( $label['label'] ) ? (string) $label['label'] : '';
			}
			if ( '' === (string) $label ) {
				continue;
			}
			$class = 'rwgc-inner-nav__link' . ( (string) $slug === (string) $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( (string) $label ) . '</a>';
		}
		echo '</nav>';
	}
}

if ( ! function_exists( 'rw_geo_register_module' ) ) {
	/**
	 * Deprecated alias of rwgc_register_module().
	 *
	 * @param array<string, mixed> $module Module row.
	 * @return void
	 */
	function rw_geo_register_module( array $module ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		_deprecated_function( __FUNCTION__, '1.9.6', 'rwgc_register_module' );
		rwgc_register_module( $module );
	}
}

if ( ! function_exists( 'rw_geo_register_dashboard_card' ) ) {
	/**
	 * Deprecated alias of rwgc_register_dashboard_card().
	 *
	 * @param callable $callback Card renderer.
	 * @return void
	 */
	function rw_geo_register_dashboard_card( $callback ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		_deprecated_function( __FUNCTION__, '1.9.6', 'rwgc_register_dashboard_card' );
		rwgc_register_dashboard_card( $callback );
	}
}

if ( ! function_exists( 'rw_geo_register_app_route' ) ) {
	/**
	 * Deprecated alias of rwgc_register_app_route().
	 *
	 * @param array<string, mixed> $args Route arguments.
	 * @return string|false
	 */
	function rw_geo_register_app_route( array $args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		_deprecated_function( __FUNCTION__, '1.9.6', 'rwgc_register_app_route' );
		return rwgc_register_app_route( $args );
	}
}

if ( ! function_exists( 'rw_geo_app_url' ) ) {
	/**
	 * Deprecated alias of rwgc_app_url().
	 *
	 * @param string $section_id Goal section id.
	 * @param string $menu_slug  Optional menu slug.
	 * @return string
	 */
	function rw_geo_app_url( $section_id, $menu_slug = '' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		_deprecated_function( __FUNCTION__, '1.9.6', 'rwgc_app_url' );
		return rwgc_app_url( $section_id, $menu_slug );
	}
}

if ( ! function_exists( 'rw_geo_register_admin_submenu' ) ) {
	/**
	 * Deprecated alias of rwgc_register_admin_submenu().
	 *
	 * @param array<string, mixed> $args Submenu arguments.
	 * @return string|false
	 */
	function rw_geo_register_admin_submenu( array $args ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		_deprecated_function( __FUNCTION__, '1.9.6', 'rwgc_register_admin_submenu' );
		return rwgc_register_admin_submenu( $args );
	}
}

if ( ! function_exists( 'rw_geo_render_inner_nav' ) ) {
	/**
	 * Deprecated alias of rwgc_render_inner_nav().
	 *
	 * @param array<string, string|array{label:string,url?:string}> $items   Nav items.
	 * @param string                                                  $current Active slug.
	 * @param array<string, mixed>                                    $args    Optional args.
	 * @return void
	 */
	function rw_geo_render_inner_nav( array $items, $current, $args = array() ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
		_deprecated_function( __FUNCTION__, '1.9.6', 'rwgc_render_inner_nav' );
		rwgc_render_inner_nav( $items, $current, $args );
	}
}

