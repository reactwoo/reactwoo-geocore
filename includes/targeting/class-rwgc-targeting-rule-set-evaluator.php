<?php
/**
 * Back-compat facade for {@see RWGC_Rule_Evaluator}.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated Use {@see RWGC_Rule_Evaluator} directly. Kept for existing call sites.
 */
class RWGC_Targeting_Rule_Set_Evaluator {

	/**
	 * @param array<string, mixed>   $set      Sanitized rule set.
	 * @param RWGC_Context_Snapshot  $snapshot Visitor snapshot.
	 * @return bool
	 */
	public static function matches( array $set, RWGC_Context_Snapshot $snapshot ) {
		return RWGC_Rule_Evaluator::matches( $set, $snapshot );
	}

	/**
	 * @param array<string, mixed>  $set      Sanitized rule set.
	 * @param RWGC_Context_Snapshot $snapshot Snapshot.
	 * @return bool
	 */
	public static function should_render_content( array $set, RWGC_Context_Snapshot $snapshot ) {
		return RWGC_Rule_Evaluator::should_render_content( $set, $snapshot );
	}
}
