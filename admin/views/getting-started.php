<?php
/**
 * Getting Started — 3-step wizard: goal → environment → detection preview.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$state         = isset( $state ) && is_array( $state ) ? $state : RWGC_Onboarding::get_state();
$readiness     = isset( $readiness ) && is_array( $readiness ) ? $readiness : array();
$launchers     = isset( $launchers ) && is_array( $launchers ) ? $launchers : array();
$guidance      = isset( $guidance ) && is_array( $guidance ) ? $guidance : array( 'headline' => '', 'body' => '' );
$visitor_data  = isset( $visitor_data ) && is_array( $visitor_data ) ? $visitor_data : array();
$wizard_step   = isset( $wizard_step ) ? (int) $wizard_step : 1;
$goal          = isset( $state['goal'] ) ? (string) $state['goal'] : '';
$saved         = isset( $_GET['rwgc_saved'] ) && '1' === $_GET['rwgc_saved']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$welcome       = isset( $_GET['rwgc_welcome'] ) && '1' === $_GET['rwgc_welcome']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$goal_labels = array(
	'variants'    => __( 'Show different page content by country', 'reactwoo-geocore' ),
	'ai'          => __( 'Create localised page versions with AI', 'reactwoo-geocore' ),
	'tests'       => __( 'Run a geo split test', 'reactwoo-geocore' ),
	'commerce'    => __( 'Personalise WooCommerce pricing or offers', 'reactwoo-geocore' ),
	'foundation'  => __( 'Just set up geolocation first', 'reactwoo-geocore' ),
);
$goal_label    = isset( $goal_labels[ $goal ] ) ? $goal_labels[ $goal ] : '';
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Geo Suite — Getting Started', 'reactwoo-geocore' ),
		__( 'Three short steps: pick an outcome, confirm your environment, then verify detection. You can return here any time from the Geo Core menu.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-getting-started' ); ?>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Progress saved.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $welcome ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Welcome — you are in the right place. Work through the steps below, then use Suite Home for day-to-day shortcuts.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<section class="rwgc-card rwgc-suite-stepper-card" aria-labelledby="rwgc-stepper-heading">
		<h2 id="rwgc-stepper-heading" class="screen-reader-text"><?php esc_html_e( 'Setup steps', 'reactwoo-geocore' ); ?></h2>
		<ol class="rwgc-suite-stepper" aria-label="<?php esc_attr_e( 'Setup progress', 'reactwoo-geocore' ); ?>">
			<li class="rwgc-suite-stepper__item<?php echo $wizard_step >= 1 ? ' is-reached' : ''; ?><?php echo 1 === $wizard_step ? ' is-current' : ''; ?><?php echo $wizard_step > 1 ? ' is-done' : ''; ?>">
				<span class="rwgc-suite-stepper__num" aria-hidden="true">1</span>
				<span class="rwgc-suite-stepper__label"><?php esc_html_e( 'Goal', 'reactwoo-geocore' ); ?></span>
			</li>
			<li class="rwgc-suite-stepper__item<?php echo $wizard_step >= 2 ? ' is-reached' : ''; ?><?php echo 2 === $wizard_step ? ' is-current' : ''; ?><?php echo $wizard_step > 2 ? ' is-done' : ''; ?>">
				<span class="rwgc-suite-stepper__num" aria-hidden="true">2</span>
				<span class="rwgc-suite-stepper__label"><?php esc_html_e( 'Environment', 'reactwoo-geocore' ); ?></span>
			</li>
			<li class="rwgc-suite-stepper__item<?php echo $wizard_step >= 3 ? ' is-reached' : ''; ?><?php echo 3 === $wizard_step ? ' is-current' : ''; ?>">
				<span class="rwgc-suite-stepper__num" aria-hidden="true">3</span>
				<span class="rwgc-suite-stepper__label"><?php esc_html_e( 'Detection', 'reactwoo-geocore' ); ?></span>
			</li>
		</ol>
	</section>

	<section class="rwgc-suite-wizard-section" aria-labelledby="rwgc-wz-s1">
		<h2 id="rwgc-wz-s1" class="rwgc-suite-wizard-section__title"><?php esc_html_e( 'Step 1 — What do you want to achieve first?', 'reactwoo-geocore' ); ?></h2>
		<?php if ( $wizard_step > 1 && $goal_label ) : ?>
			<p class="rwgc-suite-wizard-section__done"><?php echo esc_html( $goal_label ); ?> <span class="description">(<?php esc_html_e( 'saved', 'reactwoo-geocore' ); ?>)</span></p>
		<?php endif; ?>
		<?php if ( 1 === $wizard_step ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-suite-wizard">
			<?php wp_nonce_field( 'rwgc_save_wizard' ); ?>
			<input type="hidden" name="action" value="rwgc_save_wizard" />
			<input type="hidden" name="rwgc_wizard_action" value="goal" />
			<div class="rwgc-suite-goal-options">
				<label><input type="radio" name="rwgc_wizard_goal" value="variants" <?php checked( $goal, 'variants' ); ?> required />
					<?php echo esc_html( $goal_labels['variants'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="ai" <?php checked( $goal, 'ai' ); ?> />
					<?php echo esc_html( $goal_labels['ai'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="tests" <?php checked( $goal, 'tests' ); ?> />
					<?php echo esc_html( $goal_labels['tests'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="commerce" <?php checked( $goal, 'commerce' ); ?> />
					<?php echo esc_html( $goal_labels['commerce'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="foundation" <?php checked( $goal, 'foundation' ); ?> />
					<?php echo esc_html( $goal_labels['foundation'] ); ?></label>
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Save goal and continue', 'reactwoo-geocore' ); ?></button>
			</p>
		</form>
		<?php endif; ?>
	</section>

	<?php if ( $goal && ! empty( $guidance['headline'] ) && ! empty( $guidance['body'] ) && $wizard_step >= 2 ) : ?>
		<div class="rwgc-suite-goal-panel notice" role="region" aria-label="<?php esc_attr_e( 'Guidance for your goal', 'reactwoo-geocore' ); ?>">
			<p><strong><?php echo esc_html( $guidance['headline'] ); ?></strong></p>
			<p class="rwgc-suite-goal-panel__body"><?php echo esc_html( $guidance['body'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $wizard_step >= 2 ) : ?>
	<section class="rwgc-suite-wizard-section" aria-labelledby="rwgc-wz-s2">
		<h2 id="rwgc-wz-s2" class="rwgc-suite-wizard-section__title"><?php esc_html_e( 'Step 2 — Environment checklist', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Fix anything marked “needs setup” before relying on geo in production. Links open the right Geo Core screen.', 'reactwoo-geocore' ); ?></p>
		<div class="rwgc-suite-readiness">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Item', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'reactwoo-geocore' ); ?></th>
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
							<td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
							<td><span class="rwgc-suite-status rwgc-suite-status--<?php echo esc_attr( str_replace( '_', '-', $st ) ); ?>"><?php echo esc_html( $st ); ?></span></td>
							<td>
								<?php echo isset( $row['detail'] ) ? esc_html( (string) $row['detail'] ) : ''; ?>
								<?php if ( ! empty( $row['consequence'] ) ) : ?>
									<br /><span class="description"><?php echo esc_html( (string) $row['consequence'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['configure_url'] ) && in_array( $st, array( 'needs_setup', 'missing' ), true ) ) : ?>
									<a href="<?php echo esc_url( $row['configure_url'] ); ?>" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm"><?php esc_html_e( 'Configure', 'reactwoo-geocore' ); ?></a>
								<?php elseif ( ! empty( $row['admin_url'] ) && 'missing' === $st ) : ?>
									<a href="<?php echo esc_url( $row['admin_url'] ); ?>" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm"><?php esc_html_e( 'View', 'reactwoo-geocore' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( 2 === $wizard_step ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'rwgc_save_wizard' ); ?>
			<input type="hidden" name="action" value="rwgc_save_wizard" />
			<input type="hidden" name="rwgc_wizard_action" value="advance_env" />
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Continue to detection check', 'reactwoo-geocore' ); ?></button>
			</p>
		</form>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( $wizard_step >= 3 ) : ?>
	<section class="rwgc-suite-wizard-section" aria-labelledby="rwgc-wz-s3">
		<h2 id="rwgc-wz-s3" class="rwgc-suite-wizard-section__title"><?php esc_html_e( 'Step 3 — Quick detection check (admin preview)', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'This sample uses the same visitor APIs as the front end. Use Tools if the database is missing or country looks wrong.', 'reactwoo-geocore' ); ?></p>
		<?php if ( ! empty( $visitor_data ) ) : ?>
			<div class="rwgc-visitor-stats rwgc-suite-visitor-preview">
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $visitor_data['country_code'] . ' – ' . $visitor_data['country_name'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $visitor_data['currency'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $visitor_data['source'] ); ?></strong>
				</div>
			</div>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No preview data yet — open Tools to verify the database.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Recommended next steps', 'reactwoo-geocore' ); ?></h3>
		<p class="rwgc-suite-shell__intro"><?php esc_html_e( 'Open a workflow below. Satellite plugins stay in their own menus — these buttons deep-link with context for integrations.', 'reactwoo-geocore' ); ?></p>
		<div class="rwgc-suite-launchers">
			<?php foreach ( $launchers as $launcher ) : ?>
				<?php
				if ( empty( $launcher['title'] ) || empty( $launcher['url'] ) ) {
					continue;
				}
				$icon = isset( $launcher['icon'] ) && is_string( $launcher['icon'] ) ? $launcher['icon'] : 'dashicons-arrow-right-alt';
				?>
				<div class="rwgc-suite-launcher">
					<div class="rwgc-suite-launcher__icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></div>
					<h3 class="rwgc-suite-launcher__title"><?php echo esc_html( $launcher['title'] ); ?></h3>
					<p class="rwgc-suite-launcher__desc"><?php echo isset( $launcher['description'] ) ? esc_html( $launcher['description'] ) : ''; ?></p>
					<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $launcher['url'] ); ?>"><?php esc_html_e( 'Continue', 'reactwoo-geocore' ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-suite-wizard-complete">
			<?php wp_nonce_field( 'rwgc_save_wizard' ); ?>
			<input type="hidden" name="action" value="rwgc_save_wizard" />
			<input type="hidden" name="rwgc_wizard_action" value="complete" />
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Mark setup as done', 'reactwoo-geocore' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-suite-home' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Suite Home', 'reactwoo-geocore' ); ?></a>
			</p>
		</form>
	</section>
	<?php endif; ?>

	<p class="rwgc-suite-wizard-footer rwgc-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-suite-home' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Suite Home', 'reactwoo-geocore' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-dashboard' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Classic dashboard', 'reactwoo-geocore' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-suite-variants' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Page versions', 'reactwoo-geocore' ); ?></a>
	</p>
</div>
