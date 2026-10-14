<?php
/**
 * Targeting → Advanced tools.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_links = array(
	array(
		'label'       => __( 'Rule builder', 'reactwoo-geocore' ),
		'description' => __( 'Manual conditions, nested logic, and JSON playground.', 'reactwoo-geocore' ),
		'url'         => function_exists( 'rwgc_app_url' ) ? rwgc_app_url( 'targeting', 'rwgc-target-types' ) : admin_url( 'admin.php?page=rwgc-target-types' ),
	),
	array(
		'label'       => __( 'Audiences', 'reactwoo-geocore' ),
		'description' => __( 'Saved visitor groups for Pro targeting.', 'reactwoo-geocore' ),
		'url'         => function_exists( 'rwgc_app_url' ) ? rwgc_app_url( 'targeting', 'rwgc-targeting-audiences' ) : admin_url( 'admin.php?page=rwgc-targeting-audiences' ),
		'capability'  => 'advanced_rules',
	),
	array(
		'label'       => __( 'Campaigns', 'reactwoo-geocore' ),
		'description' => __( 'Campaign references for Pro targeting.', 'reactwoo-geocore' ),
		'url'         => function_exists( 'rwgc_app_url' ) ? rwgc_app_url( 'targeting', 'rwgc-targeting-campaigns' ) : admin_url( 'admin.php?page=rwgc-targeting-campaigns' ),
		'capability'  => 'advanced_rules',
	),
);
$rwgc_platform_shell = function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell();
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-targeting-advanced">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Advanced', 'reactwoo-geocore' ),
		__( 'For complex rules, nested logic, priority control, and debugging.', 'reactwoo-geocore' )
	);
	?>
	<?php
	if ( ! $rwgc_platform_shell && class_exists( 'RWGC_Admin_Targeting_Nav', false ) ) {
		RWGC_Admin_Targeting_Nav::render_tabs( 'rwgc-targeting-advanced' );
	}
	?>

	<div class="rwgc-targeting-advanced__grid">
		<?php foreach ( $rwgc_links as $rwgc_link ) : ?>
			<div class="rwgc-card rwgc-targeting-advanced__card">
				<h3 class="rwgc-targeting-advanced__title">
					<?php echo esc_html( (string) $rwgc_link['label'] ); ?>
					<?php
					if ( ! empty( $rwgc_link['capability'] ) && class_exists( 'RWGC_Capability_Registry', false ) ) {
						RWGC_Capability_Registry::render_badge( (string) $rwgc_link['capability'] );
					}
					?>
				</h3>
				<p class="description"><?php echo esc_html( (string) $rwgc_link['description'] ); ?></p>
				<p><a class="button" href="<?php echo esc_url( (string) $rwgc_link['url'] ); ?>"><?php esc_html_e( 'Open', 'reactwoo-geocore' ); ?></a></p>
			</div>
		<?php endforeach; ?>
	</div>
</div>
