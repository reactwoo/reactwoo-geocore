<?php
/**
 * Getting Started — 3-step wizard: goal → environment → detection preview.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_state         = isset( $rwgc_state ) && is_array( $rwgc_state ) ? $rwgc_state : RWGC_Onboarding::get_state();
$rwgc_readiness     = isset( $rwgc_readiness ) && is_array( $rwgc_readiness ) ? $rwgc_readiness : array();
$rwgc_launchers     = isset( $rwgc_launchers ) && is_array( $rwgc_launchers ) ? $rwgc_launchers : array();
$rwgc_guidance      = isset( $rwgc_guidance ) && is_array( $rwgc_guidance ) ? $rwgc_guidance : array( 'headline' => '', 'body' => '' );
$rwgc_visitor_data  = isset( $rwgc_visitor_data ) && is_array( $rwgc_visitor_data ) ? $rwgc_visitor_data : array();
$rwgc_wizard_step   = isset( $rwgc_wizard_step ) ? (int) $rwgc_wizard_step : 1;
$rwgc_goal          = isset( $rwgc_state['goal'] ) ? (string) $rwgc_state['goal'] : '';
$rwgc_saved         = isset( $_GET['rwgc_saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['rwgc_saved'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgc_welcome       = isset( $_GET['rwgc_welcome'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['rwgc_welcome'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$rwgc_goal_labels = array(
	'variants'    => __( 'Show different page content by country', 'reactwoo-geocore' ),
	'ai'          => __( 'Draft localised copy with a separate AI plugin', 'reactwoo-geocore' ),
	'tests'       => __( 'Run a geo split test', 'reactwoo-geocore' ),
	'commerce'    => __( 'Personalise WooCommerce pricing or offers', 'reactwoo-geocore' ),
	'foundation'  => __( 'Just set up geolocation first', 'reactwoo-geocore' ),
);
$rwgc_goal_label    = isset( $rwgc_goal_labels[ $rwgc_goal ] ) ? $rwgc_goal_labels[ $rwgc_goal ] : '';
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'ReactWoo Geo — Setup wizard', 'reactwoo-geocore' ),
		__( 'Three short steps: choose your goal, confirm setup, then verify visitor detection.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-getting-started' ); ?>

	<?php if ( $rwgc_saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Progress saved.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $rwgc_welcome ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Welcome. Complete setup, then use Dashboard and visibility rules for daily work.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<section class="rwgc-card rwgc-suite-stepper-card" aria-labelledby="rwgc-stepper-heading">
		<h2 id="rwgc-stepper-heading" class="screen-reader-text"><?php esc_html_e( 'Setup steps', 'reactwoo-geocore' ); ?></h2>
		<ol class="rwgc-suite-stepper" aria-label="<?php esc_attr_e( 'Setup progress', 'reactwoo-geocore' ); ?>">
			<li class="rwgc-suite-stepper__item<?php echo $rwgc_wizard_step >= 1 ? ' is-reached' : ''; ?><?php echo 1 === $rwgc_wizard_step ? ' is-current' : ''; ?><?php echo $rwgc_wizard_step > 1 ? ' is-done' : ''; ?>">
				<span class="rwgc-suite-stepper__num" aria-hidden="true">1</span>
				<span class="rwgc-suite-stepper__label"><?php esc_html_e( 'Goal', 'reactwoo-geocore' ); ?></span>
			</li>
			<li class="rwgc-suite-stepper__item<?php echo $rwgc_wizard_step >= 2 ? ' is-reached' : ''; ?><?php echo 2 === $rwgc_wizard_step ? ' is-current' : ''; ?><?php echo $rwgc_wizard_step > 2 ? ' is-done' : ''; ?>">
				<span class="rwgc-suite-stepper__num" aria-hidden="true">2</span>
				<span class="rwgc-suite-stepper__label"><?php esc_html_e( 'Environment', 'reactwoo-geocore' ); ?></span>
			</li>
			<li class="rwgc-suite-stepper__item<?php echo $rwgc_wizard_step >= 3 ? ' is-reached' : ''; ?><?php echo 3 === $rwgc_wizard_step ? ' is-current' : ''; ?>">
				<span class="rwgc-suite-stepper__num" aria-hidden="true">3</span>
				<span class="rwgc-suite-stepper__label"><?php esc_html_e( 'Detection', 'reactwoo-geocore' ); ?></span>
			</li>
		</ol>
	</section>

	<section class="rwgc-suite-wizard-section" aria-labelledby="rwgc-wz-s1">
		<h2 id="rwgc-wz-s1" class="rwgc-suite-wizard-section__title"><?php esc_html_e( 'Step 1 — What do you want to achieve first?', 'reactwoo-geocore' ); ?></h2>
		<?php if ( $rwgc_wizard_step > 1 && $rwgc_goal_label ) : ?>
			<p class="rwgc-suite-wizard-section__done"><?php echo esc_html( $rwgc_goal_label ); ?> <span class="description">(<?php esc_html_e( 'saved', 'reactwoo-geocore' ); ?>)</span></p>
		<?php endif; ?>
		<?php if ( 1 === $rwgc_wizard_step ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-suite-wizard">
			<?php wp_nonce_field( 'rwgc_save_wizard' ); ?>
			<input type="hidden" name="action" value="rwgc_save_wizard" />
			<input type="hidden" name="rwgc_wizard_action" value="goal" />
			<div class="rwgc-suite-goal-options">
				<label><input type="radio" name="rwgc_wizard_goal" value="variants" <?php checked( $rwgc_goal, 'variants' ); ?> required />
					<?php echo esc_html( $rwgc_goal_labels['variants'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="ai" <?php checked( $rwgc_goal, 'ai' ); ?> />
					<?php echo esc_html( $rwgc_goal_labels['ai'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="tests" <?php checked( $rwgc_goal, 'tests' ); ?> />
					<?php echo esc_html( $rwgc_goal_labels['tests'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="commerce" <?php checked( $rwgc_goal, 'commerce' ); ?> />
					<?php echo esc_html( $rwgc_goal_labels['commerce'] ); ?></label>
				<label><input type="radio" name="rwgc_wizard_goal" value="foundation" <?php checked( $rwgc_goal, 'foundation' ); ?> />
					<?php echo esc_html( $rwgc_goal_labels['foundation'] ); ?></label>
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Save goal and continue', 'reactwoo-geocore' ); ?></button>
			</p>
		</form>
		<?php endif; ?>
	</section>

	<?php if ( $rwgc_goal && ! empty( $rwgc_guidance['headline'] ) && ! empty( $rwgc_guidance['body'] ) && $rwgc_wizard_step >= 2 ) : ?>
		<div class="rwgc-suite-goal-panel notice" role="region" aria-label="<?php esc_attr_e( 'Guidance for your goal', 'reactwoo-geocore' ); ?>">
			<p><strong><?php echo esc_html( $rwgc_guidance['headline'] ); ?></strong></p>
			<p class="rwgc-suite-goal-panel__body"><?php echo esc_html( $rwgc_guidance['body'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $rwgc_wizard_step >= 2 ) : ?>
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
					<?php foreach ( $rwgc_readiness as $rwgc_row ) : ?>
						<?php
						if ( empty( $rwgc_row['label'] ) ) {
							continue;
						}
						$rwgc_st = isset( $rwgc_row['status'] ) ? (string) $rwgc_row['status'] : 'optional';
						?>
						<tr>
							<td><strong><?php echo esc_html( $rwgc_row['label'] ); ?></strong></td>
							<td><span class="rwgc-suite-status rwgc-suite-status--<?php echo esc_attr( str_replace( '_', '-', $rwgc_st ) ); ?>"><?php echo esc_html( $rwgc_st ); ?></span></td>
							<td>
								<?php echo isset( $rwgc_row['detail'] ) ? esc_html( (string) $rwgc_row['detail'] ) : ''; ?>
								<?php if ( ! empty( $rwgc_row['consequence'] ) ) : ?>
									<br /><span class="description"><?php echo esc_html( (string) $rwgc_row['consequence'] ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $rwgc_row['configure_url'] ) && in_array( $rwgc_st, array( 'needs_setup', 'missing' ), true ) ) : ?>
									<a href="<?php echo esc_url( $rwgc_row['configure_url'] ); ?>" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm"><?php esc_html_e( 'Configure', 'reactwoo-geocore' ); ?></a>
								<?php elseif ( ! empty( $rwgc_row['admin_url'] ) && 'missing' === $rwgc_st ) : ?>
									<a href="<?php echo esc_url( $rwgc_row['admin_url'] ); ?>" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm"><?php esc_html_e( 'View', 'reactwoo-geocore' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( 2 === $rwgc_wizard_step ) : ?>
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

	<?php if ( $rwgc_wizard_step >= 3 ) : ?>
	<section class="rwgc-suite-wizard-section" aria-labelledby="rwgc-wz-s3">
		<h2 id="rwgc-wz-s3" class="rwgc-suite-wizard-section__title"><?php esc_html_e( 'Step 3 — Quick detection check (admin preview)', 'reactwoo-geocore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'This sample uses the same visitor APIs as the front end. Use Integrations → MaxMind if the database is missing or country looks wrong.', 'reactwoo-geocore' ); ?></p>
		<?php if ( ! empty( $rwgc_visitor_data ) ) : ?>
			<div class="rwgc-visitor-stats rwgc-suite-visitor-preview">
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $rwgc_visitor_data['country_code'] . ' – ' . $rwgc_visitor_data['country_name'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $rwgc_visitor_data['currency'] ); ?></strong>
				</div>
				<div class="rwgc-visitor-stat">
					<span class="rwgc-visitor-stat__label"><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?></span>
					<strong class="rwgc-visitor-stat__value"><?php echo esc_html( $rwgc_visitor_data['source'] ); ?></strong>
				</div>
			</div>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'No preview data yet — open Integrations → MaxMind to verify the database.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Recommended next steps', 'reactwoo-geocore' ); ?></h3>
		<p class="rwgc-suite-shell__intro"><?php esc_html_e( 'Open a workflow below. Capabilities open inside ReactWoo Geo sections (Targeting, Commerce, Insights, Integrations).', 'reactwoo-geocore' ); ?></p>
		<div class="rwgc-suite-launchers">
			<?php foreach ( $rwgc_launchers as $rwgc_launcher ) : ?>
				<?php
				if ( empty( $rwgc_launcher['title'] ) || empty( $rwgc_launcher['url'] ) ) {
					continue;
				}
				$rwgc_icon = isset( $rwgc_launcher['icon'] ) && is_string( $rwgc_launcher['icon'] ) ? $rwgc_launcher['icon'] : 'dashicons-arrow-right-alt';
				?>
				<div class="rwgc-suite-launcher">
					<div class="rwgc-suite-launcher__icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr( $rwgc_icon ); ?>"></span></div>
					<h3 class="rwgc-suite-launcher__title"><?php echo esc_html( $rwgc_launcher['title'] ); ?></h3>
					<p class="rwgc-suite-launcher__desc"><?php echo isset( $rwgc_launcher['description'] ) ? esc_html( $rwgc_launcher['description'] ) : ''; ?></p>
					<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $rwgc_launcher['url'] ); ?>"><?php esc_html_e( 'Continue', 'reactwoo-geocore' ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-suite-wizard-complete">
			<?php wp_nonce_field( 'rwgc_save_wizard' ); ?>
			<input type="hidden" name="action" value="rwgc_save_wizard" />
			<input type="hidden" name="rwgc_wizard_action" value="complete" />
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Mark setup as done', 'reactwoo-geocore' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-dashboard' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Dashboard', 'reactwoo-geocore' ); ?></a>
			</p>
		</form>
	</section>
	<?php endif; ?>

	<p class="rwgc-suite-wizard-footer rwgc-actions">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-dashboard' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Dashboard', 'reactwoo-geocore' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-visibility-rules' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Visibility rules', 'reactwoo-geocore' ); ?></a>
	</p>
</div>
