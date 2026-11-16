<?php
/**
 * One A/B test: all-time totals, the last 30 days, and keep-arm actions.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_ab_result = isset( $rwgc_ab_result ) && is_array( $rwgc_ab_result ) ? $rwgc_ab_result : array();
$rwgc_ab_notice = isset( $rwgc_ab_notice ) ? sanitize_key( (string) $rwgc_ab_notice ) : '';
$rwgc_ab_id     = isset( $rwgc_ab_result['id'] ) ? absint( $rwgc_ab_result['id'] ) : 0;
$rwgc_ab_title  = isset( $rwgc_ab_result['title'] ) ? (string) $rwgc_ab_result['title'] : '';
$rwgc_ab_status = isset( $rwgc_ab_result['status'] ) ? (string) $rwgc_ab_result['status'] : '';
$rwgc_ab_kept   = isset( $rwgc_ab_result['kept'] ) ? sanitize_key( (string) $rwgc_ab_result['kept'] ) : '';
$rwgc_ab_verdict = isset( $rwgc_ab_result['verdict'] ) ? (string) $rwgc_ab_result['verdict'] : '';
$rwgc_ab_uplift = array_key_exists( 'uplift', $rwgc_ab_result ) ? $rwgc_ab_result['uplift'] : null;
$rwgc_ab_sig    = isset( $rwgc_ab_result['significance'] ) && is_array( $rwgc_ab_result['significance'] ) ? $rwgc_ab_result['significance'] : null;
$rwgc_ab_arms   = isset( $rwgc_ab_result['arms'] ) && is_array( $rwgc_ab_result['arms'] ) ? $rwgc_ab_result['arms'] : array();
$rwgc_ab_daily  = isset( $rwgc_ab_result['daily'] ) && is_array( $rwgc_ab_result['daily'] ) ? $rwgc_ab_result['daily'] : array();
$rwgc_ab_back  = admin_url( 'admin.php?page=rwgc-ab-tests' );
$rwgc_ab_ended = class_exists( 'RWGC_AB_Tests', false ) && RWGC_AB_Tests::STATUS_ENDED === $rwgc_ab_status;

$rwgc_ab_confirm_a = __( 'Show variant A to everyone? Counting stops, and you can switch the variant later from this screen.', 'reactwoo-geocore' );
$rwgc_ab_confirm_b = __( 'Show variant B to everyone? Counting stops, and you can switch the variant later from this screen.', 'reactwoo-geocore' );

/**
 * @param mixed $arm Arm totals.
 * @return array{views:int,conversions:int,rate:float}
 */
$rwgc_ab_arm = static function ( $arm ) {
	$arm = is_array( $arm ) ? $arm : array();
	return array(
		'views'       => isset( $arm['views'] ) ? absint( $arm['views'] ) : 0,
		'conversions' => isset( $arm['conversions'] ) ? absint( $arm['conversions'] ) : 0,
		'rate'        => isset( $arm['rate'] ) ? (float) $arm['rate'] : 0.0,
	);
};
$rwgc_ab_a = $rwgc_ab_arm( isset( $rwgc_ab_arms['a'] ) ? $rwgc_ab_arms['a'] : array() );
$rwgc_ab_b = $rwgc_ab_arm( isset( $rwgc_ab_arms['b'] ) ? $rwgc_ab_arms['b'] : array() );

$rwgc_ab_rate_text = static function ( $rate ) {
	return sprintf( '%.2f%%', (float) $rate * 100 );
};
$rwgc_ab_uplift_text = '—';
if ( is_float( $rwgc_ab_uplift ) || is_int( $rwgc_ab_uplift ) ) {
	$rwgc_ab_uplift_text = sprintf( '%+.1f%%', (float) $rwgc_ab_uplift * 100 );
}
$rwgc_ab_p_text = '—';
$rwgc_ab_z_text = '—';
if ( is_array( $rwgc_ab_sig ) && isset( $rwgc_ab_sig['p_value'] ) ) {
	$rwgc_ab_p = (float) $rwgc_ab_sig['p_value'];
	$rwgc_ab_p_text = ( $rwgc_ab_p > 0.0 && $rwgc_ab_p < 0.0001 ) ? '< 0.0001' : sprintf( '%.4f', $rwgc_ab_p );
	$rwgc_ab_z_text = isset( $rwgc_ab_sig['z'] ) ? sprintf( '%.4f', (float) $rwgc_ab_sig['z'] ) : '—';
}

