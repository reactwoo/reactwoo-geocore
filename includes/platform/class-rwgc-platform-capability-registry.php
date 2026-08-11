<?php
/**
 * Platform Capability Registry (decision engine — not the admin UX product map).
 *
 * Distinct from {@see RWGC_Capability_Registry} (suite upgrade / product gating UI).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authoritative in-process registry for dotted capability IDs.
 */
final class RWGC_Platform_Capability_Registry {

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private static $capabilities = array();

	/**
	 * @var list<array{id: string, provider: string, message: string}>
	 */
	private static $collisions = array();

	/**
	 * Reset (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$capabilities = array();
		self::$collisions   = array();
	}

	/**
	 * Register a capability definition.
	 *
	 * @param array<string, mixed> $definition Definition (see docs/contracts/capabilities.md).
	 * @return true|RWGC_Contract_Exception True on success; throws/returns exception pattern via false + last error avoided — returns WP_Error-like array on failure when $throw false.
	 * @throws RWGC_Contract_Exception When $throw is true and registration fails.
	 */
	public static function register( array $definition, $throw = true ) {
		if ( ! class_exists( 'RWGC_Contract_Capability', false ) ) {
			RWGC_Contracts::load();
		}

		try {
			$contract = RWGC_Contract_Capability::from_array( $definition );
		} catch ( RWGC_Contract_Exception $e ) {
			if ( $throw ) {
				throw $e;
			}
			return $e;
		}

		$id       = $contract->id();
		$provider = self::optional_string( $definition, 'provider', 'unknown' );
		$row      = $contract->to_array();
		$row['provider'] = $provider;
		$row['availability'] = isset( $definition['availability'] ) && is_callable( $definition['availability'] )
			? $definition['availability']
			: null;
		$row['entitlement'] = isset( $row['entitlement'] ) ? (string) $row['entitlement'] : self::optional_string( $definition, 'entitlement_requirement' );

		if ( isset( self::$capabilities[ $id ] ) ) {
			$existing = self::$capabilities[ $id ];
			$existing_provider = isset( $existing['provider'] ) ? (string) $existing['provider'] : '';
			if ( $existing_provider !== $provider ) {
				$msg = sprintf(
					'Capability "%s" already registered by "%s"; refusing registration from "%s".',
					$id,
					$existing_provider,
					$provider
				);
				self::$collisions[] = array(
					'id'       => $id,
					'provider' => $provider,
					'message'  => $msg,
				);
				/**
				 * Fires when a capability registration collides.
				 *
				 * @param string               $id Capability ID.
				 * @param array<string, mixed> $existing Existing row.
				 * @param array<string, mixed> $attempted Attempted row.
				 */
				do_action( 'reactwoo_capability_collision', $id, $existing, $row );
				$ex = new RWGC_Contract_Exception( $msg );
				if ( $throw ) {
					throw $ex;
				}
				return $ex;
			}
			// Same provider: replace (explicit upgrade path for that plugin).
		}

		self::$capabilities[ $id ] = $row;

		/**
		 * Fires after a capability is registered.
		 *
		 * @param string               $id Capability ID.
		 * @param array<string, mixed> $row Stored row.
		 */
		do_action( 'reactwoo_capability_registered', $id, $row );

		return true;
	}

	/**
	 * @param string $id Capability ID (aliases accepted).
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		$id = RWGC_Schema::normalize_capability_id( $id );
		if ( '' === $id || ! isset( self::$capabilities[ $id ] ) ) {
			return null;
		}
		return self::$capabilities[ $id ];
	}

	/**
	 * @param string $id Capability ID.
	 * @return bool
	 */
	public static function has( $id ) {
		return null !== self::get( $id );
	}

	/**
	 * Whether the capability is currently available (runs availability callback when set).
	 *
	 * @param string $id Capability ID.
	 * @return bool
	 */
	public static function is_available( $id ) {
		$row = self::get( $id );
		if ( null === $row ) {
			return false;
		}
		if ( isset( $row['availability'] ) && is_callable( $row['availability'] ) ) {
			return (bool) call_user_func( $row['availability'] );
		}
		return true;
	}

	/**
	 * @param string|null $type Optional type filter.
	 * @return array<string, array<string, mixed>>
	 */
	public static function all( $type = null ) {
		if ( null === $type || '' === $type ) {
			return self::$capabilities;
		}
		$type = strtolower( (string) $type );
		$out  = array();
		foreach ( self::$capabilities as $id => $row ) {
			if ( isset( $row['type'] ) && $type === $row['type'] ) {
				$out[ $id ] = $row;
			}
		}
		return $out;
	}

	/**
	 * @return list<array{id: string, provider: string, message: string}>
	 */
	public static function collisions() {
		return self::$collisions;
	}

	/**
	 * Public export for Cloud heartbeat / diagnostics (no callables).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function export_for_report() {
		$out = array();
		foreach ( self::$capabilities as $id => $row ) {
			$out[] = array(
				'id'          => $id,
				'type'        => isset( $row['type'] ) ? $row['type'] : '',
				'label'       => isset( $row['label'] ) ? $row['label'] : '',
				'provider'    => isset( $row['provider'] ) ? $row['provider'] : '',
				'version'     => isset( $row['version'] ) ? $row['version'] : '',
				'entitlement' => isset( $row['entitlement'] ) ? $row['entitlement'] : '',
				'available'   => self::is_available( $id ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @param string               $key Key.
	 * @param string               $default Default.
	 * @return string
	 */
	private static function optional_string( array $data, $key, $default = '' ) {
		if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
			return $default;
		}
		return trim( (string) $data[ $key ] );
	}
}
