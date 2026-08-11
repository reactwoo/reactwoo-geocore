<?php
/**
 * Atomic current + previous known-good manifest cache.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validation failure retains previous. Cloud outage does not clear cache.
 */
final class RWGC_Cloud_Manifest_Store {

	const OPTION_CURRENT  = 'rwgc_cloud_manifest_current';
	const OPTION_PREVIOUS = 'rwgc_cloud_manifest_previous';

	/**
	 * @return array<string, mixed>|null Raw manifest array.
	 */
	public static function current_raw() {
		$raw = get_option( self::OPTION_CURRENT, null );
		return is_array( $raw ) ? $raw : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function previous_raw() {
		$raw = get_option( self::OPTION_PREVIOUS, null );
		return is_array( $raw ) ? $raw : null;
	}

	/**
	 * @return RWGC_Contract_Manifest|null
	 */
	public static function current() {
		$raw = self::current_raw();
		if ( ! $raw ) {
			return null;
		}
		try {
			return RWGC_Contract_Manifest::from_array( $raw );
		} catch ( RWGC_Contract_Exception $e ) {
			return null;
		}
	}

	/**
	 * Atomically install a validated manifest for the expected site.
	 *
	 * @param array<string, mixed> $data Manifest data.
	 * @param string               $expected_site_id Site ID.
	 * @return array{ok: bool, reason: string, revision: int}
	 */
	public static function install( array $data, $expected_site_id ) {
		$expected_site_id = (string) $expected_site_id;
		try {
			$manifest = RWGC_Contract_Manifest::from_array( $data );
		} catch ( RWGC_Contract_Exception $e ) {
			return array(
				'ok'       => false,
				'reason'   => 'invalid_manifest',
				'revision' => 0,
			);
		}

		if ( $manifest->site() !== $expected_site_id ) {
			return array(
				'ok'       => false,
				'reason'   => 'wrong_site',
				'revision' => $manifest->revision(),
			);
		}

		$current = self::current_raw();
		if ( is_array( $current ) ) {
			update_option( self::OPTION_PREVIOUS, $current, false );
		}

		$stored = $manifest->to_array();
		update_option( self::OPTION_CURRENT, $stored, false );

		/**
		 * Fires after a Cloud manifest is installed locally.
		 *
		 * @param RWGC_Contract_Manifest $manifest Manifest.
		 */
		do_action( 'reactwoo_cloud_manifest_installed', $manifest );

		// Hydrate variant runtime catalog for Decision / slot rendering.
		if ( class_exists( 'RWGC_Variant_Store', false ) ) {
			foreach ( $manifest->variants() as $variant ) {
				RWGC_Variant_Store::put_runtime( $variant->to_array() );
			}
		}

		return array(
			'ok'       => true,
			'reason'   => 'installed',
			'revision' => $manifest->revision(),
		);
	}

	/**
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_CURRENT );
		delete_option( self::OPTION_PREVIOUS );
	}
}
