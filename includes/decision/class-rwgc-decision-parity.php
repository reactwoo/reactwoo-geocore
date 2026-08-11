<?php
/**
 * Gate A helpers: compare portable rule matching with Decision Runtime audiences.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build equivalent Decision Runtime inputs from simple portable country rules.
 */
final class RWGC_Decision_Parity {

	/**
	 * Whether a portable country "in" rule matches the same as a geo.country audience.
	 *
	 * @param string $country_iso2 Visitor country.
	 * @param string[] $allowed Allowed countries in the portable rule.
	 * @return array{portable: bool, decision: bool, equivalent: bool}
	 */
	public static function compare_country_in_rule( $country_iso2, array $allowed ) {
		RWGC_Decision::load();

		$country_iso2 = strtoupper( trim( (string) $country_iso2 ) );
		$allowed      = array_values(
			array_filter(
				array_map(
					static function ( $c ) {
						return strtoupper( trim( (string) $c ) );
					},
					$allowed
				)
			)
		);

		$portable = false;
		if ( class_exists( 'RWGC_Rule_Evaluator', false ) && class_exists( 'RWGC_Context_Snapshot', false ) ) {
			$set = array(
				'enabled' => true,
				'mode'    => 'show',
				'match'   => 'any',
				'rules'   => array(
					array(
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'country',
								'operator' => 'in',
								'value'    => $allowed,
							),
						),
					),
				),
			);
			$snapshot = RWGC_Context_Snapshot::from_array( array( 'country' => $country_iso2 ) );
			$portable = RWGC_Rule_Evaluator::matches( $set, $snapshot );
		}

		$manifest = RWGC_Contract_Manifest::from_array(
			array(
				'schema'      => '1.0',
				'revision'    => 1,
				'site'        => 'parity',
				'audiences'   => array(
					array(
						'id'         => 'aud_parity',
						'name'       => 'Parity',
						'conditions' => array(
							'all' => array(
								array(
									'capability' => 'geo.country',
									'operator'   => 'in',
									'value'      => $allowed,
								),
							),
						),
					),
				),
				'experiences' => array(
					array(
						'id'          => 'exp_parity',
						'name'        => 'Parity',
						'audience_id' => 'aud_parity',
						'slot_id'     => 'slot_parity',
						'variant_id'  => 'default',
						'status'      => 'active',
						'priority'    => 50,
					),
				),
				'variants'    => array(),
				'experiments' => array(),
				'goals'       => array(),
				'slots'       => array(),
			)
		);

		$ctx = RWGC_Contract_Context::from_array(
			array(
				'geo.country' => $country_iso2,
			)
		);

		// Ensure capability exists for Decision Runtime fail-closed checks.
		if ( function_exists( 'reactwoo_register_condition' ) && function_exists( 'reactwoo_has_capability' ) && ! reactwoo_has_capability( 'geo.country' ) ) {
			reactwoo_register_condition(
				'geo.country',
				array(
					'label'    => 'Country',
					'provider' => 'reactwoo-geocore',
				)
			);
		}

		$result   = RWGC_Decision_Runtime::evaluate( $manifest, $ctx );
		$decision = in_array( 'aud_parity', $result->matched_audiences(), true );

		return array(
			'portable'    => $portable,
			'decision'    => $decision,
			'equivalent'  => $portable === $decision,
		);
	}
}
