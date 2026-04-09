<?php
/**
 * Geography-related targets (Geo Core visitor payload).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers geography targets and resolves them from {@see RWGC_API} / GeoIP.
 */
class RWGC_Target_Provider_Geo implements RWGC_Target_Provider_Interface {

	/**
	 * @inheritDoc
	 */
	public function get_provider_key() {
		return 'geo';
	}

	/**
	 * @inheritDoc
	 */
	public function is_available() {
		return function_exists( 'rwgc_get_visitor_country' );
	}

	/**
	 * @inheritDoc
	 */
	public function register_targets( RWGC_Target_Registry $registry ) {
		$ops = array( 'is', 'is_not', 'in', 'not_in' );
		foreach ( array( 'country', 'region', 'city', 'currency' ) as $key ) {
			$registry->register_target_type(
				array(
					'key'           => $key,
					'label'         => $this->label_for( $key ),
					'group'         => 'geography',
					'description'   => __( 'Resolved from Geo Core visitor geo payload.', 'reactwoo-geocore' ),
					'operators'     => $ops,
					'value_mode'    => 'single',
					'provider'      => $this->get_provider_key(),
					'supports_simulation' => true,
				)
			);
		}
	}

	/**
	 * @param string $key Key.
	 * @return string
	 */
	private function label_for( $key ) {
		$labels = array(
			'country'  => __( 'Country', 'reactwoo-geocore' ),
			'region'   => __( 'Region', 'reactwoo-geocore' ),
			'city'     => __( 'City', 'reactwoo-geocore' ),
			'currency' => __( 'Currency', 'reactwoo-geocore' ),
		);
		return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		$out = array(
			'country'  => '',
			'region'   => '',
			'city'     => '',
			'currency' => '',
		);
		if ( ! $this->is_available() ) {
			return $out;
		}
		if ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$out['country'] = strtoupper( substr( (string) rwgc_get_visitor_country(), 0, 2 ) );
		}
		if ( function_exists( 'rwgc_get_visitor_region' ) ) {
			$out['region'] = (string) rwgc_get_visitor_region();
		}
		if ( function_exists( 'rwgc_get_visitor_city' ) ) {
			$out['city'] = (string) rwgc_get_visitor_city();
		}
		if ( function_exists( 'rwgc_get_visitor_currency' ) ) {
			$out['currency'] = strtoupper( substr( (string) rwgc_get_visitor_currency(), 0, 3 ) );
		}
		return $out;
	}

	/**
	 * @inheritDoc
	 */
	public function get_admin_status() {
		$ready = function_exists( 'rwgc_is_ready' ) && rwgc_is_ready();
		return array(
			'label'  => __( 'Geo (MaxMind / API)', 'reactwoo-geocore' ),
			'state'  => $ready ? 'ok' : 'warn',
			'detail' => $ready ? __( 'Visitor geo resolution active.', 'reactwoo-geocore' ) : __( 'Geo Core reports not ready; fallback country may apply.', 'reactwoo-geocore' ),
		);
	}
}
