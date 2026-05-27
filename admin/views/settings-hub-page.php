<?php
/**
 * Settings section hub.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = isset( $cards ) && is_array( $cards ) ? $cards : array();
$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php
	RWGC_Admin_UI::render_page_header(
		$rwgc_platform_shell
			? __( 'Settings', 'reactwoo-geocore' )
			: __( 'Geo settings', 'reactwoo-geocore' ),
		__( 'Configure GeoCore and satellites.', 'reactwoo-geocore' )
	);
	?>
	<?php if ( ! $rwgc_platform_shell ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-settings-hub' ); ?>
	<?php else : ?>
		<p class="description rwgc-settings-hub__intro">
			<?php esc_html_e( 'Core detection, tools, and satellite plugin settings are grouped below.', 'reactwoo-geocore' ); ?>
		</p>
	<?php endif; ?>

	<?php
	if ( $rwgc_platform_shell && class_exists( 'RWGC_Admin_Settings_Nav', false ) ) {
		$core_links = RWGC_Admin_Settings_Nav::get_core_settings_quick_links();
		if ( ! empty( $core_links ) ) {
			echo '<div class="rwgc-card rwgc-settings-hub__core-links">';
			echo '<h2 class="rwgc-settings-hub__core-title">' . esc_html__( 'Geo Core settings', 'reactwoo-geocore' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'General detection, tools, and add-ons for the free engine.', 'reactwoo-geocore' ) . '</p>';
			echo '<p class="rwgc-settings-hub__link-row">';
			foreach ( $core_links as $i => $link ) {
				if ( $i > 0 ) {
					echo ' <span class="rwgc-settings-hub__sep" aria-hidden="true">·</span> ';
				}
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( (string) $link['url'] ),
					esc_html( (string) $link['label'] )
				);
			}
			echo '</p></div>';
		}
	}

	if ( class_exists( 'RWGC_Admin_UI', false ) ) {
		echo '<h2 class="rwgc-settings-hub__providers-title">' . esc_html__( 'Provider settings', 'reactwoo-geocore' ) . '</h2>';
		RWGC_Admin_UI::render_section_hub_cards(
			$cards,
			array(
				'empty_title' => __( 'No settings screens', 'reactwoo-geocore' ),
				'empty_body'  => __( 'Core settings will appear here once routes are registered.', 'reactwoo-geocore' ),
			)
		);
	}
	?>
</div>
