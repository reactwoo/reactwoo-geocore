<?php
/**
 * Geo Suite — onboarding state, wizard persistence, activity log.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists wizard progress and lightweight “recent activity” for Suite Home.
 */
class RWGC_Onboarding {

	const OPTION_STATE     = 'rwgc_onboarding_state';
	const OPTION_ACTIVITY  = 'rwgc_suite_activity';
	const OPTION_REDIRECT  = 'rwgc_activation_redirect';
	const MAX_ACTIVITY     = 20;

	/**
	 * Default state shape.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_state() {
		return array(
			'version'           => 1,
			'wizard_step'       => 1,
			'goal'              => '',
			'wizard_completed'  => false,
			'dismissed_welcome' => false,
			'wizard_data'       => array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_state() {
		$raw = get_option( self::OPTION_STATE, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array_merge( self::default_state(), $raw );
	}

	/**
	 * @param array<string, mixed> $merge Partial state.
	 * @return void
	 */
	public static function update_state( $merge ) {
		if ( ! is_array( $merge ) ) {
			return;
		}
		$next = array_merge( self::get_state(), $merge );
		update_option( self::OPTION_STATE, $next, false );
	}

	/**
	 * @param string               $type Type slug.
	 * @param array<string, mixed> $payload Human-readable labels and URLs.
	 * @return void
	 */
	public static function log_activity( $type, $payload = array() ) {
		$type = sanitize_key( (string) $type );
		if ( '' === $type ) {
			return;
		}
		$item = array(
			'type'      => $type,
			'time'      => time(),
			'payload'   => is_array( $payload ) ? $payload : array(),
			'site_time' => current_time( 'mysql' ),
		);
		$list = get_option( self::OPTION_ACTIVITY, array() );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		array_unshift( $list, $item );
		$list = array_slice( $list, 0, self::MAX_ACTIVITY );
		update_option( self::OPTION_ACTIVITY, $list, false );

		/**
		 * Fires when a suite activity item is recorded.
		 *
		 * @param array<string, mixed> $item Full activity row.
		 */
		do_action( 'rwgc_suite_activity_logged', $item );
	}

	/**
	 * Stored activity plus optional rows from {@see 'rwgc_suite_activity_providers'}.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_activity() {
		$list = get_option( self::OPTION_ACTIVITY, array() );
		if ( ! is_array( $list ) ) {
			$list = array();
		}

		/**
		 * Callables that return extra activity rows (newest events from satellites).
		 * Each row: `type`, `time` (unix), `payload` with `title` and optional `url`, optional `site_time`.
		 *
		 * @param array<int, callable(): array<int, array<string, mixed>>> $providers Callables.
		 */
		$providers = apply_filters( 'rwgc_suite_activity_providers', array() );
		if ( is_array( $providers ) ) {
			foreach ( $providers as $provider ) {
				if ( ! is_callable( $provider ) ) {
					continue;
				}
				$extra = call_user_func( $provider );
				if ( ! is_array( $extra ) ) {
					continue;
				}
				foreach ( $extra as $row ) {
					if ( ! is_array( $row ) || empty( $row['payload']['title'] ) ) {
						continue;
					}
					if ( empty( $row['time'] ) ) {
						$row['time'] = time();
					}
					if ( empty( $row['type'] ) ) {
						$row['type'] = 'external';
					}
					$list[] = $row;
				}
			}
		}

		usort(
			$list,
			static function ( $a, $b ) {
				$ta = isset( $a['time'] ) ? (int) $a['time'] : 0;
				$tb = isset( $b['time'] ) ? (int) $b['time'] : 0;
				return $tb <=> $ta;
			}
		);
		$list = array_slice( $list, 0, self::MAX_ACTIVITY );

