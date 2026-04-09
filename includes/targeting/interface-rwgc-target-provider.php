<?php
/**
 * Target provider contract for Geo Core (suite-wide targeting vocabulary).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each provider registers target types and contributes resolved values to {@see RWGC_Context_Snapshot}.
 */
interface RWGC_Target_Provider_Interface {

	/**
	 * Stable slug for diagnostics and definitions (e.g. geo, language, analytics).
	 *
	 * @return string
	 */
	public function get_provider_key();

	/**
	 * Register target type definitions on the registry.
	 *
	 * @param RWGC_Target_Registry $registry Registry.
	 * @return void
	 */
	public function register_targets( RWGC_Target_Registry $registry );

	/**
	 * Whether this provider can run on the current site (deps, feature flags).
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Partial context values keyed by target key (e.g. country => DE).
	 *
	 * @param array<string, mixed> $base Values merged so far (read-only use).
	 * @return array<string, mixed>
	 */
	public function resolve_context_values( array $base = array() );

	/**
	 * Admin-facing status for Providers / Integrations screen.
	 *
	 * @return array{label: string, state: string, detail?: string}
	 */
	public function get_admin_status();
}
