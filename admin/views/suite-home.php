<?php
/**
 * Suite Home — task-first entry point.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_state     = isset( $rwgc_state ) && is_array( $rwgc_state ) ? $rwgc_state : array();
$rwgc_activity  = isset( $rwgc_activity ) && is_array( $rwgc_activity ) ? $rwgc_activity : array();
$rwgc_launchers = isset( $rwgc_launchers ) && is_array( $rwgc_launchers ) ? $rwgc_launchers : array();
$rwgc_readiness = isset( $rwgc_readiness ) && is_array( $rwgc_readiness ) ? $rwgc_readiness : array();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Geo Suite — Home', 'reactwoo-geocore' ),
		__( 'One place to start workflows: country-specific pages, AI drafts, tests, and WooCommerce rules. Your modules stay separate — this screen connects them.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-suite-home' ); ?>

	<?php if ( empty( $rwgc_state['wizard_completed'] ) && empty( $rwgc_state['dismissed_welcome'] ) ) : ?>
		<div class="notice notice-info rwgc-suite-banner">
			<div class="rwgc-suite-banner__row">
				<p class="rwgc-suite-banner__text">
					<?php esc_html_e( 'New to the suite? Run the short guided setup to choose a goal and check your environment.', 'reactwoo-geocore' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-getting-started' ) ); ?>" class="button button-primary rwgc-suite-banner__cta">
						<?php esc_html_e( 'Open Getting Started', 'reactwoo-geocore' ); ?>
					</a>
				</p>
				<form class="rwgc-suite-banner__dismiss" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'rwgc_dismiss_welcome' ); ?>
					<input type="hidden" name="action" value="rwgc_dismiss_welcome" />
					<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss', 'reactwoo-geocore' ); ?></button>
				</form>
			</div>
		</div>
	<?php endif; ?>

	<h2 class="screen-reader-text"><?php esc_html_e( 'Start a workflow', 'reactwoo-geocore' ); ?></h2>
	<div class="rwgc-suite-launchers" role="list">
		<?php foreach ( $rwgc_launchers as $rwgc_launcher ) : ?>
			<?php
			if ( empty( $rwgc_launcher['title'] ) || empty( $rwgc_launcher['url'] ) ) {
				continue;
			}
			$rwgc_icon = isset( $rwgc_launcher['icon'] ) && is_string( $rwgc_launcher['icon'] ) ? $rwgc_launcher['icon'] : 'dashicons-arrow-right-alt';
			?>
			<div class="rwgc-suite-launcher" role="listitem">
				<div class="rwgc-suite-launcher__icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr( $rwgc_icon ); ?>"></span></div>
				<h3 class="rwgc-suite-launcher__title"><?php echo esc_html( $rwgc_launcher['title'] ); ?></h3>
				<p class="rwgc-suite-launcher__desc"><?php echo isset( $rwgc_launcher['description'] ) ? esc_html( $rwgc_launcher['description'] ) : ''; ?></p>
				<a class="button <?php echo ! empty( $rwgc_launcher['primary'] ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $rwgc_launcher['url'] ); ?>">
					<?php esc_html_e( 'Open', 'reactwoo-geocore' ); ?>
				</a>
			</div>
		<?php endforeach; ?>
	</div>

	<section class="rwgc-suite-readiness" aria-labelledby="rwgc-suite-readiness-h">
		<h2 id="rwgc-suite-readiness-h"><?php esc_html_e( 'Environment readiness', 'reactwoo-geocore' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Item', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Note', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rwgc_readiness as $rwgc_row ) : ?>
					<?php
					if ( empty( $rwgc_row['label'] ) ) {
						continue;
					}
					$rwgc_st = isset( $rwgc_row['status'] ) ? (string) $rwgc_row['status'] : 'optional';
					?>
					<tr>
						<td><strong><?php echo esc_html( $rwgc_row['label'] ); ?></strong>
							<?php if ( ! empty( $rwgc_row['description'] ) ) : ?>
								<br /><span class="description"><?php echo esc_html( $rwgc_row['description'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<span class="rwgc-suite-status rwgc-suite-status--<?php echo esc_attr( str_replace( '_', '-', $rwgc_st ) ); ?>">
								<?php echo esc_html( ucwords( str_replace( '_', ' ', $rwgc_st ) ) ); ?>
							</span>
						</td>
						<td>
							<?php echo isset( $rwgc_row['detail'] ) ? esc_html( (string) $rwgc_row['detail'] ) : ''; ?>
							<?php if ( ! empty( $rwgc_row['consequence'] ) ) : ?>
								<br /><span class="description"><?php echo esc_html( (string) $rwgc_row['consequence'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $rwgc_row['configure_url'] ) && in_array( $rwgc_st, array( 'needs_setup', 'missing' ), true ) ) : ?>
								<a href="<?php echo esc_url( $rwgc_row['configure_url'] ); ?>" class="button button-small"><?php esc_html_e( 'Configure', 'reactwoo-geocore' ); ?></a>
							<?php elseif ( ! empty( $rwgc_row['admin_url'] ) && 'missing' === $rwgc_st ) : ?>
								<a href="<?php echo esc_url( $rwgc_row['admin_url'] ); ?>" class="button button-small"><?php esc_html_e( 'View', 'reactwoo-geocore' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>

	<section class="rwgc-suite-activity" aria-labelledby="rwgc-suite-activity-h">
		<h2 id="rwgc-suite-activity-h"><?php esc_html_e( 'Recent activity', 'reactwoo-geocore' ); ?></h2>
		<?php if ( empty( $rwgc_activity ) ) : ?>
			<p class="description"><?php esc_html_e( 'When you create variants or complete workflows, a short history will appear here.', 'reactwoo-geocore' ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $rwgc_activity as $rwgc_item ) : ?>
					<?php
					if ( empty( $rwgc_item['payload']['title'] ) ) {
						continue;
					}
					$rwgc_url = isset( $rwgc_item['payload']['url'] ) ? (string) $rwgc_item['payload']['url'] : '';
					?>
					<li>
						<?php if ( $rwgc_url ) : ?>
							<a href="<?php echo esc_url( $rwgc_url ); ?>"><?php echo esc_html( $rwgc_item['payload']['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $rwgc_item['payload']['title'] ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $rwgc_item['site_time'] ) ) : ?>
							<span class="description"> — <?php echo esc_html( $rwgc_item['site_time'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<p class="rwgc-suite-wizard-footer">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-suite-variants' ) ); ?>" class="button"><?php esc_html_e( 'Page versions', 'reactwoo-geocore' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-getting-started' ) ); ?>" class="button"><?php esc_html_e( 'Getting Started', 'reactwoo-geocore' ); ?></a>
	</p>
</div>
