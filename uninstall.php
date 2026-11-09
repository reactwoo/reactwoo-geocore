<?php

/**
 * Uninstall handler for ReactWoo Geo Core.
 *
 * Removes settings and cache. Leaves downloaded DB by default.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'rwgc_settings' );
delete_option( 'rwgc_cache_version' );
delete_option( 'rwgc_maxmind_schedule_version' );

/**
 * Remove Geo Core A/B tests on this site, or on every site in a network.
 *
 * Drops only `{prefix}rwgc_ab_daily` and deletes `rwgc_ab_test` posts and their meta.
 * No other plugin table is queried or dropped.
 *
 * @return void
 */
function rwgc_uninstall_ab_tests() {
	if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) && function_exists( 'switch_to_blog' ) && function_exists( 'restore_current_blog' ) ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);
		if ( is_array( $site_ids ) && ! empty( $site_ids ) ) {
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				rwgc_uninstall_ab_tests_for_site();
				restore_current_blog();
			}
			return;
		}
	}

	rwgc_uninstall_ab_tests_for_site();
}

/**
 * Remove Geo Core A/B tests for the current site.
 *
 * @return void
 */
function rwgc_uninstall_ab_tests_for_site() {
	global $wpdb;

	require_once __DIR__ . '/includes/ab/class-rwgc-ab-db.php';
	require_once __DIR__ . '/includes/ab/class-rwgc-ab-tests.php';

	$table = RWGC_AB_DB::table_name();
	if ( preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed Core table name, checked above.
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}
	delete_option( RWGC_AB_DB::OPTION );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
			RWGC_AB_Tests::POST_TYPE
		)
	);
	if ( ! is_array( $post_ids ) ) {
		return;
	}
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
}

rwgc_uninstall_ab_tests();

