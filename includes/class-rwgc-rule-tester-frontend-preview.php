<?php
/**
 * Signed short-lived frontend preview for Rule Tester simulated visitors.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only preview mode: overrides geo/context for one front-end request chain.
 */
class RWGC_Rule_Tester_Frontend_Preview {

	const QUERY_FLAG  = 'rwgc_preview';
	const QUERY_TOKEN = 'rwgc_preview_token';
	const TOKEN_TTL   = 900;

	/**
	 * @var array<string,mixed>|null
	 */
	private static $payload = null;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'bootstrap' ), 1 );
	}

	/**
	 * @return void
	 */
	public static function bootstrap() {
		if ( is_admin() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ self::QUERY_FLAG ] ) || '1' !== (string) wp_unslash( $_GET[ self::QUERY_FLAG ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET[ self::QUERY_TOKEN ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ self::QUERY_TOKEN ] ) ) : '';
		if ( '' === $token ) {
			return;
		}

		$payload = self::verify_token( $token );
		if ( ! is_array( $payload ) || ! self::can_preview() ) {
			return;
		}

		self::$payload = $payload;

		if ( class_exists( 'RWGC_Context_Resolver', false ) ) {
			RWGC_Context_Resolver::reset_cache();
		}

		add_action( 'send_headers', array( __CLASS__, 'send_no_cache_headers' ), 0 );
		add_filter( 'rwgc_geo_data', array( __CLASS__, 'filter_geo_data' ), 999 );
		add_filter( 'rwgc_context_snapshot_values', array( __CLASS__, 'filter_context_snapshot' ), 999 );
		add_filter( 'rwgc_context_attribution_should_persist', array( __CLASS__, 'filter_skip_persist' ), 999 );
		add_action( 'wp_footer', array( __CLASS__, 'render_banner' ), 5 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_notice' ), 100 );

		/**
		 * Fires when Rule Tester signed preview mode is active on the front end.
		 *
		 * @param array<string, mixed> $payload Verified preview payload.
		 */
		do_action( 'rwgc_rule_tester_preview_bootstrapped', $payload );
	}

	/**
	 * @return bool
	 */
	public static function is_active() {
		return is_array( self::$payload );
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function get_payload() {
		return self::$payload;
	}

	/**
	 * @return bool
	 */
	public static function can_preview() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$can = class_exists( 'RWGC_Admin', false ) && RWGC_Admin::can_manage();
		/**
		 * @param bool $can Default: Geo Core managers.
		 */
		return (bool) apply_filters( 'rwgc_rule_tester_can_preview', $can );
	}

	/**
	 * @param array<string,mixed> $data Token payload (context, content, rule_id, rule_label).
	 * @return string|\WP_Error
	 */
	public static function create_token( array $data ) {
		if ( ! self::can_preview() ) {
			return new WP_Error( 'rwgc_preview_forbidden', __( 'You do not have permission to create preview links.', 'reactwoo-geocore' ) );
		}

		$payload = array(
			'exp'         => time() + self::TOKEN_TTL,
			'uid'         => get_current_user_id(),
			'rule_id'     => isset( $data['rule_id'] ) ? absint( $data['rule_id'] ) : 0,
			'rule_label'  => isset( $data['rule_label'] ) ? sanitize_text_field( (string) $data['rule_label'] ) : '',
			'content'     => isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array(),
			'context'     => isset( $data['context'] ) && is_array( $data['context'] ) ? $data['context'] : array(),
			'assignment'  => isset( $data['assignment'] ) && is_array( $data['assignment'] ) ? $data['assignment'] : array(),
		);

		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) || '' === $json ) {
			return new WP_Error( 'rwgc_preview_encode', __( 'Could not encode preview token.', 'reactwoo-geocore' ) );
		}

		$sig   = hash_hmac( 'sha256', $json, wp_salt( 'rwgc_rule_tester_preview' ) );
		$token = self::base64url_encode( $json ) . '.' . $sig;
		return $token;
	}

	/**
	 * @param array<string,mixed> $data   Tester payload.
	 * @return string|\WP_Error
	 */
	public static function build_preview_url( array $data ) {
		$token = self::create_token( $data );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = self::resolve_target_url( $data );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return add_query_arg(
			array(
				self::QUERY_FLAG  => '1',
				self::QUERY_TOKEN => (string) $token,
			),
			$url
		);
	}

	/**
	 * @param string $token Signed token.
	 * @return array<string,mixed>|null
	 */
	public static function verify_token( $token ) {
		$token = (string) $token;
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		$json = self::base64url_decode( $parts[0] );
		$sig  = (string) $parts[1];
		if ( ! is_string( $json ) || '' === $json ) {
			return null;
		}

		$expected = hash_hmac( 'sha256', $json, wp_salt( 'rwgc_rule_tester_preview' ) );
		if ( ! hash_equals( $expected, $sig ) ) {
			return null;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( empty( $data['exp'] ) || (int) $data['exp'] < time() ) {
			return null;
		}
		if ( empty( $data['uid'] ) || (int) $data['uid'] !== get_current_user_id() ) {
			return null;
		}

		return $data;
	}

	/**
	 * @param bool $should Default persist flag.
	 * @return bool
	 */
	public static function filter_skip_persist( $should ) {
		if ( self::is_active() ) {
			return false;
		}
		return (bool) $should;
	}

	/**
	 * @return void
	 */
	public static function send_no_cache_headers() {
		if ( ! self::is_active() ) {
			return;
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-RWGC-Rule-Tester-Preview: 1' );
		}
	}

	/**
	 * @param array<string,mixed> $data Geo payload.
	 * @return array<string,mixed>
	 */
	public static function filter_geo_data( $data ) {
		if ( ! self::is_active() || ! is_array( $data ) ) {
			return $data;
		}
		$context = isset( self::$payload['context'] ) && is_array( self::$payload['context'] ) ? self::$payload['context'] : array();
		$country = isset( $context['country'] ) ? strtoupper( substr( sanitize_text_field( (string) $context['country'] ), 0, 2 ) ) : '';
		if ( '' === $country ) {
			return $data;
		}
		$data['country_code'] = $country;
		$data['country_name'] = class_exists( 'RWGC_GeoIP', false ) ? RWGC_GeoIP::get_country_name( $country ) : $country;
		$data['source']       = 'rule_tester_preview';
		$data['cached']       = false;
		return $data;
	}

	/**
	 * @param array<string,mixed> $merged Context values.
	 * @return array<string,mixed>
	 */
	public static function filter_context_snapshot( array $merged ) {
		if ( ! self::is_active() ) {
			return $merged;
		}
		$context = isset( self::$payload['context'] ) && is_array( self::$payload['context'] ) ? self::$payload['context'] : array();

		if ( ! empty( $context['country'] ) ) {
			$merged['country'] = strtoupper( substr( sanitize_text_field( (string) $context['country'] ), 0, 2 ) );
		}
		if ( ! empty( $context['device'] ) ) {
			$merged['device_type'] = sanitize_key( (string) $context['device'] );
		}
		if ( ! empty( $context['page_type'] ) ) {
			$pt                    = sanitize_key( (string) $context['page_type'] );
			$merged['page_type']   = $pt;
			$merged['page_types']  = array( $pt );
		}
		if ( isset( $context['request_uri'] ) && '' !== trim( (string) $context['request_uri'] ) ) {
			$merged['request_uri'] = sanitize_text_field( (string) $context['request_uri'] );
		}
		if ( isset( $context['utm_source'] ) ) {
			$merged['source'] = sanitize_text_field( (string) $context['utm_source'] );
		}
		if ( isset( $context['utm_medium'] ) ) {
			$merged['medium'] = sanitize_text_field( (string) $context['utm_medium'] );
		}
		if ( ! empty( $context['gclid'] ) ) {
			$merged['gclid'] = '1';
		} else {
			$merged['gclid'] = '';
		}

		$merged['rule_tester_preview'] = true;
		return $merged;
	}

	/**
	 * @return void
	 */
	public static function render_banner() {
		if ( ! self::is_active() || ! self::can_preview() ) {
			return;
		}
		$context = isset( self::$payload['context'] ) && is_array( self::$payload['context'] ) ? self::$payload['context'] : array();
		$parts   = array();
		if ( ! empty( $context['country'] ) ) {
			$parts[] = strtoupper( (string) $context['country'] );
		}
		if ( ! empty( $context['device'] ) ) {
			$parts[] = ucfirst( sanitize_key( (string) $context['device'] ) );
		}
		if ( ! empty( $context['page_type'] ) && class_exists( 'RWGC_Rule_Context_Compatibility', false ) ) {
			$parts[] = RWGC_Rule_Context_Compatibility::page_type_label( (string) $context['page_type'] );
		}
		if ( ! empty( self::$payload['rule_label'] ) ) {
			$parts[] = (string) self::$payload['rule_label'];
		}

		$exit = remove_query_arg( array( self::QUERY_FLAG, self::QUERY_TOKEN ) );
		$line = implode( ' · ', array_filter( $parts ) );

		echo '<div class="rwgc-rule-tester-preview-banner" style="position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#1d2327;color:#fff;padding:10px 16px;font:14px/1.4 sans-serif;display:flex;align-items:center;justify-content:space-between;gap:12px;">';
		echo '<span><strong>' . esc_html__( 'GeoCore Preview Mode', 'reactwoo-geocore' ) . '</strong>';
		if ( '' !== $line ) {
			echo ' — ' . esc_html( $line );
		}
		echo '</span>';
		if ( $exit ) {
			echo '<a href="' . esc_url( $exit ) . '" style="color:#fff;text-decoration:underline;white-space:nowrap;">' . esc_html__( 'Exit preview', 'reactwoo-geocore' ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
	 * @return void
	 */
	public static function admin_bar_notice( $wp_admin_bar ) {
		if ( ! self::is_active() || ! $wp_admin_bar instanceof WP_Admin_Bar ) {
			return;
		}
		$wp_admin_bar->add_node(
			array(
				'id'    => 'rwgc-rule-tester-preview',
				'title' => esc_html__( 'GeoCore rule preview active', 'reactwoo-geocore' ),
				'href'  => esc_url( remove_query_arg( array( self::QUERY_FLAG, self::QUERY_TOKEN ) ) ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $data Tester data.
	 * @return string|\WP_Error
	 */
	private static function resolve_target_url( array $data ) {
		$content = isset( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : array();
		$type    = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$id      = isset( $content['id'] ) ? absint( $content['id'] ) : 0;

		if ( in_array( $type, array( 'page', 'post', 'product' ), true ) && $id > 0 ) {
			$link = get_permalink( $id );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
			return new WP_Error( 'rwgc_preview_no_url', __( 'Could not resolve a preview URL for the selected content.', 'reactwoo-geocore' ) );
		}

		if ( 'manual' === $type || ! empty( $content['url'] ) ) {
			$path = isset( $content['url'] ) ? (string) $content['url'] : '';
			if ( '' !== trim( $path ) ) {
				if ( 0 === strpos( $path, 'http' ) ) {
					return esc_url_raw( $path );
				}
				return home_url( ltrim( $path, '/' ) );
			}
		}

		if ( ! empty( $data['context']['request_uri'] ) ) {
			$path = (string) $data['context']['request_uri'];
			if ( 0 === strpos( $path, 'http' ) ) {
				return esc_url_raw( $path );
			}
			return home_url( ltrim( $path, '/' ) );
		}

		return new WP_Error( 'rwgc_preview_no_url', __( 'Select content with a valid URL to preview.', 'reactwoo-geocore' ) );
	}

	/**
	 * @param string $raw Raw string.
	 * @return string
	 */
	private static function base64url_encode( $raw ) {
		return rtrim( strtr( base64_encode( (string) $raw ), '+/', '-_' ), '=' );
	}

	/**
	 * @param string $raw Encoded string.
	 * @return string|false
	 */
	private static function base64url_decode( $raw ) {
		$raw = (string) $raw;
		$pad = strlen( $raw ) % 4;
		if ( $pad > 0 ) {
			$raw .= str_repeat( '=', 4 - $pad );
		}
		return base64_decode( strtr( $raw, '-_', '+/' ), true );
	}
}
