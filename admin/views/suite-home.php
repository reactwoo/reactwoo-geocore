<?php
/**
 * Suite Home — task-first entry point.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$state     = isset( $state ) && is_array( $state ) ? $state : array();
$activity  = isset( $activity ) && is_array( $activity ) ? $activity : array();
$launchers = isset( $launchers ) && is_array( $launchers ) ? $launchers : array();
$readiness = isset( $readiness ) && is_array( $readiness ) ? $readiness : array();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Geo Suite — Home', 'reactwoo-geocore' ),
		__( 'One place to start workflows: country-specific pages, AI drafts, tests, and WooCommerce rules. Your modules stay separate — this screen connects them.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-suite-home' ); ?>

	<?php if ( empty( $state['wizard_completed'] ) && empty( $state['dismissed_welcome'] ) ) : ?>
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
		<?php foreach ( $launchers as $launcher ) : ?>
			<?php
			if ( empty( $launcher['title'] ) || empty( $launcher['url'] ) ) {
				continue;
			}
			$icon = isset( $launcher['icon'] ) && is_string( $launcher['icon'] ) ? $launcher['icon'] : 'dashicons-arrow-right-alt';
			?>
			<div class="rwgc-suite-launcher" role="listitem">
				<div class="rwgc-suite-launcher__icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></div>
				<h3 class="rwgc-suite-launcher__title"><?php echo esc_html( $launcher['title'] ); ?></h3>
				<p class="rwgc-suite-launcher__desc"><?php echo isset( $launcher['description'] ) ? esc_html( $launcher['description'] ) : ''; ?></p>
				<a class="button <?php echo ! empty( $launcher['primary'] ) ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $launcher['url'] ); ?>">
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
				<?php foreach ( $readiness as $row ) : ?>
					<?php
					if ( empty( $row['label'] ) ) {
						continue;
					}
					$st = isset( $row['status'] ) ? (string) $row['status'] : 'optional';
					?>
					<tr>
						<td><strong><?php echo esc_html( $row['label'] ); ?></strong>
							<?php if ( ! empty( $row['description'] ) ) : ?>
								<br /><span class="description"><?php echo esc_html( $row['description'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<span class="rwgc-suite-status rwgc-suite-status--<?php echo esc_attr( str_replace( '_', '-', $st ) ); ?>">
								<?php echo esc_html( ucwords( str_replace( '_', ' ', $st ) ) ); ?>
							</span>
						</td>
						<td>
							<?php echo isset( $row['detail'] ) ? esc_html( (string) $row['detail'] ) : ''; ?>
							<?php if ( ! empty( $row['consequence'] ) ) : ?>
								<br /><span class="description"><?php echo esc_html( (string) $row['consequence'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $row['configure_url'] ) && in_array( $st, array( 'needs_setup', 'missing' ), true ) ) : ?>
								<a href="<?php echo esc_url( $row['configure_url'] ); ?>" class="button button-small"><?php esc_html_e( 'Configure', 'reactwoo-geocore' ); ?></a>
							<?php elseif ( ! empty( $row['admin_url'] ) && 'missing' === $st ) : ?>
								<a href="<?php echo esc_url( $row['admin_url'] ); ?>" class="button button-small"><?php esc_html_e( 'View', 'reactwoo-geocore' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</section>

	<section class="rwgc-suite-activity" aria-labelledby="rwgc-suite-activity-h">
		<h2 id="rwgc-suite-activity-h"><?php esc_html_e( 'Recent activity', 'reactwoo-geocore' ); ?></h2>
		<?php if ( empty( $activity ) ) : ?>
			<p class="description"><?php esc_html_e( 'When you create variants or complete workflows, a short history will appear here.', 'reactwoo-geocore' ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $activity as $item ) : ?>
					<?php
					if ( empty( $item['payload']['title'] ) ) {
						continue;
					}
					$url = isset( $item['payload']['url'] ) ? (string) $item['payload']['url'] : '';
					?>
					<li>
						<?php if ( $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $item['payload']['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $item['payload']['title'] ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $item['site_time'] ) ) : ?>
							<span class="description"> — <?php echo esc_html( $item['site_time'] ); ?></span>
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
