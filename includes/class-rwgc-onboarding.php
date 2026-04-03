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
		if ( 'plugins.php' !== $pagenow && 'index.php' !== $pagenow ) {
			return;
		}

		delete_option( self::OPTION_REDIRECT );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgc-getting-started&rwgc_welcome=1' ) );
		exit;
	}
}
