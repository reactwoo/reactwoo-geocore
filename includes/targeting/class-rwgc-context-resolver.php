<?php
/**
 * Builds {@see RWGC_Context_Snapshot} from registered providers.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves current visitor context and single target values.
 */
class RWGC_Context_Resolver {

	/**
	 * Cached snapshot for request.
	 *
	 * @var RWGC_Context_Snapshot|null
	 */
	private static $current = null;

	/**
	 * Resolve snapshot for the active request (cached).
	 *
	 * @return RWGC_Context_Snapshot
	 */
	public static function resolve_current() {
		if ( null !== self::$current ) {
			return self::$current;
		}
		RWGC_Target_Registry::init();

		$merged = self::collect_provider_values();
		$merged = self::apply_definition_resolve_callbacks( $merged );

		/**
		 * Filter merged raw context values before wrapping in snapshot.
		 *
		 * @param array<string, mixed> $merged Keyed values.
		 */
		$merged = apply_filters( 'rwgc_context_snapshot_values', $merged );

		self::$current = new RWGC_Context_Snapshot( is_array( $merged ) ? $merged : array() );
		return self::$current;
	}

	/**
	 * Preview context with admin overrides (not cached as current).
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return RWGC_Context_Snapshot
	 */
	public static function resolve_for_preview( array $overrides = array() ) {
		RWGC_Target_Registry::init();
		$merged = self::collect_provider_values();
		$merged = self::apply_definition_resolve_callbacks( $merged );
		$merged = is_array( $merged ) ? $merged : array();
		$base   = new RWGC_Context_Snapshot( $merged );
		return RWGC_Target_Simulator::apply_overrides( $base, $overrides );
	}

	/**
	 * Resolve one target value from the current snapshot.
	 *
	 * @param string $target_key Target key.
	 * @return mixed|null
	 */
	public static function resolve_target_value( $target_key ) {
		$snap = self::resolve_current();
		return $snap->get( (string) $target_key, null );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_provider_values() {
		$classes = apply_filters(
			'rwgc_target_provider_classes',
			array(
				'RWGC_Target_Provider_Geo',
				'RWGC_Target_Provider_Language',
				'RWGC_Target_Provider_Time',
				'RWGC_Target_Provider_Device',
				'RWGC_Target_Provider_Weather',
				'RWGC_Target_Provider_Analytics',
				'RWGC_Target_Provider_Commerce',
			)
		);

		$merged = array(
			'custom' => array(),
		);

		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			$obj = new $class();
			if ( ! $obj instanceof RWGC_Target_Provider_Interface || ! $obj->is_available() ) {
				continue;
			}
			$chunk = $obj->resolve_context_values( $merged );
			if ( ! is_array( $chunk ) ) {
				continue;
			}
			foreach ( $chunk as $k => $v ) {
				$k = sanitize_key( (string) $k );
				if ( '' === $k ) {
					continue;
				}
				$merged[ $k ] = $v;
			}
		}

		return $merged;
	}

	/**
	 * Optional per-definition resolve callbacks (extensions).
	 *
	 * @param array<string, mixed> $merged Values.
	 * @return array<string, mixed>
	 */
	private static function apply_definition_resolve_callbacks( array $merged ) {
		$registry = RWGC_Target_Registry::instance();
		foreach ( $registry->get_target_types() as $key => $def ) {
			if ( empty( $def['resolve_callback'] ) || ! is_callable( $def['resolve_callback'] ) ) {
				continue;
			}
			try {
				$val = call_user_func( $def['resolve_callback'], $merged );
			} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
				continue;
			}
			$merged[ $key ] = $val;
		}
		return $merged;
	}

	/**
	 * Clear cached snapshot (tests).
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$current = null;
	}
}
