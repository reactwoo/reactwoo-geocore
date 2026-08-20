<?php
/**
 * Cached Cloud entitlements (admin/cron heartbeat only).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local snapshot of Cloud entitlements. Never fetched on the visitor render path.
 */
final class RWGC_Cloud_Entitlement_Store {

	const OPTION = 'rwgc_cloud_entitlements';

	/**
	 * @param array<string, mixed> $payload Heartbeat entitlements object.
	 * @return void
	 */
	public static function put( array $payload ) {
		$items = array();
		if ( isset( $payload['items'] ) && is_array( $payload['items'] ) ) {
			foreach ( $payload['items'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['key'] ) ) {
					continue;
				}
				$items[] = array(
					'key'     => (string) $row['key'],
					'allowed' => ! empty( $row['allowed'] ),
					'limit'   => array_key_exists( 'limit', $row ) ? $row['limit'] : null,
					'source'  => 'cloud',
				);
			}
			$items = array_values(
				array_filter(
					$items,
					static function ( $row ) {
						return is_string( $row['key'] ) && (bool) preg_match( '/^[a-z][a-z0-9_.]*$/', $row['key'] );
					}
				)
			);
		}

		update_option(
			self::OPTION,
			array(
				'plan'        => isset( $payload['plan'] ) ? (string) $payload['plan'] : '',
				'status'      => isset( $payload['status'] ) ? (string) $payload['status'] : 'inactive',
				'grace'       => ! empty( $payload['grace'] ),
				'grace_until' => isset( $payload['grace_until'] ) ? (string) $payload['grace_until'] : '',
				'source'      => 'cloud',
				'items'       => $items,
				'updated_at'  => gmdate( 'c' ),
			),
			false
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get() {
		$stored = get_option( self::OPTION, null );
		if ( ! is_array( $stored ) || empty( $stored['items'] ) || ! is_array( $stored['items'] ) ) {
			return null;
		}
		return $stored;
	}

	/**
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Connected Cloud cache is usable.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( ! class_exists( 'RWGC_Cloud_Connection', false ) || ! RWGC_Cloud_Connection::is_connected() ) {
			return false;
		}
		return null !== self::get();
	}

	/**
	 * Cloud bundle is the current commercial source (active or past_due-in-grace).
	 *
	 * @return bool
	 */
	public static function is_commercially_active() {
		if ( ! self::is_active() ) {
			return false;
		}
		$snapshot = self::get();
		if ( ! is_array( $snapshot ) ) {
			return false;
		}
		$status = isset( $snapshot['status'] ) ? strtolower( (string) $snapshot['status'] ) : '';
		if ( in_array( $status, array( 'active', 'pending-cancel' ), true ) ) {
			return true;
		}
		if ( $status === 'past_due' && ! empty( $snapshot['grace'] ) ) {
			return true;
		}
		return false;
	}
}
