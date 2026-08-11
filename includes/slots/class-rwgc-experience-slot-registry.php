<?php
/**
 * Persist and look up Experience Slots.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option-backed registry. Soft-deletes via status=unavailable.
 */
final class RWGC_Experience_Slot_Registry {

	const OPTION = 'rwgc_experience_slots';

	/**
	 * In-memory cache for the current request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static $cache = null;

	/**
	 * Reset cache (tests).
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$cache = null;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all_raw() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		self::$cache = $stored;
		return self::$cache;
	}

	/**
	 * @return list<RWGC_Contract_Experience_Slot>
	 */
	public static function all() {
		$out = array();
		foreach ( self::all_raw() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			try {
				$out[] = RWGC_Contract_Experience_Slot::from_array( $row );
			} catch ( RWGC_Contract_Exception $e ) {
				continue;
			}
		}
		return $out;
	}

	/**
	 * @param string $id Slot ID.
	 * @return RWGC_Contract_Experience_Slot|null
	 */
	public static function get( $id ) {
		$id  = (string) $id;
		$raw = self::all_raw();
		if ( ! isset( $raw[ $id ] ) || ! is_array( $raw[ $id ] ) ) {
			return null;
		}
		try {
			return RWGC_Contract_Experience_Slot::from_array( $raw[ $id ] );
		} catch ( RWGC_Contract_Exception $e ) {
			return null;
		}
	}

	/**
	 * Register or update a slot. Enforces unique binding ownership.
	 *
	 * @param array<string, mixed> $data Slot data (+ optional binding_key in metadata).
	 * @return array{slot: RWGC_Contract_Experience_Slot, regenerated: bool, previous_id: string}
	 * @throws RWGC_Contract_Exception When data invalid.
	 */
	public static function register( array $data ) {
		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			throw new RWGC_Contract_Exception( 'Slot name is required.' );
		}

		$id          = isset( $data['id'] ) ? trim( (string) $data['id'] ) : '';
		$regenerated = false;
		$previous_id = $id;
		$binding     = '';
		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) && isset( $data['metadata']['binding_key'] ) ) {
			$binding = (string) $data['metadata']['binding_key'];
		}

		$raw = self::all_raw();

		if ( '' === $id || ! RWGC_Experience_Slot_Id::is_valid( $id ) ) {
			$id          = RWGC_Experience_Slot_Id::generate( $name );
			$regenerated = true;
		} elseif ( isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ) {
			$existing_binding = '';
			if ( isset( $raw[ $id ]['metadata'] ) && is_array( $raw[ $id ]['metadata'] ) && isset( $raw[ $id ]['metadata']['binding_key'] ) ) {
				$existing_binding = (string) $raw[ $id ]['metadata']['binding_key'];
			}
			// Clone/paste: same ID claimed by a different element → new identity.
			if ( '' !== $binding && '' !== $existing_binding && $binding !== $existing_binding ) {
				$previous_id = $id;
				$id          = RWGC_Experience_Slot_Id::generate( $name );
				$regenerated = true;
			}
		}

		// Collision with another row (duplicate ID insert).
		if ( isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ) {
			$existing_binding = '';
			if ( isset( $raw[ $id ]['metadata'] ) && is_array( $raw[ $id ]['metadata'] ) && isset( $raw[ $id ]['metadata']['binding_key'] ) ) {
				$existing_binding = (string) $raw[ $id ]['metadata']['binding_key'];
			}
			if ( '' !== $binding && '' !== $existing_binding && $binding !== $existing_binding ) {
				$previous_id = $id;
				$id          = RWGC_Experience_Slot_Id::generate( $name );
				$regenerated = true;
			}
		}

		$data['id']   = $id;
		$data['name'] = $name;
		if ( empty( $data['status'] ) ) {
			$data['status'] = 'active';
		}

		$slot = RWGC_Contract_Experience_Slot::from_array( $data );
		$row  = $slot->to_array();
		$row['updated_at'] = gmdate( 'c' );
		if ( ! isset( $raw[ $id ]['created_at'] ) ) {
			$row['created_at'] = $row['updated_at'];
		} else {
			$row['created_at'] = $raw[ $id ]['created_at'];
		}

		$raw[ $id ]  = $row;
		self::$cache = $raw;
		update_option( self::OPTION, $raw, false );

		/**
		 * Fires after an experience slot is registered/updated.
		 *
		 * @param RWGC_Contract_Experience_Slot $slot Slot.
		 * @param bool                          $regenerated Whether ID was regenerated.
		 */
		do_action( 'reactwoo_experience_slot_registered', $slot, $regenerated );

		return array(
			'slot'        => $slot,
			'regenerated' => $regenerated,
			'previous_id' => $previous_id,
		);
	}

	/**
	 * Soft-delete: mark unavailable (does not destroy default website content).
	 *
	 * @param string $id Slot ID.
	 * @return bool
	 */
	public static function mark_unavailable( $id ) {
		$id  = (string) $id;
		$raw = self::all_raw();
		if ( ! isset( $raw[ $id ] ) || ! is_array( $raw[ $id ] ) ) {
			return false;
		}
		$raw[ $id ]['status']     = 'unavailable';
		$raw[ $id ]['updated_at'] = gmdate( 'c' );
		self::$cache              = $raw;
		update_option( self::OPTION, $raw, false );
		do_action( 'reactwoo_experience_slot_unavailable', $id );
		return true;
	}

	/**
	 * Diagnostics payload for admin.
	 *
	 * @return array{total: int, active: int, unavailable: int, invalid: int, duplicates: list<string>}
	 */
	public static function diagnostics() {
		$raw          = self::all_raw();
		$total        = 0;
		$active       = 0;
		$unavailable  = 0;
		$invalid      = 0;
		$seen_bindings = array();
		$duplicates   = array();

		foreach ( $raw as $id => $row ) {
			++$total;
			if ( ! is_array( $row ) ) {
				++$invalid;
				continue;
			}
			try {
				$slot = RWGC_Contract_Experience_Slot::from_array( $row );
			} catch ( RWGC_Contract_Exception $e ) {
				++$invalid;
				continue;
			}
			if ( 'unavailable' === $slot->status() ) {
				++$unavailable;
			} elseif ( 'active' === $slot->status() ) {
				++$active;
			}
			$meta = $slot->metadata();
			if ( ! empty( $meta['binding_key'] ) ) {
				$bk = (string) $meta['binding_key'];
				if ( isset( $seen_bindings[ $bk ] ) && $seen_bindings[ $bk ] !== $id ) {
					$duplicates[] = $bk;
				}
				$seen_bindings[ $bk ] = (string) $id;
			}
			if ( ! RWGC_Experience_Slot_Id::is_valid( (string) $id ) ) {
				++$invalid;
			}
		}

		return array(
			'total'       => $total,
			'active'      => $active,
			'unavailable' => $unavailable,
			'invalid'     => $invalid,
			'duplicates'  => array_values( array_unique( $duplicates ) ),
		);
	}
}
