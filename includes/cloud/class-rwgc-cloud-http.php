<?php
/**
 * Cloud HTTP transport (admin / cron only).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON client with injectable transport for tests.
 */
final class RWGC_Cloud_Http {

	/** @var int */
	private static $attempts = 0;

	/** @var bool True after template_redirect on the public front end. */
	private static $visitor_render = false;

	/**
	 * @return int Cloud HTTP attempts this PHP request.
	 */
	public static function attempt_count() {
		return self::$attempts;
	}

	/**
	 * @return void
	 */
	public static function reset_attempt_count() {
		self::$attempts = 0;
	}

	/**
	 * Hook visitor-render guard. Admin, cron, REST, and WP-CLI never set this.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'begin_visitor_render' ), 0 );
		}
	}

	/**
	 * Mark the rest of this request as a visitor page render.
	 *
	 * @return void
	 */
	public static function begin_visitor_render() {
		self::$visitor_render = true;
	}

	/**
	 * @return void
	 */
	public static function end_visitor_render() {
		self::$visitor_render = false;
	}

	/**
	 * Visitor page render must never call Cloud. Pairing/sync stay allowed
	 * until template_redirect (admin, cron, REST, WP-CLI).
	 *
	 * @return bool
	 */
	public static function is_forbidden_on_current_request() {
		$forbidden = self::$visitor_render;
		if ( $forbidden && function_exists( 'is_admin' ) && is_admin() ) {
			$forbidden = false;
		}
		if ( $forbidden && function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			$forbidden = false;
		}
		if ( $forbidden && defined( 'WP_CLI' ) && WP_CLI ) {
			$forbidden = false;
		}
		/**
		 * Block Cloud HTTP (visitor render path).
		 *
		 * @param bool $forbidden Forbidden.
		 */
		return (bool) apply_filters( 'rwgc_cloud_http_forbidden', $forbidden );
	}

	/**
	 * @param string               $method Method.
	 * @param string               $path Path under API base (e.g. /sites/pair).
	 * @param array<string, mixed> $body JSON body.
	 * @param array<string, string> $headers Extra headers.
	 * @param array<string, mixed> $args Extra (api_base, site_secret).
	 * @return array{ok: bool, status: int, body: array<string, mixed>|null, raw: string, error: string}
	 */
	public static function request( $method, $path, array $body = array(), array $headers = array(), array $args = array() ) {
		if ( self::is_forbidden_on_current_request() ) {
			return self::fail( 0, 'cloud_http_forbidden_on_render' );
		}
		++self::$attempts;
		$base = isset( $args['api_base'] ) ? (string) $args['api_base'] : RWGC_Cloud_Config::api_base();
		if ( ! RWGC_Cloud_Config::is_secure_base( $base ) ) {
			return self::fail( 0, 'insecure_api_base' );
		}

		$url = $base . '/' . ltrim( (string) $path, '/' );
		$headers = array_merge(
			array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'User-Agent'   => 'ReactWoo-GeoCore/' . ( defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '0' ),
			),
			$headers
		);
		if ( ! empty( $args['site_secret'] ) ) {
			$headers['Authorization'] = 'Bearer ' . (string) $args['site_secret'];
		}

		$payload = array(
			'method'  => strtoupper( (string) $method ),
			'url'     => $url,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 20,
		);

		/**
		 * Replace HTTP transport (tests). Return same shape as this method.
		 *
		 * @param null|array $response Response.
		 * @param array      $payload Payload.
		 */
		$filtered = apply_filters( 'rwgc_cloud_http_transport', null, $payload );
		if ( is_array( $filtered ) && isset( $filtered['ok'] ) ) {
			return $filtered;
		}

		if ( ! function_exists( 'wp_remote_request' ) ) {
			return self::fail( 0, 'wp_http_unavailable' );
		}

		$request_args = array(
			'method'  => $payload['method'],
			'timeout' => $payload['timeout'],
			'headers' => $headers,
		);
		if ( in_array( $payload['method'], array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$request_args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $request_args );
		if ( is_wp_error( $response ) ) {
			return self::fail( 0, $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$decoded = null;
		if ( '' !== $raw ) {
			$tmp = json_decode( $raw, true );
			if ( is_array( $tmp ) ) {
				$decoded = $tmp;
			}
		}

		return array(
			'ok'     => $status >= 200 && $status < 300,
			'status' => $status,
			'body'   => $decoded,
			'raw'    => $raw,
			'error'  => $status >= 200 && $status < 300 ? '' : 'http_' . $status,
		);
	}

	/**
	 * @param int    $status Status.
	 * @param string $error Error.
	 * @return array{ok: bool, status: int, body: null, raw: string, error: string}
	 */
	private static function fail( $status, $error ) {
		return array(
			'ok'     => false,
			'status' => (int) $status,
			'body'   => null,
			'raw'    => '',
			'error'  => (string) $error,
		);
	}
}
