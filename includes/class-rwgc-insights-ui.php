<?php
/**
 * Reusable Insights dashboard UI components.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard-style admin components for the Capability + Intelligence Centre.
 */
class RWGC_Insights_UI {

	/**
	 * Status badge for capability / provider states.
	 *
	 * @param string $status Provider status slug.
	 * @return void
	 */
	public static function render_status_badge( $status ) {
		self::render_short_badge( $status );
	}

	/**
	 * Short status badge for dashboard cards.
	 *
	 * @param string $status Provider status slug.
	 * @return void
	 */
	public static function render_short_badge( $status ) {
		$status = sanitize_key( (string) $status );
		$map    = array(
			'active'              => array( 'label' => __( 'Active', 'reactwoo-geocore' ), 'tone' => 'success' ),
			'inactive'            => array( 'label' => __( 'Needs setup', 'reactwoo-geocore' ), 'tone' => 'warning' ),
			'missing'             => array( 'label' => __( 'Not installed', 'reactwoo-geocore' ), 'tone' => 'neutral' ),
			'requires_license'    => array( 'label' => __( 'Locked', 'reactwoo-geocore' ), 'tone' => 'locked' ),
			'requires_dependency' => array( 'label' => __( 'Locked', 'reactwoo-geocore' ), 'tone' => 'locked' ),
			'planned'             => array( 'label' => __( 'Planned', 'reactwoo-geocore' ), 'tone' => 'neutral' ),
			'no_data'             => array( 'label' => __( 'No data', 'reactwoo-geocore' ), 'tone' => 'warning' ),
		);
		$row = isset( $map[ $status ] ) ? $map[ $status ] : array( 'label' => $status, 'tone' => 'neutral' );
		echo '<span class="rwgc-geo-badge rwgc-geo-badge--' . esc_attr( $row['tone'] ) . '" title="' . esc_attr( self::badge_tooltip( $status ) ) . '">' . esc_html( $row['label'] ) . '</span>';
	}

	/**
	 * @param string $status Status slug.
	 * @return string
	 */
	private static function badge_tooltip( $status ) {
		$tips = array(
			'active'           => __( 'Working on this site.', 'reactwoo-geocore' ),
			'inactive'         => __( 'Installed but needs configuration.', 'reactwoo-geocore' ),
			'no_data'          => __( 'No data has been collected yet.', 'reactwoo-geocore' ),
			'requires_license' => __( 'Requires licence activation.', 'reactwoo-geocore' ),
			'missing'          => __( 'Product is not installed.', 'reactwoo-geocore' ),
		);
		return isset( $tips[ $status ] ) ? $tips[ $status ] : '';
	}

	/**
	 * Satellite dashboard card — one primary CTA + View details link.
	 *
	 * @param array<string, mixed> $provider Provider row.
	 * @return void
	 */
	public static function render_satellite_dashboard_card( array $provider ) {
		if ( empty( $provider['label'] ) ) {
			return;
		}

		$status  = isset( $provider['status'] ) ? (string) $provider['status'] : 'inactive';
		$metric  = self::get_dashboard_metric_line( $provider );
		$cta     = self::get_dashboard_primary_action( $provider );
		$details = class_exists( 'RWGC_Insights', false )
			? RWGC_Insights::get_provider_details_url( (string) $provider['id'] )
			: '';
		$summary = ! empty( $provider['summary'] ) ? wp_trim_words( (string) $provider['summary'], 14, '…' ) : '';

		echo '<article class="rwgc-insights-dash-card rwgc-insights-dash-card--' . esc_attr( sanitize_key( $status ) ) . '">';
		echo '<header class="rwgc-insights-dash-card__head">';
		echo '<h3 class="rwgc-insights-dash-card__title">' . esc_html( (string) $provider['label'] ) . '</h3>';
		self::render_short_badge( $status );
		echo '</header>';
		if ( '' !== $summary ) {
			echo '<p class="rwgc-insights-dash-card__summary">' . esc_html( $summary ) . '</p>';
		}
		if ( '' !== $metric ) {
			echo '<p class="rwgc-insights-dash-card__metric">' . esc_html( $metric ) . '</p>';
		}
		echo '<footer class="rwgc-insights-dash-card__foot">';
		if ( is_array( $cta ) && ! empty( $cta['url'] ) && ! empty( $cta['label'] ) ) {
			echo '<a class="button button-primary rwgc-geo-btn" href="' . esc_url( (string) $cta['url'] ) . '">' . esc_html( (string) $cta['label'] ) . '</a>';
		}
		if ( '' !== $details ) {
			echo '<a class="rwgc-geo-link rwgc-insights-dash-card__details" href="' . esc_url( $details ) . '">' . esc_html__( 'View details', 'reactwoo-geocore' ) . '</a>';
		}
		echo '</footer></article>';
	}

