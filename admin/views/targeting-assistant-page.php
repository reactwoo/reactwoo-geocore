<?php
/**
 * Targeting Assistant — chat-style smart action interface.
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

	<div
		class="rwgc-targeting-assistant"
		id="rwgc-targeting-assistant"
		data-variant-url="<?php echo esc_url( $variant_wizard_url ); ?>"
		data-rules-url="<?php echo esc_url( $rules_create_url ); ?>"
		data-experiences-url="<?php echo esc_url( $experiences_url ); ?>"
		data-exp-state="<?php echo esc_attr( (string) ( $exp_status['state'] ?? '' ) ); ?>"
	>
		<div class="rwgc-targeting-assistant__chat">
			<div class="rwgc-geo-assistant-panel">
				<div class="rwgc-geo-assistant-panel__head"><?php esc_html_e( 'Geo Assistant', 'reactwoo-geocore' ); ?></div>
				<div class="rwgc-geo-assistant-panel__body">
					<div class="rwgc-targeting-assistant__thread" id="rwgc-targeting-thread" aria-live="polite"></div>
					<div class="rwgc-targeting-assistant__live-preview rwgc-is-hidden" id="rwgc-targeting-live-preview" aria-live="polite"></div>
					<div class="rwgc-targeting-assistant__step" id="rwgc-targeting-step"></div>

					<div class="rwgc-targeting-assistant__hints" id="rwgc-targeting-hints" aria-label="<?php esc_attr_e( 'Example keywords and keywords', 'reactwoo-geocore' ); ?>"></div>

					<div class="rwgc-targeting-assistant__composer" id="rwgc-targeting-composer">
						<div class="rwgc-targeting-assistant__input-wrap">
							<textarea
								id="rwgc-targeting-phrase"
								class="rwgc-targeting-assistant__phrase"
								rows="2"
								placeholder="<?php esc_attr_e( 'Type your targeting goal…', 'reactwoo-geocore' ); ?>"
								aria-label="<?php esc_attr_e( 'Targeting goal', 'reactwoo-geocore' ); ?>"
							></textarea>
							<button type="button" class="rwgc-targeting-assistant__send" id="rwgc-targeting-send-btn" aria-label="<?php esc_attr_e( 'Send', 'reactwoo-geocore' ); ?>">
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
							</button>
						</div>
						<div class="rwgc-targeting-assistant__composer-meta">
							<span class="rwgc-targeting-assistant__detecting rwgc-is-hidden" id="rwgc-targeting-detecting"><?php esc_html_e( 'Detecting…', 'reactwoo-geocore' ); ?></span>
							<button type="button" class="button-link rwgc-targeting-assistant__reset" id="rwgc-targeting-reset-btn"><?php esc_html_e( 'Start over', 'reactwoo-geocore' ); ?></button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<aside class="rwgc-targeting-assistant__setup" aria-label="<?php esc_attr_e( 'Setup summary', 'reactwoo-geocore' ); ?>">
			<div class="rwgc-geo-setup-panel">
				<h2 class="rwgc-geo-setup-panel__title"><?php esc_html_e( 'Setup', 'reactwoo-geocore' ); ?></h2>
				<p class="rwgc-geo-setup-panel__empty" id="rwgc-targeting-setup-empty"><?php esc_html_e( 'No setup yet.', 'reactwoo-geocore' ); ?></p>
				<p class="description rwgc-geo-setup-panel__hint" id="rwgc-targeting-setup-hint"><?php esc_html_e( 'Your interpreted targeting plan will appear here.', 'reactwoo-geocore' ); ?></p>
				<div class="rwgc-geo-setup-plan rwgc-is-hidden" id="rwgc-targeting-setup-plan"></div>
				<dl class="rwgc-geo-setup-rows rwgc-is-hidden" id="rwgc-targeting-summary">
					<div class="rwgc-geo-setup-row"><dt><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></dt><dd data-key="status" class="is-empty">—</dd></div>
				</dl>
			</div>
		</aside>
	</div>

	<div class="rwgc-targeting-assistant__debug rwgc-is-hidden" id="rwgc-targeting-debug-panel" hidden>
		<div class="rwgc-targeting-assistant__debug-inner">
			<div class="rwgc-targeting-assistant__debug-head">
				<h3><?php esc_html_e( 'Detection debug', 'reactwoo-geocore' ); ?></h3>
				<button type="button" class="button-link" id="rwgc-targeting-debug-close"><?php esc_html_e( 'Close', 'reactwoo-geocore' ); ?></button>
			</div>
			<pre id="rwgc-targeting-debug-body" class="rwgc-targeting-assistant__debug-body"></pre>
		</div>
	</div>

	<div class="rwgc-targeting-assistant__edit rwgc-is-hidden" id="rwgc-targeting-edit-panel" hidden>
		<div class="rwgc-targeting-assistant__edit-inner">
			<h3><?php esc_html_e( 'Edit setup', 'reactwoo-geocore' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Adjust detected values before confirming.', 'reactwoo-geocore' ); ?></p>
			<div id="rwgc-targeting-edit-fields"></div>
			<p>
				<button type="button" class="button button-primary" id="rwgc-targeting-edit-save"><?php esc_html_e( 'Apply changes', 'reactwoo-geocore' ); ?></button>
				<button type="button" class="button" id="rwgc-targeting-edit-cancel"><?php esc_html_e( 'Cancel', 'reactwoo-geocore' ); ?></button>
			</p>
		</div>
	</div>
</div>
