<?php
/**
 * Cached Cloud recommendations (admin/cron). Never applied to visitors.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advisory list from Decision Cloud. Approve/dismiss never writes the live manifest.
 */
final class RWGC_Cloud_Recommendations {

	const OPTION = 'rwgc_cloud_recommendations';

	/**
	 * @return list<array<string, mixed>>
	 */
	public static function current() {
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) || empty( $raw['items'] ) || ! is_array( $raw['items'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw['items'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row['live'] = false;
			$out[]       = $row;
		}
		return $out;
	}

	/**
	 * @return array{ok: bool, error: string, items: list<array<string, mixed>>}
	 */
	public static function refresh() {
		$creds = self::creds_or_error();
		if ( isset( $creds['error'] ) ) {
			return array(
				'ok'    => false,
				'error' => $creds['error'],
				'items' => self::current(),
			);
		}

		$response = RWGC_Cloud_Http::request(
			'GET',
			'/sites/' . rawurlencode( $creds['site_id'] ) . '/recommendations',
			array(),
			array(),
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
			)
		);
		if ( ! $response['ok'] || ! is_array( $response['body'] ) ) {
			return array(
				'ok'    => false,
				'error' => $response['error'] ? $response['error'] : 'recommendations_failed',
				'items' => self::current(),
			);
		}
		$items = isset( $response['body']['items'] ) && is_array( $response['body']['items'] )
			? $response['body']['items']
			: array();
		self::store( $items );
		return array(
			'ok'    => true,
			'error' => '',
			'items' => self::current(),
		);
	}

	/**
	 * Approve a proposed recommendation. Cloud saves a draft only.
	 *
	 * @param string $id Recommendation ID.
	 * @return array{ok: bool, error: string, live: bool, compiled: bool}
	 */
	public static function approve( $id ) {
		return self::act( $id, 'approve' );
	}

	/**
	 * @param string $id Recommendation ID.
	 * @return array{ok: bool, error: string, live: bool, compiled: bool}
	 */
	public static function dismiss( $id ) {
		return self::act( $id, 'dismiss' );
	}

	/**
	 * @param string $id ID.
	 * @param string $action approve|dismiss.
	 * @return array{ok: bool, error: string, live: bool, compiled: bool}
	 */
	private static function act( $id, $action ) {
		$id = trim( (string) $id );
		if ( '' === $id ) {
			return array(
				'ok'       => false,
				'error'    => 'missing_id',
				'live'     => false,
				'compiled' => false,
			);
		}
		$creds = self::creds_or_error();
		if ( isset( $creds['error'] ) ) {
			return array(
				'ok'       => false,
				'error'    => $creds['error'],
				'live'     => false,
				'compiled' => false,
			);
		}

		$response = RWGC_Cloud_Http::request(
			'POST',
			'/sites/' . rawurlencode( $creds['site_id'] ) . '/recommendations/' . rawurlencode( $id ) . '/' . $action,
			array(),
			array(),
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
			)
		);
		if ( ! $response['ok'] ) {
			return array(
				'ok'       => false,
				'error'    => $response['error'] ? $response['error'] : $action . '_failed',
				'live'     => false,
				'compiled' => false,
			);
		}
		self::refresh();
		return array(
			'ok'       => true,
			'error'    => '',
			'live'     => false,
			'compiled' => false,
		);
	}

	/**
	 * @param list<array<string, mixed>> $items Items.
	 * @return void
	 */
	public static function store( array $items ) {
		update_option(
			self::OPTION,
			array(
				'items'      => $items,
				'updated_at' => function_exists( 'gmdate' ) ? gmdate( 'c' ) : '',
			)
		);
	}

	/**
	 * @return array{site_id: string, site_secret: string, api_base: string}|array{error: string}
	 */
	private static function creds_or_error() {
		if ( ! class_exists( 'RWGC_Cloud_Connection', false ) || ! RWGC_Cloud_Connection::is_connected() ) {
			return array( 'error' => 'not_connected' );
		}
		$creds = RWGC_Cloud_Credentials::get();
		if ( ! $creds ) {
			return array( 'error' => 'missing_credentials' );
		}
		return $creds;
	}
}
