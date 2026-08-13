<?php
/**
 * Local Cloud event queue — persist on shutdown, flush on cron/admin only.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Never talks to Cloud during visitor HTML generation.
 */
final class RWGC_Cloud_Event_Queue {

	const OPTION     = 'rwgc_cloud_event_queue';
	const MAX_ITEMS  = 2000;
	const BATCH_SIZE = 100;

	/** @var list<array<string, mixed>> */
	private static $buffer = array();

	/** @var bool */
	private static $shutdown_registered = false;

	/**
	 * @return list<string>
	 */
	public static function allowed_types() {
		return array(
			'experience.impression',
			'variant.impression',
			'goal.click',
			'goal.lead',
			'commerce.add_to_cart',
			'commerce.purchase',
		);
	}

	/**
	 * Buffer an event for later persist. No HTTP.
	 *
	 * @param array<string, mixed> $event Event.
	 * @return bool
	 */
	public static function enqueue( array $event ) {
		$normalized = self::normalize( $event );
		if ( ! $normalized ) {
			return false;
		}
		self::$buffer[] = $normalized;
		self::ensure_shutdown();
		return true;
	}

	/**
	 * Merge the request buffer into the durable option. Safe on shutdown.
	 *
	 * @return int Number of items now queued.
	 */
	public static function persist_buffer() {
		if ( ! self::$buffer ) {
			return self::size();
		}
		$state = self::state();
		foreach ( self::$buffer as $item ) {
			$state['items'][] = $item;
		}
		self::$buffer = array();
		$state        = self::enforce_limit( $state );
		self::save( $state );
		return count( $state['items'] );
	}

	/**
	 * Upload a batch to Cloud. Cron / admin only.
	 *
	 * @return array{ok: bool, status: string, uploaded: int, remaining: int, error: string}
	 */
	public static function flush() {
		self::persist_buffer();

		if ( ! self::may_talk_to_cloud() ) {
			return array(
				'ok'        => false,
				'status'    => 'skipped',
				'uploaded'  => 0,
				'remaining' => self::size(),
				'error'     => 'not_allowed',
			);
		}

		$state = self::state();
		$now   = time();
		if ( (int) $state['backoff_until'] > $now ) {
			return array(
				'ok'        => false,
				'status'    => 'backoff',
				'uploaded'  => 0,
				'remaining' => count( $state['items'] ),
				'error'     => 'backoff',
			);
		}

		if ( ! $state['items'] ) {
			return array(
				'ok'        => true,
				'status'    => 'empty',
				'uploaded'  => 0,
				'remaining' => 0,
				'error'     => '',
			);
		}

		$creds = class_exists( 'RWGC_Cloud_Credentials', false ) ? RWGC_Cloud_Credentials::get() : null;
		if ( ! $creds ) {
			return array(
				'ok'        => false,
				'status'    => 'skipped',
				'uploaded'  => 0,
				'remaining' => count( $state['items'] ),
				'error'     => 'missing_credentials',
			);
		}

		$batch = array_slice( $state['items'], 0, self::BATCH_SIZE );
		$response = RWGC_Cloud_Http::request(
			'POST',
			'/sites/' . rawurlencode( $creds['site_id'] ) . '/events/batch',
			array( 'events' => $batch ),
			array(),
			array(
				'site_secret' => $creds['site_secret'],
				'api_base'    => $creds['api_base'],
				'timeout'     => 15,
			)
		);

		if ( ! $response['ok'] ) {
			$fails = (int) $state['fail_count'] + 1;
			$delay = min( 3600, 60 * (int) pow( 2, min( $fails, 6 ) ) );
			$state['fail_count']     = $fails;
			$state['backoff_until']  = $now + $delay;
			$state['last_error']     = $response['error'] ? $response['error'] : 'http_error';
			self::save( $state );
			return array(
				'ok'        => false,
				'status'    => 'failed',
				'uploaded'  => 0,
				'remaining' => count( $state['items'] ),
				'error'     => $state['last_error'],
			);
		}

		$state['items']          = array_slice( $state['items'], count( $batch ) );
		$state['fail_count']     = 0;
		$state['backoff_until']  = 0;
		$state['last_error']     = '';
		$state['last_flush_at']  = gmdate( 'c' );
		self::save( $state );

		return array(
			'ok'        => true,
			'status'    => 'uploaded',
			'uploaded'  => count( $batch ),
			'remaining' => count( $state['items'] ),
			'error'     => '',
		);
	}

	/**
	 * @return int
	 */
	public static function size() {
		$state = self::state();
		return count( $state['items'] ) + count( self::$buffer );
	}

