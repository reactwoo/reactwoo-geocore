<?php
/**
 * Page versions — master / local version relationships (Geo Core free routing).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$overview_rows = isset( $overview_rows ) && is_array( $overview_rows ) ? $overview_rows : array();

// Build a unified rules list so implemented popup/section/page rules are visible in one screen.
$rule_rows = array();

// 1) Geo Core page-version routing rules.
foreach ( $overview_rows as $row ) {
	$condition = ! empty( $row['variant']['country_iso2'] ) ? (string) $row['variant']['country_iso2'] : __( 'Default visitor', 'reactwoo-geocore' );
	$target    = ! empty( $row['variant']['variant_title'] ) ? (string) $row['variant']['variant_title'] : __( 'Default page', 'reactwoo-geocore' );
	$action_url = admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_master_page_id=' . (int) $row['master_id'] );

	$rule_rows[] = array(
		'rule'       => sprintf( __( 'Route %s', 'reactwoo-geocore' ), (string) $row['master_title'] ),
		'condition'  => $condition,
		'action'     => __( 'Route to page version', 'reactwoo-geocore' ),
		'target'     => $target,
		'status'     => __( 'Active', 'reactwoo-geocore' ),
		'action_url' => $action_url,
		'action_label' => __( 'Edit rule', 'reactwoo-geocore' ),
	);
}

// 2) GeoElementor/Geo rules CPT (if available).
if ( post_type_exists( 'geo_rule' ) ) {
	$geo_rules = get_posts(
		array(
			'post_type'      => 'geo_rule',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	foreach ( $geo_rules as $rule_post ) {
		$target_type = (string) get_post_meta( (int) $rule_post->ID, 'egp_target_type', true );
		$target_id   = (int) get_post_meta( (int) $rule_post->ID, 'egp_target_id', true );
		$countries   = get_post_meta( (int) $rule_post->ID, 'egp_countries', true );
		$is_active   = '1' === (string) get_post_meta( (int) $rule_post->ID, 'egp_active', true ) || 'publish' === $rule_post->post_status;

		$country_label = __( 'Any visitor', 'reactwoo-geocore' );
		if ( is_array( $countries ) && ! empty( $countries ) ) {
			$country_label = implode( ', ', array_map( 'strval', $countries ) );
		}

		$target_label = '';
		if ( $target_id > 0 ) {
			$target_post = get_post( $target_id );
			if ( $target_post ) {
				$target_label = (string) $target_post->post_title;
			}
		}
		if ( '' === $target_label ) {
			$target_label = $target_type ? ucfirst( $target_type ) : __( 'Content target', 'reactwoo-geocore' );
		}

		$rule_rows[] = array(
			'rule'         => (string) $rule_post->post_title,
			'condition'    => $country_label,
			'action'       => __( 'Show / Hide', 'reactwoo-geocore' ),
			'target'       => $target_label,
			'status'       => $is_active ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
			'action_url'   => get_edit_post_link( (int) $rule_post->ID, 'raw' ),
			'action_label' => __( 'Edit rule', 'reactwoo-geocore' ),
		);
	}
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwgc-suite-shell">
	<?php
	RWGC_Admin_UI::render_page_header(
		__( 'Rules / Page Versions', 'reactwoo-geocore' ),
		__( 'Create and manage rules that show, hide, redirect, or route page versions for the right visitors.', 'reactwoo-geocore' )
	);
	?>
	<?php RWGC_Admin::render_inner_nav( 'rwgc-suite-variants' ); ?>

	<p class="rwgc-suite-shell__intro">
		<?php esc_html_e( 'Use plain-English rules: "When a visitor matches conditions, show an action on a target."', 'reactwoo-geocore' ); ?>
	</p>

	<div class="rwgc-grid">
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Create a geo rule', 'reactwoo-geocore' ); ?></h2>
			<ol class="rwgc-steps">
				<li><?php esc_html_e( 'Name the rule', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Choose visitor conditions', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Choose action: Show / Hide / Redirect / Route', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Choose target', 'reactwoo-geocore' ); ?></li>
				<li><?php esc_html_e( 'Review and activate', 'reactwoo-geocore' ); ?></li>
			</ol>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-workflow-variant' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Create Geo Rule', 'reactwoo-geocore' ); ?></a></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Rule sentence preview', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'When a visitor matches [condition], then [action] [target].', 'reactwoo-geocore' ); ?></p>
			<p class="description"><?php esc_html_e( 'Example: When a visitor is in the UK, route to the UK homepage version.', 'reactwoo-geocore' ); ?></p>
		</div>
	</div>

	<?php if ( empty( $rule_rows ) ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'No rules found yet. Create your first rule to control what visitors see.', 'reactwoo-geocore' ); ?></p></div>
	<?php else : ?>
		<table class="widefat striped rwgc-suite-variants-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Rule', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Condition', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Target', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geocore' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rule_rows as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $row['rule'] ); ?></strong></td>
						<td><?php echo esc_html( (string) $row['condition'] ); ?></td>
						<td><?php echo esc_html( (string) $row['action'] ); ?></td>
						<td><?php echo esc_html( (string) $row['target'] ); ?></td>
						<td><?php echo esc_html( (string) $row['status'] ); ?></td>
						<td>
							<?php if ( ! empty( $row['action_url'] ) ) : ?>
								<a class="button button-small" href="<?php echo esc_url( (string) $row['action_url'] ); ?>"><?php echo esc_html( (string) $row['action_label'] ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
