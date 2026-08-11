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
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( self::notice_message( $notice ) ); ?></p></div>
			<?php endif; ?>

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
			<?php endif; ?>
		</div>
		<?php
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
			'paired'       => __( 'Site paired with ReactWoo Cloud.', 'reactwoo-geocore' ),
			'pair_failed'  => __( 'Pairing failed. Check the token and try again.', 'reactwoo-geocore' ),
			'disconnected' => __( 'Disconnected. Cached manifests were kept; site content was not removed.', 'reactwoo-geocore' ),
			'synced'       => __( 'Cloud sync finished.', 'reactwoo-geocore' ),
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : $key;
	}
}
