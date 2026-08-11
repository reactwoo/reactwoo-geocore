<?php
/**
 * Admin: ReactWoo → System → Capabilities inspection.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Developer-facing capability list (platform registry).
 */
final class RWGC_Platform_Capabilities_Admin {

	const PAGE = 'rwgc-system-capabilities';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 65 );
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		$parent = class_exists( 'RWGC_Admin_Platform', false ) ? RWGC_Admin_Platform::menu_parent() : 'rwgc-dashboard';
		$cap    = class_exists( 'RWGC_Admin', false ) ? RWGC_Admin::required_capability() : 'manage_options';

		add_submenu_page(
			$parent,
			__( 'Platform capabilities', 'reactwoo-geocore' ),
			__( 'Capabilities', 'reactwoo-geocore' ),
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

		$rows       = RWGC_Platform_Capability_Registry::export_for_report();
		$collisions = RWGC_Platform_Capability_Registry::collisions();
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( (string) $a['id'], (string) $b['id'] );
			}
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReactWoo platform capabilities', 'reactwoo-geocore' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Decision-engine capability registry (dotted IDs). This is separate from the suite product capability cards used for upgrades.', 'reactwoo-geocore' ); ?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %d: schema version */
					esc_html__( 'Schema version: %d', 'reactwoo-geocore' ),
					(int) RWGC_Schema::VERSION
				);
				?>
				&nbsp;|&nbsp;
				<?php
				printf(
					/* translators: %d: count */
					esc_html__( 'Registered: %d', 'reactwoo-geocore' ),
					count( $rows )
				);
				?>
			</p>
			<?php if ( ! empty( $collisions ) ) : ?>
				<div class="notice notice-warning">
					<p><strong><?php esc_html_e( 'Capability collisions', 'reactwoo-geocore' ); ?></strong></p>
					<ul>
						<?php foreach ( $collisions as $c ) : ?>
							<li><?php echo esc_html( $c['message'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Type', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Label', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Version', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Available', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Entitlement', 'reactwoo-geocore' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No capabilities registered yet.', 'reactwoo-geocore' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><code><?php echo esc_html( $row['id'] ); ?></code></td>
								<td><?php echo esc_html( $row['type'] ); ?></td>
								<td><?php echo esc_html( $row['label'] ); ?></td>
								<td><code><?php echo esc_html( $row['provider'] ); ?></code></td>
								<td><?php echo esc_html( $row['version'] ); ?></td>
								<td><?php echo ! empty( $row['available'] ) ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></td>
								<td><?php echo esc_html( $row['entitlement'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
