<?php
/**
 * Secure site pairing with short-lived Cloud tokens.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP initiates pairing; Cloud returns revocable site credentials.
 * No WordPress user passwords are sent.
 */
final class RWGC_Cloud_Pairing {

	/**
	 * Pair using a one-time token from the Cloud portal.
	 *
	 * @param string $pairing_token Token.
	 * @return array{ok: bool, error: string, site_id: string}
	 */
	public static function pair( $pairing_token ) {
		$pairing_token = trim( (string) $pairing_token );
		if ( '' === $pairing_token ) {
			return array(
				'ok'      => false,
				'error'   => 'empty_token',
				'site_id' => '',
			);
		}

		RWGC_Cloud_Connection::update(
			array(
				'state'      => RWGC_Cloud_Connection::STATE_PAIRING,
				'last_error' => '',
			)
		);

		$body = array(
			'pairing_token' => $pairing_token,
			'site_url'      => function_exists( 'home_url' ) ? home_url( '/' ) : '',
			'site_name'     => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : '',
			'plugins'       => self::plugin_report(),
			'core_version'  => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
		);

		$response = RWGC_Cloud_Http::request( 'POST', '/sites/pair', $body );
		if ( ! $response['ok'] || ! is_array( $response['body'] ) ) {
			RWGC_Cloud_Connection::update(
				array(
					'state'      => RWGC_Cloud_Connection::STATE_ERROR,
					'last_error' => $response['error'] ? $response['error'] : 'pair_failed',
				)
			);
			return array(
				'ok'      => false,
				'error'   => $response['error'] ? $response['error'] : 'pair_failed',
				'site_id' => '',
			);
		}

		$site_id     = isset( $response['body']['site_id'] ) ? (string) $response['body']['site_id'] : '';
		$site_secret = isset( $response['body']['site_secret'] ) ? (string) $response['body']['site_secret'] : '';
		$api_base    = isset( $response['body']['api_base'] ) ? (string) $response['body']['api_base'] : RWGC_Cloud_Config::api_base();

		if ( '' === $site_id || '' === $site_secret ) {
			RWGC_Cloud_Connection::update(
				array(
					'state'      => RWGC_Cloud_Connection::STATE_ERROR,
					'last_error' => 'invalid_pair_response',
				)
			);
			return array(
				'ok'      => false,
				'error'   => 'invalid_pair_response',
				'site_id' => '',
			);
		}

		if ( ! RWGC_Cloud_Credentials::store( $site_id, $site_secret, $api_base ) ) {
			RWGC_Cloud_Connection::update(
				array(
					'state'      => RWGC_Cloud_Connection::STATE_ERROR,
					'last_error' => 'credential_store_failed',
				)
			);
			return array(
				'ok'      => false,
				'error'   => 'credential_store_failed',
				'site_id' => '',
			);
		}

		$confirm = self::confirm( $site_id, $site_secret, $api_base );
		if ( ! $confirm['ok'] ) {
			RWGC_Cloud_Connection::update(
				array(
					'state'      => RWGC_Cloud_Connection::STATE_ERROR,
					'last_error' => $confirm['error'],
					'site_id'    => $site_id,
				)
			);
			return array(
				'ok'      => false,
				'error'   => $confirm['error'],
				'site_id' => $site_id,
			);
		}

		RWGC_Cloud_Connection::update(
			array(
				'state'           => RWGC_Cloud_Connection::STATE_CONNECTED,
				'site_id'         => $site_id,
				'paired_at'       => gmdate( 'c' ),
				'last_error'      => '',
				'management_mode' => 'local',
			)
		);

		return array(
			'ok'      => true,
			'error'   => '',
			'site_id' => $site_id,
		);
	}

	/**
	 * @param string $site_id Site ID.
	 * @param string $site_secret Secret.
	 * @param string $api_base API base.
	 * @return array{ok: bool, error: string}
	 */
	public static function confirm( $site_id, $site_secret, $api_base = '' ) {
		$response = RWGC_Cloud_Http::request(
			'POST',
			'/sites/confirm',
			array( 'site_id' => (string) $site_id ),
			array(),
			array(
				'site_secret' => $site_secret,
				'api_base'    => $api_base ? $api_base : RWGC_Cloud_Config::api_base(),
			)
		);
		if ( ! $response['ok'] ) {
			return array(
				'ok'    => false,
				'error' => $response['error'] ? $response['error'] : 'confirm_failed',
			);
		}
		return array(
			'ok'    => true,
			'error' => '',
		);
	}

	/**
	 * @return list<array{slug: string, version: string, active: bool}>
	 */
	public static function plugin_report() {
		$report = array(
			array(
				'slug'    => 'reactwoo-geocore',
				'version' => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
				'active'  => true,
			),
		);
		/**
		 * Filter plugin inventory reported to Cloud.
		 *
		 * @param list<array{slug: string, version: string, active: bool}> $report Report.
		 */
		$filtered = apply_filters( 'rwgc_cloud_plugin_report', $report );
		return is_array( $filtered ) ? $filtered : $report;
	}
}
