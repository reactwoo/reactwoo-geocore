<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'ReactWoo Geo Core', 'reactwoo-geocore' ); ?></h1>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Status', 'reactwoo-geocore' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Geo enabled', 'reactwoo-geocore' ); ?>:</strong> <?php echo RWGC_Settings::get( 'enabled', 1 ) ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'MaxMind license key', 'reactwoo-geocore' ); ?>:</strong> <?php echo ! empty( $settings['maxmind_license_key'] ) ? esc_html__( 'Configured', 'reactwoo-geocore' ) : esc_html__( 'Not set', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'DB path', 'reactwoo-geocore' ); ?>:</strong> <code><?php echo esc_html( $status['path'] ); ?></code></li>
				<li><strong><?php esc_html_e( 'DB exists', 'reactwoo-geocore' ); ?>:</strong> <?php echo $status['exists'] ? esc_html__( 'Yes', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'DB last updated', 'reactwoo-geocore' ); ?>:</strong> <?php echo $status['last_updated'] ? esc_html( $status['last_updated'] ) : esc_html__( 'Unknown', 'reactwoo-geocore' ); ?></li>
				<li><strong><?php esc_html_e( 'DB stale', 'reactwoo-geocore' ); ?>:</strong> <?php echo $status['is_stale'] ? esc_html__( 'Possibly', 'reactwoo-geocore' ) : esc_html__( 'No', 'reactwoo-geocore' ); ?></li>
			</ul>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Current visitor', 'reactwoo-geocore' ); ?></h2>
			<?php if ( ! empty( $data ) ) : ?>
				<ul>
					<li><strong><?php esc_html_e( 'IP', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['ip'] ); ?></li>
					<li><strong><?php esc_html_e( 'Country', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['country_code'] . ' – ' . $data['country_name'] ); ?></li>
					<li><strong><?php esc_html_e( 'Region', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['region'] ); ?></li>
					<li><strong><?php esc_html_e( 'City', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['city'] ); ?></li>
					<li><strong><?php esc_html_e( 'Currency', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['currency'] ); ?></li>
					<li><strong><?php esc_html_e( 'Source', 'reactwoo-geocore' ); ?>:</strong> <?php echo esc_html( $data['source'] ); ?><?php echo ! empty( $data['cached'] ) ? ' (' . esc_html__( 'cached', 'reactwoo-geocore' ) . ')' : ''; ?></li>
				</ul>
			<?php else : ?>
				<p><?php esc_html_e( 'No geo data available yet.', 'reactwoo-geocore' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Quick actions', 'reactwoo-geocore' ); ?></h2>
			<p><?php esc_html_e( 'Use the Tools tab to update the MaxMind database, clear cache, and test your setup.', 'reactwoo-geocore' ); ?></p>
			<p><?php esc_html_e( 'Use the Add-ons tab to discover GeoElementor and WHMCS Bridge integrations.', 'reactwoo-geocore' ); ?></p>
		</div>
	</div>
</div>

