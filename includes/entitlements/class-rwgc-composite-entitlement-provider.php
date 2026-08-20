<?php
/**
 * Composite: any currently valid grant wins (PLAN.md).
 *
 * Cloud is the commercial source when commercially active. Connection must not
 * destroy a still-valid individual/standalone grant.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single facade target for feature code.
 */
final class RWGC_Composite_Entitlement_Provider implements RWGC_Entitlement_Provider_Interface {

	/** @var string[] Cloud-only limit keys. */
	private static $cloud_limit_keys = array( 'sites.max', 'team_members.max', 'history.days' );

	/** @var RWGC_Standalone_License_Provider */
	private $standalone;
	/** @var RWGC_Cloud_Entitlement_Provider */
	private $cloud;

	public function __construct( RWGC_Standalone_License_Provider $standalone, RWGC_Cloud_Entitlement_Provider $cloud ) {
		$this->standalone = $standalone;
		$this->cloud      = $cloud;
	}

	/**
	 * {@inheritdoc}
	 */
	public function allows( $key ) {
		if ( $this->standalone->allows( $key ) ) {
			return true;
		}
		if ( $this->cloud_grant_valid() && $this->cloud->allows( $key ) ) {
			return true;
		}
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function limit( $key ) {
		$key = (string) $key;
		if ( in_array( $key, self::$cloud_limit_keys, true ) && $this->cloud_grant_valid() ) {
			return $this->cloud->limit( $key );
		}
		if ( $this->cloud_grant_valid() && $this->cloud->allows( $key ) && ! $this->standalone->allows( $key ) ) {
			return $this->cloud->limit( $key );
		}
		return $this->standalone->limit( $key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function source() {
		return $this->cloud_grant_valid() ? 'cloud' : 'standalone';
	}

	/**
	 * {@inheritdoc}
	 */
	public function all() {
		$out = $this->standalone->all();
		if ( $this->cloud->is_active() ) {
			foreach ( $this->cloud->all() as $grant ) {
				$out[] = $grant;
			}
		}
		return $out;
	}

	/**
	 * Cloud bundle grant is currently valid (active or grace). Canceled snapshots do not count.
	 *
	 * @return bool
	 */
	private function cloud_grant_valid() {
		if ( ! $this->cloud->is_active() ) {
			return false;
		}
		return RWGC_Cloud_Entitlement_Store::is_commercially_active();
	}
}