		/**
		 * Filter merged suite activity shown on Suite Home (newest first).
		 *
		 * @param array<int, array<string, mixed>> $list Activity rows.
		 */
		return apply_filters( 'rwgc_suite_activity', $list );
	}

	/**
	 * Flag activation redirect (set from {@see RWGC_Plugin::activate()}).
	 *
	 * @return void
	 */
	public static function flag_activation_redirect() {
		update_option( self::OPTION_REDIRECT, '1', false );
	}

	/**
	 * Clear stale activation redirect so plugins.php is never trapped.
	 *
	 * @return void
	 */
	public static function clear_stale_activation_redirect() {
		if ( ! is_admin() ) {
			return;
		}
		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( 'plugins.php' !== $pagenow ) {
			return;
		}
		$is_post_activate = isset( $_GET['activate'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| isset( $_GET['activated'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| isset( $_GET['activate-multi'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $is_post_activate ) {
			delete_option( self::OPTION_REDIRECT );
		}
	}

	/**
	 * One-time redirect after plugin activation (dashboard or plugins screen only).
	 *
	 * @return void
	 */
	public static function maybe_redirect_after_activation() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( '1' !== get_option( self::OPTION_REDIRECT, '' ) ) {
			return;
		}

		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';

		// Do not trap normal plugins.php visits (deactivate, bulk actions, etc.).
		if ( 'plugins.php' === $pagenow ) {
			$is_post_activate = isset( $_GET['activate'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				|| isset( $_GET['activated'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				|| isset( $_GET['activate-multi'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! $is_post_activate ) {
				delete_option( self::OPTION_REDIRECT );
				return;
			}
		} elseif ( 'index.php' !== $pagenow ) {
			delete_option( self::OPTION_REDIRECT );
			return;
		}

		delete_option( self::OPTION_REDIRECT );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-getting-started&rwgc_welcome=1' ) );
		exit;
	}

	/**
	 * Whether the platform setup wizard is marked complete.
	 *
	 * @return bool
	 */
	public static function is_setup_complete() {
		$state = self::get_state();
		return ! empty( $state['wizard_completed'] );
	}

	/**
	 * Platform onboarding checklist for Overview (filterable; satellites may append steps).
	 *
	 * Each step: id, label, done (bool), url (string), optional (bool), hint (string).
	 *
	 * @return array{steps: array<int, array<string, mixed>>, completed: int, total: int, percent: int}
	 */
	public static function get_setup_progress() {
		$state = self::get_state();
		$goal  = isset( $state['goal'] ) ? (string) $state['goal'] : '';

		$db_ready   = false;
		$maxmind_ok = false;
		if ( class_exists( 'RWGC_MaxMind', false ) ) {
			$status   = RWGC_MaxMind::get_status();
			$db_ready = ! empty( $status['exists'] );
		}
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$maxmind_ok = '' !== trim( (string) RWGC_Settings::get( 'maxmind_license_key', '' ) );
		}

		$geo_enabled = class_exists( 'RWGC_Settings', false )
			? (bool) RWGC_Settings::get( 'enabled', 1 )
			: true;

		$rules_count = 0;
		if ( class_exists( 'RWGC_Variant_Manager', false ) ) {
			$rows        = RWGC_Variant_Manager::get_routing_overview_rows();
			$rules_count = is_array( $rows ) ? count( $rows ) : 0;
		}

		$pro_on = function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled();
		$sync   = class_exists( 'RWGC_Platform_Sync_Status', false )
			? RWGC_Platform_Sync_Status::get_snapshot()
			: array();

		$setup_url = admin_url( 'admin.php?page=rwgc-getting-started' );
		$steps     = array(
			array(
				'id'       => 'setup_wizard',
				'label'    => __( 'Complete setup wizard', 'reactwoo-geocore' ),
				'done'     => self::is_setup_complete() || ( '' !== $goal && (int) ( $state['wizard_step'] ?? 1 ) >= 3 ),
				'url'      => $setup_url,
				'optional' => false,
				'hint'     => __( 'Goal, environment, and detection check.', 'reactwoo-geocore' ),
			),
			array(
				'id'       => 'geo_database',
				'label'    => __( 'Geo database ready', 'reactwoo-geocore' ),
				'done'     => $db_ready && $maxmind_ok,
				'url'      => admin_url( 'admin.php?page=rwgc-settings' ),
				'optional' => false,
				'hint'     => __( 'MaxMind credentials and country database.', 'reactwoo-geocore' ),
			),
			array(
				'id'       => 'detection',
				'label'    => __( 'Visitor detection enabled', 'reactwoo-geocore' ),
				'done'     => $geo_enabled && $db_ready,
				'url'      => admin_url( 'admin.php?page=rwgc-tools' ),
				'optional' => false,
				'hint'     => __( 'Verify country preview in Tools.', 'reactwoo-geocore' ),
			),
			array(
				'id'       => 'google_sync',
				'label'    => __( 'Google audiences & campaigns synced', 'reactwoo-geocore' ),
				'done'     => $pro_on && isset( $sync['variant'] ) && 'success' === $sync['variant'],
				'url'      => isset( $sync['url'] ) && is_string( $sync['url'] ) && '' !== $sync['url']
					? $sync['url']
					: admin_url( 'admin.php?page=rwgcp-geocore-pro&rwgcp_tab=integrations' ),
				'optional' => ! $pro_on,
				'hint'     => $pro_on
					? __( 'GeoCore Pro → Integrations.', 'reactwoo-geocore' )
					: __( 'Optional — requires GeoCore Pro.', 'reactwoo-geocore' ),
			),
			array(
				'id'       => 'first_experience',
				'label'    => __( 'Create first page version or rule', 'reactwoo-geocore' ),
				'done'     => $rules_count > 0,
				'url'      => admin_url( 'admin.php?page=rwgc-suite-variants' ),
				'optional' => false,
				'hint'     => __( 'Page versions under Targeting.', 'reactwoo-geocore' ),
			),
		);

		/**
		 * Filter platform onboarding steps shown on Overview and setup surfaces.
		 *
		 * @param array<int, array<string, mixed>> $steps Checklist rows.
		 * @param array<string, mixed>           $state Onboarding state option.
		 */
		$steps = apply_filters( 'rwgc_onboarding_platform_steps', $steps, $state );

		$completed = 0;
		$total     = 0;
		if ( is_array( $steps ) ) {
			foreach ( $steps as $step ) {
				if ( empty( $step['label'] ) ) {
					continue;
				}
				if ( ! empty( $step['optional'] ) ) {
					continue;
				}
				++$total;
				if ( ! empty( $step['done'] ) ) {
					++$completed;
				}
			}
		}

		$percent = $total > 0 ? (int) round( ( $completed / $total ) * 100 ) : 0;

		return array(
			'steps'     => is_array( $steps ) ? $steps : array(),
			'completed' => $completed,
			'total'     => $total,
			'percent'   => min( 100, max( 0, $percent ) ),
		);
	}
}
