<?php
/**
 * Preview / simulate context values (admin and diagnostics).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merges overrides onto a resolved snapshot for keys that support simulation.
 */
class RWGC_Target_Simulator {

	/**
	 * Apply preview overrides (admin) to a base snapshot.
	 *
	 * @param RWGC_Context_Snapshot $base Base snapshot.
	 * @param array<string, mixed>  $overrides Keys to force (e.g. country => FR).
	 * @return RWGC_Context_Snapshot
	 */
	public static function apply_overrides( RWGC_Context_Snapshot $base, array $overrides ) {
		if ( empty( $overrides ) ) {
			return $base;
		}
		$registry = RWGC_Target_Registry::instance();
		$clean    = array();
		foreach ( $overrides as $k => $v ) {
			$key = sanitize_key( (string) $k );
			if ( '' === $key ) {
				continue;
			}
			$def = $registry->get_target_type( $key );
			if ( is_array( $def ) && empty( $def['supports_simulation'] ) ) {
				continue;
			}
			$clean[ $key ] = $v;
		}
		return $base->merge( $clean );
	}
}
