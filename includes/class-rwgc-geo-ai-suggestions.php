<?php
/**
 * Inline Geo AI suggestions for Targeting, Experiences, and Insights.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compact actionable Geo AI hints (not a floating chatbot).
 */
class RWGC_Geo_AI_Suggestions {

	/**
	 * @param string $context targeting|insights|experiences.
	 * @return array<string, mixed>|null
	 */
	public static function get_for_context( $context ) {
		$context = sanitize_key( (string) $context );
		$row     = null;
		if ( 'targeting' === $context ) {
			$row = self::get_targeting_suggestion();
		} elseif ( 'insights' === $context ) {
			$row = self::get_insights_suggestion();
		} elseif ( 'experiences' === $context ) {
			$row = self::get_experiences_suggestion();
		}

		/**
		 * @param array<string, mixed>|null $row     Suggestion row or null.
		 * @param string                    $context Screen context.
		 */
		return apply_filters( 'rwgc_geo_ai_suggestion', $row, $context );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function get_targeting_suggestion() {
		if ( ! class_exists( 'RWGC_Variant_Manager', false ) ) {
			return null;
		}
		$rows = RWGC_Variant_Manager::get_routing_overview_rows( 20 );
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['variant'] ) ) {
				continue;
			}
			$title = isset( $row['master_title'] ) ? (string) $row['master_title'] : __( 'a page', 'reactwoo-geocore' );
			$mid   = isset( $row['master_id'] ) ? (int) $row['master_id'] : 0;
			return array(
				'title'   => __( 'Geo AI suggestion', 'reactwoo-geocore' ),
				'body'    => sprintf(
					/* translators: %s: page title */
					__( '%s has no country variant yet. Create one for your top visitor countries.', 'reactwoo-geocore' ),
					$title
				),
				'cta'     => __( 'Create variant', 'reactwoo-geocore' ),
				'cta_url' => admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_master_page_id=' . $mid ),
			);
		}

		if ( class_exists( 'RWGC_Insights', false ) && RWGC_Insights::count_published_visibility_rules() <= 0 ) {
			return array(
				'title'   => __( 'Geo AI suggestion', 'reactwoo-geocore' ),
				'body'    => __( 'You have geo detection but no content rules yet. Show or hide a banner by country.', 'reactwoo-geocore' ),
				'cta'     => __( 'Create rule', 'reactwoo-geocore' ),
				'cta_url' => admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=new' ),
			);
		}

		return null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function get_insights_suggestion() {
		if ( ! class_exists( 'RWGC_Insights', false ) ) {
			return null;
		}
		$pending = RWGC_Insights::count_setup_tasks_remaining();
		if ( $pending <= 0 ) {
			return null;
		}
		return array(
			'title'   => __( 'Geo AI', 'reactwoo-geocore' ),
			'body'    => sprintf(
				/* translators: %d: gap count */
				_n( 'I found %d setup gap.', 'I found %d setup gaps.', $pending, 'reactwoo-geocore' ),
				$pending
			),
			'cta'     => __( 'Review actions', 'reactwoo-geocore' ),
			'cta_url' => class_exists( 'RWGC_Insights_Nav', false )
				? RWGC_Insights_Nav::get_url( 'rwgc-insights-readiness' )
				: admin_url( 'admin.php?page=rwgc-insights-readiness' ),
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function get_experiences_suggestion() {
		if ( ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			return null;
		}
		foreach ( RWGO_Experiment_Repository::query_experiments() as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$config = RWGO_Experiment_Repository::get_config( (int) $post->ID );
			$goals  = isset( $config['goals'] ) && is_array( $config['goals'] ) ? $config['goals'] : array();
			if ( empty( $goals ) && in_array( (string) ( $config['status'] ?? '' ), array( 'running', 'active' ), true ) ) {
				return array(
					'title'   => __( 'Geo AI suggestion', 'reactwoo-geocore' ),
					'body'    => sprintf(
						/* translators: %s: experiment name */
						__( '%s has no goal attached. Add one so the test can pick a winner.', 'reactwoo-geocore' ),
						$post->post_title
					),
					'cta'     => __( 'Add goal', 'reactwoo-geocore' ),
					'cta_url' => admin_url( 'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . (int) $post->ID ),
				);
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed>|null $suggestion Suggestion row.
	 * @return void
	 */
	public static function render_inline( $suggestion ) {
		if ( ! is_array( $suggestion ) || empty( $suggestion['body'] ) ) {
			return;
		}
		$title = isset( $suggestion['title'] ) ? (string) $suggestion['title'] : __( 'Geo AI', 'reactwoo-geocore' );
		echo '<div class="rwgc-geo-ai-hint">';
		echo '<div class="rwgc-geo-ai-hint__label">' . esc_html( $title ) . '</div>';
		echo '<p class="rwgc-geo-ai-hint__body">' . esc_html( (string) $suggestion['body'] ) . '</p>';
		if ( ! empty( $suggestion['cta_url'] ) && ! empty( $suggestion['cta'] ) ) {
			echo '<a class="button button-primary rwgc-geo-btn" href="' . esc_url( (string) $suggestion['cta_url'] ) . '">' . esc_html( (string) $suggestion['cta'] ) . '</a>';
		}
		echo '</div>';
	}
}
