<?php
/**
 * Standalone license → entitlement keys.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps existing local product licenses onto the shared entitlement contract.
 */
final class RWGC_Standalone_License_Provider implements RWGC_Entitlement_Provider_Interface {

	/**
	 * @return array<string, array{allowed: bool, limit: mixed}>
	 */
	private function map() {
		$pro        = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
		$suite      = function_exists( 'rwgc_get_suite_capability_map' ) ? rwgc_get_suite_capability_map() : array();
		$commerce   = ! empty( $suite['geo_commerce_licensed'] );
		$optimise   = ! empty( $suite['geo_optimise_licensed'] );
		$components = true;
		$insights   = $optimise || class_exists( 'RWGC_Insights', false );

		$rows = array(
			'cloud.personalisation' => array( 'allowed' => $pro, 'limit' => null ),
			'cloud.commerce'        => array( 'allowed' => $commerce, 'limit' => null ),
			'cloud.optimise'        => array( 'allowed' => $optimise, 'limit' => null ),
			'cloud.components'      => array( 'allowed' => $components, 'limit' => null ),
			'cloud.insights'        => array( 'allowed' => $insights, 'limit' => null ),
			'sites.max'             => array( 'allowed' => true, 'limit' => 1 ),
			'team_members.max'      => array( 'allowed' => true, 'limit' => 1 ),
			'history.days'          => array( 'allowed' => true, 'limit' => null ),
		);

		/**
		 * @param array<string, array{allowed: bool, limit: mixed}> $rows Map.
		 */
		return apply_filters( 'rwgc_standalone_entitlements', $rows );
	}

	/**
	 * {@inheritdoc}
	 */
	public function allows( $key ) {
		$rows = $this->map();
		$key  = (string) $key;
		return isset( $rows[ $key ] ) ? (bool) $rows[ $key ]['allowed'] : false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function limit( $key ) {
		$rows = $this->map();
		$key  = (string) $key;
		if ( ! isset( $rows[ $key ] ) ) {
			return null;
		}
		return $rows[ $key ]['limit'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function source() {
		return 'standalone';
	}

	/**
	 * {@inheritdoc}
	 */
	public function all() {
		$out = array();
		foreach ( $this->map() as $key => $row ) {
			$out[] = RWGC_Contract_Entitlement::from_array(
				array(
					'key'     => $key,
					'allowed' => $row['allowed'],
					'limit'   => $row['limit'],
					'source'  => 'standalone',
				)
			);
		}
		return $out;
	}
}
