<?php
/**
 * Plugins-screen update checks must not store an expiring R2 URL or block on every transient save.
 *
 * Run: php tests/test-rwgc-satellite-updater.php
 *
 * @package ReactWoo_Geo_Core
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['rw_failed']      = 0;
$GLOBALS['rw_posts']       = array();
$GLOBALS['rw_transients']  = array();
$GLOBALS['rw_options']     = array();
$GLOBALS['rw_site']        = false;
$GLOBALS['rw_downloads']   = array();
$GLOBALS['rw_login_mode']  = 'token';
$GLOBALS['rw_check_body']  = array();
$GLOBALS['rw_transport']   = false;

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Minimal WP_Error.
	 */
	class WP_Error {
		/**
		 * @var string
		 */
		public $code = '';

		/**
		 * @var string
		 */
		public $message = '';

		/**
		 * @param string $code Code.
		 * @param string $message Message.
		 * @param mixed  $data Data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			unset( $data );
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @return void
	 */
	function add_filter() {
	}
}
if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @return void
	 */
	function add_action() {
	}
}
if ( ! function_exists( 'do_action' ) ) {
	/**
	 * @return void
	 */
	function do_action() {
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) { // phpcs:ignore
		if ( 'rwgc_reactwoo_license_key' === $hook ) {
			return 'test-license';
		}
		if ( 'rwgc_reactwoo_api_base' === $hook ) {
			return 'https://api.reactwoo.com';
		}
		return $value;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param mixed $text Text.
	 * @return string
	 */
	function sanitize_text_field( $text ) {
		return trim( (string) $text );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @return string
	 */
	function home_url() {
		return 'https://shop.example/';
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function trailingslashit( $value ) {
		return rtrim( (string) $value, "/\\" ) . '/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, "/\\" );
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl TTL.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['rw_transients'][ $key ] = array(
			'value' => $value,
			'ttl'   => (int) $ttl,
		);
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return isset( $GLOBALS['rw_transients'][ $key ] ) ? $GLOBALS['rw_transients'][ $key ]['value'] : false;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_transient( $key ) {
		unset( $GLOBALS['rw_transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_site_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_site_transient( $key ) {
		if ( 'update_plugins' === $key ) {
			return $GLOBALS['rw_site'];
		}
		return false;
	}
}
if ( ! function_exists( 'delete_site_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_site_transient( $key ) {
		if ( 'update_plugins' === $key ) {
			$GLOBALS['rw_site'] = false;
		}
		return true;
	}
}
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['rw_options'] ) ? $GLOBALS['rw_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		$GLOBALS['rw_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param mixed $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return ( is_array( $response ) && isset( $response['response']['code'] ) ) ? (int) $response['response']['code'] : 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param mixed $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return ( is_array( $response ) && isset( $response['body'] ) ) ? (string) $response['body'] : '';
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * @param string $url URL.
	 * @param array  $args Args.
	 * @return array<string, mixed>|\WP_Error
	 */
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['rw_posts'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( ! empty( $GLOBALS['rw_transport'] ) ) {
			return new WP_Error( 'http_request_failed', 'timed out' );
		}
		if ( false !== strpos( $url, '/auth/login' ) ) {
			if ( 'fail' === $GLOBALS['rw_login_mode'] ) {
				return new WP_Error( 'http_request_failed', 'login timed out' );
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => (string) wp_json_encode(
					array(
						'access_token' => 'jwt-from-login',
						'expires_in'   => 3600,
					)
				),
			);
		}
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => (string) wp_json_encode( $GLOBALS['rw_check_body'] ),
		);
	}
}
if ( ! function_exists( 'download_url' ) ) {
	/**
	 * @param string $url URL.
	 * @param int    $timeout Timeout.
	 * @return string
	 */
	function download_url( $url, $timeout = 300 ) {
		$GLOBALS['rw_downloads'][] = array(
			'url'     => $url,
			'timeout' => $timeout,
		);
		return '/tmp/reactwoo-update.zip';
	}
}

/**
 * Records the login timeout passed by the updater.
 */
class RWGC_Test_Bearer {
	/**
	 * @var int[]
	 */
	public static $timeouts = array();

	/**
	 * @param int $timeout Timeout.
	 * @return string
	 */
	public static function token( $timeout = 30 ) {
		self::$timeouts[] = (int) $timeout;
		return 'callback-jwt';
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-platform-client.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-satellite-updater.php';

/**
 * @param string $label Label.
 * @param bool   $ok Condition.
 * @return void
 */
function rwgc_upd_assert( $label, $ok ) {
	if ( $ok ) {
		echo "ok  - {$label}\n";
		return;
	}
	echo "FAIL - {$label}\n";
	++$GLOBALS['rw_failed'];
}

/**
 * @return int
 */
function rwgc_upd_posts() {
	return count( $GLOBALS['rw_posts'] );
}

/**
 * @param string $needle URL fragment.
 * @return array<int, array<string, mixed>>
 */
function rwgc_upd_posts_matching( $needle ) {
	$found = array();
	foreach ( $GLOBALS['rw_posts'] as $post ) {
		if ( false !== strpos( (string) $post['url'], $needle ) ) {
			$found[] = $post;
		}
	}
	return $found;
}

/**
 * @param string $basename Plugin basename.
 * @param string $version Installed version.
 * @return stdClass
 */
function rwgc_upd_transient( $basename, $version ) {
	$transient           = new stdClass();
	$transient->checked  = array( $basename => $version );
	$transient->response = array();
	return $transient;
}

$core     = 'reactwoo-geocore/reactwoo-geocore.php';
$commerce = 'reactwoo-geo-commerce/reactwoo-geo-commerce.php';
$callback = 'reactwoo-callback/reactwoo-callback.php';

RWGC_Satellite_Updater::register(
	array(
		'basename'            => $core,
		'version'             => '1.0.0',
		'catalog_slug'        => 'reactwoo-geocore',
		'attach_bearer_token' => false,
		'name'                => 'ReactWoo Geo Core',
	)
);
RWGC_Satellite_Updater::register(
	array(
		'basename'            => $commerce,
		'version'             => '1.0.0',
		'catalog_slug'        => 'reactwoo-geo-commerce',
		'attach_bearer_token' => true,
		'name'                => 'ReactWoo Geo Commerce',
	)
);
RWGC_Satellite_Updater::register(
	array(
		'basename'            => $callback,
		'version'             => '1.0.0',
		'catalog_slug'        => 'reactwoo-callback',
		'get_bearer_callback' => array( 'RWGC_Test_Bearer', 'token' ),
		'name'                => 'Callback plugin',
	)
);

$stored                  = new stdClass();
$stored->last_checked    = 10;
$stored->checked         = array( $core => '1.0.0' );
$stored->response        = array( 'keep' => 'yes' );
$incoming                = new stdClass();
$incoming->last_checked  = 99;
$incoming->checked       = array( $core => '1.0.0' );
$incoming->response      = array( 'keep' => 'yes' );
$GLOBALS['rw_site']      = $stored;
$before                  = rwgc_upd_posts();
$skipped                 = RWGC_Satellite_Updater::filter_update_transient( $incoming );
rwgc_upd_assert( 'a save that only changes last_checked makes no remote call', rwgc_upd_posts() === $before );
rwgc_upd_assert( 'that save returns the transient unchanged', $skipped === $incoming );

$same               = new stdClass();
$same->last_checked = 1;
$same->checked      = array( $core => '1.0.0' );
$same->response     = array();
$GLOBALS['rw_site'] = $same;
$same->last_checked = 2;
$before             = rwgc_upd_posts();
RWGC_Satellite_Updater::filter_update_transient( $same );
rwgc_upd_assert( 'WordPress mutating last_checked on the stored object makes no remote call', rwgc_upd_posts() === $before );
$GLOBALS['rw_site'] = false;

$expired = 'https://downloads.example/reactwoo-geocore.zip?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Expires=600&X-Amz-Signature=old';
$GLOBALS['rw_check_body'] = array(
	'update'       => true,
	'version'      => '1.2.0',
	'download_url' => $expired,
	'tested_up_to' => '6.7',
	'min_wp'       => '6.2',
);
$full = rwgc_upd_transient( $core, '1.0.0' );
$full->last_checked = 500;
$updated = RWGC_Satellite_Updater::filter_update_transient( $full );
$package = isset( $updated->response[ $core ] ) ? (string) $updated->response[ $core ]->package : '';
rwgc_upd_assert( 'the free plugin check uses an 8 second timeout', 1 === count( rwgc_upd_posts_matching( '/updates/check' ) ) && 8 === (int) rwgc_upd_posts_matching( '/updates/check' )[0]['args']['timeout'] );
rwgc_upd_assert( 'the free plugin does not log in', array() === rwgc_upd_posts_matching( '/auth/login' ) );
rwgc_upd_assert( 'the transient stores a placeholder instead of the signed URL', 0 === strpos( $package, 'reactwoo-update://' ) && false === strpos( $package, 'X-Amz-' ) );
rwgc_upd_assert( 'the placeholder names the offered and installed versions', false !== strpos( $package, 'version=1.2.0' ) && false !== strpos( $package, 'current=1.0.0' ) );
rwgc_upd_assert( 'tested and requires are still copied', isset( $updated->response[ $core ] ) && '6.7' === $updated->response[ $core ]->tested && '6.2' === $updated->response[ $core ]->requires );

$offer_ttls = array();
foreach ( $GLOBALS['rw_transients'] as $key => $row ) {
	if ( 0 === strpos( (string) $key, 'rwgc_uo_' ) ) {
		$offer_ttls[] = (int) $row['ttl'];
		$cached       = $row['value'];
		rwgc_upd_assert( 'the one-hour offer cache does not keep the signed URL', is_array( $cached ) && isset( $cached['offer']['version'] ) && ! isset( $cached['offer']['package'] ) );
	}
}
rwgc_upd_assert( 'offers are cached for one hour', array( 3600 ) === $offer_ttls );

$before = rwgc_upd_posts();
RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $core, '1.0.0' ) );
rwgc_upd_assert( 'a second save within the hour does not call the API again', rwgc_upd_posts() === $before );

$fresh = 'https://downloads.example/reactwoo-geocore.zip?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Expires=600&X-Amz-Signature=fresh';
$GLOBALS['rw_check_body']['download_url'] = $fresh;
$downloaded = RWGC_Satellite_Updater::filter_upgrader_pre_download( false, $package, null, array() );
$check_posts = rwgc_upd_posts_matching( '/updates/check' );
$last_check  = $check_posts[ count( $check_posts ) - 1 ];
rwgc_upd_assert( 'install performs one fresh check', 2 === count( $check_posts ) );
rwgc_upd_assert( 'the install-time check times out at 10 seconds', 10 === (int) $last_check['args']['timeout'] );
rwgc_upd_assert( 'the download uses the fresh signed URL', '/tmp/reactwoo-update.zip' === $downloaded && isset( $GLOBALS['rw_downloads'][0]['url'] ) && $fresh === $GLOBALS['rw_downloads'][0]['url'] && $expired !== $GLOBALS['rw_downloads'][0]['url'] );
rwgc_upd_assert( 'a foreign package is left for WordPress', false === RWGC_Satellite_Updater::filter_upgrader_pre_download( false, 'https://downloads.wordpress.org/plugin/akismet.zip', null, array() ) );

$GLOBALS['rw_transients'] = array();
$GLOBALS['rw_check_body'] = array(
	'update'  => false,
	'version' => '1.0.0',
);
$before = rwgc_upd_posts();
$none   = RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $core, '1.0.0' ) );
rwgc_upd_assert( 'no update still counts as a completed check', rwgc_upd_posts() === $before + 1 && ! isset( $none->response[ $core ] ) );
$before = rwgc_upd_posts();
RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $core, '1.0.0' ) );
rwgc_upd_assert( 'a cached no-update result is not requested again', rwgc_upd_posts() === $before );

