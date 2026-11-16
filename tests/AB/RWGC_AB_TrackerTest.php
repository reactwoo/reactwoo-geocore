<?php
/**
 * Free A/B view and goal counts: one view, one conversion, no stored address.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Enough of WP_Error for REST parameter errors. Accepts the data argument core passes.
	 */
	class WP_Error {

		/** @var string */
		public $code;

		/** @var string */
		public $message;

		/** @var mixed */
		public $data;

		/**
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
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

		/**
		 * @return string[]
		 */
		public function get_error_messages() {
			return array( $this->message );
		}

		/**
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
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

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record a filter so the REST dispatcher can run it.
	 *
	 * @param string   $hook          Hook.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		unset( $priority, $accepted_args );
		if ( ! isset( $GLOBALS['rwgc_test_hook_callbacks'][ $hook ] ) || ! is_array( $GLOBALS['rwgc_test_hook_callbacks'][ $hook ] ) ) {
			$GLOBALS['rwgc_test_hook_callbacks'][ $hook ] = array();
		}
		$GLOBALS['rwgc_test_hook_callbacks'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook          Hook.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'rest_is_integer' ) ) {
	/**
	 * Same rule as WordPress rest_is_integer().
	 *
	 * @param mixed $maybe_integer Value.
	 * @return bool
	 */
	function rest_is_integer( $maybe_integer ) {
		return is_numeric( $maybe_integer ) && round( (float) $maybe_integer ) === (float) $maybe_integer;
	}
}

if ( ! function_exists( 'rest_get_best_type_for_value' ) ) {
	/**
	 * Same order as WordPress: the first listed type that matches wins.
	 *
	 * @param mixed    $value Value.
	 * @param string[] $types Types.
	 * @return string
	 */
	function rest_get_best_type_for_value( $value, $types ) {
		$checks = array(
			'integer' => 'rest_is_integer',
			'number'  => 'is_numeric',
			'string'  => 'is_string',
			'boolean' => 'is_bool',
			'null'    => 'is_null',
		);
		foreach ( $types as $type ) {
			if ( isset( $checks[ $type ] ) && $checks[ $type ]( $value ) ) {
				return $type;
			}
		}
		return '';
	}
}

if ( ! function_exists( 'rest_validate_value_from_schema' ) ) {
	/**
	 * The string/integer part of WordPress rest_validate_value_from_schema().
	 *
	 * A JSON number fails type string. type integer|string accepts both.
	 *
	 * @param mixed                $value Value.
	 * @param array<string, mixed> $args  Schema.
	 * @param string               $param Parameter.
	 * @return true|WP_Error
	 */
	function rest_validate_value_from_schema( $value, $args, $param = '' ) {
		$type = isset( $args['type'] ) ? $args['type'] : '';
		if ( is_array( $type ) ) {
			$best = rest_get_best_type_for_value( $value, $type );
			if ( '' === $best ) {
				return new WP_Error( 'rest_invalid_type', sprintf( '%1$s is not of type %2$s.', $param, implode( ',', $type ) ), array( 'status' => 400 ) );
			}
			$type = $best;
		}
		if ( 'string' === $type && ! is_string( $value ) ) {
			return new WP_Error( 'rest_invalid_type', sprintf( '%1$s is not of type %2$s.', $param, 'string' ), array( 'status' => 400, 'param' => $param ) );
		}
		if ( 'integer' === $type && ! rest_is_integer( $value ) ) {
			return new WP_Error( 'rest_invalid_type', sprintf( '%1$s is not of type %2$s.', $param, 'integer' ), array( 'status' => 400, 'param' => $param ) );
		}
		return true;
	}
}

if ( ! function_exists( 'rest_sanitize_value_from_schema' ) ) {
	/**
	 * @param mixed                $value Value.
	 * @param array<string, mixed> $args  Schema.
	 * @param string               $param Parameter.
	 * @return mixed
	 */
	function rest_sanitize_value_from_schema( $value, $args, $param = '' ) {
		unset( $param );
		$type = isset( $args['type'] ) ? $args['type'] : '';
		if ( is_array( $type ) ) {
			$type = rest_get_best_type_for_value( $value, $type );
		}
		if ( 'integer' === $type ) {
			return (int) $value;
		}
		if ( 'string' === $type ) {
			return (string) $value;
		}
		return $value;
	}
}

if ( ! function_exists( 'rest_validate_request_arg' ) ) {
	/**
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter.
	 * @return true|WP_Error
	 */
	function rest_validate_request_arg( $value, $request, $param ) {
		$attributes = $request->get_attributes();
		if ( ! isset( $attributes['args'][ $param ] ) || ! is_array( $attributes['args'][ $param ] ) ) {
			return true;
		}
		return rest_validate_value_from_schema( $value, $attributes['args'][ $param ], $param );
	}
}

if ( ! function_exists( 'rest_sanitize_request_arg' ) ) {
	/**
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter.
	 * @return mixed
	 */
	function rest_sanitize_request_arg( $value, $request, $param ) {
		$attributes = $request->get_attributes();
		if ( ! isset( $attributes['args'][ $param ] ) || ! is_array( $attributes['args'][ $param ] ) ) {
			return $value;
		}
		return rest_sanitize_value_from_schema( $value, $attributes['args'][ $param ], $param );
	}
}

if ( ! function_exists( 'rest_parse_request_arg' ) ) {
	/**
	 * WordPress default when an arg has a type and no sanitize_callback.
	 *
	 * This is the check that rejects a JSON number for type string.
	 *
	 * @param mixed           $value   Value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter.
	 * @return mixed|WP_Error
	 */
	function rest_parse_request_arg( $value, $request, $param ) {
		$is_valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}
		return rest_sanitize_request_arg( $value, $request, $param );
	}
}

/**
 * In-memory daily aggregate.
 */
class RWGC_AB_Tracker_Wpdb {

	/** @var string */
	public $prefix = 'wp_';

	/** @var bool */
	public $missing_table = false;