	/**
	 * Test helper — clear buffer + option.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$buffer = array();
		delete_option( self::OPTION );
	}

	/**
	 * @return bool
	 */
	private static function may_talk_to_cloud() {
		$connected = class_exists( 'RWGC_Cloud_Connection', false ) && RWGC_Cloud_Connection::is_connected();
		/**
		 * Whether event flush may perform HTTP (never true on visitor render).
		 *
		 * @param bool $allowed Allowed.
		 */
		$allowed = (bool) apply_filters( 'rwgc_cloud_can_flush_events', $connected );
		if ( ! $allowed ) {
			return false;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}
		/**
		 * Tests and explicit callers may flush without cron/admin.
		 *
		 * @param bool $force Force.
		 */
		return (bool) apply_filters( 'rwgc_cloud_force_event_flush', false );
	}

	/**
	 * @return void
	 */
	private static function ensure_shutdown() {
		if ( self::$shutdown_registered ) {
			return;
		}
		self::$shutdown_registered = true;
		if ( function_exists( 'add_action' ) ) {
			add_action( 'shutdown', array( __CLASS__, 'persist_buffer' ), 20 );
		}
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @return array<string, mixed>|null
	 */
	private static function normalize( array $event ) {
		$type = isset( $event['type'] ) ? strtolower( (string) $event['type'] ) : '';
		if ( ! in_array( $type, self::allowed_types(), true ) ) {
			return null;
		}

		$visitor = '';
		if ( ! empty( $event['anonymous_visitor_id'] ) ) {
			$visitor = (string) $event['anonymous_visitor_id'];
		} elseif ( ! empty( $event['visitor_id'] ) ) {
			$visitor = (string) $event['visitor_id'];
		}
		if ( $visitor && ! preg_match( '/^[a-zA-Z0-9._:-]{1,64}$/', $visitor ) ) {
			$visitor = '';
		}

		$pick = static function ( $value ) {
			$s = is_string( $value ) || is_numeric( $value ) ? (string) $value : '';
			return preg_match( '/^[a-zA-Z0-9._:-]{1,128}$/', $s ) ? $s : '';
		};

		$value = null;
		if ( isset( $event['value'] ) && is_numeric( $event['value'] ) ) {
			$value = round( (float) $event['value'], 2 );
			if ( $value < 0 || $value > 1e9 ) {
				$value = null;
			}
		}

		$count = 1;
		if ( isset( $event['_count'] ) && is_numeric( $event['_count'] ) ) {
			$count = max( 1, min( 10000, (int) $event['_count'] ) );
		}

		$out = array(
			'type'                  => $type,
			'timestamp'             => ! empty( $event['timestamp'] ) ? (string) $event['timestamp'] : gmdate( 'c' ),
			'experience'            => $pick( isset( $event['experience'] ) ? $event['experience'] : '' ),
			'variant'               => $pick( isset( $event['variant'] ) ? $event['variant'] : '' ),
			'audience'              => $pick( isset( $event['audience'] ) ? $event['audience'] : '' ),
			'goal'                  => $pick( isset( $event['goal'] ) ? $event['goal'] : '' ),
			'anonymous_visitor_id'  => $visitor,
			'value'                 => $value,
		);
		if ( $count > 1 ) {
			$out['_count'] = $count;
		}
		return $out;
	}

	/**
	 * Drop/aggregate when over MAX_ITEMS. Prefer compacting impressions, then drop oldest.
	 *
	 * @param array<string, mixed> $state State.
	 * @return array<string, mixed>
	 */
	private static function enforce_limit( array $state ) {
		$items = isset( $state['items'] ) && is_array( $state['items'] ) ? $state['items'] : array();
		if ( count( $items ) <= self::MAX_ITEMS ) {
			$state['items'] = $items;
			return $state;
		}

		$compacted = array();
		$next      = 0;
		foreach ( $items as $item ) {
			$type = isset( $item['type'] ) ? (string) $item['type'] : '';
			if ( 'variant.impression' === $type || 'experience.impression' === $type ) {
				$key = $type . '|' . ( isset( $item['experience'] ) ? $item['experience'] : '' ) . '|' . ( isset( $item['variant'] ) ? $item['variant'] : '' ) . '|' . ( isset( $item['audience'] ) ? $item['audience'] : '' );
				if ( isset( $compacted[ $key ] ) ) {
					$compacted[ $key ]['_count'] = ( isset( $compacted[ $key ]['_count'] ) ? (int) $compacted[ $key ]['_count'] : 1 ) + ( isset( $item['_count'] ) ? (int) $item['_count'] : 1 );
					continue;
				}
				$compacted[ $key ] = $item;
				continue;
			}
			$compacted[ 'n' . $next ] = $item;
			++$next;
		}
		$items = array_values( $compacted );

		if ( count( $items ) > self::MAX_ITEMS ) {
			$dropped         = count( $items ) - self::MAX_ITEMS;
			$items           = array_slice( $items, $dropped );
			$state['dropped'] = (int) $state['dropped'] + $dropped;
		}

		$state['items'] = $items;
		return $state;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function state() {
		$defaults = array(
			'items'         => array(),
			'backoff_until' => 0,
			'fail_count'    => 0,
			'dropped'       => 0,
			'last_flush_at' => '',
			'last_error'    => '',
		);
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	private static function save( array $state ) {
		update_option( self::OPTION, $state, false );
	}
}
