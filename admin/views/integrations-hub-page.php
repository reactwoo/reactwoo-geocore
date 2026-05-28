<?php
/**
 * Integrations section hub.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards                 = isset( $cards ) && is_array( $cards ) ? $cards : array();
$rwgc_platform_shell   = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
$rwgc_sync_snapshot    = class_exists( 'RWGC_Platform_Sync_Status', false ) ? RWGC_Platform_Sync_Status::get_snapshot() : array();
$rwgc_integrations     = class_exists( 'RWGC_Platform_Integrations', false ) ? RWGC_Platform_Integrations::get_items() : array();
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		$rwgc_platform_shell
			? __( 'Integrations', 'reactwoo-geocore' )
			: __( 'Geo integrations', 'reactwoo-geocore' ),
		__( 'Connect analytics, ads, APIs, commerce, and content builders.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-integrations-hub' ); ?>
	<?php else : ?>
		<p class="description rwgc-integrations-hub__intro">
			<?php esc_html_e( 'Choose an integration category. Each area only shows the providers and settings that belong there.', 'reactwoo-geocore' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php RWGC_Admin_UI::render_sync_status_card( $rwgc_sync_snapshot ); ?>
	<?php endif; ?>

	<?php if ( ! empty( $cards ) && class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_section_hub_cards(
			$cards,
			array(
				'empty_title' => __( 'No integration categories', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Install GeoCore Pro or a builder plugin to add integrations.', 'reactwoo-geocore' ),
			)
		);
		?>
	<?php endif; ?>
</div>
