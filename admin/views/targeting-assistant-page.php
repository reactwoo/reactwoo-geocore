<?php
/**
 * Targeting Assistant — guided chat-style entry.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$variant_wizard_url = function_exists( 'rw_geo_app_url' )
	? add_query_arg( array(), admin_url( 'admin.php?page=rwgc-workflow-variant' ) )
	: admin_url( 'admin.php?page=rwgc-workflow-variant' );
$rules_create_url   = function_exists( 'rw_geo_app_url' )
	? rw_geo_app_url( 'targeting', 'rwgc-visibility-rules' ) . '&rwgc_edit=new'
	: admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=new' );
$experiences_url    = class_exists( 'RWGO_Admin', false )
	? admin_url( 'admin.php?page=rwgo-dashboard' )
	: admin_url( 'admin.php?page=rwgc-experiences-hub' );
$exp_status         = class_exists( 'RWGC_Capability_Registry', false )
	? RWGC_Capability_Registry::get_status( 'experiences' )
	: array( 'state' => 'not_installed' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-targeting-assistant-wrap">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Targeting', 'reactwoo-geocore' ),
		__( 'Decide who sees what — pages, content, and sections.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( class_exists( 'RWGC_Admin_Targeting_Nav', false ) ) {
		RWGC_Admin_Targeting_Nav::render_tabs( 'rwgc-targeting-hub' );
	}
	?>

	<div class="rwgc-targeting-assistant" id="rwgc-targeting-assistant" data-variant-url="<?php echo esc_url( $variant_wizard_url ); ?>" data-rules-url="<?php echo esc_url( $rules_create_url ); ?>" data-experiences-url="<?php echo esc_url( $experiences_url ); ?>" data-exp-state="<?php echo esc_attr( (string) ( $exp_status['state'] ?? '' ) ); ?>">
		<div class="rwgc-targeting-assistant__main">
			<div class="rwgc-targeting-assistant__thread" id="rwgc-targeting-thread" aria-live="polite"></div>
			<div class="rwgc-targeting-assistant__step" id="rwgc-targeting-step"></div>
		</div>
		<aside class="rwgc-targeting-assistant__summary" aria-label="<?php esc_attr_e( 'Summary', 'reactwoo-geocore' ); ?>">
			<h2 class="rwgc-targeting-assistant__summary-title"><?php esc_html_e( 'Summary', 'reactwoo-geocore' ); ?></h2>
			<dl class="rwgc-targeting-assistant__summary-list" id="rwgc-targeting-summary">
				<div><dt><?php esc_html_e( 'Goal', 'reactwoo-geocore' ); ?></dt><dd data-key="goal">—</dd></div>
				<div><dt><?php esc_html_e( 'Type', 'reactwoo-geocore' ); ?></dt><dd data-key="type">—</dd></div>
				<div><dt><?php esc_html_e( 'Availability', 'reactwoo-geocore' ); ?></dt><dd data-key="availability">—</dd></div>
			</dl>
		</aside>
	</div>

	<div class="rwgc-targeting-assistant__lock-panel rwgc-is-hidden" id="rwgc-targeting-lock-panel" hidden>
		<h3 id="rwgc-targeting-lock-title"></h3>
		<p id="rwgc-targeting-lock-body"></p>
		<p class="rwgc-targeting-assistant__lock-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-addons' ) ); ?>" id="rwgc-targeting-lock-learn"><?php esc_html_e( 'Learn more', 'reactwoo-geocore' ); ?></a>
			<button type="button" class="button" id="rwgc-targeting-lock-back"><?php esc_html_e( 'Back', 'reactwoo-geocore' ); ?></button>
		</p>
	</div>
</div>