	/** @var string[] */
	public $queries = array();

	/**
	 * @var array<string, array{test_id:int,variant:string,day:string,views:int,conversions:int}>
	 */
	public $rows = array();

	/**
	 * @param string $query SQL.
	 * @param mixed  ...$args Values.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		$prepared = (string) $query;
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) || ( is_string( $arg ) && preg_match( '/^-?\d+$/', $arg ) ) ) {
				$prepared = preg_replace( '/%d/', (string) (int) $arg, $prepared, 1 );
				continue;
			}
			$safe     = str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), (string) $arg );
			$prepared = preg_replace( '/%s/', "'" . $safe . "'", $prepared, 1 );
		}
		return $prepared;
	}

	/**
	 * @param string $sql SQL.
	 * @return int
	 */
	public function query( $sql ) {
		$sql             = (string) $sql;
		$this->queries[] = $sql;
		if ( preg_match( "/INSERT INTO `[^`]+` \\(test_id, variant, day, views, conversions\\) VALUES \\((\\d+), '([^']*)', '([^']*)', (\\d+), (\\d+)\\) ON DUPLICATE KEY UPDATE views = views \\+ (\\d+), conversions = conversions \\+ (\\d+)/", $sql, $match ) ) {
			$key = $match[1] . '|' . $match[2] . '|' . $match[3];
			if ( ! isset( $this->rows[ $key ] ) ) {
				$this->rows[ $key ] = array(
					'test_id'     => (int) $match[1],
					'variant'     => $match[2],
					'day'         => $match[3],
					'views'       => (int) $match[4],
					'conversions' => (int) $match[5],
				);
			} else {
				$this->rows[ $key ]['views']       += (int) $match[6];
				$this->rows[ $key ]['conversions'] += (int) $match[7];
			}
		}
		return 1;
	}

