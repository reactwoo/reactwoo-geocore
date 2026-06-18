<?php
/**
 * Targeting Assistant — chat-style guided flow.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant_wizard_url = admin_url( 'admin.php?page=rwgc-workflow-variant' );
$rules_create_url   = function_exists( 'rw_geo_app_url' )
	? rw_geo_app_url( 'targeting', 'rwgc-visibility-rules' ) . '&rwgc_edit=new'
	: admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=new' );
$experiences_url    = class_exists( 'RWGO_Admin', false )
	? admin_url( 'admin.php?page=rwgo-dashboard' )
	: admin_url( 'admin.php?page=rwgc-experiences-hub' );
$exp_status         = class_exists( 'RWGC_Capability_Registry', false )
	? RWGC_Capability_Registry::get_status( 'experiences' )
	: array( 'state' => 'not_installed' );
$ai_suggestion      = class_exists( 'RWGC_Geo_AI_Suggestions', false )
	? RWGC_Geo_AI_Suggestions::get_for_context( 'targeting' )
	: null;
$platform_shell     = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-targeting-assistant-wrap">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Targeting', 'reactwoo-geocore' ),
		__( 'Decide who sees what.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( ! $platform_shell && class_exists( 'RWGC_Admin_Targeting_Nav', false ) ) {
		RWGC_Admin_Targeting_Nav::render_tabs( 'rwgc-targeting-hub' );
	}
	?>

	<?php
	if ( class_exists( 'RWGC_Geo_AI_Suggestions', false ) ) {
		RWGC_Geo_AI_Suggestions::render_inline( $ai_suggestion );
	}
	?>

	<div class="rwgc-targeting-assistant" id="rwgc-targeting-assistant" data-variant-url="<?php echo esc_url( $variant_wizard_url ); ?>" data-rules-url="<?php echo esc_url( $rules_create_url ); ?>" data-experiences-url="<?php echo esc_url( $experiences_url ); ?>" data-exp-state="<?php echo esc_attr( (string) ( $exp_status['state'] ?? '' ) ); ?>">
		<div class="rwgc-targeting-assistant__chat">
			<div class="rwgc-geo-assistant-panel">
				<div class="rwgc-geo-assistant-panel__head"><?php esc_html_e( 'Geo Assistant', 'reactwoo-geocore' ); ?></div>
				<div class="rwgc-geo-assistant-panel__body">
					<div class="rwgc-targeting-assistant__thread" id="rwgc-targeting-thread" aria-live="polite"></div>
					<div class="rwgc-targeting-assistant__step" id="rwgc-targeting-step"></div>
					<div class="rwgc-targeting-assistant__composer" id="rwgc-targeting-composer">
						<label class="rwgc-targeting-assistant__composer-label" for="rwgc-targeting-phrase"><?php esc_html_e( 'Describe your targeting goal', 'reactwoo-geocore' ); ?></label>
						<textarea
							id="rwgc-targeting-phrase"
							class="rwgc-targeting-assistant__phrase"
							rows="2"
							placeholder="<?php esc_attr_e( 'Example: Show a different version of the homepage in Australia for mobile users', 'reactwoo-geocore' ); ?>"
						></textarea>
						<div class="rwgc-targeting-assistant__composer-context">
							<div class="rwgc-targeting-assistant__composer-field">
								<label for="rwgc-composer-page"><?php esc_html_e( 'Page (optional)', 'reactwoo-geocore' ); ?></label>
								<select id="rwgc-composer-page" class="rwgc-targeting-assistant__select">
									<option value=""><?php esc_html_e( 'Detect from phrase or choose…', 'reactwoo-geocore' ); ?></option>
								</select>
							</div>
							<div class="rwgc-targeting-assistant__composer-field">
								<label for="rwgc-composer-country"><?php esc_html_e( 'Country (optional)', 'reactwoo-geocore' ); ?></label>
								<select id="rwgc-composer-country" class="rwgc-targeting-assistant__select">
									<option value=""><?php esc_html_e( 'Detect from phrase…', 'reactwoo-geocore' ); ?></option>
								</select>
							</div>
							<div class="rwgc-targeting-assistant__composer-field">
								<label for="rwgc-composer-device"><?php esc_html_e( 'Device (optional)', 'reactwoo-geocore' ); ?></label>
								<select id="rwgc-composer-device" class="rwgc-targeting-assistant__select">
									<option value=""><?php esc_html_e( 'Any / detect from phrase…', 'reactwoo-geocore' ); ?></option>
									<option value="mobile"><?php esc_html_e( 'Mobile', 'reactwoo-geocore' ); ?></option>
									<option value="desktop"><?php esc_html_e( 'Desktop', 'reactwoo-geocore' ); ?></option>
									<option value="tablet"><?php esc_html_e( 'Tablet', 'reactwoo-geocore' ); ?></option>
								</select>
							</div>
						</div>
						<div class="rwgc-targeting-assistant__composer-actions">
							<button type="button" class="button button-primary rwgc-geo-btn" id="rwgc-targeting-interpret-btn"><?php esc_html_e( 'Interpret', 'reactwoo-geocore' ); ?></button>
							<button type="button" class="button" id="rwgc-targeting-reset-btn"><?php esc_html_e( 'Start over', 'reactwoo-geocore' ); ?></button>
						</div>
						<p class="description rwgc-targeting-assistant__composer-hint"><?php esc_html_e( 'Type a goal in plain language, or use the quick-start buttons when the assistant asks.', 'reactwoo-geocore' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<aside class="rwgc-targeting-assistant__setup" aria-label="<?php esc_attr_e( 'Setup summary', 'reactwoo-geocore' ); ?>">
			<div class="rwgc-geo-setup-panel">
				<h2 class="rwgc-geo-setup-panel__title"><?php esc_html_e( 'Setup', 'reactwoo-geocore' ); ?></h2>
				<p class="rwgc-geo-setup-panel__empty" id="rwgc-targeting-setup-empty"><?php esc_html_e( 'No setup yet.', 'reactwoo-geocore' ); ?></p>
				<dl class="rwgc-geo-setup-rows rwgc-is-hidden" id="rwgc-targeting-summary">
					<div class="rwgc-geo-setup-row"><dt><?php esc_html_e( 'Goal', 'reactwoo-geocore' ); ?></dt><dd data-key="goal" class="is-empty">—</dd></div>
					<div class="rwgc-geo-setup-row"><dt><?php esc_html_e( 'Page', 'reactwoo-geocore' ); ?></dt><dd data-key="page" class="is-empty">—</dd></div>
					<div class="rwgc-geo-setup-row"><dt><?php esc_html_e( 'Type', 'reactwoo-geocore' ); ?></dt><dd data-key="type" class="is-empty">—</dd></div>
					<div class="rwgc-geo-setup-row"><dt><?php esc_html_e( 'Condition', 'reactwoo-geocore' ); ?></dt><dd data-key="condition" class="is-empty">—</dd></div>
					<div class="rwgc-geo-setup-row"><dt><?php esc_html_e( 'Destination', 'reactwoo-geocore' ); ?></dt><dd data-key="destination" class="is-empty">—</dd></div>
					<div class="rwgc-geo-setup-row"><dt><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></dt><dd data-key="status" class="is-empty">—</dd></div>
				</dl>
			</div>
		</aside>
	</div>

	<div class="rwgc-targeting-assistant__lock-panel rwgc-is-hidden" id="rwgc-targeting-lock-panel" hidden>
		<h3 id="rwgc-targeting-lock-title"></h3>
		<p id="rwgc-targeting-lock-body"></p>
		<p>
			<a class="rwgc-geo-link" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>"><?php esc_html_e( 'Learn more', 'reactwoo-geocore' ); ?></a>
			<button type="button" class="button" id="rwgc-targeting-lock-back"><?php esc_html_e( 'Back', 'reactwoo-geocore' ); ?></button>
		</p>
	</div>
</div>
