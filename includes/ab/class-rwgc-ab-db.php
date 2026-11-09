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
	 * @return void
	 */
	private static function create_table() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( self::schema_sql() );
	}
}