$GLOBALS['rw_transients'] = array();
$GLOBALS['rw_transport']  = true;
$before = rwgc_upd_posts();
RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $core, '1.0.0' ) );
RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $core, '1.0.0' ) );
rwgc_upd_assert( 'a transport error is not cached for an hour', rwgc_upd_posts() === $before + 2 );
$GLOBALS['rw_transport'] = false;

$GLOBALS['rw_transients'] = array();
$GLOBALS['rw_login_mode'] = 'fail';
$before_login = count( rwgc_upd_posts_matching( '/auth/login' ) );
$paid = RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $commerce, '1.0.0' ) );
$login_posts = rwgc_upd_posts_matching( '/auth/login' );
rwgc_upd_assert( 'a failed commercial login uses a 10 second timeout', count( $login_posts ) === $before_login + 1 && 10 === (int) $login_posts[ count( $login_posts ) - 1 ]['args']['timeout'] );
rwgc_upd_assert( 'a failed login does not offer an update', ! isset( $paid->response[ $commerce ] ) );
$fail_ttls = array();
foreach ( $GLOBALS['rw_transients'] as $key => $row ) {
	if ( 0 === strpos( (string) $key, 'rwgc_ulf_' ) ) {
		$fail_ttls[] = (int) $row['ttl'];
	}
}
rwgc_upd_assert( 'a failed login is cached for 15 minutes', array( 900 ) === $fail_ttls );
$before_login = count( $login_posts );
RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $commerce, '1.0.0' ) );
rwgc_upd_assert( 'the cached login failure is not attempted again', count( rwgc_upd_posts_matching( '/auth/login' ) ) === $before_login );

