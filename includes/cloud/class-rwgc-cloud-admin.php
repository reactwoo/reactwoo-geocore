<?php
/**
 * Admin UI for ReactWoo Cloud Connector.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geo Core → Cloud screen (pairing / status / sync). No visitor impact.
 */
final class RWGC_Cloud_Admin {

	const PAGE = 'rwgc-cloud';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 67 );
		add_action( 'admin_post_rwgc_cloud_pair', array( __CLASS__, 'handle_pair' ) );
		add_action( 'admin_post_rwgc_cloud_disconnect', array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_rwgc_cloud_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_post_rwgc_cloud_reconnect_sync', array( __CLASS__, 'handle_sync' ) );
		add_action( 'admin_post_rwgc_cloud_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_rwgc_cloud_switch_mode', array( __CLASS__, 'handle_switch_mode' ) );
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		$parent = class_exists( 'RWGC_Admin_Platform', false ) ? RWGC_Admin_Platform::menu_parent() : 'rwgc-dashboard';
		$cap    = class_exists( 'RWGC_Admin', false ) ? RWGC_Admin::required_capability() : 'manage_options';

		add_submenu_page(
			$parent,
			__( 'ReactWoo Cloud', 'reactwoo-geocore' ),
			__( 'Cloud', 'reactwoo-geocore' ),
			$cap,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( class_exists( 'RWGC_Admin', false ) ? RWGC_Admin::required_capability() : 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'reactwoo-geocore' ) );
		}

		$conn   = RWGC_Cloud_Connection::get();
		$creds  = RWGC_Cloud_Credentials::get();
		$notice = isset( $_GET['rwgc_cloud_notice'] ) ? sanitize_key( (string) wp_unslash( $_GET['rwgc_cloud_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReactWoo Cloud', 'reactwoo-geocore' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Connect this site to ReactWoo Cloud for authored experiences. Cloud is never contacted during visitor page rendering.', 'reactwoo-geocore' ); ?>
			</p>
			<?php if ( $notice ) : ?>
				<div class="notice <?php echo esc_attr( self::notice_class( $notice ) ); ?> is-dismissible"><p><?php echo esc_html( self::notice_message( $notice ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( RWGC_Cloud_Connection::is_connected() && 'cloud' === (string) $conn['management_mode'] ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'This site is Cloud-managed. Experiences are authored in ReactWoo Cloud. Disconnecting keeps WordPress content and the local migration backup.', 'reactwoo-geocore' ); ?></p>
				</div>
			<?php endif; ?>
			<?php self::render_health(); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Connection state', 'reactwoo-geocore' ); ?></th>
					<td><code><?php echo esc_html( (string) $conn['state'] ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Site ID', 'reactwoo-geocore' ); ?></th>
					<td><code><?php echo esc_html( $creds ? $creds['site_id'] : (string) $conn['site_id'] ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Manifest revision', 'reactwoo-geocore' ); ?></th>
					<td><?php echo esc_html( (string) (int) $conn['manifest_revision'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last sync', 'reactwoo-geocore' ); ?></th>
					<td><?php echo esc_html( (string) $conn['last_sync_at'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last heartbeat', 'reactwoo-geocore' ); ?></th>
					<td><?php echo esc_html( (string) $conn['last_heartbeat_at'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last error', 'reactwoo-geocore' ); ?></th>
					<td><?php echo esc_html( (string) $conn['last_error'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Management mode', 'reactwoo-geocore' ); ?></th>
					<td><code><?php echo esc_html( (string) $conn['management_mode'] ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'API base', 'reactwoo-geocore' ); ?></th>
					<td><code><?php echo esc_html( $creds ? $creds['api_base'] : RWGC_Cloud_Config::api_base() ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Site secret', 'reactwoo-geocore' ); ?></th>
					<td><?php echo $creds ? '<code>••••••••</code>' : '&mdash;'; ?></td>
				</tr>
			</table>

			<?php if ( ! RWGC_Cloud_Connection::is_connected() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwgc_cloud_pair" />
					<?php wp_nonce_field( 'rwgc_cloud_pair' ); ?>
					<h2><?php esc_html_e( 'Pair site', 'reactwoo-geocore' ); ?></h2>
					<p>
						<label for="rwgc_pairing_token"><?php esc_html_e( 'Pairing token', 'reactwoo-geocore' ); ?></label><br />
						<input type="text" class="regular-text" name="rwgc_pairing_token" id="rwgc_pairing_token" autocomplete="off" />
					</p>
					<?php submit_button( __( 'Connect', 'reactwoo-geocore' ) ); ?>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
					<input type="hidden" name="action" value="rwgc_cloud_sync" />
					<?php wp_nonce_field( 'rwgc_cloud_sync' ); ?>
					<?php submit_button( __( 'Sync now', 'reactwoo-geocore' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="rwgc_cloud_disconnect" />
					<?php wp_nonce_field( 'rwgc_cloud_disconnect' ); ?>
					<?php submit_button( __( 'Disconnect', 'reactwoo-geocore' ), 'delete', 'submit', false ); ?>
				</form>
				<?php self::render_migration(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	private static function render_health() {
		$health = RWGC_Cloud_Health::snapshot();
		$status = (string) $health['status'];
		$class  = 'notice-info';
		if ( RWGC_Cloud_Health::STATUS_HEALTHY === $status ) {
			$class = 'notice-success';
		} elseif ( RWGC_Cloud_Health::STATUS_WARNING === $status ) {
			$class = 'notice-warning';
		} elseif ( RWGC_Cloud_Health::STATUS_CONFIGURATION_ERROR === $status || RWGC_Cloud_Health::STATUS_DISCONNECTED === $status ) {
			$class = 'notice-error';
		}
		$env = isset( $health['environment'] ) && is_array( $health['environment'] ) ? $health['environment'] : array();
		?>
		<div class="notice <?php echo esc_attr( $class ); ?>">
			<p>
				<strong><?php esc_html_e( 'Site health', 'reactwoo-geocore' ); ?>:</strong>
				<?php echo esc_html( (string) $health['status_label'] ); ?>
			</p>
			<?php if ( ! empty( $health['issues'] ) ) : ?>
				<ul>
					<?php foreach ( $health['issues'] as $issue ) : ?>
						<li>
							<?php echo esc_html( (string) ( $issue['message'] ?? '' ) ); ?>
							<?php if ( ! empty( $issue['remediation'] ) ) : ?>
								<br /><em><?php echo esc_html( (string) $issue['remediation'] ); ?></em>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: WP version, 2: PHP version, 3: Geo Core version */
						__( 'WordPress %1$s · PHP %2$s · Geo Core %3$s', 'reactwoo-geocore' ),
						(string) ( $env['wordpress'] ?? '' ),
						(string) ( $env['php'] ?? '' ),
						(string) ( $env['geocore'] ?? '' )
					)
				);
				if ( ! empty( $env['woocommerce'] ) ) {
					echo ' · ' . esc_html( sprintf( /* translators: %s version */ __( 'WooCommerce %s', 'reactwoo-geocore' ), (string) $env['woocommerce'] ) );
				}
				if ( ! empty( $env['elementor'] ) ) {
					echo ' · ' . esc_html( sprintf( /* translators: %s version */ __( 'Elementor %s', 'reactwoo-geocore' ), (string) $env['elementor'] ) );
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Import preview and explicit switch. Pairing never imports.
	 *
	 * @return void
	 */
	private static function render_migration() {
		$preview = RWGC_Cloud_Migration::preview();
		$mode    = (string) $preview['management_mode'];
		$imported = ! empty( $preview['imported'] );
		$detected = isset( $preview['detected'] ) && is_array( $preview['detected'] ) ? $preview['detected'] : array();
		?>
		<hr />
		<h2><?php esc_html_e( 'Import existing configuration', 'reactwoo-geocore' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Connecting Cloud does not change your local rules. Preview first, then import, then switch to Cloud-managed.', 'reactwoo-geocore' ); ?>
		</p>
		<ul>
			<li><?php echo esc_html( sprintf( /* translators: %d count */ __( 'Visibility rules: %d', 'reactwoo-geocore' ), (int) ( $detected['visibility_rules'] ?? 0 ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d count */ __( 'Experience slots: %d', 'reactwoo-geocore' ), (int) ( $detected['slots'] ?? 0 ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d count */ __( 'Variants: %d', 'reactwoo-geocore' ), (int) ( $detected['variants'] ?? 0 ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d count */ __( 'Experiments: %d', 'reactwoo-geocore' ), (int) ( $detected['experiments'] ?? 0 ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %d count */ __( 'Commerce rules: %d', 'reactwoo-geocore' ), (int) ( $detected['commerce_rules'] ?? 0 ) ) ); ?></li>
		</ul>
		<?php self::render_migration_table( __( 'Can import', 'reactwoo-geocore' ), isset( $preview['supported'] ) && is_array( $preview['supported'] ) ? $preview['supported'] : array() ); ?>
		<?php self::render_migration_table( __( 'Needs review (not imported)', 'reactwoo-geocore' ), isset( $preview['unsupported'] ) && is_array( $preview['unsupported'] ) ? $preview['unsupported'] : array() ); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
			<input type="hidden" name="action" value="rwgc_cloud_import" />
			<?php wp_nonce_field( 'rwgc_cloud_import' ); ?>
			<?php submit_button( __( 'Import to ReactWoo Cloud', 'reactwoo-geocore' ), 'primary', 'submit', false ); ?>
		</form>
		<?php if ( $imported && 'cloud' !== $mode ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="rwgc_cloud_switch_mode" />
				<input type="hidden" name="rwgc_management_mode" value="cloud" />
				<?php wp_nonce_field( 'rwgc_cloud_switch_mode' ); ?>
				<?php submit_button( __( 'Switch to Cloud-managed', 'reactwoo-geocore' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php elseif ( 'cloud' === $mode ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="rwgc_cloud_switch_mode" />
				<input type="hidden" name="rwgc_management_mode" value="local" />
				<?php wp_nonce_field( 'rwgc_cloud_switch_mode' ); ?>
				<?php submit_button( __( 'Switch to local-managed', 'reactwoo-geocore' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Import must succeed before you can switch management mode.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param string                           $title Title.
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return void
	 */
	private static function render_migration_table( $title, array $rows ) {
		echo '<h3>' . esc_html( $title ) . '</h3>';
		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'None.', 'reactwoo-geocore' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Kind', 'reactwoo-geocore' ) . '</th>';
		echo '<th>' . esc_html__( 'ID', 'reactwoo-geocore' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'reactwoo-geocore' ) . '</th>';
		echo '<th>' . esc_html__( 'Reason', 'reactwoo-geocore' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $row['kind'] ?? '' ) ) . '</td>';
			echo '<td><code>' . esc_html( (string) ( $row['id'] ?? '' ) ) . '</code></td>';
			echo '<td>' . esc_html( (string) ( $row['name'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['reason'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @return void
	 */
	public static function handle_pair() {
		self::guard( 'rwgc_cloud_pair' );
		$token  = isset( $_POST['rwgc_pairing_token'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgc_pairing_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result = RWGC_Cloud_Pairing::pair( $token );
		if ( $result['ok'] ) {
			RWGC_Cloud_Sync::run_maintenance();
			RWGC_Cloud_Scheduler::ensure_schedule();
			self::redirect( 'paired' );
		}
		self::redirect( 'pair_failed' );
	}

	/**
	 * @return void
	 */
	public static function handle_disconnect() {
		self::guard( 'rwgc_cloud_disconnect' );
		RWGC_Cloud_Connection::disconnect();
		RWGC_Cloud_Scheduler::clear_schedule();
		self::redirect( 'disconnected' );
	}

	/**
	 * @return void
	 */
	public static function handle_sync() {
		self::guard( 'rwgc_cloud_sync' );
		RWGC_Cloud_Sync::run_maintenance();
		self::redirect( 'synced' );
	}

	/**
	 * @return void
	 */
	public static function handle_import() {
		self::guard( 'rwgc_cloud_import' );
		$result = RWGC_Cloud_Migration::import();
		self::redirect( $result['ok'] ? 'imported' : 'import_failed' );
	}

	/**
	 * @return void
	 */
	public static function handle_switch_mode() {
		self::guard( 'rwgc_cloud_switch_mode' );
		$mode   = isset( $_POST['rwgc_management_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgc_management_mode'] ) ) : 'local'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result = RWGC_Cloud_Migration::switch_mode( $mode );
		if ( ! $result['ok'] ) {
			self::redirect( 'import_required' === $result['error'] ? 'import_required' : 'switch_failed' );
		}
		self::redirect( 'cloud' === $result['management_mode'] ? 'switched_cloud' : 'switched_local' );
	}

	/**
	 * @param string $nonce_action Action.
	 * @return void
	 */
	private static function guard( $nonce_action ) {
		if ( ! current_user_can( class_exists( 'RWGC_Admin', false ) ? RWGC_Admin::required_capability() : 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'reactwoo-geocore' ) );
		}
		check_admin_referer( $nonce_action );
	}

	/**
	 * @param string $notice Notice key.
	 * @return void
	 */
	private static function redirect( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::PAGE,
					'rwgc_cloud_notice'=> $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @param string $key Key.
	 * @return string
	 */
	private static function notice_message( $key ) {
		$map = array(
			'paired'         => __( 'Site paired with ReactWoo Cloud. Management mode is still local until you import and switch.', 'reactwoo-geocore' ),
			'pair_failed'    => __( 'Pairing failed. Check the token and try again.', 'reactwoo-geocore' ),
			'disconnected'   => __( 'Disconnected. Cached manifests, WordPress content, and the migration backup were kept.', 'reactwoo-geocore' ),
			'synced'         => __( 'Cloud sync finished.', 'reactwoo-geocore' ),
			'imported'       => __( 'Local configuration was imported to ReactWoo Cloud. Review it, then switch to Cloud-managed.', 'reactwoo-geocore' ),
			'import_failed'  => __( 'Import failed. Local configuration was not changed.', 'reactwoo-geocore' ),
			'switched_cloud' => __( 'This site is now Cloud-managed.', 'reactwoo-geocore' ),
			'switched_local' => __( 'This site is now local-managed.', 'reactwoo-geocore' ),
			'switch_failed'  => __( 'Could not change management mode.', 'reactwoo-geocore' ),
			'import_required'=> __( 'Import to ReactWoo Cloud before switching to Cloud-managed.', 'reactwoo-geocore' ),
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : $key;
	}

	/**
	 * @param string $key Notice key.
	 * @return string
	 */
	private static function notice_class( $key ) {
		$errors = array( 'pair_failed', 'import_failed', 'switch_failed', 'import_required' );
		return in_array( $key, $errors, true ) ? 'notice-error' : 'notice-info';
	}
}
