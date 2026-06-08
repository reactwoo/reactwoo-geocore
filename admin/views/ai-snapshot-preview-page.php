<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$snapshot      = isset( $rwgc_ai_snapshot ) && is_array( $rwgc_ai_snapshot ) ? $rwgc_ai_snapshot : array();
$sync_status   = isset( $rwgc_ai_sync_status ) && is_array( $rwgc_ai_sync_status ) ? $rwgc_ai_sync_status : array();
$section_counts = array(
	'rules'              => isset( $snapshot['rules'] ) && is_array( $snapshot['rules'] ) ? count( $snapshot['rules'] ) : 0,
	'variants'           => isset( $snapshot['variants'] ) && is_array( $snapshot['variants'] ) ? count( $snapshot['variants'] ) : 0,
	'popups'             => isset( $snapshot['popups'] ) && is_array( $snapshot['popups'] ) ? count( $snapshot['popups'] ) : 0,
	'forms'              => isset( $snapshot['forms'] ) && is_array( $snapshot['forms'] ) ? count( $snapshot['forms'] ) : 0,
	'relationships'      => isset( $snapshot['relationships'] ) && is_array( $snapshot['relationships'] ) ? count( $snapshot['relationships'] ) : 0,
	'target_providers'   => isset( $snapshot['target_providers'] ) && is_array( $snapshot['target_providers'] ) ? count( $snapshot['target_providers'] ) : 0,
);
$hash = isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '';
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
			<?php echo esc_html( isset( $snapshot['schema_version'] ) ? (string) $snapshot['schema_version'] : '' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Generated (GMT)', 'reactwoo-geocore' ); ?>:</strong>
			<?php echo esc_html( isset( $snapshot['generated_at_gmt'] ) ? (string) $snapshot['generated_at_gmt'] : '' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Snapshot hash', 'reactwoo-geocore' ); ?>:</strong>
			<code><?php echo esc_html( $hash ? substr( $hash, 0, 16 ) . '…' : '' ); ?></code>
		</p>
		<ul style="list-style:disc;margin-left:20px;">
			<?php foreach ( $section_counts as $key => $count ) : ?>
				<li>
					<strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?>:</strong>
					<?php echo esc_html( (string) $count ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?php if ( ! empty( $sync_status['last_built_at_gmt'] ) ) : ?>
	<div class="rwgc-card" style="margin:16px 0;padding:16px;border:1px solid #c3c4c7;background:#fff;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Local sync status', 'reactwoo-geocore' ); ?></h2>
		<p>
			<strong><?php esc_html_e( 'Last built', 'reactwoo-geocore' ); ?>:</strong>
			<?php echo esc_html( (string) $sync_status['last_built_at_gmt'] ); ?>
		</p>
		<?php if ( ! empty( $sync_status['last_synced_at_gmt'] ) ) : ?>
			<p>
				<strong><?php esc_html_e( 'Last cloud sync', 'reactwoo-geocore' ); ?>:</strong>
				<?php echo esc_html( (string) $sync_status['last_synced_at_gmt'] ); ?>
				(<?php echo esc_html( (string) ( $sync_status['last_sync_status'] ?? '' ) ); ?>)
			</p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Cloud sync is owned by Geo AI when licensed.', 'reactwoo-geocore' ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Full snapshot JSON (read-only)', 'reactwoo-geocore' ); ?></summary>
		<pre class="rwgc-code-block" style="max-height:32em;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;"><?php echo esc_html( wp_json_encode( $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</details>
</div>