$GLOBALS['rw_transients'] = array();
$GLOBALS['rw_login_mode'] = 'token';
$GLOBALS['rw_check_body'] = array(
	'update'       => true,
	'version'      => '2.0.0',
	'download_url' => $expired,
);
$called = RWGC_Satellite_Updater::filter_update_transient( rwgc_upd_transient( $callback, '1.0.0' ) );
$callback_package = isset( $called->response[ $callback ] ) ? (string) $called->response[ $callback ]->package : '';
rwgc_upd_assert( 'a bearer callback receives the 10 second login timeout', RWGC_Test_Bearer::$timeouts === array( 10 ) );
rwgc_upd_assert( 'a callback plugin also stores a placeholder', 0 === strpos( $callback_package, 'reactwoo-update://reactwoo-callback' ) && false === strpos( $callback_package, 'X-Amz-' ) );

$info = RWGC_Satellite_Updater::filter_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'reactwoo-geocore' ) );
$detail_checks = rwgc_upd_posts_matching( '/updates/check' );
rwgc_upd_assert( 'plugin details use the 8 second check timeout', is_object( $info ) && '1.2.0' !== $info->version && 8 === (int) $detail_checks[ count( $detail_checks ) - 1 ]['args']['timeout'] );

if ( 0 !== $GLOBALS['rw_failed'] ) {
	fwrite( STDERR, $GLOBALS['rw_failed'] . " failed\n" );
	exit( 1 );
}
echo "satellite updater checks passed\n";
exit( 0 );
