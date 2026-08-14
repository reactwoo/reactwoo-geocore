<?php
/**
 * Cloud-cached entitlements.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the heartbeat cache. Never calls Cloud during visitor rendering.
 */
final class RWGC_Cloud_Entitlement_Provider implements RWGC_Entitlement_Provider_Interface {

	/**
	 * @return bool
	 */
	public function is_active() {
		return RWGC_Cloud_Entitlement_Store::is_active();
	}

	/**
	 * {@inheritdoc}
	 */
	public function allows( $key ) {
		$row = $this->row( $key );
		return $row ? (bool) $row['allowed'] : false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function limit( $key ) {
		$row = $this->row( $key );
		if ( ! $row || ! array_key_exists( 'limit', $row ) ) {
			return null;
		}
		return $row['limit'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function source() {
		return 'cloud';
	}

	/**
	 * {@inheritdoc}
	 */
	public function all() {
		$snapshot = RWGC_Cloud_Entitlement_Store::get();
		if ( ! $snapshot ) {
			return array();
		}
		$out = array();
		foreach ( $snapshot['items'] as $row ) {
			$out[] = RWGC_Contract_Entitlement::from_array(
				array(
					'key'     => $row['key'],
					'allowed' => $row['allowed'],
					'limit'   => $row['limit'],
					'source'  => 'cloud',
				)
			);
		}
		return $out;
	}

	/**
	 * @param string $key Key.
	 * @return array<string, mixed>|null
	 */
	private function row( $key ) {
		$snapshot = RWGC_Cloud_Entitlement_Store::get();
		if ( ! $snapshot ) {
			return null;
		}
		$key = (string) $key;
		foreach ( $snapshot['items'] as $row ) {
			if ( isset( $row['key'] ) && $row['key'] === $key ) {
				return $row;
			}
		}
		return null;
	}
}
