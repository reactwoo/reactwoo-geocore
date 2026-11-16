<?php
/**
 * Geo lookup cache names are an HMAC, and the stored payload has no IP (RG-85).
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'wp_salt' ) ) {
	/**
	 * @param string $scheme Scheme.
	 * @return string
	 */
	function wp_salt( $scheme = 'auth' ) {
		unset( $scheme );
		return 'rwgc-ab-test-salt';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		if ( isset( $GLOBALS['rwgc_test_get_option'] ) && is_callable( $GLOBALS['rwgc_test_get_option'] ) ) {
			return $GLOBALS['rwgc_test_get_option']( $key, $default );
		}
		if ( isset( $GLOBALS['rwgc_test_options'] ) && is_array( $GLOBALS['rwgc_test_options'] ) && array_key_exists( $key, $GLOBALS['rwgc_test_options'] ) ) {
			return $GLOBALS['rwgc_test_options'][ $key ];
		}
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		$GLOBALS['rwgc_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return isset( $GLOBALS['rwgc_test_transients'][ $key ] ) ? $GLOBALS['rwgc_test_transients'][ $key ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		$GLOBALS['rwgc_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $key Transient name.
	 * @return bool
	 */
	function delete_transient( $key ) {
		unset( $GLOBALS['rwgc_test_transients'][ $key ] );
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'forget_transient' ) ) {
			$GLOBALS['wpdb']->forget_transient( $key );
		}
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook ) {
		return isset( $GLOBALS['rwgc_test_cron'][ $hook ]['timestamp'] ) ? $GLOBALS['rwgc_test_cron'][ $hook ]['timestamp'] : false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * @param int    $timestamp Timestamp.
	 * @param string $hook      Hook.
	 * @param array  $args      Args.
	 * @return bool
	 */
	function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
		unset( $args );
		$GLOBALS['rwgc_test_cron'][ $hook ] = array(
			'timestamp'  => $timestamp,
			'recurrence' => false,
		);
		return true;
	}
}

/**
 * Options-table stand-in. Applies the LIKE patterns from the prepared SQL.
 */
class RWGC_Cache_Legacy_Wpdb {

	/** @var string */
	public $options = 'wp_options';

	/** @var list<object> */
	public $rows = array();

	/** @var string */
	public $last_sql = '';

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	/**
	 * @param string $query SQL with placeholders.
	 * @param mixed  ...$args Values.
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			if ( is_int( $arg ) ) {
				$query = preg_replace( '/%d/', (string) $arg, $query, 1 );
				continue;
			}
			$quoted = str_replace( "'", "''", (string) $arg );
			$query  = preg_replace( '/%s/', "'" . $quoted . "'", $query, 1 );
		}
		return $query;
	}

	/**
	 * @param string $query Prepared SQL.
	 * @return list<object>
	 */
	public function get_results( $query ) {
		$this->last_sql = (string) $query;
		if ( false === strpos( $this->last_sql, $this->options ) ) {
			return array();
		}
		if ( ! preg_match( '/option_id > (\d+)/', $this->last_sql, $after_match ) ) {
			return array();
		}
		if ( ! preg_match( '/LIMIT (\d+)/', $this->last_sql, $limit_match ) ) {
			return array();
		}
		if ( ! preg_match_all( "/LIKE '((?:\\\\'|[^'])*)'/", $this->last_sql, $like_match ) ) {
			return array();
		}
		$after    = (int) $after_match[1];
		$limit    = (int) $limit_match[1];
		$patterns = array();
		foreach ( $like_match[1] as $raw ) {
			$patterns[] = $this->like_to_regex( str_replace( "''", "'", $raw ) );
		}
		$rows = $this->rows;
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) $a->option_id <=> (int) $b->option_id;
			}
		);
		$out = array();
		foreach ( $rows as $row ) {
			if ( (int) $row->option_id <= $after ) {
				continue;
			}
			foreach ( $patterns as $pattern ) {
				if ( 1 === preg_match( $pattern, (string) $row->option_name ) ) {
					$out[] = $row;
					break;
				}
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param string $key Transient name.
	 * @return void
	 */
	public function forget_transient( $key ) {
		$value   = '_transient_' . $key;
		$timeout = '_transient_timeout_' . $key;
		$this->rows = array_values(
			array_filter(
				$this->rows,
				static function ( $row ) use ( $value, $timeout ) {
					return $row->option_name !== $value && $row->option_name !== $timeout;
				}
			)
		);
	}

	/**
	 * @return list<string>
	 */
	public function names() {
		$names = array();
		foreach ( $this->rows as $row ) {
			$names[] = (string) $row->option_name;
		}
		sort( $names );
		return $names;
	}

	/**
	 * @param string $like LIKE pattern after esc_like, with a trailing %.
	 * @return string
	 */
	private function like_to_regex( $like ) {
		$regex = '';
		$length = strlen( $like );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $like[ $i ];
			if ( '\\' === $char && $i + 1 < $length ) {
				$regex .= preg_quote( $like[ ++$i ], '/' );
				continue;
			}
			if ( '%' === $char ) {
				$regex .= '.*';
				continue;
			}
			if ( '_' === $char ) {
				$regex .= '.';
				continue;
			}
			$regex .= preg_quote( $char, '/' );
		}
		return '/^' . $regex . '$/';
	}
}

