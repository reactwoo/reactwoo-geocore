<?php
/**
 * Render a resolved Variant to HTML with mandatory default fallback.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gate C renderer — never fatals on visitor path.
 */
final class RWGC_Variant_Renderer {

	/**
	 * @param RWGC_Variant_Interface $variant Variant.
	 * @param string                 $default_html Default HTML.
	 * @param array<string, mixed>   $context Context.
	 * @return string
	 */
	public static function render( RWGC_Variant_Interface $variant, $default_html, array $context = array() ) {
		$default_html = is_string( $default_html ) ? $default_html : '';
		$slot_id      = '';
		if ( isset( $context['slot'] ) && $context['slot'] instanceof RWGC_Contract_Experience_Slot ) {
			$slot_id = $context['slot']->id();
		}

		try {
			switch ( $variant->type() ) {
				case RWGC_Contract_Variant::TYPE_DEFAULT:
					return $default_html;

				case RWGC_Contract_Variant::TYPE_CONTENT:
					return self::render_content( $variant, $default_html, $slot_id );

				case RWGC_Contract_Variant::TYPE_REACTWOO_COMPONENT:
					return self::render_component( $variant, $default_html, $slot_id, $context );

				case RWGC_Contract_Variant::TYPE_NATIVE_REFERENCE:
					return self::render_native( $variant, $default_html, $slot_id, $context );

				default:
					RWGC_Variant_Diagnostics::record( 'invalid', $variant->id(), $slot_id, array( 'type' => $variant->type() ) );
					return $default_html;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			RWGC_Variant_Diagnostics::record(
				'error',
				$variant->id(),
				$slot_id,
				array( 'message' => $e->getMessage() )
			);
			return $default_html;
		}
	}

	/**
	 * Resolve + render by ID (convenience for slot filter).
	 *
	 * @param string                             $variant_id Variant ID.
	 * @param string                             $default_html Default.
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @param array<string, mixed>               $context Context.
	 * @return string|null Null means “use slot default path” when unresolved.
	 */
	public static function render_id( $variant_id, $default_html, $slot = null, array $context = array() ) {
		$resolved = RWGC_Variant_Resolver::resolve( $variant_id, $slot );
		if ( ! $resolved['ok'] || ! $resolved['variant'] ) {
			return null;
		}
		if ( isset( $context['slot'] ) ) {
			// keep
		} elseif ( $slot ) {
			$context['slot'] = $slot;
		}
		$html = self::render( $resolved['variant'], $default_html, $context );
		if ( RWGC_Contract_Variant::TYPE_DEFAULT === $resolved['variant']->type() ) {
			return $default_html;
		}
		return is_string( $html ) && '' !== $html ? $html : null;
	}

	/**
	 * @param RWGC_Variant_Interface $variant Variant.
	 * @param string                 $default_html Default.
	 * @param string                 $slot_id Slot ID.
	 * @return string
	 */
	private static function render_content( RWGC_Variant_Interface $variant, $default_html, $slot_id ) {
		if ( ! $variant instanceof RWGC_Content_Variant ) {
			RWGC_Variant_Diagnostics::record( 'invalid', $variant->id(), $slot_id );
			return $default_html;
		}
		$html = $variant->html();
		if ( '' === $html ) {
			RWGC_Variant_Diagnostics::record( 'invalid', $variant->id(), $slot_id, array( 'detail' => 'empty_content' ) );
			return $default_html;
		}
		/**
		 * Filter content-variant HTML (trusted author content).
		 *
		 * @param string                 $html HTML.
		 * @param RWGC_Content_Variant   $variant Variant.
		 */
		$html = apply_filters( 'reactwoo_variant_content_html', $html, $variant );
		return is_string( $html ) && '' !== $html ? $html : $default_html;
	}

	/**
	 * @param RWGC_Variant_Interface $variant Variant.
	 * @param string                 $default_html Default.
	 * @param string                 $slot_id Slot ID.
	 * @param array<string, mixed>   $context Context.
	 * @return string
	 */
	private static function render_component( RWGC_Variant_Interface $variant, $default_html, $slot_id, array $context ) {
		if ( ! $variant instanceof RWGC_Component_Variant ) {
			RWGC_Variant_Diagnostics::record( 'invalid', $variant->id(), $slot_id );
			return $default_html;
		}
		if ( ! function_exists( 'reactwoo_render_component' ) ) {
			RWGC_Variant_Diagnostics::record( 'error', $variant->id(), $slot_id, array( 'detail' => 'components_unavailable' ) );
			return $default_html;
		}
		$html = reactwoo_render_component( $variant->component_type(), $variant->props(), $context );
		if ( '' === $html ) {
			RWGC_Variant_Diagnostics::record( 'error', $variant->id(), $slot_id, array( 'detail' => 'component_render_empty' ) );
			return $default_html;
		}
		return $html;
	}

	/**
	 * @param RWGC_Variant_Interface $variant Variant.
	 * @param string                 $default_html Default.
	 * @param string                 $slot_id Slot ID.
	 * @param array<string, mixed>   $context Context.
	 * @return string
	 */
	private static function render_native( RWGC_Variant_Interface $variant, $default_html, $slot_id, array $context ) {
		if ( ! $variant instanceof RWGC_Native_Reference_Variant ) {
			RWGC_Variant_Diagnostics::record( 'invalid', $variant->id(), $slot_id );
			return $default_html;
		}
		$ref = $variant->reference();
		/**
		 * Resolve a native design reference to HTML.
		 *
		 * @param string|null                    $html HTML.
		 * @param string                         $reference Reference.
		 * @param RWGC_Native_Reference_Variant  $variant Variant.
		 * @param array<string, mixed>           $context Context.
		 */
		$html = apply_filters( 'reactwoo_resolve_native_reference', null, $ref, $variant, $context );
		if ( ! is_string( $html ) || '' === $html ) {
			RWGC_Variant_Diagnostics::record( 'missing', $variant->id(), $slot_id, array( 'reference' => $ref ) );
			return $default_html;
		}
		return $html;
	}
}
