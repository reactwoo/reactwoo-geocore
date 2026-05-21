<?php
/**
 * Platform sync snapshot for the ReactWoo Geo admin app shell topbar.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives human-readable sync status from Geo Core + GeoCore Pro state.
 */
class RWGC_Platform_Sync_Status {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_app_shell_sync_label', array( __CLASS__, 'filter_topbar_sync_label' ), 10, 2 );
		add_filter( 'rwgc_app_shell_sync_hint', array( __CLASS__, 'filter_topbar_sync_hint' ), 10, 2 );
	}

	/**
	 * @return array{label:string,hint:string,variant:string,url:string}
	 */
	public static function get_snapshot() {
		$pro = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
		$ctx = function_exists( 'rwgc_get_portable_targeting_editor_context' )
			? rwgc_get_portable_targeting_editor_context()
			: array();

		$audience_count = 0;
		$campaign_count = 0;
		if ( ! empty( $ctx['audiences'] ) && is_array( $ctx['audiences'] ) ) {
			$audience_count = count( $ctx['audiences'] );
		}
		if ( ! empty( $ctx['campaigns'] ) && is_array( $ctx['campaigns'] ) ) {
			$campaign_count = count( $ctx['campaigns'] );
		}

		$integrations_url = admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=integrations' );
		if ( ! $pro ) {
			return array(
				'label'   => __( 'Core geo', 'reactwoo-geocore' ),
				'hint'    => __( 'Country and visitor targeting without cloud sync.', 'reactwoo-geocore' ),
				'variant' => 'neutral',
				'url'     => '',
			);
		}

		$license_ok = false;
		$cloud_ok   = false;
		if ( class_exists( 'RWGCP_License', false ) ) {
			$license_ok = '' !== trim( (string) RWGCP_License::get_license_key() );
			$cloud_ok   = RWGCP_License::is_cached_token_valid();
		}

		$last_sync = 0;
		$ads_count = 0;
		$ga_count  = 0;
		if ( class_exists( 'RWGCP_Google_Integration', false ) ) {
			$sync_meta = RWGCP_Google_Integration::get_sync_meta();
			$last_sync = isset( $sync_meta['last_sync_at'] ) ? (int) $sync_meta['last_sync_at'] : 0;
			$ads_count = isset( $sync_meta['ads_count'] ) ? (int) $sync_meta['ads_count'] : 0;
			$ga_count  = isset( $sync_meta['ga_count'] ) ? (int) $sync_meta['ga_count'] : 0;
		}
		if ( $audience_count <= 0 && $ga_count > 0 ) {
			$audience_count = $ga_count;
		}
		if ( $campaign_count <= 0 && $ads_count > 0 ) {
			$campaign_count = $ads_count;
		}

		if ( ! $license_ok || ! $cloud_ok ) {
			return array(
				'label'   => __( 'Connect Pro', 'reactwoo-geocore' ),
				'hint'    => __( 'GeoCore Pro license or cloud connection is required for Google sync.', 'reactwoo-geocore' ),
				'variant' => 'warning',
				'url'     => admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=setup' ),
			);
		}

		if ( $audience_count > 0 && $campaign_count > 0 ) {
			$hint = sprintf(
				/* translators: 1: audience count, 2: campaign count */
				__( '%1$d GA4 audiences and %2$d Ads campaigns available in the rule builder.', 'reactwoo-geocore' ),
				$audience_count,
				$campaign_count
			);
			if ( $last_sync > 0 ) {
				$hint .= ' ' . sprintf(
					/* translators: %s: human time diff */
					__( 'Last sync %s ago.', 'reactwoo-geocore' ),
					human_time_diff( $last_sync, time() )
				);
			}
			return array(
				'label'   => __( 'Synced', 'reactwoo-geocore' ),
				'hint'    => $hint,
				'variant' => 'success',
				'url'     => $integrations_url,
			);
		}

		if ( $audience_count > 0 || $campaign_count > 0 ) {
			return array(
				'label'   => __( 'Partial sync', 'reactwoo-geocore' ),
				'hint'    => __( 'Some Google lists are available. Run a full sync in Integrations.', 'reactwoo-geocore' ),
				'variant' => 'warning',
				'url'     => $integrations_url,
			);
		}

		return array(
			'label'   => __( 'Sync needed', 'reactwoo-geocore' ),
			'hint'    => __( 'Connect Google in GeoCore Pro and sync audiences and campaigns.', 'reactwoo-geocore' ),
			'variant' => 'warning',
			'url'     => $integrations_url,
		);
	}

	/**
	 * @param string               $label Current label.
	 * @param array<string, mixed> $ctx   Route context.
	 * @return string
	 */
	public static function filter_topbar_sync_label( $label, $ctx ) {
		unset( $ctx );
		return self::get_snapshot()['label'];
	}

	/**
	 * @param string               $hint Current hint.
	 * @param array<string, mixed> $ctx  Route context.
	 * @return string
	 */
	public static function filter_topbar_sync_hint( $hint, $ctx ) {
		unset( $ctx );
		return self::get_snapshot()['hint'];
	}

}
