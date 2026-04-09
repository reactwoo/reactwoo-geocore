<?php
/**
 * Analytics / audience targets (GA4-friendly placeholders; async-friendly).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers analytics targets; request-time values may be empty — satellites should handle gracefully.
 */
class RWGC_Target_Provider_Analytics implements RWGC_Target_Provider_Interface {

	/**
	 * @inheritDoc
	 */
	public function get_provider_key() {
		return 'analytics';
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
		$ops = array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains' );
		$registry->register_target_type(
			array(
				'key'           => 'ga_audience',
				'label'         => __( 'Analytics audience', 'reactwoo-geocore' ),
				'group'         => 'analytics',
				'description'   => __( 'Audience membership from analytics integrations (may be async).', 'reactwoo-geocore' ),
				'operators'     => $ops,
				'value_mode'    => 'multi',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
				'is_available_callback' => array( __CLASS__, 'is_analytics_configured' ),
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'analytics_device_type',
				'label'         => __( 'Analytics device type', 'reactwoo-geocore' ),
				'group'         => 'analytics',
				'description'   => __( 'Device classification from analytics when available.', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
				'is_available_callback' => array( __CLASS__, 'is_analytics_configured' ),
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'analytics_user_type',
				'label'         => __( 'Analytics user type', 'reactwoo-geocore' ),
				'group'         => 'analytics',
				'description'   => __( 'New vs returning from analytics when available.', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
				'is_available_callback' => array( __CLASS__, 'is_analytics_configured' ),
			)
		);
		foreach ( array( 'source', 'medium', 'campaign' ) as $utm ) {
			$registry->register_target_type(
				array(
					'key'           => $utm,
					'label'         => ucfirst( $utm ),
					'group'         => 'analytics',
					'description'   => __( 'First-touch or session campaign fields when present on the request.', 'reactwoo-geocore' ),
					'operators'     => array( 'is', 'is_not', 'contains', 'not_contains', 'in', 'not_in' ),
					'value_mode'    => 'text',
					'provider'      => $this->get_provider_key(),
					'supports_simulation' => true,
				)
			);
		}
		$registry->register_target_type(
			array(
				'key'           => 'returning_visitor',
				'label'         => __( 'Returning visitor', 'reactwoo-geocore' ),
				'group'         => 'analytics',
				'description'   => __( 'Cookie-based returning hint (best-effort).', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not' ),
				'value_mode'    => 'boolean',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'new_visitor',
				'label'         => __( 'New visitor', 'reactwoo-geocore' ),
				'group'         => 'analytics',
				'description'   => __( 'Inverse of returning visitor hint.', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not' ),
				'value_mode'    => 'boolean',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
	}

	/**
	 * @param array<string, mixed> $definition Definition.
	 * @return bool
	 */
	public static function is_analytics_configured( $definition ) {
		/**
		 * Whether analytics-driven targets are fully configured (GA / GTM / Measurement Protocol, etc.).
		 *
		 * @param bool $configured Default false.
		 */
		return (bool) apply_filters( 'rwgc_analytics_targets_configured', false );
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		$utm = self::read_utm_from_request();
		$returning = self::is_returning_visitor();

		$audiences = array();
		/**
		 * Filter resolved analytics audience slugs for the current request.
		 *
		 * @param string[] $audiences Audience slugs.
		 * @param array<string, mixed> $base Base merged values.
		 */
		$audiences = apply_filters( 'rwgc_analytics_audiences', $audiences, $base );

		return array(
			'ga_audience'             => $audiences,
			'analytics_device_type'   => isset( $base['device_type'] ) ? (string) $base['device_type'] : '',
			'analytics_user_type'     => $returning ? 'returning' : 'new',
			'source'                  => isset( $utm['source'] ) ? $utm['source'] : '',
			'medium'                  => isset( $utm['medium'] ) ? $utm['medium'] : '',
			'campaign'                => isset( $utm['campaign'] ) ? $utm['campaign'] : '',
			'returning_visitor'       => $returning,
			'new_visitor'             => ! $returning,
		);
	}

	/**
	 * @return array{source: string, medium: string, campaign: string}
	 */
	private static function read_utm_from_request() {
		$out = array( 'source' => '', 'medium' => '', 'campaign' => '' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only analytics context.
		if ( isset( $_GET['utm_source'] ) ) {
			$out['source'] = sanitize_text_field( wp_unslash( (string) $_GET['utm_source'] ) );
		}
		if ( isset( $_GET['utm_medium'] ) ) {
			$out['medium'] = sanitize_text_field( wp_unslash( (string) $_GET['utm_medium'] ) );
		}
		if ( isset( $_GET['utm_campaign'] ) ) {
			$out['campaign'] = sanitize_text_field( wp_unslash( (string) $_GET['utm_campaign'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $out;
	}

	/**
	 * @return bool
	 */
	private static function is_returning_visitor() {
		$cookie = isset( $_COOKIE['rwgc_returning'] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE['rwgc_returning'] ) ) : '';
		if ( '' !== $cookie ) {
			return true;
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function get_admin_status() {
		$on = (bool) apply_filters( 'rwgc_analytics_targets_configured', false );
		return array(
			'label'  => __( 'Analytics', 'reactwoo-geocore' ),
			'state'  => $on ? 'ok' : 'warn',
			'detail' => $on
				? __( 'Analytics integration reports configured.', 'reactwoo-geocore' )
				: __( 'GA audience targets register; connect an integration for full fidelity.', 'reactwoo-geocore' ),
		);
	}
}
