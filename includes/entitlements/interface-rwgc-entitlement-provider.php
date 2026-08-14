<?php
/**
 * Entitlement provider contract (WP15).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature code asks allows()/limit() only — never Stripe or license JWT fields.
 */
interface RWGC_Entitlement_Provider_Interface {

	/**
	 * @param string $key Entitlement key (e.g. cloud.commerce).
	 * @return bool
	 */
	public function allows( $key );

	/**
	 * @param string $key Entitlement key.
	 * @return mixed Null when unlimited or unknown.
	 */
	public function limit( $key );

	/**
	 * @return string standalone|cloud
	 */
	public function source();

	/**
	 * @return array<int, RWGC_Contract_Entitlement>
	 */
	public function all();
}
