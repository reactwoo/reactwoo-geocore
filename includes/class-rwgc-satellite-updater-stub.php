<?php
/**
 * No-op stand-in used only by the WordPress.org build.
 *
 * The directory zip does not include the ReactWoo update client. A Pro add-on
 * that still calls RWGC_Satellite_Updater::register() gets this class instead
 * of a fatal error. The call does nothing. That add-on must ship its own
 * updater. This file is not loaded when the real class is present.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'RWGC_Satellite_Updater', false ) ) {
	return;
}

/**
 * Accepts the same public calls as the ReactWoo updater and ignores them.
 */
class RWGC_Satellite_Updater {

	/**
	 * @param array<string, mixed> $config Ignored.
	 * @return void
	 */
	public static function register( $config ) {
		unset( $config );
	}

	/**
	 * @return void
	 */
	public static function force_check_updates() {
	}

	/**
	 * @return void
	 */
	public static function handle_admin_force_check() {
	}

	/**
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function filter_update_transient( $transient ) {
		return $transient;
	}

	/**
	 * @param mixed  $result Plugins API result.
	 * @param string $action Requested action.
	 * @param mixed  $args   Request arguments.
	 * @return mixed
	 */
	public static function filter_plugins_api( $result, $action, $args ) {
		unset( $action, $args );
		return $result;
	}
}
