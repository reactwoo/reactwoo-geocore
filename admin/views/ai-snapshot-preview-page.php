<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_snapshot      = isset( $rwgc_ai_snapshot ) && is_array( $rwgc_ai_snapshot ) ? $rwgc_ai_snapshot : array();
$rwgc_sync_status   = isset( $rwgc_ai_sync_status ) && is_array( $rwgc_ai_sync_status ) ? $rwgc_ai_sync_status : array();
$rwgc_section_counts = array(
	'rules'              => isset( $rwgc_snapshot['rules'] ) && is_array( $rwgc_snapshot['rules'] ) ? count( $rwgc_snapshot['rules'] ) : 0,
	'variants'           => isset( $rwgc_snapshot['variants'] ) && is_array( $rwgc_snapshot['variants'] ) ? count( $rwgc_snapshot['variants'] ) : 0,
	'popups'             => isset( $rwgc_snapshot['popups'] ) && is_array( $rwgc_snapshot['popups'] ) ? count( $rwgc_snapshot['popups'] ) : 0,
	'forms'              => isset( $rwgc_snapshot['forms'] ) && is_array( $rwgc_snapshot['forms'] ) ? count( $rwgc_snapshot['forms'] ) : 0,
	'relationships'      => isset( $rwgc_snapshot['relationships'] ) && is_array( $rwgc_snapshot['relationships'] ) ? count( $rwgc_snapshot['relationships'] ) : 0,
	'target_providers'   => isset( $rwgc_snapshot['target_providers'] ) && is_array( $rwgc_snapshot['target_providers'] ) ? count( $rwgc_snapshot['target_providers'] ) : 0,
);
$rwgc_hash = isset( $rwgc_snapshot['snapshot_hash'] ) ? (string) $rwgc_snapshot['snapshot_hash'] : '';
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'AI Data Snapshot', 'reactwoo-geocore' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Compact site intelligence exported for Geo AI cloud workflows. No page content, Elementor JSON, or personal data is included.', 'reactwoo-geocore' ); ?>
	</p>

	<div class="rwgc-card" style="margin:16px 0;padding:16px;border:1px solid #c3c4c7;background:#fff;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Summary', 'reactwoo-geocore' ); ?></h2>
		<p>
			<strong><?php esc_html_e( 'Schema version', 'reactwoo-geocore' ); ?>:</strong>
			<?php echo esc_html( isset( $rwgc_snapshot['schema_version'] ) ? (string) $rwgc_snapshot['schema_version'] : '' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Generated (GMT)', 'reactwoo-geocore' ); ?>:</strong>
			<?php echo esc_html( isset( $rwgc_snapshot['generated_at_gmt'] ) ? (string) $rwgc_snapshot['generated_at_gmt'] : '' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Snapshot hash', 'reactwoo-geocore' ); ?>:</strong>
			<code><?php echo esc_html( $rwgc_hash ? substr( $rwgc_hash, 0, 16 ) . '…' : '' ); ?></code>
		</p>
		<ul style="list-style:disc;margin-left:20px;">
			<?php foreach ( $rwgc_section_counts as $rwgc_key => $rwgc_count ) : ?>
				<li>
					<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $rwgc_key ) ) ); ?>:</strong>
					<?php echo esc_html( (string) $rwgc_count ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php if ( ! empty( $rwgc_sync_status['last_built_at_gmt'] ) ) : ?>
	<div class="rwgc-card" style="margin:16px 0;padding:16px;border:1px solid #c3c4c7;background:#fff;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Local sync status', 'reactwoo-geocore' ); ?></h2>
		<p>
			<strong><?php esc_html_e( 'Last built', 'reactwoo-geocore' ); ?>:</strong>
			<?php echo esc_html( (string) $rwgc_sync_status['last_built_at_gmt'] ); ?>
		</p>
		<?php if ( ! empty( $rwgc_sync_status['last_synced_at_gmt'] ) ) : ?>
			<p>
				<strong><?php esc_html_e( 'Last cloud sync', 'reactwoo-geocore' ); ?>:</strong>
				<?php echo esc_html( (string) $rwgc_sync_status['last_synced_at_gmt'] ); ?>
				(<?php echo esc_html( (string) ( $rwgc_sync_status['last_sync_status'] ?? '' ) ); ?>)
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Cloud sync is owned by Geo AI when licensed.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Full snapshot JSON (read-only)', 'reactwoo-geocore' ); ?></summary>
		<pre class="rwgc-code-block" style="max-height:32em;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;"><?php echo esc_html( wp_json_encode( $rwgc_snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</details>
</div>
