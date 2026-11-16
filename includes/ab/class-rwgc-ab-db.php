<?php
/**
 * Daily aggregate table for free A/B tests.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates {prefix}rwgc_ab_daily once per schema version.
 *
 * Counts are aggregates only: test, variant, and day. No visitor rows.
 */
class RWGC_AB_DB {

	/**
	 * Schema version stored in the options table.
	 */
	const VERSION = '1';

	/**
	 * Option that records the installed schema version.
	 */
	const OPTION = 'rwgc_ab_db_version';

	/**
	 * Table name without the WordPress prefix.
	 */
	const TABLE = 'rwgc_ab_daily';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Prefixed table name. This is the only table this class creates.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		$prefix = 'wp_';
		if ( is_object( $wpdb ) && isset( $wpdb->prefix ) && is_string( $wpdb->prefix ) && '' !== $wpdb->prefix ) {
			$prefix = $wpdb->prefix;
		}
		return $prefix . self::TABLE;
	}

	/**
	 * dbDelta statement for the daily aggregate table.
	 *
	 * @return string
	 */
	public static function schema_sql() {
		global $wpdb;

		$table   = self::table_name();
		$charset = '';
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ) {
			$charset = (string) $wpdb->get_charset_collate();
		}

		$sql  = "CREATE TABLE {$table} (\n";
		$sql .= "  test_id bigint(20) unsigned NOT NULL,\n";
		$sql .= "  variant varchar(16) NOT NULL,\n";
		$sql .= "  day date NOT NULL,\n";
		$sql .= "  views int(10) unsigned NOT NULL default 0,\n";
		$sql .= "  conversions int(10) unsigned NOT NULL default 0,\n";
		$sql .= "  PRIMARY KEY  (test_id,variant,day)\n";
		$sql .= ') ' . $charset . ';';

		return $sql;
	}

	/**
	 * Create or update the table and record the schema version.
	 *
	 * Safe to call from activation. dbDelta is idempotent.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_table();
		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * Run dbDelta only when the stored schema version differs.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = get_option( self::OPTION, '' );
		if ( (string) $stored === self::VERSION ) {
			return;
		}
		self::install();
	}

	/**
	 * Add one view or one conversion to the daily aggregate.
	 *
	 * Creates the table when the schema option is missing. The row is test,
	 * variant, and day. Nothing else is written.
	 *
	 * @param int    $test_id Test post ID.
	 * @param string $variant Arm id from the signed cookie.
	 * @param string $metric  views|conversions.
	 * @return bool
	 */
	public static function increment( $test_id, $variant, $metric ) {
		global $wpdb;

		$test_id = absint( $test_id );
		$variant = sanitize_key( (string) $variant );
		if ( strlen( $variant ) > 16 ) {
			$variant = substr( $variant, 0, 16 );
		}
		if ( $test_id <= 0 || ! preg_match( '/^[a-z0-9_\-]{1,16}$/', $variant ) ) {
			return false;
		}
		$metric = ( 'conversions' === $metric ) ? 'conversions' : 'views';
		self::maybe_upgrade();
		if ( self::table_missing() ) {
			self::install();
		}
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		$table = self::table_name();
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return false;
		}

		$day         = self::today();
		$views       = ( 'views' === $metric ) ? 1 : 0;
		$conversions = ( 'conversions' === $metric ) ? 1 : 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed Core daily table, checked above.
		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} is this plugin's rwgc_ab_daily table, validated above, and cannot be a placeholder.
			"INSERT INTO `{$table}` (test_id, variant, day, views, conversions) VALUES (%d, %s, %s, %d, %d) ON DUPLICATE KEY UPDATE views = views + %d, conversions = conversions + %d",
			$test_id,
			$variant,
			$day,
			$views,
			$conversions,
			$views,
			$conversions
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Prepared above. Table name is the fixed Core daily table.
		$wpdb->query( $sql );
		return true;
	}

	/**
	 * Remove aggregate rows for one test. Other tests are left in place.
	 *
	 * @param int $test_id Test post ID.
	 * @return void
	 */
	public static function delete_for_test( $test_id ) {
		global $wpdb;

		$test_id = absint( $test_id );
		if ( $test_id <= 0 || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return;
		}
		$table = self::table_name();
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is the fixed Core daily table, checked above.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE test_id = %d", $test_id ) );
	}

	/**
	 * Site-local calendar day for the aggregate row.
	 *
	 * @return string Y-m-d.
	 */
	private static function today() {
		$raw = function_exists( 'current_time' ) ? (string) current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $raw, $match ) ) {
			return $match[1];
		}
		return gmdate( 'Y-m-d' );
	}

	/**
	 * True when this request can see that the daily table is not installed.
	 *
	 * A stub database without get_var() is treated as present so callers that
	 * already ran maybe_upgrade() do not loop. Production $wpdb can check.
	 *
	 * @return bool
	 */
	private static function table_missing() {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		$table = self::table_name();
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return false;
		}
		$like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : addcslashes( $table, '_%\\' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		return (string) $found !== $table;
	}

	/**
	 * @return void
	 */
	private static function create_table() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( self::schema_sql() );
	}
}
