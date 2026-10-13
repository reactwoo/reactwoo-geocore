<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration helper from legacy geo settings to Geo Core.
 */
class RWGC_Migration {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_run_migration' ) );
	}

	/**
	 * Run one-time migration if needed.
	 *
	 * @return void
	 */
	public static function maybe_run_migration() {
		$settings = RWGC_Settings::get_settings();
		if ( ! empty( $settings['migration_completed'] ) ) {
			return;
		}

		$changed = false;

		if ( self::has_legacy_whmcs_settings() ) {
			$changed = self::migrate_whmcs_settings() || $changed;
		}

		if ( $changed ) {
			$settings                      = RWGC_Settings::get_settings();
			$settings['migration_completed'] = 1;
			RWGC_Settings::update( $settings );
		}
	}

	/**
	 * Detect legacy WHMCS geo settings (placeholder for future).
	 *
	 * @return bool
	 */
	public static function has_legacy_whmcs_settings() {
		// Currently no shared geo infrastructure in WHMCS Bridge to migrate.
		return false;
	}

	/**
	 * Placeholder for WHMCS migration (currently no shared geo to move).
	 *
	 * @return bool
	 */
	public static function migrate_whmcs_settings() {
		return false;
	}
}

