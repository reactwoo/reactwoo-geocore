<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle simple actions via query args + nonce.
if ( isset( $_GET['rwgc_action'], $_GET['_wpnonce'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action = sanitize_key( wp_unslash( $_GET['rwgc_action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $action, array( 'ai_health', 'ai_usage' ), true ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwgc_tools_action_' . $action ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'ai_health' === $action ) {
			$result = RWGC_AI_Orchestrator::ai_health();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_ai_health_err', $result->get_error_message(), 'error' );
			} else {
				$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
				$snippet = '';
				if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
					$snippet = wp_json_encode( $result['data'] );
					if ( is_string( $snippet ) && strlen( $snippet ) > 280 ) {
						$snippet = substr( $snippet, 0, 280 ) . '…';
					}
				}
				add_settings_error(
					'rwgc_tools',
					'rwgc_ai_health_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: response JSON or note */
						__( 'AI service reachability: HTTP %1$s. %2$s', 'reactwoo-geocore' ),
						(string) $code,
						$snippet ? $snippet : __( '(empty body)', 'reactwoo-geocore' )
					),
					'updated'
				);
			}
		} elseif ( 'ai_usage' === $action ) {
			$result = RWGC_AI_Orchestrator::get_usage();
			if ( is_wp_error( $result ) ) {
				add_settings_error( 'rwgc_tools', 'rwgc_ai_usage_err', $result->get_error_message(), 'error' );
			} else {
				$http = isset( $result['http_code'] ) ? (int) $result['http_code'] : 0;
				$body = isset( $result['body'] ) ? $result['body'] : null;
				$snippet = is_array( $body ) ? wp_json_encode( $body ) : '';
				if ( is_string( $snippet ) && strlen( $snippet ) > 280 ) {
					$snippet = substr( $snippet, 0, 280 ) . '…';
				}
				add_settings_error(
					'rwgc_tools',
					'rwgc_ai_usage_ok',
					sprintf(
						/* translators: 1: HTTP status code, 2: API JSON or note */
						__( 'ReactWoo API (authenticated) assistant usage: HTTP %1$s. %2$s', 'reactwoo-geocore' ),
						(string) $http,
						$snippet ? $snippet : __( '(empty body)', 'reactwoo-geocore' )
					),
					'updated'
				);
			}
		}
	}
}

settings_errors( 'rwgc_tools' );
?>
<div class="wrap rwgc-wrap">
	<h1><?php esc_html_e( 'Geo Core Tools', 'reactwoo-geocore' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Optional developer diagnostics. MaxMind credentials and visitor tests live under Integrations.', 'reactwoo-geocore' ); ?></p>
	<?php if ( ! function_exists( 'rwgc_uses_platform_shell' ) || ! rwgc_uses_platform_shell() ) : ?>
		<?php RWGC_Admin::render_inner_nav( 'rwgc-tools' ); ?>
	<?php endif; ?>

	<?php if ( function_exists( 'rwgc_get_maxmind_admin_url' ) ) : ?>
	<div class="notice notice-info inline" style="margin:12px 0;">
		<p>
			<?php esc_html_e( 'MaxMind credentials and the country database are managed under Integrations.', 'reactwoo-geocore' ); ?>
			<a class="button button-secondary" href="<?php echo esc_url( rwgc_get_maxmind_admin_url() ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'Open MaxMind integration', 'reactwoo-geocore' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

	<details class="rwgc-tech-ref-details">
		<summary class="rwgc-tech-ref-details__summary"><?php esc_html_e( 'Advanced: Developer tools', 'reactwoo-geocore' ); ?></summary>
		<div class="rwgc-card rwgc-card--full rwgc-tech-ref-details__inner">
		<h2><?php esc_html_e( 'ReactWoo AI (optional)', 'reactwoo-geocore' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Core Geo does not require a ReactWoo product license. These actions only help verify optional AI endpoints after you add a license under Settings → ReactWoo API.', 'reactwoo-geocore' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'ai_health' ), 'rwgc_tools_action_ai_health' ) ); ?>" class="button">
				<?php esc_html_e( 'Test AI service reachability', 'reactwoo-geocore' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'rwgc_action', 'ai_usage' ), 'rwgc_tools_action_ai_usage' ) ); ?>" class="button">
				<?php esc_html_e( 'Test license & API (assistant usage)', 'reactwoo-geocore' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-settings' ) ); ?>" class="button">
				<?php esc_html_e( 'Open ReactWoo API settings', 'reactwoo-geocore' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'Reachability calls the public health endpoint (no license). The assistant usage test exchanges your license for a token and calls a protected route — use it to confirm credentials.', 'reactwoo-geocore' ); ?>
		</p>
		</div>
	</details>
</div>

