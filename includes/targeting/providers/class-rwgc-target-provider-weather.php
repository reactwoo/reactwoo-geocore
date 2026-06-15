<?php
/**
 * Weather targets — shopping facets when GeoCore Pro weather is configured.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers shopping-weather facet targets for diagnostics and future simulators.
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
				'key'                   => 'weather_facet',
				'label'                 => __( 'Shopping weather', 'reactwoo-geocore' ),
				'group'                 => 'weather',
				'description'           => __( 'Wet, dry, hot, cold, windy, sunny — requires GeoCore Pro weather.', 'reactwoo-geocore' ),
				'operators'             => array( 'in', 'not_in', 'is', 'is_not' ),
				'value_mode'            => 'multi',
				'provider'              => $this->get_provider_key(),
				'supports_simulation'   => true,
				'is_available_callback' => array( __CLASS__, 'weather_configured' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $definition Definition.
	 * @return bool
	 */
	public static function weather_configured( $definition ) {
		unset( $definition );
		return (bool) apply_filters( 'rwgc_weather_targets_configured', false );
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		$facets = array();
		if ( isset( $base['weather'] ) && is_array( $base['weather'] ) && ! empty( $base['weather']['facets'] ) && is_array( $base['weather']['facets'] ) ) {
			$facets = array_values( array_map( 'strval', $base['weather']['facets'] ) );
		}
		/**
		 * Filter resolved weather facet values for targeting diagnostics.
		 *
		 * @param array{weather_facet?: string[]} $values Facet slugs.
		 * @param array<string, mixed>           $base   Merged base values.
		 */
		$filtered = apply_filters(
			'rwgc_weather_context_values',
			array(
				'weather_facet' => $facets,
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
			'detail' => $on
				? __( 'Shopping weather facets available for targeting rules.', 'reactwoo-geocore' )
				: __( 'Configure GeoCore Pro weather to enable shopping-weather rules.', 'reactwoo-geocore' ),
		);
	}
}
