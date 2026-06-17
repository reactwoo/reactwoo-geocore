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
