<?php
/**
 * Structured Cloud site health (WP17). Admin/cron only — never on visitor render.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Same status vocabulary as Decision Cloud: healthy | warning | disconnected | configuration_error.
 */
final class RWGC_Cloud_Health {

	const SCHEMA = 'reactwoo.site_health/v1';

	const STATUS_HEALTHY              = 'healthy';
	const STATUS_WARNING              = 'warning';
	const STATUS_DISCONNECTED         = 'disconnected';
	const STATUS_CONFIGURATION_ERROR  = 'configuration_error';

	const HEARTBEAT_STALE_SECONDS = 7200;
	const QUEUE_BACKLOG           = 100;

	/**
	 * Human labels for admin/portal.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			self::STATUS_HEALTHY             => __( 'Healthy', 'reactwoo-geocore' ),
			self::STATUS_WARNING             => __( 'Warning', 'reactwoo-geocore' ),
			self::STATUS_DISCONNECTED        => __( 'Disconnected', 'reactwoo-geocore' ),
			self::STATUS_CONFIGURATION_ERROR => __( 'Configuration Error', 'reactwoo-geocore' ),
		);
	}

	/**
	 * @param string $status Status.
	 * @return string
	 */
	public static function label( $status ) {
		$map = self::labels();
		return isset( $map[ $status ] ) ? $map[ $status ] : (string) $status;
	}

	/**
	 * Gather local facts and evaluate. No HTTP.
	 *
	 * @return array<string, mixed>
	 */
	public static function snapshot() {
		return self::evaluate( self::facts() );
	}