	/**
	 * @param array<string, mixed> $provider Provider row.
	 * @return string
	 */
	public static function get_dashboard_metric_line( array $provider ) {
		$id = isset( $provider['id'] ) ? (string) $provider['id'] : '';
		if ( ! empty( $provider['metrics'] ) && is_array( $provider['metrics'] ) ) {
			foreach ( $provider['metrics'] as $metric ) {
				if ( ! is_array( $metric ) || empty( $metric['label'] ) ) {
					continue;
				}
				$value = isset( $metric['value'] ) ? (string) $metric['value'] : '';
				if ( '' === $value || '—' === $value ) {
					continue;
				}
				return sprintf(
					/* translators: 1: value, 2: label */
					__( '%1$s %2$s', 'reactwoo-geocore' ),
					$value,
					strtolower( (string) $metric['label'] )
				);
			}
		}
		if ( 'geo-ai' === $id ) {
			return __( 'Sync not enabled', 'reactwoo-geocore' );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $provider Provider row.
	 * @return array<string, mixed>
	 */
	public static function get_dashboard_primary_action( array $provider ) {
		$id = isset( $provider['id'] ) ? (string) $provider['id'] : '';
		if ( 'geo-core' === $id ) {
			return array(
				'url'     => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'targeting', 'rwgc-targeting-hub' ) : admin_url( 'admin.php?page=rwgc-targeting-hub' ),
				'label'   => __( 'Manage', 'reactwoo-geocore' ),
				'primary' => true,
			);
		}

		$action = self::get_provider_primary_action( $provider );
		if ( empty( $action['label'] ) ) {
			return $action;
		}
		$short = array(
			'Manage rules'           => __( 'Manage', 'reactwoo-geocore' ),
			'MaxMind settings'       => __( 'Configure', 'reactwoo-geocore' ),
			'Run site audit'         => __( 'Enable sync', 'reactwoo-geocore' ),
			'Open experiments'       => __( 'View', 'reactwoo-geocore' ),
			'Open experiment reports'=> __( 'View reports', 'reactwoo-geocore' ),
			'Create your first rule' => __( 'Create', 'reactwoo-geocore' ),
		);
		$label = (string) $action['label'];
		if ( isset( $short[ $label ] ) ) {
			$action['label'] = $short[ $label ];
		}
		return $action;
	}

	/**
	 * Compact numbered top actions list.
	 *
	 * @param array<int, array<string, mixed>> $recommendations Rows.
	 * @return void
	 */
	public static function render_top_actions( array $recommendations ) {
		echo '<section class="rwgc-insights-top-actions" aria-labelledby="rwgc-insights-top-actions-title">';
		echo '<h2 id="rwgc-insights-top-actions-title" class="rwgc-insights-section-title">' . esc_html__( 'Top actions', 'reactwoo-geocore' ) . '</h2>';
		if ( empty( $recommendations ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing urgent right now.', 'reactwoo-geocore' ) . '</p></section>';
			return;
		}
		echo '<ol class="rwgc-insights-top-actions__list">';
		$n = 0;
		foreach ( $recommendations as $rec ) {
			if ( ! is_array( $rec ) || empty( $rec['label'] ) || $n >= 3 ) {
				continue;
			}
			++$n;
			$provider = isset( $rec['provider_label'] ) ? (string) $rec['provider_label'] : '';
			echo '<li class="rwgc-insights-top-actions__item">';
			echo '<div class="rwgc-insights-top-actions__text">';
			if ( '' !== $provider ) {
				echo '<span class="rwgc-insights-top-actions__product">' . esc_html( $provider ) . '</span> ';
			}
			echo '<strong>' . esc_html( (string) $rec['label'] ) . '</strong>';
			if ( ! empty( $rec['reason'] ) ) {
				echo '<span class="rwgc-insights-top-actions__reason"> — ' . esc_html( (string) $rec['reason'] ) . '</span>';
			}
			echo '</div></li>';
		}
		echo '</ol></section>';
	}

	/**
	 * Status badge for capability / provider states (legacy full labels).
	 *
	 * @param string $status Provider status slug.
	 * @return void
	 */
	public static function render_status_badge_legacy( $status ) {
		$status = sanitize_key( (string) $status );
		$labels = array(
			'active'              => __( 'Active', 'reactwoo-geocore' ),
			'inactive'            => __( 'Installed but inactive', 'reactwoo-geocore' ),
			'missing'             => __( 'Not installed', 'reactwoo-geocore' ),
			'requires_license'    => __( 'Requires licence', 'reactwoo-geocore' ),
			'requires_dependency' => __( 'Requires dependency', 'reactwoo-geocore' ),
			'planned'             => __( 'Planned', 'reactwoo-geocore' ),
			'no_data'             => __( 'No data yet', 'reactwoo-geocore' ),
		);
		$variants = array(
			'active'              => 'success',
			'inactive'            => 'neutral',
			'missing'             => 'neutral',
			'requires_license'    => 'warning',
			'requires_dependency' => 'warning',
			'planned'             => 'info',
			'no_data'             => 'neutral',
		);

		$label   = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		$variant = isset( $variants[ $status ] ) ? $variants[ $status ] : 'neutral';

		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_badge( $label, $variant );
			return;
		}

		printf(
			'<span class="rwgc-insights-badge rwgc-insights-badge--%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Compact horizontal health chips (Capability Map).
	 *
	 * @param array<int, array<string, mixed>> $chips Chip rows.
	 * @return void
	 */
	public static function render_health_chips( array $chips ) {
		if ( empty( $chips ) ) {
			return;
		}
		echo '<div class="rwgc-insights-health-chips" role="list">';
		foreach ( $chips as $chip ) {
			if ( ! is_array( $chip ) || empty( $chip['label'] ) ) {
				continue;
			}
			$tone  = isset( $chip['tone'] ) ? sanitize_key( (string) $chip['tone'] ) : 'neutral';
			$value = isset( $chip['value'] ) ? (string) $chip['value'] : '';
			echo '<div class="rwgc-insights-health-chip rwgc-insights-health-chip--' . esc_attr( $tone ) . '" role="listitem">';
			echo '<span class="rwgc-insights-health-chip__label">' . esc_html( (string) $chip['label'] ) . '</span>';
			echo '<span class="rwgc-insights-health-chip__value">' . esc_html( $value ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Short product card for the Capability Map grid.
	 *
	 * @param array<string, mixed> $provider Normalized provider row.
	 * @return void
	 */
	public static function render_compact_product_card( array $provider ) {
		if ( empty( $provider['label'] ) ) {
			return;
		}

		$status   = isset( $provider['status'] ) ? (string) $provider['status'] : 'inactive';
		$metric   = self::get_provider_primary_metric( $provider );
		$warning  = self::get_provider_primary_warning( $provider );
		$cta      = self::get_provider_primary_action( $provider );
		$details  = class_exists( 'RWGC_Insights', false )
			? RWGC_Insights::get_provider_details_url( (string) $provider['id'] )
			: '';

		echo '<article class="rwgc-insights-product-card rwgc-insights-product-card--' . esc_attr( sanitize_key( $status ) ) . '" role="listitem">';
		echo '<header class="rwgc-insights-product-card__header">';
		echo '<h3 class="rwgc-insights-product-card__title">' . esc_html( (string) $provider['label'] ) . '</h3>';
		self::render_status_badge( $status );
		echo '</header>';

		if ( ! empty( $provider['summary'] ) ) {
			$summary = wp_trim_words( (string) $provider['summary'], 18, '…' );
			echo '<p class="rwgc-insights-product-card__summary">' . esc_html( $summary ) . '</p>';
		}

		if ( '' !== $metric ) {
			echo '<p class="rwgc-insights-product-card__metric">' . esc_html( $metric ) . '</p>';
		}

		if ( '' !== $warning ) {
			echo '<p class="rwgc-insights-product-card__warning">' . esc_html( $warning ) . '</p>';
		}

		echo '<footer class="rwgc-insights-product-card__footer">';
		if ( is_array( $cta ) && ! empty( $cta['url'] ) && ! empty( $cta['label'] ) ) {
			$primary = ! empty( $cta['primary'] ) ? ' button-primary' : '';
			echo '<a class="button' . esc_attr( $primary ) . '" href="' . esc_url( (string) $cta['url'] ) . '">' . esc_html( (string) $cta['label'] ) . '</a>';
		}
		if ( '' !== $details ) {
			echo '<a class="rwgc-insights-product-card__details" href="' . esc_url( $details ) . '">' . esc_html__( 'View details', 'reactwoo-geocore' ) . '</a>';
		}
		echo '</footer>';
		echo '</article>';
	}

	/**
	 * @param array<string, mixed> $provider Provider row.
	 * @return string
	 */
	public static function get_provider_primary_metric( array $provider ) {
		if ( ! empty( $provider['metrics'] ) && is_array( $provider['metrics'] ) ) {
			foreach ( $provider['metrics'] as $metric ) {
				if ( ! is_array( $metric ) || empty( $metric['label'] ) ) {
					continue;
				}
				$value = isset( $metric['value'] ) ? (string) $metric['value'] : '';
				if ( '' === $value || '—' === $value ) {
					continue;
				}
				return sprintf(
					/* translators: 1: metric value, 2: metric label */
					__( '%1$s · %2$s', 'reactwoo-geocore' ),
					$value,
					(string) $metric['label']
				);
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $provider Provider row.
	 * @return string
	 */
	public static function get_provider_primary_warning( array $provider ) {
		if ( ! empty( $provider['missing_setup'] ) && is_array( $provider['missing_setup'] ) ) {
			return (string) $provider['missing_setup'][0];
		}
		if ( 'missing' === ( $provider['status'] ?? '' ) ) {
			return __( 'Not installed', 'reactwoo-geocore' );
		}
		if ( 'requires_license' === ( $provider['status'] ?? '' ) ) {
			return __( 'Requires licence activation', 'reactwoo-geocore' );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $provider Provider row.
	 * @return array<string, mixed>
	 */
	public static function get_provider_primary_action( array $provider ) {
		if ( empty( $provider['actions'] ) || ! is_array( $provider['actions'] ) ) {
			return array();
		}
		foreach ( $provider['actions'] as $action ) {
			if ( ! is_array( $action ) || empty( $action['url'] ) || empty( $action['label'] ) ) {
				continue;
			}
			if ( ! empty( $action['primary'] ) ) {
				return $action;
			}
		}
		$first = $provider['actions'][0];
		return is_array( $first ) ? $first : array();
	}

	/**
	 * Top opportunities preview with link to full tab.
	 *
	 * @param array<int, array<string, mixed>> $recommendations Recommendation rows.
	 * @param string                           $view_all_url     URL for full list.
	 * @return void
	 */
	public static function render_opportunities_preview( array $recommendations, $view_all_url = '' ) {
		echo '<section class="rwgc-insights-opportunities" aria-labelledby="rwgc-insights-opportunities-title">';
		echo '<div class="rwgc-insights-opportunities__head">';
		echo '<h2 id="rwgc-insights-opportunities-title" class="rwgc-insights-section-title">' . esc_html__( 'Top opportunities', 'reactwoo-geocore' ) . '</h2>';
		if ( '' !== $view_all_url ) {
			echo '<a class="rwgc-insights-opportunities__more" href="' . esc_url( $view_all_url ) . '">' . esc_html__( 'View all', 'reactwoo-geocore' ) . '</a>';
		}
		echo '</div>';

		if ( empty( $recommendations ) ) {
			echo '<p class="description">' . esc_html__( 'Your capability map looks healthy. Check individual products for optional improvements.', 'reactwoo-geocore' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<div class="rwgc-insights-recommendations rwgc-insights-recommendations--compact">';
		foreach ( $recommendations as $rec ) {
			if ( ! is_array( $rec ) ) {
				continue;
			}
			self::render_recommendation_card( $rec );
		}
		echo '</div></section>';
	}

	/**
	 * Health summary metric card.
	 *
	 * @param array<string, mixed> $card Card row.
	 * @return void
	 */
	public static function render_metric_card( array $card ) {
		if ( empty( $card['label'] ) ) {
			return;
		}
		$value = isset( $card['value'] ) ? (string) $card['value'] : '';
		$tone  = isset( $card['tone'] ) ? sanitize_key( (string) $card['tone'] ) : 'default';
		$hint  = isset( $card['hint'] ) ? (string) $card['hint'] : '';

		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_stat_card( (string) $card['label'], $value, array( 'tone' => $tone, 'hint' => $hint ) );
			return;
		}

		echo '<div class="rwgc-insights-metric">';
		echo '<div class="rwgc-insights-metric__label">' . esc_html( (string) $card['label'] ) . '</div>';
		echo '<div class="rwgc-insights-metric__value">' . esc_html( $value ) . '</div>';
		if ( '' !== $hint ) {
			echo '<div class="rwgc-insights-metric__hint">' . esc_html( $hint ) . '</div>';
		}
		echo '</div>';
	}

	/**
	 * Capability feature list inside a satellite card.
	 *
	 * @param array<int, array<string, mixed>> $capabilities Capability rows.
	 * @return void
	 */
	public static function render_capability_list( array $capabilities ) {
		if ( empty( $capabilities ) ) {
			return;
		}
		echo '<ul class="rwgc-insights-capability-list">';
		foreach ( $capabilities as $cap ) {
			if ( ! is_array( $cap ) || empty( $cap['label'] ) ) {
				continue;
			}
			$cap_status = isset( $cap['status'] ) ? sanitize_key( (string) $cap['status'] ) : 'inactive';
			echo '<li class="rwgc-insights-capability-list__item rwgc-insights-capability-list__item--' . esc_attr( $cap_status ) . '">';
			echo '<span class="rwgc-insights-capability-list__label">' . esc_html( (string) $cap['label'] ) . '</span>';
			self::render_capability_chip( $cap_status );
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * @param string $status Capability status slug.
	 * @return void
	 */
	private static function render_capability_chip( $status ) {
		$labels = array(
			'active'   => __( 'Active', 'reactwoo-geocore' ),
			'inactive' => __( 'Inactive', 'reactwoo-geocore' ),
			'planned'  => __( 'Planned', 'reactwoo-geocore' ),
			'missing'  => __( 'Missing', 'reactwoo-geocore' ),
		);
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		echo '<span class="rwgc-insights-capability-list__chip">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Recommendation row.
	 *
	 * @param array<string, mixed> $rec Recommendation row.
	 * @return void
	 */
	public static function render_recommendation_card( array $rec ) {
		if ( empty( $rec['label'] ) ) {
			return;
		}
		echo '<article class="rwgc-insights-recommendation">';
		echo '<h3 class="rwgc-insights-recommendation__title">' . esc_html( (string) $rec['label'] ) . '</h3>';
		if ( ! empty( $rec['provider_label'] ) ) {
			echo '<p class="rwgc-insights-recommendation__provider">' . esc_html( (string) $rec['provider_label'] ) . '</p>';
		}
		if ( ! empty( $rec['reason'] ) ) {
			echo '<p class="rwgc-insights-recommendation__reason">' . esc_html( (string) $rec['reason'] ) . '</p>';
		}
		echo '</article>';
	}

	/**
	 * Setup checklist panel.
	 *
	 * @param array<int, array<string, mixed>> $items Checklist rows.
	 * @return void
	 */
	public static function render_setup_checklist( array $items ) {
		if ( empty( $items ) ) {
			return;
		}
		echo '<ul class="rwgc-insights-checklist">';
		foreach ( $items as $item ) {
			if ( empty( $item['label'] ) ) {
				continue;
			}
			$done = ! empty( $item['done'] );
			$url  = isset( $item['url'] ) ? (string) $item['url'] : '';
			echo '<li class="rwgc-insights-checklist__item' . ( $done ? ' is-done' : ' is-pending' ) . '">';
			echo '<span class="rwgc-insights-checklist__mark" aria-hidden="true">' . ( $done ? '✓' : '○' ) . '</span>';
			if ( ! $done && '' !== $url ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $item['label'] ) . '</a>';
			} else {
				echo '<span>' . esc_html( (string) $item['label'] ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Satellite capability card.
	 *
	 * @param array<string, mixed> $provider Normalized provider row.
	 * @return void
	 */
	public static function render_satellite_card( array $provider ) {
		if ( empty( $provider['label'] ) ) {
			return;
		}

		$status = isset( $provider['status'] ) ? (string) $provider['status'] : 'inactive';
		echo '<article class="rwgc-insights-satellite rwgc-insights-satellite--' . esc_attr( sanitize_key( $status ) ) . '">';
		echo '<header class="rwgc-insights-satellite__header">';
		echo '<h3 class="rwgc-insights-satellite__title">' . esc_html( (string) $provider['label'] ) . '</h3>';
		self::render_status_badge( $status );
		echo '</header>';

		if ( ! empty( $provider['summary'] ) ) {
			echo '<p class="rwgc-insights-satellite__summary">' . esc_html( (string) $provider['summary'] ) . '</p>';
		}

		if ( ! empty( $provider['metrics'] ) && is_array( $provider['metrics'] ) ) {
			echo '<div class="rwgc-insights-satellite__metrics">';
			foreach ( $provider['metrics'] as $metric ) {
				if ( ! is_array( $metric ) || empty( $metric['label'] ) ) {
					continue;
				}
				$value = isset( $metric['value'] ) ? (string) $metric['value'] : '';
				echo '<div class="rwgc-insights-satellite__metric">';
				echo '<span class="rwgc-insights-satellite__metric-label">' . esc_html( (string) $metric['label'] ) . '</span>';
				echo '<span class="rwgc-insights-satellite__metric-value">' . esc_html( $value ) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}

		if ( ! empty( $provider['capabilities'] ) ) {
			echo '<div class="rwgc-insights-satellite__capabilities">';
			echo '<h4 class="rwgc-insights-satellite__subhead">' . esc_html__( 'Available features', 'reactwoo-geocore' ) . '</h4>';
			self::render_capability_list( $provider['capabilities'] );
			echo '</div>';
		}

		if ( ! empty( $provider['missing_setup'] ) && is_array( $provider['missing_setup'] ) ) {
			echo '<div class="rwgc-insights-satellite__missing">';
			echo '<h4 class="rwgc-insights-satellite__subhead">' . esc_html__( 'Missing setup', 'reactwoo-geocore' ) . '</h4>';
			echo '<ul class="rwgc-insights-missing-list">';
			foreach ( $provider['missing_setup'] as $missing ) {
				echo '<li>' . esc_html( (string) $missing ) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		if ( ! empty( $provider['empty_state'] ) && is_array( $provider['empty_state'] ) && ! empty( $provider['empty_state']['title'] ) ) {
			self::render_provider_empty_state( $provider['empty_state'], $provider['actions'] );
		}

		if ( ! empty( $provider['actions'] ) && is_array( $provider['actions'] ) ) {
			echo '<div class="rwgc-insights-satellite__actions">';
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_quick_actions( $provider['actions'] );
			}
			echo '</div>';
		}

		echo '</article>';
	}

	/**
	 * @param array<string, mixed>              $empty   Empty state row.
	 * @param array<int, array<string, mixed>> $actions CTA actions.
	 * @return void
	 */
	private static function render_provider_empty_state( array $empty, $actions = array() ) {
		$type = isset( $empty['type'] ) ? sanitize_key( (string) $empty['type'] ) : 'not_configured';
		$titles = array(
			'not_installed'   => __( 'Not installed', 'reactwoo-geocore' ),
			'not_configured'  => __( 'Installed but not configured', 'reactwoo-geocore' ),
			'no_data'         => __( 'Configured but no data', 'reactwoo-geocore' ),
		);
		$type_label = isset( $titles[ $type ] ) ? $titles[ $type ] : '';

		echo '<div class="rwgc-insights-empty rwgc-insights-empty--' . esc_attr( $type ) . '">';
		if ( '' !== $type_label ) {
			echo '<p class="rwgc-insights-empty__type">' . esc_html( $type_label ) . '</p>';
		}
		echo '<p class="rwgc-insights-empty__title">' . esc_html( (string) $empty['title'] ) . '</p>';
		if ( ! empty( $empty['body'] ) ) {
			echo '<p class="rwgc-insights-empty__body">' . esc_html( (string) $empty['body'] ) . '</p>';
		}
		if ( is_array( $actions ) && ! empty( $actions ) && class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_quick_actions( $actions );
		}
		echo '</div>';
	}

	/**
	 * Recent activity list (suite activity providers).
	 *
	 * @param array<int, array<string, mixed>> $activity Activity rows.
	 * @return void
	 */
	public static function render_recent_activity( array $activity ) {
		echo '<section class="rwgc-insights-activity" aria-labelledby="rwgc-insights-activity-title">';
		echo '<h2 id="rwgc-insights-activity-title" class="rwgc-insights-section-title">' . esc_html__( 'Recent activity', 'reactwoo-geocore' ) . '</h2>';
		if ( empty( $activity ) ) {
			echo '<p class="description">' . esc_html__( 'When you create rules, experiments, or sync site intelligence, activity will appear here.', 'reactwoo-geocore' ) . '</p>';
			echo '</section>';
			return;
		}
		echo '<ul class="rwgc-insights-activity__list">';
		foreach ( array_slice( $activity, 0, 8 ) as $item ) {
			if ( empty( $item['payload']['title'] ) ) {
				continue;
			}
			$url = isset( $item['payload']['url'] ) ? (string) $item['payload']['url'] : '';
			echo '<li>';
			if ( '' !== $url ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $item['payload']['title'] ) . '</a>';
			} else {
				echo esc_html( $item['payload']['title'] );
			}
			if ( ! empty( $item['site_time'] ) ) {
				echo ' <span class="description">— ' . esc_html( (string) $item['site_time'] ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul></section>';
	}
}
