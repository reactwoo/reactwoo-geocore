<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches Geo events to WordPress hooks (`rwgc_geo_event` filter + action).
 */
class RWGC_Events {

	/**
	 * Emit a geo event: `rwgc_geo_event` filter then action.
	 *
	 * The filter receives an array payload; return a non-array to cancel the action.
	 *
	 * @param RWGC_Event $event Event envelope.
	 * @return void
	 */
	public static function emit( $event ) {
		if ( ! $event instanceof RWGC_Event ) {
			return;
		}
		$payload = $event->to_array();
		/**
		 * Filter Geo Core event payload before dispatch.
		 *
		 * @param array<string, mixed> $payload Event data.
		 */
		$payload = apply_filters( 'rwgc_geo_event', $payload );
		if ( ! is_array( $payload ) ) {
			return;
		}
		/**
		 * Fires when a Geo Core analytics/experiment event is emitted.
		 *
		 * @param array<string, mixed> $payload Event data.
		 */
		do_action( 'rwgc_geo_event', $payload );
	}
}
