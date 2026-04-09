<?php
/**
 * Weather targets (placeholder provider; integration later).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers weather targets; values empty until a weather integration is configured.
 */
class RWGC_Target_Provider_Weather implements RWGC_Target_Provider_Interface {

	/**
	 * @inheritDoc
	 */
	public function get_provider_key() {
		return 'weather';
	}

	/**
	 * @inheritDoc
	 */
	public function is_available() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function register_targets( RWGC_Target_Registry $registry ) {
		$registry->register_target_type(
			array(
				'key'           => 'weather_condition',
				'label'         => __( 'Weather condition', 'reactwoo-geocore' ),
				'group'         => 'weather',
				'description'   => __( 'Requires a weather integration (not configured).', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
				'is_available_callback' => array( __CLASS__, 'target_not_configured' ),
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'temperature_band',
				'label'         => __( 'Temperature band', 'reactwoo-geocore' ),
				'group'         => 'weather',
				'description'   => __( 'Requires a weather integration (not configured).', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'between', 'greater_than', 'less_than' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
				'is_available_callback' => array( __CLASS__, 'target_not_configured' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $definition Definition.
	 * @return bool
	 */
	public static function target_not_configured( $definition ) {
		/**
		 * Whether weather targets are configured (future integration).
		 *
		 * @param bool $configured Default false.
		 */
		return (bool) apply_filters( 'rwgc_weather_targets_configured', false );
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		$condition = '';
		$band      = '';
		/**
		 * Filter resolved weather context values.
		 *
		 * @param array{weather_condition?: string, temperature_band?: string} $values Values.
		 * @param array<string, mixed> $base Merged base values.
		 */
		$filtered = apply_filters(
			'rwgc_weather_context_values',
			array(
				'weather_condition' => $condition,
				'temperature_band'  => $band,
			),
			$base
		);
		return is_array( $filtered ) ? $filtered : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_admin_status() {
		$on = (bool) apply_filters( 'rwgc_weather_targets_configured', false );
		return array(
			'label'  => __( 'Weather', 'reactwoo-geocore' ),
			'state'  => $on ? 'ok' : 'warn',
			'detail' => $on ? __( 'Weather provider connected.', 'reactwoo-geocore' ) : __( 'Not configured; targets register for future use.', 'reactwoo-geocore' ),
		);
	}
}