$rwgc_ab_max = 1;
foreach ( $rwgc_ab_daily as $rwgc_ab_point ) {
	if ( ! is_array( $rwgc_ab_point ) ) {
		continue;
	}
	$rwgc_ab_point_a = isset( $rwgc_ab_point['a']['views'] ) ? absint( $rwgc_ab_point['a']['views'] ) : 0;
	$rwgc_ab_point_b = isset( $rwgc_ab_point['b']['views'] ) ? absint( $rwgc_ab_point['b']['views'] ) : 0;
	$rwgc_ab_max     = max( $rwgc_ab_max, $rwgc_ab_point_a, $rwgc_ab_point_b );
}
?>
<div class="wrap rwgc-wrap rwgc-suite">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			$rwgc_ab_title,
			__( 'All-time views, conversions, and whether B is ahead of A. The chart is the last 30 days and is not a saved date range.', 'reactwoo-geocore' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( $rwgc_ab_title ); ?></h1>
		<p><?php esc_html_e( 'All-time views, conversions, and whether B is ahead of A. The chart is the last 30 days and is not a saved date range.', 'reactwoo-geocore' ); ?></p>
	<?php endif; ?>

	<p><a href="<?php echo esc_url( $rwgc_ab_back ); ?>"><?php esc_html_e( 'Back to A/B tests', 'reactwoo-geocore' ); ?></a></p>

	<?php if ( 'kept-a' === $rwgc_ab_notice || 'kept-b' === $rwgc_ab_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			echo esc_html(
				'kept-b' === $rwgc_ab_notice
					? __( 'Visitors now see variant B. Counting has stopped. You can switch the variant from this screen.', 'reactwoo-geocore' )
					: __( 'Visitors now see variant A. Counting has stopped. You can switch the variant from this screen.', 'reactwoo-geocore' )
			);
			?>
		</p></div>
	<?php elseif ( 'notfound' === $rwgc_ab_notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Test not found.', 'reactwoo-geocore' ); ?></p></div>
	<?php endif; ?>

	<p>
		<strong><?php esc_html_e( 'Verdict', 'reactwoo-geocore' ); ?>:</strong>
		<?php echo esc_html( $rwgc_ab_verdict ); ?>
		<?php if ( $rwgc_ab_ended ) : ?>
			<span class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: variant A or B */
						__( 'Ended. Visitors see variant %s.', 'reactwoo-geocore' ),
						'b' === $rwgc_ab_kept ? 'B' : 'A'
					)
				);
				?>
			</span>
		<?php endif; ?>
	</p>

	<table class="widefat striped">
		<caption class="screen-reader-text"><?php esc_html_e( 'All-time results', 'reactwoo-geocore' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Arm', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Views', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Conversions', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Conversion rate', 'reactwoo-geocore' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'A', 'reactwoo-geocore' ); ?></th>
				<td><?php echo esc_html( (string) $rwgc_ab_a['views'] ); ?></td>
				<td><?php echo esc_html( (string) $rwgc_ab_a['conversions'] ); ?></td>
				<td><?php echo esc_html( $rwgc_ab_rate_text( $rwgc_ab_a['rate'] ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'B', 'reactwoo-geocore' ); ?></th>
				<td><?php echo esc_html( (string) $rwgc_ab_b['views'] ); ?></td>
				<td><?php echo esc_html( (string) $rwgc_ab_b['conversions'] ); ?></td>
				<td><?php echo esc_html( $rwgc_ab_rate_text( $rwgc_ab_b['rate'] ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<ul>
		<li><?php echo esc_html( sprintf( /* translators: %s: relative uplift of B over A */ __( 'Uplift of B over A: %s', 'reactwoo-geocore' ), $rwgc_ab_uplift_text ) ); ?></li>
		<li><?php echo esc_html( sprintf( /* translators: %s: two-proportion z */ __( 'z: %s', 'reactwoo-geocore' ), $rwgc_ab_z_text ) ); ?></li>
		<li><?php echo esc_html( sprintf( /* translators: %s: two-sided p-value */ __( 'p-value: %s', 'reactwoo-geocore' ), $rwgc_ab_p_text ) ); ?></li>
	</ul>

	<h2><?php esc_html_e( 'Last 30 days', 'reactwoo-geocore' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Views per day. Days with no counts are shown as zero. Totals above include every stored day.', 'reactwoo-geocore' ); ?></p>
	<?php
	$rwgc_ab_chart_h = 120;
	$rwgc_ab_bar_w   = 8;
	$rwgc_ab_gap     = 4;
	$rwgc_ab_group   = ( $rwgc_ab_bar_w * 2 ) + $rwgc_ab_gap + 6;
	$rwgc_ab_width   = max( 1, count( $rwgc_ab_daily ) ) * $rwgc_ab_group;
	?>
	<svg class="rwgc-ab-chart" width="<?php echo esc_attr( (string) $rwgc_ab_width ); ?>" height="<?php echo esc_attr( (string) ( $rwgc_ab_chart_h + 28 ) ); ?>" viewBox="0 0 <?php echo esc_attr( (string) $rwgc_ab_width ); ?> <?php echo esc_attr( (string) ( $rwgc_ab_chart_h + 28 ) ); ?>" role="img" aria-hidden="true" focusable="false">
		<?php
		$rwgc_ab_index = 0;
		foreach ( $rwgc_ab_daily as $rwgc_ab_point ) :
			if ( ! is_array( $rwgc_ab_point ) ) {
				continue;
			}
			$rwgc_ab_views_a = isset( $rwgc_ab_point['a']['views'] ) ? absint( $rwgc_ab_point['a']['views'] ) : 0;
			$rwgc_ab_views_b = isset( $rwgc_ab_point['b']['views'] ) ? absint( $rwgc_ab_point['b']['views'] ) : 0;
			$rwgc_ab_ha      = (int) round( ( $rwgc_ab_views_a / $rwgc_ab_max ) * $rwgc_ab_chart_h );
			$rwgc_ab_hb      = (int) round( ( $rwgc_ab_views_b / $rwgc_ab_max ) * $rwgc_ab_chart_h );
			$rwgc_ab_x       = $rwgc_ab_index * $rwgc_ab_group;
			?>
			<rect x="<?php echo esc_attr( (string) $rwgc_ab_x ); ?>" y="<?php echo esc_attr( (string) ( $rwgc_ab_chart_h - $rwgc_ab_ha ) ); ?>" width="<?php echo esc_attr( (string) $rwgc_ab_bar_w ); ?>" height="<?php echo esc_attr( (string) $rwgc_ab_ha ); ?>" fill="#2271b1"></rect>
			<rect x="<?php echo esc_attr( (string) ( $rwgc_ab_x + $rwgc_ab_bar_w ) ); ?>" y="<?php echo esc_attr( (string) ( $rwgc_ab_chart_h - $rwgc_ab_hb ) ); ?>" width="<?php echo esc_attr( (string) $rwgc_ab_bar_w ); ?>" height="<?php echo esc_attr( (string) $rwgc_ab_hb ); ?>" fill="#dba617"></rect>
			<?php
			++$rwgc_ab_index;
		endforeach;
		?>
		<text x="0" y="<?php echo esc_attr( (string) ( $rwgc_ab_chart_h + 18 ) ); ?>" fill="#2271b1" font-size="12"><?php echo esc_html( __( 'A', 'reactwoo-geocore' ) ); ?></text>
		<text x="24" y="<?php echo esc_attr( (string) ( $rwgc_ab_chart_h + 18 ) ); ?>" fill="#dba617" font-size="12"><?php echo esc_html( __( 'B', 'reactwoo-geocore' ) ); ?></text>
	</svg>

	<table class="widefat striped">
		<caption><?php esc_html_e( 'Daily counts for the last 30 days', 'reactwoo-geocore' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Day', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'A views', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'A conversions', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'B views', 'reactwoo-geocore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'B conversions', 'reactwoo-geocore' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rwgc_ab_daily as $rwgc_ab_point ) : ?>
				<?php
				if ( ! is_array( $rwgc_ab_point ) ) {
					continue;
				}
				$rwgc_ab_day = isset( $rwgc_ab_point['day'] ) ? (string) $rwgc_ab_point['day'] : '';
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $rwgc_ab_day ); ?></th>
					<td><?php echo esc_html( (string) ( isset( $rwgc_ab_point['a']['views'] ) ? absint( $rwgc_ab_point['a']['views'] ) : 0 ) ); ?></td>
					<td><?php echo esc_html( (string) ( isset( $rwgc_ab_point['a']['conversions'] ) ? absint( $rwgc_ab_point['a']['conversions'] ) : 0 ) ); ?></td>
					<td><?php echo esc_html( (string) ( isset( $rwgc_ab_point['b']['views'] ) ? absint( $rwgc_ab_point['b']['views'] ) : 0 ) ); ?></td>
					<td><?php echo esc_html( (string) ( isset( $rwgc_ab_point['b']['conversions'] ) ? absint( $rwgc_ab_point['b']['conversions'] ) : 0 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p>
		<?php if ( class_exists( 'RWGC_AB_Admin', false ) ) : ?>
			<?php
			RWGC_AB_Admin::render_end_button( $rwgc_ab_id, 'a', __( 'End and keep A', 'reactwoo-geocore' ), 'button button-secondary', $rwgc_ab_confirm_a );
			RWGC_AB_Admin::render_end_button( $rwgc_ab_id, 'b', __( 'End and keep B', 'reactwoo-geocore' ), 'button button-primary', $rwgc_ab_confirm_b );
			?>
		<?php endif; ?>
	</p>
</div>
