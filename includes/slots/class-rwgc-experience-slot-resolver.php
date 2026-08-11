<?php
/**
 * Resolve Experience Slots for render/decision.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Look up slots; missing/unavailable never throw.
 */
final class RWGC_Experience_Slot_Resolver {

	/**
	 * @var list<string>
	 */
	private static $missing = array();

	/**
	 * @return void
	 */
	public static function reset_diagnostics() {
		self::$missing = array();
	}

	/**
	 * @return list<string>
	 */
	public static function missing_ids() {
		return self::$missing;
	}

	/**
	 * @param string $id Slot ID.
	 * @return array{ok: bool, slot: RWGC_Contract_Experience_Slot|null, reason: string}
	 */
	public static function resolve( $id ) {
		$id = (string) $id;
		if ( '' === $id ) {
			return array(
				'ok'     => false,
				'slot'   => null,
				'reason' => 'empty_id',
			);
		}

		$slot = RWGC_Experience_Slot_Registry::get( $id );
		if ( null === $slot ) {
			self::$missing[] = $id;
			self::$missing   = array_values( array_unique( self::$missing ) );
			/**
			 * Missing slot diagnostic.
			 *
			 * @param string $id Slot ID.
			 */
			do_action( 'reactwoo_experience_slot_missing', $id );
			return array(
				'ok'     => false,
				'slot'   => null,
				'reason' => 'missing',
			);
		}

		if ( 'unavailable' === $slot->status() || 'deleted' === $slot->status() ) {
			return array(
				'ok'     => false,
				'slot'   => $slot,
				'reason' => 'unavailable',
			);
		}

		if ( 'active' !== $slot->status() ) {
			return array(
				'ok'     => false,
				'slot'   => $slot,
				'reason' => 'inactive:' . $slot->status(),
			);
		}

		return array(
			'ok'     => true,
			'slot'   => $slot,
			'reason' => 'ok',
		);
	}
}