/**
 * @covers RWGC_Cache
 */
final class RWGC_CachePrivacyTest extends TestCase {

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-settings.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-cache.php';
		require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-geoip.php';
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_options']    = array(
			'rwgc_cache_version' => 1,
			'rwgc_settings'      => array(
				'cache_enabled'     => 1,
				'behind_cloudflare' => 0,
				'quic_cloud_cdn'    => 0,
				'trusted_proxies'   => '',
				'enabled'           => 1,
			),
		);
		$GLOBALS['rwgc_test_transients'] = array();
		$GLOBALS['rwgc_test_cron']       = array();
		if ( ! isset( $GLOBALS['rwgc_test_filters'] ) || ! is_array( $GLOBALS['rwgc_test_filters'] ) ) {
			$GLOBALS['rwgc_test_filters'] = array();
		}
		unset( $GLOBALS['rwgc_test_filters']['rwgc_geo_cache_site_secret'] );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['rwgc_test_options'],
			$GLOBALS['rwgc_test_transients'],
			$GLOBALS['wpdb']
		);
		if ( isset( $GLOBALS['rwgc_test_filters'] ) && is_array( $GLOBALS['rwgc_test_filters'] ) ) {
			unset( $GLOBALS['rwgc_test_filters']['rwgc_geo_cache_site_secret'] );
		}
		if ( isset( $GLOBALS['rwgc_test_cron'][ RWGC_Cache::PURGE_HOOK ] ) ) {
			unset( $GLOBALS['rwgc_test_cron'][ RWGC_Cache::PURGE_HOOK ] );
		}
		parent::tearDown();
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public function address_provider(): array {
		return array(
			'ipv4'           => array( '203.0.113.10' ),
			'ipv6'           => array( '2001:db8:85a3::8a2e:370:7334' ),
			'ipv6 uppercase' => array( '2001:DB8::1' ),
		);
	}

	/**
	 * @dataProvider address_provider
	 * @param string $ip Visitor address.
	 * @return void
	 */
	public function test_stored_value_has_no_address_and_the_key_is_not_md5( $ip ): void {
		$payload = array(
			'ip'           => $ip,
			'country_code' => 'US',
			'country_name' => 'United States',
			'note'         => 'seen ' . $ip,
			'meta'         => array(
				'copy' => strtoupper( $ip ),
			),
		);

		RWGC_Cache::set( $ip, $payload );

		$this->assertCount( 1, $GLOBALS['rwgc_test_transients'] );
		$key    = (string) array_key_first( $GLOBALS['rwgc_test_transients'] );
		$stored = $GLOBALS['rwgc_test_transients'][ $key ];
		$blob   = (string) wp_json_encode( $stored );

		$secret   = (string) wp_salt( 'auth' );
		$expected = 'rwgc_geo_h1_' . substr( hash_hmac( 'sha256', 'geo|' . $ip, '' !== $secret ? $secret : 'rwgc-ab-event' ), 0, 32 );

		$this->assertSame( $expected, $key );
		$this->assertNotSame( 'rwgc_geo_v1_' . md5( $ip ), $key );
		$this->assertNotSame( 'rwgc_geo_h1_' . md5( $ip ), $key );
		$this->assertStringNotContainsString( md5( $ip ), $key );
		$this->assertStringNotContainsString( $ip, $key );
		$this->assertLessThanOrEqual( 172, strlen( $key ) );
		$this->assertIsArray( $stored );
		$this->assertArrayNotHasKey( 'ip', $stored );
		$this->assertSame( 'US', $stored['country_code'] );
		$this->assertFalse( stripos( $blob, $ip ) );
		$this->assertStringNotContainsString( strtolower( $ip ), strtolower( $blob ) );

		$got = RWGC_Cache::get( $ip );
		$this->assertIsArray( $got );
		$this->assertSame( $ip, $got['ip'] );
		$this->assertSame( 'US', $got['country_code'] );
		$this->assertFalse( stripos( (string) wp_json_encode( $GLOBALS['rwgc_test_transients'][ $key ] ), $ip ) );
	}

	/**
	 * @return void
	 */
	public function test_key_changes_when_the_site_secret_changes(): void {
		$ip   = '203.0.113.44';
		$keys = array();
		foreach ( array( 'alpha-site-secret', 'beta-site-secret' ) as $secret ) {
			$GLOBALS['rwgc_test_filters']['rwgc_geo_cache_site_secret'] = static function () use ( $secret ) {
				return $secret;
			};
			$GLOBALS['rwgc_test_transients'] = array();
			RWGC_Cache::set(
				$ip,
				array(
					'ip'           => $ip,
					'country_code' => 'DE',
				)
			);
			$keys[] = (string) array_key_first( $GLOBALS['rwgc_test_transients'] );
			$this->assertStringNotContainsString( $ip, (string) wp_json_encode( $GLOBALS['rwgc_test_transients'] ) );
		}

		$this->assertNotSame( $keys[0], $keys[1] );
		$this->assertSame( 'rwgc_geo_h1_' . substr( hash_hmac( 'sha256', 'geo|' . $ip, 'alpha-site-secret' ), 0, 32 ), $keys[0] );
		$this->assertSame( 'rwgc_geo_h1_' . substr( hash_hmac( 'sha256', 'geo|' . $ip, 'beta-site-secret' ), 0, 32 ), $keys[1] );
		$this->assertNotSame( 'rwgc_geo_v1_' . md5( $ip ), $keys[0] );
	}

	/**
	 * The salt is the one the A/B event limiter already uses.
	 *
	 * @return void
	 */
	public function test_secret_matches_the_ab_event_rate_limiter(): void {
		$cache   = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgc-cache.php' );
		$tracker = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/ab/class-rwgc-ab-tracker.php' );
		$this->assertStringContainsString( "wp_salt( 'auth' )", $cache );
		$this->assertStringContainsString( "wp_salt( 'auth' )", $tracker );
		$this->assertStringContainsString( "'rwgc-ab-event'", $cache );
		$this->assertStringContainsString( "'rwgc-ab-event'", $tracker );
	}

	/**
	 * @return void
	 */
	public function test_clear_all_and_delete_use_the_hmac_name(): void {
		$ip = '198.51.100.8';
		RWGC_Cache::set( $ip, array( 'country_code' => 'FR', 'ip' => $ip ) );
		$first = (string) array_key_first( $GLOBALS['rwgc_test_transients'] );
		RWGC_Cache::delete( $ip );
		$this->assertArrayNotHasKey( $first, $GLOBALS['rwgc_test_transients'] );

		RWGC_Cache::set( $ip, array( 'country_code' => 'FR' ) );
		RWGC_Cache::clear_all();
		$GLOBALS['rwgc_test_transients'] = array();
		RWGC_Cache::set( $ip, array( 'country_code' => 'FR' ) );
		$second = (string) array_key_first( $GLOBALS['rwgc_test_transients'] );
		$this->assertNotSame( $first, $second );
		$this->assertStringStartsWith( 'rwgc_geo_h2_', $second );
	}

	/**
	 * A cache hit still reports the request address to rwgc_geo_data.
	 *
	 * @return void
	 */
	public function test_cache_hit_keeps_the_public_ip_field_without_storing_it(): void {
		$ip = '203.0.113.50';
		RWGC_Cache::set(
			$ip,
			array(
				'ip'           => $ip,
				'country_code' => 'SE',
				'country_name' => 'Sweden',
				'currency'     => 'SEK',
				'source'       => 'maxmind',
				'cached'       => false,
			)
		);
		$stored = (string) wp_json_encode( $GLOBALS['rwgc_test_transients'] );
		$this->assertStringNotContainsString( $ip, $stored );

		$previous = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
		$_SERVER['REMOTE_ADDR'] = $ip;
		$data = RWGC_GeoIP::resolve_visitor();
		if ( null === $previous ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $previous;
		}

		$this->assertSame( $ip, $data['ip'] );
		$this->assertSame( 'SE', $data['country_code'] );
		$this->assertTrue( $data['cached'] );
		$this->assertStringNotContainsString( $ip, (string) wp_json_encode( $GLOBALS['rwgc_test_transients'] ) );
	}

	/**
	 * @return void
	 */
	public function test_legacy_md5_rows_are_deleted_in_batches_and_hmac_rows_stay(): void {
		$ipv4 = '203.0.113.10';
		$ipv6 = '2001:db8::2';
		RWGC_Cache::set( '198.51.100.20', array( 'country_code' => 'FR', 'ip' => '198.51.100.20' ) );
		$live = (string) array_key_first( $GLOBALS['rwgc_test_transients'] );

		$wpdb       = new RWGC_Cache_Legacy_Wpdb();
		$wpdb->rows = array(
			$this->option_row( 1, '_transient_rwgc_geo_v1_' . md5( $ipv4 ) ),
			$this->option_row( 2, '_transient_timeout_rwgc_geo_v1_' . md5( $ipv4 ) ),
			$this->option_row( 3, '_transient_rwgc_geo_v4_' . md5( $ipv6 ) ),
			$this->option_row( 4, '_transient_' . $live ),
			$this->option_row( 5, '_transient_rwgc_geo_v9_short' ),
			$this->option_row( 6, '_transient_rwgc_other' ),
		);
		$GLOBALS['wpdb'] = $wpdb;

		$guard = 0;
		do {
			$done = RWGC_Cache::purge_legacy_batch( 2 );
			++$guard;
		} while ( ! $done && $guard < 8 );

		$this->assertTrue( $done );
		$this->assertLessThan( 8, $guard );
		$this->assertStringContainsString( '\_transient\_rwgc\_geo\_v', $wpdb->last_sql );
		$this->assertStringContainsString( $wpdb->options, $wpdb->last_sql );
		$names = $wpdb->names();
		$this->assertNotContains( '_transient_rwgc_geo_v1_' . md5( $ipv4 ), $names );
		$this->assertNotContains( '_transient_timeout_rwgc_geo_v1_' . md5( $ipv4 ), $names );
		$this->assertNotContains( '_transient_rwgc_geo_v4_' . md5( $ipv6 ), $names );
		$this->assertContains( '_transient_' . $live, $names );
		$this->assertContains( '_transient_rwgc_geo_v9_short', $names );
		$this->assertContains( '_transient_rwgc_other', $names );
		$this->assertStringStartsWith( 'rwgc_geo_h', $live );
	}

	/**
	 * @return void
	 */
	public function test_one_batch_marks_the_purge_finished_when_nothing_remains(): void {
		$wpdb       = new RWGC_Cache_Legacy_Wpdb();
		$wpdb->rows = array(
			$this->option_row( 1, '_transient_rwgc_geo_v1_' . md5( '203.0.113.77' ) ),
		);
		$GLOBALS['wpdb'] = $wpdb;

		RWGC_Cache::purge_one_legacy_batch();

		$this->assertSame( array(), $wpdb->names() );
		$this->assertSame( 1, (int) $GLOBALS['rwgc_test_options'][ RWGC_Cache::PURGE_OPTION ] );

		$wpdb->rows = array(
			$this->option_row( 9, '_transient_rwgc_geo_v1_' . md5( '203.0.113.78' ) ),
		);
		RWGC_Cache::purge_one_legacy_batch();
		$this->assertSame(
			array( '_transient_rwgc_geo_v1_' . md5( '203.0.113.78' ) ),
			$wpdb->names()
		);
	}

	/**
	 * @return void
	 */
	public function test_schedule_stops_after_the_purge_flag_is_set(): void {
		RWGC_Cache::schedule_legacy_purge();
		$this->assertNotFalse( wp_next_scheduled( RWGC_Cache::PURGE_HOOK ) );

		$GLOBALS['rwgc_test_options'][ RWGC_Cache::PURGE_OPTION ] = 1;
		unset( $GLOBALS['rwgc_test_cron'][ RWGC_Cache::PURGE_HOOK ] );
		RWGC_Cache::schedule_legacy_purge();
		$this->assertFalse( wp_next_scheduled( RWGC_Cache::PURGE_HOOK ) );
	}

	/**
	 * @param int    $id   Option id.
	 * @param string $name option_name.
	 * @return object
	 */
	private function option_row( $id, $name ) {
		return (object) array(
			'option_id'   => $id,
			'option_name' => $name,
		);
	}
}
