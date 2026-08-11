<?php
/**
 * Admin diagnostics for Experience Slots.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geo Core → Experience Slots screen.
 */
final class RWGC_Experience_Slots_Admin {

	const PAGE = 'rwgc-experience-slots';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 66 );
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		$parent = class_exists( 'RWGC_Admin_Platform', false ) ? RWGC_Admin_Platform::menu_parent() : 'rwgc-dashboard';
		$cap    = class_exists( 'RWGC_Admin', false ) ? RWGC_Admin::required_capability() : 'manage_options';

		add_submenu_page(
			$parent,
			__( 'Experience Slots', 'reactwoo-geocore' ),
			__( 'Experience Slots', 'reactwoo-geocore' ),
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

		$diag    = RWGC_Experience_Slot_Registry::diagnostics();
		$slots   = RWGC_Experience_Slot_Registry::all();
		$missing = RWGC_Experience_Slot_Resolver::missing_ids();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Experience Slots', 'reactwoo-geocore' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Stable named locations where ReactWoo may select alternate content. Default website content is always the safe fallback. Elementor/Gutenberg adapters (WP6–7) bind slots to builders.', 'reactwoo-geocore' ); ?>
			</p>
			<ul>
				<li><?php printf( esc_html__( 'Total: %d', 'reactwoo-geocore' ), (int) $diag['total'] ); ?></li>
				<li><?php printf( esc_html__( 'Active: %d', 'reactwoo-geocore' ), (int) $diag['active'] ); ?></li>
				<li><?php printf( esc_html__( 'Unavailable: %d', 'reactwoo-geocore' ), (int) $diag['unavailable'] ); ?></li>
				<li><?php printf( esc_html__( 'Invalid rows: %d', 'reactwoo-geocore' ), (int) $diag['invalid'] ); ?></li>
				<?php if ( ! empty( $diag['duplicates'] ) ) : ?>
					<li>
						<strong><?php esc_html_e( 'Duplicate binding keys:', 'reactwoo-geocore' ); ?></strong>
						<code><?php echo esc_html( implode( ', ', $diag['duplicates'] ) ); ?></code>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $missing ) ) : ?>
					<li>
						<strong><?php esc_html_e( 'Missing slots requested this request:', 'reactwoo-geocore' ); ?></strong>
						<code><?php echo esc_html( implode( ', ', $missing ) ); ?></code>
					</li>
				<?php endif; ?>
			</ul>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Name', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Adapter', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Page', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
						<th><?php esc_html_e( 'Binding', 'reactwoo-geocore' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $slots ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No experience slots registered yet.', 'reactwoo-geocore' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $slots as $slot ) : ?>
							<?php
							$meta    = $slot->metadata();
							$binding = isset( $meta['binding_key'] ) ? (string) $meta['binding_key'] : '';
							?>
							<tr>
								<td><code><?php echo esc_html( $slot->id() ); ?></code></td>
								<td><?php echo esc_html( $slot->name() ); ?></td>
								<td><?php echo esc_html( $slot->adapter() ); ?></td>
								<td><?php echo esc_html( $slot->page() ); ?></td>
								<td><?php echo esc_html( $slot->status() ); ?></td>
								<td><code><?php echo esc_html( $binding ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
