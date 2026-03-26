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

		if ( self::has_legacy_geoelementor_settings() ) {
			$changed = self::migrate_geoelementor_settings() || $changed;
		}

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
	 * Detect legacy GeoElementor settings.
	 *
	 * @return bool
	 */
	public static function has_legacy_geoelementor_settings() {
		return '' !== get_option( 'egp_maxmind_license_key', '' );
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
	 * Migrate MaxMind license from GeoElementor.
	 *
	 * @return bool True if changed.
	 */
	public static function migrate_geoelementor_settings() {
		$license = get_option( 'egp_maxmind_license_key', '' );
		if ( '' === $license ) {
			return false;
		}
		$settings                      = RWGC_Settings::get_settings();
		$settings['maxmind_license_key'] = $settings['maxmind_license_key'] ? $settings['maxmind_license_key'] : $license;
		RWGC_Settings::update( $settings );
		return true;
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

