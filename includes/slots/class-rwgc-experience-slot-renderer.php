<?php
/**
 * Render an Experience Slot with mandatory default-content fallback.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gate B foundation: default website content always wins on failure / default variant.
 *
 * Not wired to Elementor/Gutenberg yet (WP6–7). Callers pass default HTML.
 */
final class RWGC_Experience_Slot_Renderer {

	/**
	 * Render slot output.
	 *
	 * @param string                    $slot_id Slot ID.
	 * @param callable|string           $default_content Callable returning HTML, or HTML string.
	 * @param RWGC_Decision_Result|null $decision Optional decision result.
	 * @return string HTML
	 */
	public static function render( $slot_id, $default_content, $decision = null ) {
		$default_html = self::resolve_default( $default_content );

		try {
			$resolved = RWGC_Experience_Slot_Resolver::resolve( $slot_id );
			if ( ! $resolved['ok'] ) {
				return $default_html;
			}

			$variant_id = '';
			if ( $decision instanceof RWGC_Decision_Result ) {
				$variant_id = $decision->variant_for_slot( (string) $slot_id );
			}

			/**
			 * Filter selected variant for a slot before render.
			 *
			 * @param string                         $variant_id Variant ID.
			 * @param string                         $slot_id Slot ID.
			 * @param RWGC_Decision_Result|null      $decision Decision.
			 * @param RWGC_Contract_Experience_Slot  $slot Slot.
			 */
			$variant_id = apply_filters( 'reactwoo_experience_slot_variant', $variant_id, $slot_id, $decision, $resolved['slot'] );
			$variant_id = is_string( $variant_id ) ? $variant_id : '';

			if ( '' === $variant_id || 'default' === $variant_id || 'variant_original' === $variant_id ) {
				return $default_html;
			}

			/**
			 * Render a non-default variant. Return null/empty to fall back to default.
			 *
			 * @param string|null                   $html Prior HTML.
			 * @param string                        $slot_id Slot ID.
			 * @param string                        $variant_id Variant ID.
			 * @param string                        $default_html Default HTML.
			 * @param RWGC_Decision_Result|null     $decision Decision.
			 * @param RWGC_Contract_Experience_Slot $slot Slot.
			 */
			$html = apply_filters( 'reactwoo_experience_slot_render_variant', null, $slot_id, $variant_id, $default_html, $decision, $resolved['slot'] );
			if ( is_string( $html ) && '' !== $html ) {
				return $html;
			}

			return $default_html;
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			if ( function_exists( 'error_log' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'RWGC_Experience_Slot_Renderer: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return $default_html;
		}
	}

	/**
	 * @param callable|string $default_content Default.
	 * @return string
	 */
	private static function resolve_default( $default_content ) {
		try {
			if ( is_callable( $default_content ) ) {
				$out = call_user_func( $default_content );
				return is_string( $out ) ? $out : '';
			}
			return is_string( $default_content ) ? $default_content : '';
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			return '';
		}
	}
}