	/**
	 * @param string $sql SQL.
	 * @return string
	 */
	public function get_var( $sql ) {
		unset( $sql );
		if ( $this->missing_table ) {
			return '';
		}
		return 'wp_rwgc_ab_daily';
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * @return string
	 */
	public function get_charset_collate() {
		return 'DEFAULT CHARSET=utf8mb4';
	}
}

if ( ! class_exists( 'WP_REST_Request', false ) ) {
	/**
	 * Request used by rest_do_request(). Parameter checks follow WP_REST_Request.
	 */
	class WP_REST_Request {

		/** @var string */
		private $method;

		/** @var string */
		private $route;

		/** @var array<string, string> */
		private $headers = array();

		/** @var string */
		private $body = '';

		/** @var array<string, array<string, mixed>> */
		private $params = array();

		/** @var array<string, mixed> */
		private $attributes = array();

		/** @var bool */
		private $json_parsed = false;

		/**
		 * @param string $method Method.
		 * @param string $route  Route.
		 */
		public function __construct( $method = 'GET', $route = '' ) {
			$this->method = strtoupper( (string) $method );
			$this->route  = (string) $route;
		}

		/**
		 * @param string $name  Header.
		 * @param string $value Value.
		 * @return void
		 */
		public function set_header( $name, $value ) {
			$this->headers[ strtolower( (string) $name ) ] = (string) $value;
		}

		/**
		 * @param string $body Raw body.
		 * @return void
		 */
		public function set_body( $body ) {
			$this->body        = (string) $body;
			$this->json_parsed = false;
			unset( $this->params['JSON'] );
		}

		/**
		 * @return string
		 */
		public function get_method() {
			return $this->method;
		}

		/**
		 * @return string
		 */
		public function get_route() {
			return $this->route;
		}

		/**
		 * @return array<string, mixed>
		 */
		public function get_attributes() {
			return $this->attributes;
		}

		/**
		 * @param array<string, mixed> $attributes Handler.
		 * @return void
		 */
		public function set_attributes( array $attributes ) {
			$this->attributes = $attributes;
		}

		/**
		 * @return array<string, mixed>
		 */
		public function get_json_params() {
			$this->parse_json_params();
			return isset( $this->params['JSON'] ) && is_array( $this->params['JSON'] ) ? $this->params['JSON'] : array();
		}

		/**
		 * @param string $key Parameter.
		 * @return mixed
		 */
		public function get_param( $key ) {
			$this->parse_json_params();
			foreach ( array( 'JSON', 'POST', 'GET' ) as $source ) {
				if ( isset( $this->params[ $source ] ) && is_array( $this->params[ $source ] ) && array_key_exists( $key, $this->params[ $source ] ) ) {
					return $this->params[ $source ][ $key ];
				}
			}
			return null;
		}

		/**
		 * @return true|WP_Error
		 */
		public function has_valid_params() {
			$this->parse_json_params();
			$args = isset( $this->attributes['args'] ) && is_array( $this->attributes['args'] ) ? $this->attributes['args'] : array();
			$missing = array();
			foreach ( $args as $key => $arg ) {
				if ( is_array( $arg ) && ! empty( $arg['required'] ) && null === $this->get_param( $key ) ) {
					$missing[] = $key;
				}
			}
			if ( $missing ) {
				return new WP_Error( 'rest_missing_callback_param', 'Missing parameter(s): ' . implode( ', ', $missing ), array( 'status' => 400 ) );
			}
			foreach ( $args as $key => $arg ) {
				if ( ! is_array( $arg ) || empty( $arg['validate_callback'] ) || ! is_callable( $arg['validate_callback'] ) ) {
					continue;
				}
				$param = $this->get_param( $key );
				if ( null === $param ) {
					continue;
				}
				$valid = call_user_func( $arg['validate_callback'], $param, $this, $key );
				if ( false === $valid || is_wp_error( $valid ) ) {
					return new WP_Error( 'rest_invalid_param', 'Invalid parameter(s): ' . $key, array( 'status' => 400 ) );
				}
			}
			return true;
		}

		/**
		 * Default a typed arg with no sanitize_callback to rest_parse_request_arg.
		 *
		 * @return true|WP_Error
		 */
		public function sanitize_params() {
			$this->parse_json_params();
			$args = isset( $this->attributes['args'] ) && is_array( $this->attributes['args'] ) ? $this->attributes['args'] : array();
			foreach ( array( 'JSON', 'POST', 'GET' ) as $source ) {
				if ( empty( $this->params[ $source ] ) || ! is_array( $this->params[ $source ] ) ) {
					continue;
				}
				foreach ( $this->params[ $source ] as $key => $value ) {
					if ( ! isset( $args[ $key ] ) || ! is_array( $args[ $key ] ) ) {
						continue;
					}
					$param_args = $args[ $key ];
					if ( ! array_key_exists( 'sanitize_callback', $param_args ) && ! empty( $param_args['type'] ) ) {
						$param_args['sanitize_callback'] = 'rest_parse_request_arg';
					}
					if ( empty( $param_args['sanitize_callback'] ) || ! is_callable( $param_args['sanitize_callback'] ) ) {
						continue;
					}
					$sanitized = call_user_func( $param_args['sanitize_callback'], $value, $this, $key );
					if ( is_wp_error( $sanitized ) ) {
						return new WP_Error( 'rest_invalid_param', 'Invalid parameter(s): ' . $key, array( 'status' => 400 ) );
					}
					$this->params[ $source ][ $key ] = $sanitized;
				}
			}
			return true;
		}

		/**
		 * @return void
		 */
		private function parse_json_params() {
			if ( $this->json_parsed ) {
				return;
			}
			$this->json_parsed = true;
			$type = isset( $this->headers['content-type'] ) ? $this->headers['content-type'] : '';
			if ( false === stripos( $type, 'application/json' ) || '' === $this->body ) {
				return;
			}
			$decoded = json_decode( $this->body, true );
			if ( is_array( $decoded ) ) {
				$this->params['JSON'] = $decoded;
			}
		}
	}
}

if ( ! function_exists( 'rest_do_request' ) ) {
	/**
	 * Dispatch one route the way WP_REST_Server::dispatch() does.
	 *
	 * Parameter errors are returned before the callback. rest_request_after_callbacks
	 * still runs, which is where /ab/event marks the response no-store.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>|WP_REST_Response
	 */
	function rest_do_request( $request ) {
		$route   = rtrim( (string) $request->get_route(), '/' );
		$handler = null;
		$routes  = isset( $GLOBALS['rwgc_test_routes'] ) && is_array( $GLOBALS['rwgc_test_routes'] ) ? $GLOBALS['rwgc_test_routes'] : array();
		foreach ( $routes as $candidate ) {
			if ( ! is_array( $candidate ) || ! isset( $candidate['namespace'], $candidate['route'], $candidate['args'] ) ) {
				continue;
			}
			$full = '/' . trim( (string) $candidate['namespace'], '/' ) . '/' . ltrim( (string) $candidate['route'], '/' );
			$full = rtrim( $full, '/' );
			if ( $full !== $route || ! is_array( $candidate['args'] ) ) {
				continue;
			}
			$methods = isset( $candidate['args']['methods'] ) ? $candidate['args']['methods'] : 'GET';
			$matches = false;
			if ( is_string( $methods ) ) {
				$matches = strtoupper( $methods ) === $request->get_method();
			} elseif ( is_array( $methods ) ) {
				$matches = ! empty( $methods[ $request->get_method() ] ) || in_array( $request->get_method(), $methods, true );
			}
			if ( $matches ) {
				$handler = $candidate['args'];
				break;
			}
		}
		if ( null === $handler ) {
			return array(
				'status'  => 404,
				'headers' => array(),
				'body'    => array(
					'code'    => 'rest_no_route',
					'message' => 'No route was found matching the URL and request method.',
				),
			);
		}

		$request->set_attributes( $handler );
		$response = null;
		$valid    = $request->has_valid_params();
		if ( is_wp_error( $valid ) ) {
			$response = $valid;
		} else {
			$sanitized = $request->sanitize_params();
			if ( is_wp_error( $sanitized ) ) {
				$response = $sanitized;
			}
		}

		$response = rwgc_test_apply_hook( 'rest_request_before_callbacks', $response, $handler, $request );
		if ( ! is_wp_error( $response ) && ! empty( $handler['permission_callback'] ) && is_callable( $handler['permission_callback'] ) ) {
			$permission = call_user_func( $handler['permission_callback'], $request );
			if ( is_wp_error( $permission ) || false === $permission || null === $permission ) {
				$response = is_wp_error( $permission ) ? $permission : new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
			}
		}
		if ( ! is_wp_error( $response ) && ! empty( $handler['callback'] ) && is_callable( $handler['callback'] ) ) {
			$response = call_user_func( $handler['callback'], $request );
		}
		$response = rwgc_test_apply_hook( 'rest_request_after_callbacks', $response, $handler, $request );
		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 400,
				'headers' => array(),
				'body'    => array(
					'code'    => (string) $response->get_error_code(),
					'message' => (string) $response->get_error_message(),
				),
			);
		}
		return $response;
	}
}

if ( ! function_exists( 'rwgc_test_apply_hook' ) ) {
	/**
	 * @param string $hook  Hook.
	 * @param mixed  $value First argument.
	 * @param mixed  ...$args Further arguments.
	 * @return mixed
	 */
	function rwgc_test_apply_hook( $hook, $value, ...$args ) {
		$callbacks = isset( $GLOBALS['rwgc_test_hook_callbacks'][ $hook ] ) && is_array( $GLOBALS['rwgc_test_hook_callbacks'][ $hook ] )
			? $GLOBALS['rwgc_test_hook_callbacks'][ $hook ]
			: array();
		foreach ( $callbacks as $callback ) {
			if ( is_callable( $callback ) ) {
				$value = call_user_func( $callback, $value, ...$args );
			}
		}
		return $value;
	}
}

/**
 * @covers RWGC_AB_Tracker
 * @covers RWGC_AB_DB
 */
