<?php
/**
 * Loader for Variant Engine (WP9).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requires variant classes and wires Experience Slot render filter.
 */
final class RWGC_Variants {

	/** @var bool */
	private static $loaded = false;

	/**
	 * @return void
	 */
	public static function load() {
		if ( self::$loaded ) {
			return;
		}
		self::$loaded = true;
		$dir          = dirname( __FILE__ ) . '/';
		require_once $dir . 'interface-rwgc-variant.php';
		require_once $dir . 'class-rwgc-abstract-variant.php';
		require_once $dir . 'class-rwgc-default-variant.php';
		require_once $dir . 'class-rwgc-content-variant.php';
		require_once $dir . 'class-rwgc-component-variant.php';
		require_once $dir . 'class-rwgc-native-reference-variant.php';
		require_once $dir . 'class-rwgc-variant-factory.php';
		require_once $dir . 'class-rwgc-variant-store.php';
		require_once $dir . 'class-rwgc-variant-diagnostics.php';
		require_once $dir . 'class-rwgc-variant-resolver.php';
		require_once $dir . 'class-rwgc-variant-renderer.php';
		require_once $dir . 'functions-reactwoo-variants.php';
	}

	/**
	 * @return void
	 */
	public static function init() {
		self::load();
		add_filter( 'reactwoo_experience_slot_render_variant', array( __CLASS__, 'filter_slot_render_variant' ), 10, 6 );
	}

	/**
	 * Bridge Experience Slot → Variant Engine.
	 *
	 * @param string|null                        $html Prior.
	 * @param string                             $slot_id Slot ID.
	 * @param string                             $variant_id Variant ID.
	 * @param string                             $default_html Default.
	 * @param RWGC_Decision_Result|null          $decision Decision.
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @return string|null
	 */
	public static function filter_slot_render_variant( $html, $slot_id, $variant_id, $default_html, $decision, $slot ) {
		if ( is_string( $html ) && '' !== $html ) {
			return $html;
		}
		return RWGC_Variant_Renderer::render_id(
			$variant_id,
			is_string( $default_html ) ? $default_html : '',
			$slot instanceof RWGC_Contract_Experience_Slot ? $slot : null,
			array(
				'decision' => $decision,
				'slot'     => $slot,
				'slot_id'  => $slot_id,
			)
		);
	}
}