	/**
	 * @param array<string, mixed> $facts Facts.
	 * @return array<string, mixed>
	 */
	public static function evaluate( array $facts ) {
		$issues = array();
		$connected = ! empty( $facts['connected'] );
		$state     = isset( $facts['connection_state'] ) ? (string) $facts['connection_state'] : 'disconnected';

		if ( ! $connected ) {
			if ( 'pairing' === $state ) {
				$issues[] = self::issue(
					'pairing_unconfirmed',
					'warning',
					'Pairing started but this site is not fully connected yet.',
					'Paste a fresh pairing token on Geo Core → Cloud and click Connect.'
				);
			} else {
				$issues[] = self::issue(
					'not_connected',
					'disconnected',
					'This WordPress site is not paired with ReactWoo Cloud.',
					'Open Geo Core → Cloud, paste a pairing token, and click Connect.'
				);
			}
		} elseif ( empty( $facts['has_credentials'] ) ) {
			$issues[] = self::issue(
				'missing_credentials',
				'configuration_error',
				'Cloud credentials are missing, so the site cannot sync.',
				'Disconnect and pair the site again from Geo Core → Cloud.'
			);
		}

		$error = isset( $facts['last_error'] ) ? (string) $facts['last_error'] : '';
		if ( $error ) {
			$issues[] = self::issue_from_error( $error );
		}

		$queue_error = isset( $facts['queue_last_error'] ) ? (string) $facts['queue_last_error'] : '';
		if ( $queue_error && $queue_error !== $error ) {
			$issues[] = self::issue(
				'queue_flush_failed',
				'warning',
				'Analytics events could not be uploaded to Cloud.',
				'Click Sync now on Geo Core → Cloud. Visitor pages are unaffected.'
			);
		}

		$pending = isset( $facts['queue_pending'] ) ? (int) $facts['queue_pending'] : 0;
		if ( $pending >= self::QUEUE_BACKLOG ) {
			$issues[] = self::issue(
				'queue_backlog',
				'warning',
				sprintf( 'About %d analytics events are waiting to upload.', $pending ),
				'Click Sync now on Geo Core → Cloud, or wait for the hourly Cloud maintenance cron.'
			);
		}

		$dropped = isset( $facts['queue_dropped'] ) ? (int) $facts['queue_dropped'] : 0;
		if ( $dropped > 0 ) {
			$issues[] = self::issue(
				'queue_dropped',
				'warning',
				'Older queued analytics events were dropped because the local queue was full.',
				'Click Sync now more often, or keep Cloud connected so the hourly cron can flush events.'
			);
		}

		if ( $connected && self::is_stale( isset( $facts['last_heartbeat_at'] ) ? (string) $facts['last_heartbeat_at'] : '', isset( $facts['now'] ) ? (int) $facts['now'] : time() ) ) {
			$issues[] = self::issue(
				'heartbeat_stale',
				'warning',
				'This site has not checked in with Cloud recently.',
				'Open Geo Core → Cloud and click Sync now. Visitor pages still use the last known-good experiences.'
			);
		}

		if ( $connected && 'cloud' === ( isset( $facts['management_mode'] ) ? $facts['management_mode'] : 'local' ) && (int) ( $facts['manifest_revision'] ?? 0 ) < 1 ) {
			$issues[] = self::issue(
				'cloud_managed_without_manifest',
				'configuration_error',
				'This site is Cloud-managed but has no compiled experience manifest yet.',
				'Publish an experience in ReactWoo Cloud, then click Sync now in WordPress.'
			);
		}

		$issues = self::unique_issues( $issues );
		$status = self::rollup( $connected, $issues );

		$snapshot = array(
			'schema'       => self::SCHEMA,
			'status'       => $status,
			'status_label' => self::label( $status ),
			'checked_at'   => gmdate( 'c', isset( $facts['now'] ) ? (int) $facts['now'] : time() ),
			'issues'       => $issues,
			'environment'  => isset( $facts['environment'] ) && is_array( $facts['environment'] ) ? $facts['environment'] : array(),
			'connection'   => array(
				'state'             => $state,
				'site_id'           => isset( $facts['site_id'] ) ? (string) $facts['site_id'] : '',
				'management_mode'   => isset( $facts['management_mode'] ) ? (string) $facts['management_mode'] : 'local',
				'last_heartbeat_at' => isset( $facts['last_heartbeat_at'] ) ? (string) $facts['last_heartbeat_at'] : '',
				'last_sync_at'      => isset( $facts['last_sync_at'] ) ? (string) $facts['last_sync_at'] : '',
				'last_error'        => $error,
				'manifest_revision' => isset( $facts['manifest_revision'] ) ? (int) $facts['manifest_revision'] : 0,
			),
			'queue'        => array(
				'pending'       => $pending,
				'dropped'       => $dropped,
				'last_flush_at' => isset( $facts['queue_last_flush_at'] ) ? (string) $facts['queue_last_flush_at'] : '',
				'last_error'    => $queue_error,
			),
			'capabilities' => array(
				'count' => isset( $facts['capability_count'] ) ? (int) $facts['capability_count'] : 0,
			),
		);

		/**
		 * @param array<string, mixed> $snapshot Snapshot.
		 * @param array<string, mixed> $facts Facts.
		 */
		$filtered = apply_filters( 'rwgc_cloud_health_snapshot', $snapshot, $facts );
		return is_array( $filtered ) ? $filtered : $snapshot;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function facts() {
		$conn  = class_exists( 'RWGC_Cloud_Connection', false ) ? RWGC_Cloud_Connection::get() : array();
		$creds = class_exists( 'RWGC_Cloud_Credentials', false ) ? RWGC_Cloud_Credentials::get() : null;
		$queue = array(
			'pending'       => 0,
			'dropped'       => 0,
			'last_flush_at' => '',
			'last_error'    => '',
		);
		if ( class_exists( 'RWGC_Cloud_Event_Queue', false ) ) {
			$queue = RWGC_Cloud_Event_Queue::health_snapshot();
		}

		$capability_count = 0;
		if ( class_exists( 'RWGC_Platform_Capability_Registry', false ) ) {
			if ( method_exists( 'RWGC_Platform_Capability_Registry', 'all' ) ) {
				$all = RWGC_Platform_Capability_Registry::all();
				$capability_count = is_array( $all ) ? count( $all ) : 0;
			}
		}

		$facts = array(
			'connected'           => class_exists( 'RWGC_Cloud_Connection', false ) && RWGC_Cloud_Connection::is_connected(),
			'has_credentials'     => is_array( $creds ) && ! empty( $creds['site_secret'] ),
			'connection_state'    => isset( $conn['state'] ) ? (string) $conn['state'] : 'disconnected',
			'site_id'             => isset( $conn['site_id'] ) ? (string) $conn['site_id'] : '',
			'management_mode'     => isset( $conn['management_mode'] ) ? (string) $conn['management_mode'] : 'local',
			'last_heartbeat_at'   => isset( $conn['last_heartbeat_at'] ) ? (string) $conn['last_heartbeat_at'] : '',
			'last_sync_at'        => isset( $conn['last_sync_at'] ) ? (string) $conn['last_sync_at'] : '',
			'last_error'          => isset( $conn['last_error'] ) ? (string) $conn['last_error'] : '',
			'manifest_revision'   => isset( $conn['manifest_revision'] ) ? (int) $conn['manifest_revision'] : 0,
			'queue_pending'       => (int) $queue['pending'],
			'queue_dropped'       => (int) $queue['dropped'],
			'queue_last_flush_at' => (string) $queue['last_flush_at'],
			'queue_last_error'    => (string) $queue['last_error'],
			'capability_count'    => $capability_count,
			'environment'         => self::environment(),
			'now'                 => time(),
		);

		/**
		 * @param array<string, mixed> $facts Facts.
		 */
		$filtered = apply_filters( 'rwgc_cloud_health_facts', $facts );
		return is_array( $filtered ) ? $filtered : $facts;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function environment() {
		$plugins = class_exists( 'RWGC_Cloud_Pairing', false ) ? RWGC_Cloud_Pairing::plugin_report() : array();
		$extensions = array();
		$map = array(
			'reactwoo-geocore-pro'  => 'RWGCP_VERSION',
			'reactwoo-geo-optimise' => 'RWGO_VERSION',
			'reactwoo-geo-commerce' => 'RWGCM_VERSION',
			'reactwoo-atomic'       => 'RWA_VERSION',
			'reactwoo-atomic-pro'   => 'RWAP_VERSION',
		);
		foreach ( $map as $slug => $const ) {
			if ( defined( $const ) ) {
				$extensions[] = array(
					'slug'    => $slug,
					'version' => (string) constant( $const ),
					'active'  => true,
				);
			}
		}

		return array(
			'wordpress'  => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
			'php'        => PHP_VERSION,
			'geocore'    => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
			'woocommerce'=> defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
			'elementor'  => defined( 'ELEMENTOR_VERSION' ) ? (string) ELEMENTOR_VERSION : '',
			'plugins'    => $plugins,
			'extensions' => $extensions,
		);
	}

	/**
	 * @param string $code Code.
	 * @param string $severity Severity.
	 * @param string $message Message.
	 * @param string $remediation Remediation.
	 * @return array<string, string>
	 */
	public static function issue( $code, $severity, $message, $remediation ) {
		return array(
			'code'        => (string) $code,
			'severity'    => (string) $severity,
			'message'     => (string) $message,
			'remediation' => (string) $remediation,
		);
	}

	/**
	 * @param string $error Opaque code.
	 * @return array<string, string>
	 */
	public static function issue_from_error( $error ) {
		$map = array(
			'missing_credentials'     => array(
				'configuration_error',
				'Cloud credentials are missing, so the site cannot sync.',
				'Disconnect and pair the site again from Geo Core → Cloud.',
			),
			'invalid_pair_response'   => array(
				'configuration_error',
				'Pairing returned an incomplete response from Cloud.',
				'Generate a new pairing token in ReactWoo Cloud → Sites and connect again.',
			),
			'credential_store_failed' => array(
				'configuration_error',
				'WordPress could not store the Cloud site secret.',
				'Check that the database can save options, then pair the site again.',
			),
			'insecure_api_base'       => array(
				'configuration_error',
				'The Cloud API address is not HTTPS.',
				'Use https://cloud.reactwoo.com/api/v1, or allow insecure HTTP only on local development.',
			),
			'sync_failed'             => array(
				'warning',
				'Cloud could not refresh the experience manifest.',
				'Click Sync now. Visitor pages still use the last known-good copy.',
			),
			'heartbeat_failed'        => array(
				'warning',
				'This site could not check in with Cloud.',
				'Click Sync now. Experiences already on the site keep working.',
			),
			'http_503'                => array(
				'warning',
				'Cloud was temporarily unavailable.',
				'Try Sync now in a few minutes. Visitor pages are not blocked.',
			),
			'http_401'                => array(
				'configuration_error',
				'Cloud rejected this site’s credentials.',
				'Disconnect and pair the site again from Geo Core → Cloud.',
			),
			'wrong_site'              => array(
				'configuration_error',
				'The downloaded manifest belongs to a different site.',
				'Disconnect and pair this WordPress site again so the site ID matches.',
			),
		);
		if ( isset( $map[ $error ] ) ) {
			return self::issue( $error, $map[ $error ][0], $map[ $error ][1], $map[ $error ][2] );
		}
		return self::issue(
			$error ? $error : 'unknown_error',
			'warning',
			'Cloud reported a problem during the last sync.',
			'Click Sync now on Geo Core → Cloud. If it keeps failing, disconnect and pair again.'
		);
	}

	/**
	 * @param bool                          $connected Connected.
	 * @param array<int, array<string, string>> $issues Issues.
	 * @return string
	 */
	public static function rollup( $connected, array $issues ) {
		if ( ! $connected ) {
			foreach ( $issues as $issue ) {
				if ( 'disconnected' === $issue['severity'] ) {
					return self::STATUS_DISCONNECTED;
				}
			}
			return self::STATUS_WARNING;
		}
		foreach ( $issues as $issue ) {
			if ( 'configuration_error' === $issue['severity'] || self::STATUS_CONFIGURATION_ERROR === $issue['severity'] ) {
				return self::STATUS_CONFIGURATION_ERROR;
			}
		}
		foreach ( $issues as $issue ) {
			if ( 'warning' === $issue['severity'] ) {
				return self::STATUS_WARNING;
			}
		}
		return self::STATUS_HEALTHY;
	}

	/**
	 * @param string $iso Timestamp.
	 * @param int    $now Unix now.
	 * @return bool
	 */
	public static function is_stale( $iso, $now ) {
		$iso = trim( (string) $iso );
		if ( '' === $iso ) {
			return true;
		}
		$ts = strtotime( $iso );
		if ( ! $ts ) {
			return true;
		}
		return ( $now - $ts ) > self::HEARTBEAT_STALE_SECONDS;
	}

	/**
	 * @param array<int, array<string, string>> $issues Issues.
	 * @return array<int, array<string, string>>
	 */
	private static function unique_issues( array $issues ) {
		$out  = array();
		$seen = array();
		foreach ( $issues as $issue ) {
			$code = isset( $issue['code'] ) ? (string) $issue['code'] : '';
			if ( '' === $code || isset( $seen[ $code ] ) ) {
				continue;
			}
			$seen[ $code ] = true;
			$out[]         = $issue;
		}
		return $out;
	}
}