final class RWGC_AB_TrackerTest extends TestCase {

	/** @var array<string, mixed> */
	private $server = array();

	/** @var array<string, mixed> */
	private $get = array();

	/** @var array<string, mixed> */
	private $cookie = array();

	/** @var bool */
	private $had_wpdb = false;

	/** @var mixed */
	private $previous_wpdb;

	/** @var array<string, mixed>|null */
	private $previous_options;

	/** @var mixed */
	private $previous_posts;

	/** @var mixed */
	private $previous_meta;

	/** @var mixed */
	private $previous_seq;

	/** @var mixed */
	private $previous_transients;

	/** @var mixed */
	private $previous_dbdelta;

	/** @var bool */
	private $had_filters = false;

	/** @var array<string, mixed>|null */
	private $filters;

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'RWGC_PATH' ) ) {
			define( 'RWGC_PATH', dirname( __DIR__, 2 ) . '/' );
		}
		if ( ! defined( 'RWGC_URL' ) ) {
			define( 'RWGC_URL', 'https://example.test/wp-content/plugins/reactwoo-geocore/' );
		}
		if ( ! defined( 'RWGC_VERSION' ) ) {
			define( 'RWGC_VERSION', '1.9.26' );
		}
		require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
		require_once dirname( __DIR__, 2 ) . '/includes/events/class-rwgc-event.php';
		require_once dirname( __DIR__, 2 ) . '/includes/events/class-rwgc-events.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-db.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tests.php';
		require_once dirname( __DIR__, 2 ) . '/includes/decision/class-rwgc-decision-experiment-assigner.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-assignment.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tracker.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-rest.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-shortcodes.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->server              = $_SERVER;
		$this->get                 = $_GET;
		$this->cookie              = $_COOKIE;
		$this->had_wpdb            = array_key_exists( 'wpdb', $GLOBALS );
		$this->previous_wpdb       = $this->had_wpdb ? $GLOBALS['wpdb'] : null;
		$this->previous_options    = isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] ) ? $GLOBALS['rwgc_test_options'] : null;
		$this->previous_posts      = isset( $GLOBALS['rwgc_ab_test_posts'] ) ? $GLOBALS['rwgc_ab_test_posts'] : null;
		$this->previous_meta       = isset( $GLOBALS['rwgc_ab_test_meta'] ) ? $GLOBALS['rwgc_ab_test_meta'] : null;
		$this->previous_seq        = isset( $GLOBALS['rwgc_ab_test_seq'] ) ? $GLOBALS['rwgc_ab_test_seq'] : null;
		$this->previous_transients = isset( $GLOBALS['rwgc_test_transients'] ) ? $GLOBALS['rwgc_test_transients'] : null;
		$this->previous_dbdelta    = isset( $GLOBALS['rwgc_ab_dbdelta_calls'] ) ? $GLOBALS['rwgc_ab_dbdelta_calls'] : null;
		$this->had_filters         = isset( $GLOBALS['rwgc_test_filters'] );
		$this->filters             = $this->had_filters && is_array( $GLOBALS['rwgc_test_filters'] ) ? $GLOBALS['rwgc_test_filters'] : null;

		$_SERVER['REQUEST_METHOD']  = 'GET';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Chrome/120.0.0.0';
		$_SERVER['REMOTE_ADDR']     = '203.0.113.77';
		$_SERVER['HTTP_HOST']       = 'example.test';
		$_SERVER['REQUEST_URI']     = '/';
		$_GET                       = array( 'user_id' => '918273' );
		unset( $_COOKIE[ RWGC_AB_Assignment::COOKIE ] );

		$GLOBALS['rwgc_test_options']      = array( RWGC_AB_DB::OPTION => RWGC_AB_DB::VERSION );
		$GLOBALS['rwgc_ab_test_posts']     = array();
		$GLOBALS['rwgc_ab_test_meta']      = array();
		$GLOBALS['rwgc_ab_test_seq']       = 0;
		$GLOBALS['rwgc_test_transients']   = array();
		$GLOBALS['rwgc_ab_dbdelta_calls']  = array();
		$GLOBALS['rwgc_ab_events']         = array();
		$GLOBALS['rwgc_test_is_admin']     = false;
		$GLOBALS['rwgc_test_scripts']      = array();
		$GLOBALS['rwgc_test_localized']    = array();
		$GLOBALS['wpdb']                   = new RWGC_AB_Tracker_Wpdb();
		$GLOBALS['rwgc_test_filters']      = array(
			'rwgc_ab_emit_http_headers' => static function () {
				return false;
			},
			'rwgc_ab_event_client_ip'   => static function () {
				return '203.0.113.77';
			},
			'rwgc_geo_event'            => static function ( $payload ) {
				$GLOBALS['rwgc_ab_events'][] = $payload;
				return $payload;
			},
		);
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER = $this->server;
		$_GET    = $this->get;
		$_COOKIE = $this->cookie;
		if ( $this->had_wpdb ) {
			$GLOBALS['wpdb'] = $this->previous_wpdb;
		} else {
			unset( $GLOBALS['wpdb'] );
		}
		if ( null === $this->previous_options ) {
			unset( $GLOBALS['rwgc_test_options'] );
		} else {
			$GLOBALS['rwgc_test_options'] = $this->previous_options;
		}
		if ( null === $this->previous_posts ) {
			unset( $GLOBALS['rwgc_ab_test_posts'] );
		} else {
			$GLOBALS['rwgc_ab_test_posts'] = $this->previous_posts;
		}
		if ( null === $this->previous_meta ) {
			unset( $GLOBALS['rwgc_ab_test_meta'] );
		} else {
			$GLOBALS['rwgc_ab_test_meta'] = $this->previous_meta;
		}
		if ( null === $this->previous_seq ) {
			unset( $GLOBALS['rwgc_ab_test_seq'] );
		} else {
			$GLOBALS['rwgc_ab_test_seq'] = $this->previous_seq;
		}
		if ( null === $this->previous_transients ) {
			unset( $GLOBALS['rwgc_test_transients'] );
		} else {
			$GLOBALS['rwgc_test_transients'] = $this->previous_transients;
		}
		if ( null === $this->previous_dbdelta ) {
			unset( $GLOBALS['rwgc_ab_dbdelta_calls'] );
		} else {
			$GLOBALS['rwgc_ab_dbdelta_calls'] = $this->previous_dbdelta;
		}
		if ( $this->had_filters ) {
			$GLOBALS['rwgc_test_filters'] = $this->filters;
		} else {
			unset( $GLOBALS['rwgc_test_filters'] );
		}
		$GLOBALS['rwgc_test_is_admin'] = false;
		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Cache::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
		parent::tearDown();
	}

	public function test_upsert_creates_the_daily_table_and_adds_one_view_per_arm(): void {
		unset( $GLOBALS['rwgc_test_options'][ RWGC_AB_DB::OPTION ] );
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'b' );

		$first = $this->event( $id, 'view' );
		$this->assertTrue( $first['body']['counted'] );
		$this->assertSame( '1', (string) get_option( RWGC_AB_DB::OPTION ) );
		$this->assertNotEmpty( $GLOBALS['rwgc_ab_dbdelta_calls'] );
		$sql = (string) $GLOBALS['rwgc_ab_dbdelta_calls'][0];
		$this->assertStringContainsString( 'CREATE TABLE wp_rwgc_ab_daily', $sql );
		$this->assertStringContainsString( 'views int(10) unsigned NOT NULL default 0', $sql );
		$this->assertStringContainsString( 'conversions int(10) unsigned NOT NULL default 0', $sql );
		$this->assertStringNotContainsString( '203.0.113.77', $sql );
		$this->assertSame( 1, $this->totals( $id, 'b' )['views'] );
		$this->assertSame( 0, $this->totals( $id, 'b' )['conversions'] );

		$this->assign( $id, 'b', true );
		$again = $this->event( $id, 'view' );
		$this->assertFalse( $again['body']['counted'] );
		$this->assertSame( 1, $this->totals( $id, 'b' )['views'] );

		$this->assign_visitor( $id, 'a', 'abcdefabcdefabcdefabcdefabcdefab' );
		$this->event( $id, 'view' );
		$this->assertSame( 1, $this->totals( $id, 'a' )['views'] );
		$this->assertSame( 1, $this->totals( $id, 'b' )['views'] );

		$GLOBALS['wpdb']->missing_table = true;
		$before                          = count( $GLOBALS['rwgc_ab_dbdelta_calls'] );
		$this->assign_visitor( $id, 'b', 'fedcbafedcbafedcbafedcbafedcbafa' );
		$this->event( $id, 'view' );
		$this->assertGreaterThan( $before, count( $GLOBALS['rwgc_ab_dbdelta_calls'] ) );
		$this->assertSame( RWGC_AB_DB::VERSION, (string) get_option( RWGC_AB_DB::OPTION ) );
	}

	public function test_reloads_do_not_double_count_and_a_conversion_needs_a_view(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'a' );

		$early = $this->event( $id, 'conversion' );
		$this->assertSame( 200, $early['status'] );
		$this->assertFalse( $early['body']['counted'] );
		$this->assertSame( 0, $this->totals( $id, 'a' )['views'] );
		$this->assertSame( 0, $this->totals( $id, 'a' )['conversions'] );
		$this->assertFalse( $this->flags( $id )['converted'] );

		$view = $this->event( $id, 'view' );
		$this->assertTrue( $view['body']['counted'] );
		$this->assertTrue( $this->flags( $id )['viewed'] );
		$this->assertSame( 'ab_exposure', $GLOBALS['rwgc_ab_events'][0]['event_type'] );
		$this->assertSame( 'a', $GLOBALS['rwgc_ab_events'][0]['variant_id'] );
		$this->assertSame( (string) $id, $GLOBALS['rwgc_ab_events'][0]['experiment_id'] );
		$this->assertSame( '', $GLOBALS['rwgc_ab_events'][0]['assignment_id'] );

		RWGC_AB_Assignment::reset_request_state();
		$reload = $this->event( $id, 'view' );
		$this->assertFalse( $reload['body']['counted'] );
		$this->assertSame( 1, $this->totals( $id, 'a' )['views'] );

		$conversion = $this->event( $id, 'conversion' );
		$this->assertTrue( $conversion['body']['counted'] );
		$this->assertTrue( $this->flags( $id )['converted'] );
		$this->assertSame( 'ab_conversion', $GLOBALS['rwgc_ab_events'][1]['event_type'] );
		$this->assertSame( 'beacon', $GLOBALS['rwgc_ab_events'][1]['subject']['source'] );

		RWGC_AB_Assignment::reset_request_state();
		$second = $this->event( $id, 'conversion' );
		$this->assertFalse( $second['body']['counted'] );
		$this->assertSame( 1, $this->totals( $id, 'a' )['conversions'] );
		$this->assertCount( 2, $GLOBALS['rwgc_ab_events'] );
	}

	public function test_url_goal_counts_once_on_template_redirect_after_a_view(): void {
		$id = $this->test_id(
			array(
				'type' => 'url',
				'url'  => 'https://example.test/thanks/',
			)
		);
		$this->assign( $id, 'b', true );
		$_SERVER['REQUEST_URI'] = '/thanks?ref=1';
		$_SERVER['HTTP_HOST']   = 'example.test';

		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );
		$this->assertTrue( $this->flags( $id )['converted'] );
		$this->assertTrue( RWGC_AB_Tracker::cache_bypass_marked() );
		$this->assertSame( 'private, no-store', RWGC_AB_Tracker::nocache_headers()['Cache-Control'] );
		$this->assertSame( 'ab_conversion', $GLOBALS['rwgc_ab_events'][0]['event_type'] );
		$this->assertSame( 'url', $GLOBALS['rwgc_ab_events'][0]['subject']['source'] );

		RWGC_AB_Assignment::reset_request_state();
		RWGC_AB_Tracker::reset_request_state();
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );
		$this->assertFalse( RWGC_AB_Tracker::cache_bypass_marked() );

		$unseen = $this->test_id(
			array(
				'type' => 'url',
				'url'  => 'https://example.test/thanks',
			)
		);
		$this->assign( $unseen, 'a' );
		$_SERVER['REQUEST_URI'] = '/thanks';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertArrayNotHasKey( $unseen . '|a|2026-11-09', $GLOBALS['wpdb']->rows );

		$this->assign( $id, 'b', true );
		$_SERVER['REQUEST_URI'] = '/other';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );

		$this->assign( $id, 'b', true );
		$_SERVER['REQUEST_URI'] = '/thanks';
		$_SERVER['HTTP_HOST']   = 'evil.test';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );

		$this->assign( $id, 'b', true );
		$_SERVER['HTTP_HOST']       = 'example.test';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Googlebot/2.1';
		RWGC_AB_Tracker::maybe_count_url_goal();
		$this->assertSame( 1, $this->totals( $id, 'b' )['conversions'] );
	}

	public function test_event_route_is_public_no_store_and_rate_limited_without_storing_the_address(): void {
		$GLOBALS['rwgc_test_routes'] = array();
		RWGC_REST::register_ab_routes();
		$event = null;
		foreach ( $GLOBALS['rwgc_test_routes'] as $route ) {
			if ( '/ab/event' === $route['route'] ) {
				$event = $route;
			}
		}
		$this->assertNotNull( $event );
		$this->assertSame( 'POST', $event['args']['methods'] );
		$this->assertTrue( call_user_func( $event['args']['permission_callback'], null ) );

		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'b' );
		$via = RWGC_REST::post_ab_event(
			new RWGC_AB_Cache_Request(
				array(
					'test' => (string) $id,
					'type' => 'view',
				)
			)
		);
		$this->assertSame( 200, $via['status'] );
		$this->assertTrue( $via['body']['counted'] );
		$this->assertStringContainsString( 'no-store', $via['headers']['Cache-Control'] );

		$bad_type = $this->event( $id, 'purchase' );
		$this->assertSame( 400, $bad_type['status'] );
		$this->assertSame( 'rwgc_ab_invalid_type', $bad_type['body']['code'] );
		$this->assertStringContainsString( 'no-store', $bad_type['headers']['Cache-Control'] );

		$bad_id = $this->event( 'abc', 'view' );
		$this->assertSame( 400, $bad_id['status'] );
		$this->assertSame( 'rwgc_ab_invalid_test', $bad_id['body']['code'] );

		$missing = $this->event( 99999, 'view' );
		$this->assertSame( 404, $missing['status'] );
		$this->assertSame( 'rwgc_ab_not_running', $missing['body']['code'] );

		$draft = $this->test_id( array( 'type' => 'click' ), 'draft' );
		$this->assign( $draft, 'a' );
		$idle = $this->event( $draft, 'view' );
		$this->assertSame( 404, $idle['status'] );

		$ip = '203.0.113.77';
		$GLOBALS['rwgc_test_transients'] = array();
		for ( $i = 0; $i < 40; $i++ ) {
			$step = RWGC_AB_Tracker::consume_rate_limit( $ip );
			$this->assertTrue( $step['allowed'], 'Request ' . ( $i + 1 ) . ' should be allowed' );
		}
		$blocked = RWGC_AB_Tracker::build_event_response( $id, 'view' );
		$this->assertSame( 429, $blocked['status'] );
		$this->assertSame( 'rwgc_ab_rate_limited', $blocked['body']['code'] );
		$this->assertArrayHasKey( 'Retry-After', $blocked['headers'] );
		$this->assertStringContainsString( 'no-store', $blocked['headers']['Cache-Control'] );

		$key = RWGC_AB_Tracker::rate_transient_key( $ip );
		$this->assertArrayHasKey( $key, $GLOBALS['rwgc_test_transients'] );
		$this->assertStringNotContainsString( $ip, $key );
		$this->assertSame( array( 'start', 'count' ), array_keys( $GLOBALS['rwgc_test_transients'][ $key ] ) );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_event_rate_now'] = static function () {
			return time() + 120;
		};
		$this->assertTrue( RWGC_AB_Tracker::consume_rate_limit( $ip )['allowed'] );

		$_SERVER['REQUEST_URI'] = '/wp-json/reactwoo-geocore/v1/ab/event';
		$error                  = new WP_Error( 'rest_cookie_invalid_nonce', 'Cookie check failed' );
		$this->assertNull( RWGC_AB_Tracker::allow_public_event_without_nonce( $error ) );
		$this->assertSame( 'other', RWGC_AB_Tracker::allow_public_event_without_nonce( 'other' ) );
		$_SERVER['REQUEST_URI'] = '/';
		$this->assertSame( $error, RWGC_AB_Tracker::allow_public_event_without_nonce( $error ) );

		$root = dirname( __DIR__, 2 );
		$rest = (string) file_get_contents( $root . '/includes/class-rwgc-rest.php' );
		$src  = (string) file_get_contents( $root . '/includes/ab/class-rwgc-ab-tracker.php' );
		$this->assertStringNotContainsString( 'wp_verify_nonce', $rest );
		$this->assertStringNotContainsString( 'wp_verify_nonce', $src );
		$this->assertStringNotContainsString( 'get_current_user_id', $src );
		$this->assertStringContainsString( 'RWGC_AB_Tracker::init()', (string) file_get_contents( $root . '/includes/class-rwgc-plugin.php' ) );
		$this->assertStringContainsString( 'rwgc_ab_daily', (string) file_get_contents( $root . '/uninstall.php' ) );
	}

	public function test_rest_server_accepts_the_json_number_the_script_sends_and_a_numeric_string(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->boot_event_route();
		$args = $this->event_route_args();
		$this->assertContains( 'integer', (array) $args['test']['type'] );
		$this->assertContains( 'string', (array) $args['test']['type'] );

		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/rwgc-ab.js' );
		$this->assertStringContainsString( 'test: Number(testId)', $js );
		$this->assertStringContainsString( 'type: String(type)', $js );

		$this->assign_visitor( $id, 'b', '0123456789abcdef0123456789abcdef' );
		$number = $this->dispatch_event_body(
			wp_json_encode(
				array(
					'test' => $id,
					'type' => 'view',
				)
			)
		);
		$this->assertSame( 200, $number['status'] );
		$this->assertTrue( $number['body']['counted'] );
		$this->assertIsInt( $number['body']['test'] );
		$this->assertStringContainsString( 'no-store', $number['headers']['Cache-Control'] );
		$this->assertSame( 1, $this->totals( $id, 'b' )['views'] );

		$this->assign_visitor( $id, 'b', 'abcdefabcdefabcdefabcdefabcdefab' );
		$string = $this->dispatch_event_body( '{"test":"' . $id . '","type":"view"}' );
		$this->assertSame( 200, $string['status'] );
		$this->assertTrue( $string['body']['counted'] );
		$this->assertIsInt( $string['body']['test'] );
		$this->assertSame( $id, $string['body']['test'] );
		$this->assertSame( 2, $this->totals( $id, 'b' )['views'] );
	}

	public function test_core_validation_errors_on_the_event_route_are_no_store(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->boot_event_route();
		$this->assign( $id, 'a' );
		$rejected = $this->dispatch_event_body(
			wp_json_encode(
				array(
					'test' => $id,
					'type' => 1,
				)
			)
		);
		$this->assertSame( 400, $rejected['status'] );
		$this->assertSame( 'rest_invalid_param', $rejected['body']['code'] );
		$this->assertStringContainsString( 'no-store', $rejected['headers']['Cache-Control'] );
		$this->assertSame( 0, $this->totals( $id, 'a' )['views'] );
		$this->assertSame( 0, $this->totals( $id, 'a' )['conversions'] );

		$bad_test = $this->dispatch_event_body( '{"test":"nope","type":"view"}' );
		$this->assertSame( 400, $bad_test['status'] );
		$this->assertSame( 'rest_invalid_param', $bad_test['body']['code'] );
		$this->assertStringContainsString( 'no-store', $bad_test['headers']['Cache-Control'] );
		$this->assertSame( 0, $this->totals( $id, 'a' )['views'] );
	}

	public function test_nothing_persists_the_address_or_an_account_id(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$visitor = '0123456789abcdef0123456789abcdef';
		$this->assign_visitor( $id, 'b', $visitor );
		$response = RWGC_AB_Tracker::build_event_response( $id, 'view' );
		$this->assertTrue( $response['body']['counted'] );

		$stored = array(
			'queries'  => $GLOBALS['wpdb']->queries,
			'rows'     => $GLOBALS['wpdb']->rows,
			'options'  => $GLOBALS['rwgc_test_options'],
			'events'   => $GLOBALS['rwgc_ab_events'],
			'response' => $response,
		);
		foreach ( $GLOBALS['rwgc_test_transients'] as $key => $bucket ) {
			$this->assertStringNotContainsString( '203.0.113.77', (string) $key );
			$this->assertStringNotContainsString( $visitor, (string) $key );
			$this->assertIsArray( $bucket );
			$this->assertSame( array( 'start', 'count' ), array_keys( $bucket ) );
			$stored['buckets'][] = array(
				'count' => $bucket['count'],
			);
		}
		$blob = strtolower( (string) wp_json_encode( $stored ) );
		$this->assertStringNotContainsString( '203.0.113.77', $blob );
		$this->assertStringNotContainsString( $visitor, $blob );
		$this->assertStringNotContainsString( '918273', $blob );
		foreach ( $GLOBALS['wpdb']->rows as $row ) {
			$this->assertSame( array( 'test_id', 'variant', 'day', 'views', 'conversions' ), array_keys( $row ) );
		}
	}

	public function test_server_and_fragment_wrappers_expose_the_click_goal_and_the_beacon_script(): void {
		$id = $this->test_id(
			array(
				'type'     => 'click',
				'selector' => '.buy',
			)
		);
		$this->assign( $id, 'b', true );
		unset( $GLOBALS['rwgc_test_scripts']['rwgc-ab'], $GLOBALS['rwgc_test_localized']['rwgc-ab'] );

		$html = rwgc_ab_render_test(
			$id,
			array(
				'a' => '<p>ARM-A</p><a class="buy" href="/buy">Buy</a>',
				'b' => '<p>ARM-B</p><a class="buy" href="/buy">Buy</a>',
			)
		);
		$this->assertStringContainsString( 'data-rwgc-ab-mode="server"', $html );
		$this->assertStringContainsString( 'data-rwgc-ab-goal=".buy"', $html );
		$this->assertStringContainsString( 'ARM-B', $html );
		$this->assertSame( 'private, no-store', RWGC_AB_Cache::last_directives()['cache_control'] );
		$this->assertFalse( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertArrayHasKey( 'rwgc-ab', $GLOBALS['rwgc_test_scripts'] );
		$this->assertSame( 'https://example.test/wp-json/reactwoo-geocore/v1/ab/event', $GLOBALS['rwgc_test_localized']['rwgc-ab']['data']['eventEndpoint'] );

		$GLOBALS['rwgc_test_filters']['rwgc_ab_cache_mode'] = static function () {
			return 'fragment';
		};
		unset( $GLOBALS['rwgc_test_scripts']['rwgc-ab'], $GLOBALS['rwgc_test_localized']['rwgc-ab'] );
		RWGC_AB_Cache::reset_request_state();
		$fragment = rwgc_ab_render_test(
			$id,
			array(
				'a' => '<p>ARM-A</p>',
				'b' => '<p>ARM-B</p>',
			)
		);
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $fragment );
		$this->assertStringContainsString( 'data-rwgc-ab-goal=".buy"', $fragment );
		$this->assertStringContainsString( 'ARM-A', $fragment );
		$this->assertStringNotContainsString( 'ARM-B', $fragment );
		$this->assertTrue( RWGC_AB_Cache::fragment_script_queued() );
		$this->assertSame( '', RWGC_AB_Cache::last_directives()['cache_control'] );

		$shortcode = RWGC_Shortcodes::render_ab(
			array( 'id' => (string) $id ),
			'[rwgc_ab_variant]<a class="buy" href="/buy">A</a>[/rwgc_ab_variant][rwgc_ab_variant]<a class="buy" href="/buy">B</a>[/rwgc_ab_variant]'
		);
		$this->assertStringContainsString( 'data-rwgc-ab-goal=".buy"', $shortcode );
		$this->assertStringContainsString( 'data-rwgc-ab-mode="fragment"', $shortcode );

		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/rwgc-ab.js' );
		$this->assertStringContainsString( 'IntersectionObserver', $js );
		$this->assertStringContainsString( 'eventEndpoint', $js );
		$this->assertStringContainsString( "postEvent(testId, 'view')", $js );
		$this->assertStringContainsString( "postEvent(testId, 'conversion')", $js );
		$this->assertStringContainsString( 'data-rwgc-ab-goal', $js );
		$this->assertStringNotContainsString( 'X-WP-Nonce', $js );
	}

	/**
	 * @param array<string, string> $goal   Goal config.
	 * @param string                $status Post status.
	 * @return int
	 */
	/**
	 * @return void
	 */
	private function boot_event_route() {
		$GLOBALS['rwgc_test_hook_callbacks'] = array();
		$GLOBALS['rwgc_test_routes']         = array();
		RWGC_REST::init();
		RWGC_REST::register_ab_routes();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function event_route_args() {
		foreach ( $GLOBALS['rwgc_test_routes'] as $route ) {
			if ( is_array( $route ) && isset( $route['route'] ) && '/ab/event' === $route['route'] ) {
				return isset( $route['args']['args'] ) && is_array( $route['args']['args'] ) ? $route['args']['args'] : array();
			}
		}
		return array();
	}

	/**
	 * @param string $json JSON body, the bytes the browser posts.
	 * @return array<string, mixed>
	 */
	private function dispatch_event_body( $json ) {
		RWGC_AB_Assignment::reset_request_state();
		$request = new WP_REST_Request( 'POST', '/reactwoo-geocore/v1/ab/event' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( $json );
		$result = rest_do_request( $request );
		if ( is_array( $result ) ) {
			return $result;
		}
		$body = method_exists( $result, 'get_data' ) ? $result->get_data() : array();
		$headers = array();
		if ( method_exists( $result, 'get_headers' ) ) {
			$headers = $result->get_headers();
		}
		$cache = '';
		if ( isset( $headers['Cache-Control'] ) ) {
			$cache = is_array( $headers['Cache-Control'] ) ? implode( ', ', $headers['Cache-Control'] ) : (string) $headers['Cache-Control'];
		}
		return array(
			'status'  => method_exists( $result, 'get_status' ) ? (int) $result->get_status() : 0,
			'headers' => array(
				'Cache-Control' => $cache,
			),
			'body'    => is_array( $body ) ? $body : array(),
		);
	}

	/**
	 * @param array<string, string> $goal   Goal config.
	 * @param string                $status Post status.
	 * @return int
	 */
	private function test_id( array $goal, $status = 'publish' ) {
		return (int) RWGC_AB_Tests::create(
			array(
				'title'  => 'Counted',
				'status' => $status,
				'config' => array(
					'adapter' => 'shortcode',
					'goal'    => $goal,
				),
			)
		);
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @param bool   $viewed  .v flag.
	 * @return void
	 */
	private function assign( $test_id, $variant, $viewed = false ) {
		$this->assign_visitor( $test_id, $variant, '0123456789abcdef0123456789abcdef', $viewed );
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @param string $visitor 32 hex characters.
	 * @param bool   $viewed  .v flag.
	 * @return void
	 */
	private function assign_visitor( $test_id, $variant, $visitor, $viewed = false ) {
		$_COOKIE[ RWGC_AB_Assignment::COOKIE ] = RWGC_AB_Assignment::encode_cookie(
			$visitor,
			array(
				(int) $test_id => array(
					'variant'   => $variant,
					'viewed'    => $viewed,
					'converted' => false,
				),
			)
		);
		RWGC_AB_Assignment::reset_request_state();
	}

	/**
	 * @param mixed  $test_id Test id.
	 * @param string $type    Event type.
	 * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
	 */
	private function event( $test_id, $type ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_ACCEPT']    = 'application/json';
		$_SERVER['CONTENT_TYPE']   = 'application/json';
		RWGC_AB_Assignment::reset_request_state();
		return RWGC_AB_Tracker::build_event_response( $test_id, $type, '203.0.113.77' );
	}

	/**
	 * @param int    $test_id Test id.
	 * @param string $variant Arm.
	 * @return array{views:int,conversions:int}
	 */
	private function totals( $test_id, $variant ) {
		$key = (int) $test_id . '|' . $variant . '|2026-11-09';
		if ( ! isset( $GLOBALS['wpdb']->rows[ $key ] ) ) {
			return array(
				'views'       => 0,
				'conversions' => 0,
			);
		}
		return $GLOBALS['wpdb']->rows[ $key ];
	}

	/**
	 * @param int $test_id Test id.
	 * @return array{viewed:bool,converted:bool}
	 */
	private function flags( $test_id ) {
		RWGC_AB_Assignment::reset_request_state();
		$row = RWGC_AB_Assignment::verified_assignment( $test_id );
		return array(
			'viewed'    => is_array( $row ) && ! empty( $row['viewed'] ),
			'converted' => is_array( $row ) && ! empty( $row['converted'] ),
		);
	}
}
