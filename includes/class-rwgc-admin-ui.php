<?php
/**
 * Shared Geo Suite admin UI helpers (Phase 1 — design system shell).
 *
 * Satellites can reuse these render methods for consistent cards, headers, and badges.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reusable wp-admin components for ReactWoo Geo Core and suite styling.
 */
class RWGC_Admin_UI {

	/**
	 * Whether a plugin is active (by file under wp-content/plugins).
	 *
	 * @param string $plugin_file Relative path, e.g. reactwoo-geo-ai/reactwoo-geo-ai.php.
	 * @return bool
	 */
	public static function is_plugin_active( $plugin_file ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $plugin_file );
	}

	/**
	 * Whether a satellite plugin is active, trying `file` plus optional `alt_files` (e.g. Geo Elementor folder casing).
	 *
	 * @param array<string, mixed> $def Definition from get_suite_satellite_definitions().
	 * @return bool
	 */
	public static function satellite_plugin_is_active( $def ) {
		$paths = array();
		if ( ! empty( $def['file'] ) && is_string( $def['file'] ) ) {
			$paths[] = $def['file'];
		}
		if ( ! empty( $def['alt_files'] ) && is_array( $def['alt_files'] ) ) {
			foreach ( $def['alt_files'] as $af ) {
				if ( is_string( $af ) && '' !== $af ) {
					$paths[] = $af;
				}
			}
		}
		$paths = array_unique( $paths );
		foreach ( $paths as $p ) {
			if ( self::is_plugin_active( $p ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Compact metadata pill (dashboard add-on cards).
	 *
	 * @param string $text    Visible label.
	 * @param string $variant success|danger|neutral|warning.
	 * @return void
	 */
	public static function render_pill( $text, $variant = 'neutral' ) {
		$variant = sanitize_key( $variant );
		if ( ! in_array( $variant, array( 'success', 'danger', 'neutral', 'warning' ), true ) ) {
			$variant = 'neutral';
		}
		printf(
			'<span class="rwgc-pill rwgc-pill--%1$s">%2$s</span>',
			esc_attr( $variant ),
			esc_html( $text )
		);
	}

	/**
	 * Subtle capability provider label (satellite commercial identity without plugin nav).
	 *
	 * @param string $provider_id Provider key, e.g. geo_commerce, geo_ai, geocore_pro.
	 * @param string $label       Optional override label.
	 * @return void
	 */
	public static function render_provider_badge( $provider_id, $label = '' ) {
		$provider_id = sanitize_key( (string) $provider_id );
		if ( '' === $provider_id ) {
			return;
		}

		$labels = array(
			'geo_commerce'  => __( 'Geo Commerce', 'reactwoo-geocore' ),
			'geo_ai'        => __( 'Geo AI', 'reactwoo-geocore' ),
			'geo_optimise'  => __( 'Geo Optimise', 'reactwoo-geocore' ),
			'geo_elementor' => __( 'Elementor integration', 'reactwoo-geocore' ),
			'geocore_pro'   => __( 'GeoCore Pro', 'reactwoo-geocore' ),
		);

		/**
		 * @param array<string, string> $labels Provider id => display label.
		 */
		$labels = apply_filters( 'rwgc_provider_badge_labels', $labels );

		if ( '' === $label ) {
			$label = isset( $labels[ $provider_id ] ) ? $labels[ $provider_id ] : $provider_id;
		}

		printf(
			'<p class="rwgc-provider-badge"><span class="rwgc-provider-badge__label">%1$s</span> <span class="rwgc-provider-badge__name">%2$s</span></p>',
			esc_html__( 'Provided by', 'reactwoo-geocore' ),
			esc_html( $label )
		);
	}

	/**
	 * Capability upsell card (missing satellite or Pro feature).
	 *
	 * @param string $title       Card heading.
	 * @param string $description Supporting copy.
	 * @param string $cta_url     Primary button URL.
	 * @param string $cta_label   Primary button label.
	 * @param string $provider_id Optional provider key for badge.
	 * @return void
	 */
	public static function render_upgrade_card( $title, $description, $cta_url, $cta_label, $provider_id = '' ) {
		if ( '' === trim( (string) $title ) ) {
			return;
		}
		echo '<div class="rwgc-addon-card rwgc-addon-card--upgrade" role="region">';
		echo '<div class="rwgc-addon-card__header">';
		echo '<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons dashicons-star-filled"></span></div>';
		echo '<div class="rwgc-addon-card__heading">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		if ( is_string( $description ) && '' !== $description ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}
		echo '</div></div>';
		if ( is_string( $provider_id ) && '' !== $provider_id ) {
			self::render_provider_badge( $provider_id );
		}
		if ( is_string( $cta_url ) && '' !== $cta_url && is_string( $cta_label ) && '' !== $cta_label ) {
			echo '<div class="rwgc-addon-card__actions">';
			printf(
				'<a class="rwgc-btn rwgc-btn--primary" href="%1$s">%2$s</a>',
				esc_url( $cta_url ),
				esc_html( $cta_label )
			);
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Overview panel: setup progress, sync status, section shortcuts (platform shell).
	 *
	 * @param array<string, mixed> $progress From {@see RWGC_Onboarding::get_setup_progress()}.
	 * @return void
	 */
	public static function render_platform_overview_panel( $progress = array() ) {
		if ( ! function_exists( 'rwgc_uses_platform_shell' ) || ! rwgc_uses_platform_shell() ) {
			return;
		}

		$progress = wp_parse_args(
			is_array( $progress ) ? $progress : array(),
			array(
				'steps'     => array(),
				'completed' => 0,
				'total'     => 0,
				'percent'   => 0,
			)
		);

		$sync = class_exists( 'RWGC_Platform_Sync_Status', false )
			? RWGC_Platform_Sync_Status::get_snapshot()
			: array();

		$section_links = array(
			array(
				'label' => __( 'Targeting', 'reactwoo-geocore' ),
				'url'   => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'targeting', 'rwgc-targeting-hub' ) : admin_url( 'admin.php?page=rwgc-targeting-hub' ),
				'icon'  => 'dashicons-admin-site-alt3',
			),
			array(
				'label' => __( 'Commerce', 'reactwoo-geocore' ),
				'url'   => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'commerce', 'rwgc-commerce-hub' ) : admin_url( 'admin.php?page=rwgc-commerce-hub' ),
				'icon'  => 'dashicons-cart',
			),
			array(
				'label' => __( 'Integrations', 'reactwoo-geocore' ),
				'url'   => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'integrations', 'rwgc-integrations-hub' ) : admin_url( 'admin.php?page=rwgc-integrations-hub' ),
				'icon'  => 'dashicons-admin-plugins',
			),
			array(
				'label' => __( 'Insights', 'reactwoo-geocore' ),
				'url'   => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'insights', 'rwgc-insights-hub' ) : admin_url( 'admin.php?page=rwgc-insights-hub' ),
				'icon'  => 'dashicons-chart-bar',
			),
			array(
				'label' => __( 'Settings', 'reactwoo-geocore' ),
				'url'   => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'settings', 'rwgc-settings-hub' ) : admin_url( 'admin.php?page=rwgc-settings-hub' ),
				'icon'  => 'dashicons-admin-generic',
			),
		);

		/**
		 * Filter section shortcut tiles on the platform Overview panel.
		 *
		 * @param array<int, array{label:string,url:string,icon?:string}> $section_links Links.
		 */
		$section_links = apply_filters( 'rwgc_platform_overview_section_links', $section_links );

		echo '<section class="rwgc-platform-overview" aria-labelledby="rwgc-platform-overview-title">';

		echo '<div class="rwgc-platform-overview__grid">';

		echo '<div class="rwgc-card rwgc-platform-overview__setup">';
		echo '<h2 id="rwgc-platform-overview-title" class="rwgc-platform-overview__title">';
		esc_html_e( 'Platform setup', 'reactwoo-geocore' );
		echo '</h2>';
		if ( (int) $progress['total'] > 0 ) {
			printf(
				'<p class="rwgc-platform-overview__meta"><span class="rwgc-platform-overview__percent">%1$d%%</span> %2$s</p>',
				(int) $progress['percent'],
				esc_html(
					sprintf(
						/* translators: 1: completed steps, 2: total required steps */
						__( '%1$d of %2$d required steps complete', 'reactwoo-geocore' ),
						(int) $progress['completed'],
						(int) $progress['total']
					)
				)
			);
			echo '<div class="rwgc-platform-overview__bar" role="progressbar" aria-valuenow="' . esc_attr( (string) (int) $progress['percent'] ) . '" aria-valuemin="0" aria-valuemax="100">';
			echo '<span class="rwgc-platform-overview__bar-fill" style="width:' . esc_attr( (string) (int) $progress['percent'] ) . '%"></span>';
			echo '</div>';
		}
		if ( is_array( $progress['steps'] ) && ! empty( $progress['steps'] ) ) {
			echo '<ul class="rwgc-platform-overview__steps">';
			foreach ( $progress['steps'] as $step ) {
				if ( empty( $step['label'] ) ) {
					continue;
				}
				$done      = ! empty( $step['done'] );
				$optional  = ! empty( $step['optional'] );
				$item_cls  = 'rwgc-platform-overview__step' . ( $done ? ' is-done' : '' ) . ( $optional ? ' is-optional' : '' );
				$step_url  = isset( $step['url'] ) ? (string) $step['url'] : '';
				$step_hint = isset( $step['hint'] ) ? (string) $step['hint'] : '';
				echo '<li class="' . esc_attr( $item_cls ) . '">';
				echo '<span class="rwgc-platform-overview__step-mark" aria-hidden="true">' . ( $done ? '✓' : '○' ) . '</span>';
				echo '<span class="rwgc-platform-overview__step-body">';
				if ( '' !== $step_url && ! $done ) {
					echo '<a class="rwgc-platform-overview__step-label" href="' . esc_url( $step_url ) . '">' . esc_html( (string) $step['label'] ) . '</a>';
				} else {
					echo '<span class="rwgc-platform-overview__step-label">' . esc_html( (string) $step['label'] ) . '</span>';
				}
				if ( $optional ) {
					echo ' <span class="rwgc-platform-overview__step-tag">' . esc_html__( 'Optional', 'reactwoo-geocore' ) . '</span>';
				}
				if ( '' !== $step_hint ) {
					echo '<span class="rwgc-platform-overview__step-hint">' . esc_html( $step_hint ) . '</span>';
				}
				echo '</span></li>';
			}
			echo '</ul>';
		}
		echo '<p class="rwgc-platform-overview__actions">';
		printf(
			'<a class="rwgc-btn rwgc-btn--primary" href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=rwgc-getting-started' ) ),
			esc_html__( 'Open setup wizard', 'reactwoo-geocore' )
		);
		echo '</p></div>';

		echo '<div class="rwgc-card rwgc-platform-overview__sync">';
		echo '<h2 class="rwgc-platform-overview__title">' . esc_html__( 'Sync & integrations', 'reactwoo-geocore' ) . '</h2>';
		if ( ! empty( $sync['label'] ) ) {
			$variant = isset( $sync['variant'] ) ? sanitize_key( (string) $sync['variant'] ) : 'neutral';
			printf(
				'<p class="rwgc-platform-overview__sync-pill rwgc-platform-overview__sync-pill--%1$s"><span class="dashicons dashicons-update" aria-hidden="true"></span> %2$s</p>',
				esc_attr( $variant ),
				esc_html( (string) $sync['label'] )
			);
		}
		if ( ! empty( $sync['hint'] ) ) {
			echo '<p class="description">' . esc_html( (string) $sync['hint'] ) . '</p>';
		}
		if ( ! empty( $sync['url'] ) ) {
			printf(
				'<p><a class="rwgc-btn rwgc-btn--secondary" href="%1$s">%2$s</a></p>',
				esc_url( (string) $sync['url'] ),
				esc_html__( 'Manage integrations', 'reactwoo-geocore' )
			);
		}
		echo '</div>';

		echo '</div>';

		if ( is_array( $section_links ) && ! empty( $section_links ) ) {
			echo '<div class="rwgc-platform-overview__sections" role="navigation" aria-label="' . esc_attr__( 'Platform sections', 'reactwoo-geocore' ) . '">';
			foreach ( $section_links as $link ) {
				if ( empty( $link['label'] ) || empty( $link['url'] ) ) {
					continue;
				}
				$icon = isset( $link['icon'] ) && is_string( $link['icon'] ) ? $link['icon'] : 'dashicons-arrow-right-alt';
				printf(
					'<a class="rwgc-platform-overview__section-link" href="%1$s"><span class="dashicons %2$s" aria-hidden="true"></span><span>%3$s</span></a>',
					esc_url( (string) $link['url'] ),
					esc_attr( $icon ),
					esc_html( (string) $link['label'] )
				);
			}
			echo '</div>';
		}

		echo '</section>';
	}

	/**
	 * Platform sync snapshot card (Integrations hub / overview).
	 *
	 * @param array<string, mixed> $sync Snapshot from {@see RWGC_Platform_Sync_Status::get_snapshot()}.
	 * @return void
	 */
	public static function render_sync_status_card( $sync ) {
		if ( ! is_array( $sync ) || empty( $sync['label'] ) ) {
			return;
		}
		$variant = isset( $sync['variant'] ) ? sanitize_key( (string) $sync['variant'] ) : 'neutral';
		if ( ! in_array( $variant, array( 'success', 'warning', 'neutral' ), true ) ) {
			$variant = 'neutral';
		}
		echo '<div class="rwgc-card rwgc-sync-status-card">';
		echo '<h2 class="rwgc-sync-status-card__title">' . esc_html__( 'Platform sync', 'reactwoo-geocore' ) . '</h2>';
		printf(
			'<p class="rwgc-sync-status-card__pill rwgc-sync-status-card__pill--%1$s"><span class="dashicons dashicons-update" aria-hidden="true"></span> %2$s</p>',
			esc_attr( $variant ),
			esc_html( (string) $sync['label'] )
		);
		if ( ! empty( $sync['hint'] ) ) {
			echo '<p class="description">' . esc_html( (string) $sync['hint'] ) . '</p>';
		}
		if ( ! empty( $sync['url'] ) ) {
			printf(
				'<p><a class="rwgc-btn rwgc-btn--secondary" href="%1$s">%2$s</a></p>',
				esc_url( (string) $sync['url'] ),
				esc_html__( 'Open integration settings', 'reactwoo-geocore' )
			);
		}
		echo '</div>';
	}

	/**
	 * Integration connection rows (Integrations hub).
	 *
	 * @param array<int, array<string, mixed>> $items Rows from {@see RWGC_Platform_Integrations::get_items()}.
	 * @return void
	 */
	public static function render_integration_status_cards( $items ) {
		if ( ! is_array( $items ) || empty( $items ) ) {
			return;
		}
		echo '<div class="rwgc-integration-status__grid" role="list">';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['label'] ) ) {
				continue;
			}
			$status = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : 'neutral';
			if ( ! in_array( $status, array( 'connected', 'warning', 'neutral' ), true ) ) {
				$status = 'neutral';
			}
			$url = isset( $item['url'] ) ? (string) $item['url'] : '';
			echo '<article class="rwgc-card rwgc-integration-status__card rwgc-integration-status__card--' . esc_attr( $status ) . '" role="listitem">';
			echo '<div class="rwgc-integration-status__header">';
			echo '<h3>' . esc_html( (string) $item['label'] ) . '</h3>';
			if ( ! empty( $item['provider'] ) && class_exists( 'RWGC_Admin_UI', false ) ) {
				self::render_provider_badge( (string) $item['provider'] );
			}
			echo '</div>';
			if ( ! empty( $item['description'] ) ) {
				echo '<p class="description">' . esc_html( (string) $item['description'] ) . '</p>';
			}
			if ( '' !== $url ) {
				printf(
					'<p><a class="rwgc-btn rwgc-btn--secondary" href="%1$s">%2$s</a></p>',
					esc_url( $url ),
					esc_html__( 'Configure', 'reactwoo-geocore' )
				);
			}
			echo '</article>';
		}
		echo '</div>';
	}

	/**
	 * Grid of links to routes within a goal section (Insights / Settings hubs).
	 *
	 * @param array<int, array<string, mixed>> $cards Hub card rows.
	 * @param array<string, mixed>             $args  Optional empty_title, empty_body, class.
	 * @return void
	 */
	public static function render_section_hub_cards( $cards, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'class'       => 'rwgc-section-hub',
				'empty_title' => __( 'Nothing to show', 'reactwoo-geocore' ),
				'empty_body'  => '',
			)
		);

		if ( ! is_array( $cards ) || empty( $cards ) ) {
			self::render_empty_state(
				(string) $args['empty_title'],
				(string) $args['empty_body'],
				array(),
				array( 'dashicon' => 'dashicons-admin-generic' )
			);
			return;
		}

		echo '<div class="' . esc_attr( (string) $args['class'] ) . '__grid" role="list">';
		foreach ( $cards as $card ) {
			if ( empty( $card['label'] ) || empty( $card['url'] ) ) {
				continue;
			}
			$provider = isset( $card['provider'] ) ? (string) $card['provider'] : '';
			$desc     = isset( $card['description'] ) ? (string) $card['description'] : '';
			echo '<article class="rwgc-addon-card rwgc-section-hub__card" role="listitem">';
			echo '<div class="rwgc-addon-card__header">';
			echo '<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons dashicons-chart-area"></span></div>';
			echo '<div class="rwgc-addon-card__heading">';
			echo '<h3>' . esc_html( (string) $card['label'] ) . '</h3>';
			if ( '' !== $desc ) {
				echo '<p>' . esc_html( $desc ) . '</p>';
			}
			echo '</div></div>';
			if ( '' !== $provider ) {
				self::render_provider_badge( $provider );
			}
			echo '<div class="rwgc-addon-card__actions">';
			printf(
				'<a class="rwgc-btn rwgc-btn--primary" href="%1$s">%2$s</a>',
				esc_url( (string) $card['url'] ),
				esc_html__( 'Open', 'reactwoo-geocore' )
			);
			echo '</div></article>';
		}
		echo '</div>';
	}

	/**
	 * Hub cards grouped by capability provider (Insights consolidation).
	 *
	 * @param array<int, array<string, mixed>> $cards Hub card rows.
	 * @param array<string, mixed>             $args  Optional empty_title, empty_body, class, group_order.
	 * @return void
	 */
	public static function render_section_hub_cards_grouped( $cards, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'class'       => 'rwgc-section-hub',
				'empty_title' => __( 'Nothing to show', 'reactwoo-geocore' ),
				'empty_body'  => '',
				'group_order' => array( 'core', 'geo_ai', 'geo_optimise', 'geo_commerce', 'geocore_pro' ),
			)
		);

		if ( ! is_array( $cards ) || empty( $cards ) ) {
			self::render_empty_state(
				(string) $args['empty_title'],
				(string) $args['empty_body'],
				array(),
				array( 'dashicon' => 'dashicons-admin-generic' )
			);
			return;
		}

		$labels = array(
			'core'         => __( 'Geo Core', 'reactwoo-geocore' ),
			'geo_ai'       => __( 'Geo AI', 'reactwoo-geocore' ),
			'geo_optimise' => __( 'Geo Optimise', 'reactwoo-geocore' ),
			'geo_commerce' => __( 'Geo Commerce', 'reactwoo-geocore' ),
			'geocore_pro'  => __( 'GeoCore Pro', 'reactwoo-geocore' ),
		);
		$labels = apply_filters( 'rwgc_insights_hub_group_labels', $labels );

		$groups = array();
		foreach ( $cards as $card ) {
			$provider = isset( $card['provider'] ) ? sanitize_key( (string) $card['provider'] ) : '';
			if ( '' === $provider ) {
				$provider = 'core';
			}
			if ( ! isset( $groups[ $provider ] ) ) {
				$groups[ $provider ] = array();
			}
			$groups[ $provider ][] = $card;
		}

		$order = isset( $args['group_order'] ) && is_array( $args['group_order'] ) ? $args['group_order'] : array();
		$sorted = array();
		foreach ( $order as $key ) {
			$key = sanitize_key( (string) $key );
			if ( isset( $groups[ $key ] ) ) {
				$sorted[ $key ] = $groups[ $key ];
				unset( $groups[ $key ] );
			}
		}
		foreach ( $groups as $key => $group_cards ) {
			$sorted[ $key ] = $group_cards;
		}

		echo '<div class="' . esc_attr( (string) $args['class'] ) . ' rwgc-section-hub--grouped">';
		foreach ( $sorted as $provider => $group_cards ) {
			$heading = isset( $labels[ $provider ] ) ? (string) $labels[ $provider ] : $provider;
			echo '<section class="rwgc-section-hub__group" aria-labelledby="rwgc-insights-group-' . esc_attr( $provider ) . '">';
			echo '<h2 class="rwgc-section-hub__group-title" id="rwgc-insights-group-' . esc_attr( $provider ) . '">' . esc_html( $heading ) . '</h2>';
			self::render_section_hub_cards( $group_cards, array( 'class' => (string) $args['class'] ) );
			echo '</section>';
		}
		echo '</div>';
	}

	/**
	 * Page title + optional subtitle (suite shell).
	 *
	 * @param string               $title    Main heading (plain text).
	 * @param string               $subtitle Optional description.
	 * @param array<string, mixed> $args     Optional: class string on wrapper.
	 * @return void
	 */
	public static function render_page_header( $title, $subtitle = '', $args = array() ) {
		$args = wp_parse_args( $args, array( 'class' => 'rwgc-suite-page-header' ) );
		echo '<header class="' . esc_attr( $args['class'] ) . '">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		if ( is_string( $subtitle ) && '' !== $subtitle ) {
			echo '<p class="rwgc-suite-page-header__subtitle">' . esc_html( $subtitle ) . '</p>';
		}
		echo '</header>';
	}

	/**
	 * Single stat card.
	 *
	 * @param string               $label Metric label.
	 * @param string               $value Primary value.
	 * @param array<string, mixed> $args  Optional: hint (footer), tone: default|success|warning|neutral.
	 * @return void
	 */
	public static function render_stat_card( $label, $value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'hint' => '',
				'tone' => 'default',
			)
		);
		$tone = sanitize_key( (string) $args['tone'] );
		if ( ! in_array( $tone, array( 'default', 'success', 'warning', 'neutral' ), true ) ) {
			$tone = 'default';
		}
		$class = 'rwgc-suite-stat-card rwgc-suite-stat-card--' . $tone;
		echo '<div class="' . esc_attr( $class ) . '">';
		echo '<div class="rwgc-suite-stat-card__label">' . esc_html( $label ) . '</div>';
		echo '<div class="rwgc-suite-stat-card__value">' . esc_html( $value ) . '</div>';
		if ( is_string( $args['hint'] ) && '' !== $args['hint'] ) {
			echo '<div class="rwgc-suite-stat-card__hint">' . esc_html( $args['hint'] ) . '</div>';
		}
		echo '</div>';
	}

	/**
	 * Grid wrapper for stat cards.
	 *
	 * @param callable $callback Inner output (echo stat cards).
	 * @return void
	 */
	public static function render_stat_grid_open() {
		echo '<div class="rwgc-suite-stat-grid" role="region" aria-label="' . esc_attr__( 'Suite status overview', 'reactwoo-geocore' ) . '">';
	}

	/**
	 * @return void
	 */
	public static function render_stat_grid_close() {
		echo '</div>';
	}

	/**
	 * Status pill / badge.
	 *
	 * @param string $text   Badge text.
	 * @param string $variant success|warning|info|neutral.
	 * @return void
	 */
	public static function render_badge( $text, $variant = 'neutral' ) {
		$variant = sanitize_key( $variant );
		if ( ! in_array( $variant, array( 'success', 'warning', 'info', 'neutral' ), true ) ) {
			$variant = 'neutral';
		}
		printf(
			'<span class="rwgc-suite-badge rwgc-suite-badge--%1$s">%2$s</span>',
			esc_attr( $variant ),
			esc_html( $text )
		);
	}

	/**
	 * Onboarding checklist row.
	 *
	 * @param bool   $done    Whether step is complete.
	 * @param string $label   Step title.
	 * @param string $cta_url Optional link for “Fix” / “Open”.
	 * @param string $cta_label Optional CTA label when URL set.
	 * @return void
	 */
	public static function render_checklist_row( $done, $label, $cta_url = '', $cta_label = '' ) {
		$item_class = 'rwgc-suite-checklist__item' . ( $done ? ' is-done' : ' is-pending' );
		echo '<li class="' . esc_attr( $item_class ) . '">';
		echo '<span class="rwgc-suite-checklist__mark" aria-hidden="true">' . ( $done ? '✓' : '○' ) . '</span>';
		echo '<span class="rwgc-suite-checklist__label">' . esc_html( $label ) . '</span>';
		if ( ! $done && is_string( $cta_url ) && '' !== $cta_url && is_string( $cta_label ) && '' !== $cta_label ) {
			echo ' <a class="rwgc-suite-checklist__cta" href="' . esc_url( $cta_url ) . '">' . esc_html( $cta_label ) . '</a>';
		}
		echo '</li>';
	}

	/**
	 * Quick action buttons row.
	 *
	 * @param array<int, array{url:string,label:string,primary?:bool}> $actions Actions.
	 * @return void
	 */
	public static function render_quick_actions( $actions ) {
		if ( ! is_array( $actions ) || empty( $actions ) ) {
			return;
		}
		echo '<div class="rwgc-suite-quick-actions">';
		foreach ( $actions as $action ) {
			if ( empty( $action['url'] ) || empty( $action['label'] ) ) {
				continue;
			}
			$primary = ! empty( $action['primary'] );
			$class   = $primary ? 'rwgc-btn rwgc-btn--primary' : 'rwgc-btn rwgc-btn--secondary';
			printf(
				'<a class="%1$s" href="%2$s">%3$s</a>',
				esc_attr( $class ),
				esc_url( $action['url'] ),
				esc_html( $action['label'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Section title + optional lead (place inside `.rwgc-section` or a card).
	 *
	 * @param string $title Section heading.
	 * @param string $lead  Optional supporting line.
	 * @return void
	 */
	public static function render_section_header( $title, $lead = '' ) {
		echo '<header class="rwgc-section__head">';
		echo '<h2 class="rwgc-section__title">' . esc_html( $title ) . '</h2>';
		if ( is_string( $lead ) && '' !== $lead ) {
			echo '<p class="rwgc-section__lead">' . esc_html( $lead ) . '</p>';
		}
		echo '</header>';
	}

	/**
	 * Empty state block with optional CTA links (rwgc-btn).
	 *
	 * @param string               $title   Heading.
	 * @param string               $body    Explanation.
	 * @param array<int, array{url:string,label:string,primary?:bool}> $actions Optional buttons.
	 * @param array<string, mixed> $args    Optional: class (wrapper), dashicon (e.g. `dashicons-analytics`).
	 * @return void
	 */
	public static function render_empty_state( $title, $body, $actions = array(), $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'class'    => 'rwgc-empty-state',
				'dashicon' => '',
			)
		);
		echo '<div class="' . esc_attr( $args['class'] ) . '">';
		if ( is_string( $args['dashicon'] ) && '' !== $args['dashicon'] ) {
			$d = sanitize_html_class( $args['dashicon'] );
			echo '<div class="rwgc-empty-state__icon" aria-hidden="true"><span class="dashicons ' . esc_attr( $d ) . '"></span></div>';
		}
		echo '<h3 class="rwgc-empty-state__title">' . esc_html( $title ) . '</h3>';
		echo '<p class="rwgc-empty-state__body">' . esc_html( $body ) . '</p>';
		if ( is_array( $actions ) && ! empty( $actions ) ) {
			echo '<div class="rwgc-empty-state__actions">';
			foreach ( $actions as $action ) {
				if ( empty( $action['url'] ) || empty( $action['label'] ) ) {
					continue;
				}
				$primary = ! empty( $action['primary'] );
				$class   = $primary ? 'rwgc-btn rwgc-btn--primary' : 'rwgc-btn rwgc-btn--secondary';
				printf(
					'<a class="%1$s" href="%2$s">%3$s</a>',
					esc_attr( $class ),
					esc_url( $action['url'] ),
					esc_html( $action['label'] )
				);
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Horizontal row of rwgc buttons (links).
	 *
	 * @param array<int, array{url:string,label:string,primary?:bool,variant?:string}> $actions Actions.
	 * @param array<string, mixed> $args Optional: class, stack_mobile bool.
	 * @return void
	 */
	public static function render_button_row( $actions, $args = array() ) {
		if ( ! is_array( $actions ) || empty( $actions ) ) {
			return;
		}
		$args = wp_parse_args(
			$args,
			array(
				'class'        => 'rwgc-button-row',
				'stack_mobile' => false,
			)
		);
		$classes = $args['class'];
		if ( ! empty( $args['stack_mobile'] ) ) {
			$classes .= ' rwgc-actions--stack-mobile';
		}
		echo '<div class="' . esc_attr( $classes ) . '">';
		foreach ( $actions as $action ) {
			if ( empty( $action['url'] ) || empty( $action['label'] ) ) {
				continue;
			}
			$variant = isset( $action['variant'] ) ? sanitize_key( (string) $action['variant'] ) : '';
			if ( '' === $variant ) {
				$variant = ! empty( $action['primary'] ) ? 'primary' : 'secondary';
			}
			$map = array(
				'primary'   => 'rwgc-btn rwgc-btn--primary',
				'secondary' => 'rwgc-btn rwgc-btn--secondary',
				'tertiary'  => 'rwgc-btn rwgc-btn--tertiary',
				'danger'    => 'rwgc-btn rwgc-btn--danger',
			);
			$class = isset( $map[ $variant ] ) ? $map[ $variant ] : $map['secondary'];
			printf(
				'<a class="%1$s" href="%2$s">%3$s</a>',
				esc_attr( $class ),
				esc_url( $action['url'] ),
				esc_html( $action['label'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Status pill — alias of {@see self::render_pill()} for semantic clarity in views.
	 *
	 * @param string $text    Label.
	 * @param string $variant success|danger|neutral|warning.
	 * @return void
	 */
	public static function render_status_pill( $text, $variant = 'neutral' ) {
		self::render_pill( $text, $variant );
	}

	/**
	 * Satellite / add-on summary cards for the suite overview.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_suite_satellite_definitions() {
		$defs = array(
			array(
				'slug'      => 'geoelementor',
				'title'     => __( 'GeoElementor', 'reactwoo-geocore' ),
				'summary'   => __( 'Elementor-native geo targeting, rules, and variant groups.', 'reactwoo-geocore' ),
				'file'      => 'geo-elementor/elementor-geo-popup.php',
				'alt_files' => array( 'GeoElementor/elementor-geo-popup.php' ),
				'url'       => admin_url( 'admin.php?page=geo-elementor' ),
				'dashicon'  => 'dashicons-location-alt',
			),
			array(
				'slug'     => 'geo_ai',
				'title'    => __( 'Geo AI', 'reactwoo-geocore' ),
				'summary'  => __( 'AI-assisted page variants via the ReactWoo API.', 'reactwoo-geocore' ),
				'file'     => 'reactwoo-geo-ai/reactwoo-geo-ai.php',
				'url'      => admin_url( 'admin.php?page=rwga-dashboard' ),
				'dashicon' => 'dashicons-lightbulb',
			),
			array(
				'slug'     => 'geo_commerce',
				'title'    => __( 'Geo Commerce', 'reactwoo-geocore' ),
				'summary'  => __( 'WooCommerce pricing, fees, and order geo context.', 'reactwoo-geocore' ),
				'file'     => 'reactwoo-geo-commerce/reactwoo-geo-commerce.php',
				'url'      => admin_url( 'admin.php?page=rwgcm-dashboard' ),
				'dashicon' => 'dashicons-cart',
			),
			array(
				'slug'     => 'geo_optimise',
				'title'    => __( 'Geo Optimise', 'reactwoo-geocore' ),
				'summary'  => __( 'Page tests, variants, and reports; measurement and developer tools in one place.', 'reactwoo-geocore' ),
				'file'     => 'reactwoo-geo-optimise/reactwoo-geo-optimise.php',
				'url'      => admin_url( 'admin.php?page=rwgo-dashboard' ),
				'dashicon' => 'dashicons-chart-area',
			),
		);

		/**
		 * Filter satellite cards on the Geo Core suite dashboard.
		 *
		 * Each item may include: slug, title, summary, file, alt_files (array), url, dashicon (dashicons class suffix).
		 *
		 * @param array<int, array<string, mixed>> $defs Definitions.
		 */
		return apply_filters( 'rwgc_suite_satellite_definitions', $defs );
	}

	/**
	 * Render satellite card grid (installed / not installed + CTA).
	 *
	 * @return void
	 */
	public static function render_satellite_cards() {
		$defs = self::get_suite_satellite_definitions();
		echo '<div class="rwgc-suite-satellite-grid" role="region" aria-label="' . esc_attr__( 'ReactWoo satellite plugins', 'reactwoo-geocore' ) . '">';
		foreach ( $defs as $def ) {
			$active   = self::satellite_plugin_is_active( $def );
			$dashicon = isset( $def['dashicon'] ) && is_string( $def['dashicon'] ) ? $def['dashicon'] : 'dashicons-admin-plugins';
			echo '<div class="rwgc-addon-card rwgc-addon-card--satellite">';
			echo '<div class="rwgc-addon-card__header">';
			echo '<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons ' . esc_attr( $dashicon ) . '"></span></div>';
			echo '<div class="rwgc-addon-card__heading">';
			echo '<h3>' . esc_html( $def['title'] ) . '</h3>';
			echo '<p>' . esc_html( $def['summary'] ) . '</p>';
			echo '</div></div>';
			echo '<div class="rwgc-addon-card__meta">';
			if ( $active ) {
				self::render_pill( __( 'Active', 'reactwoo-geocore' ), 'success' );
			} else {
				self::render_pill( __( 'Not installed', 'reactwoo-geocore' ), 'neutral' );
			}
			echo '</div>';
			echo '<div class="rwgc-addon-card__actions">';
			if ( $active ) {
				printf(
					'<a class="rwgc-btn rwgc-btn--primary" href="%1$s">%2$s</a>',
					esc_url( $def['url'] ),
					esc_html__( 'Open', 'reactwoo-geocore' )
				);
			} else {
				printf(
					'<a class="rwgc-btn rwgc-btn--secondary" href="%1$s">%2$s</a>',
					esc_url( admin_url( 'plugin-install.php' ) ),
					esc_html__( 'Install plugins', 'reactwoo-geocore' )
				);
			}
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Horizontal section nav (Geo Core and satellites).
	 *
	 * @param array<string, string|array{label:string,url?:string}> $items   Slug => label or array with label + optional url.
	 * @param string                                                  $current Active page slug.
	 * @param array<string, mixed>                                      $args    Optional: filter (hook name), aria_label, show_hub_breadcrumb, hub_extension_label.
	 * @return void
	 */
	public static function render_inner_nav( array $items, $current, $args = array() ) {
		if ( function_exists( 'rwgc_uses_platform_shell' ) && rwgc_uses_platform_shell() ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'filter'              => '',
				'aria_label'          => __( 'Section navigation', 'reactwoo-geocore' ),
				'show_hub_breadcrumb' => false,
				'hub_extension_label' => '',
			)
		);

		if ( ! empty( $args['show_hub_breadcrumb'] ) && is_string( $args['hub_extension_label'] ) && '' !== $args['hub_extension_label'] ) {
			self::render_hub_breadcrumb( $args['hub_extension_label'] );
		}

		if ( is_string( $args['filter'] ) && '' !== $args['filter'] ) {
			/**
			 * Filter inner nav items before render.
			 *
			 * @param array<string, string|array{label:string,url?:string}> $items   Nav entries.
			 * @param string                                                  $current Current page slug.
			 */
			$items = apply_filters( $args['filter'], $items, $current );
		}

		if ( ! is_array( $items ) || array() === $items ) {
			return;
		}

		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr( (string) $args['aria_label'] ) . '">';
		foreach ( $items as $slug => $entry ) {
			$label = '';
			$url   = '';
			if ( is_array( $entry ) ) {
				$label = isset( $entry['label'] ) ? (string) $entry['label'] : '';
				$url   = isset( $entry['url'] ) && is_string( $entry['url'] ) && '' !== $entry['url']
					? $entry['url']
					: admin_url( 'admin.php?page=' . $slug );
			} else {
				$label = (string) $entry;
				$url   = admin_url( 'admin.php?page=' . $slug );
			}
			if ( '' === $label ) {
				continue;
			}
			$class = 'rwgc-inner-nav__link' . ( (string) $slug === (string) $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Breadcrumb back to the Geo Core hub from a satellite screen.
	 *
	 * @param string $extension_label Satellite product name (e.g. Geo Commerce).
	 * @return void
	 */
	public static function render_hub_breadcrumb( $extension_label ) {
		if ( ! is_admin() ) {
			return;
		}
		$core_url = admin_url( 'admin.php?page=rwgc-dashboard' );
		if ( function_exists( 'rwgc_admin_menu_parent' ) && 'rwgc-dashboard' !== rwgc_admin_menu_parent() ) {
			$core_url = admin_url( 'admin.php?page=' . rwgc_admin_menu_parent() );
		}
		echo '<p class="rwgc-hub-breadcrumb">';
		echo '<a class="rwgc-hub-breadcrumb__link" href="' . esc_url( $core_url ) . '">';
		if ( class_exists( 'RWGC_Admin_Platform', false ) ) {
			echo esc_html( RWGC_Admin_Platform::menu_label() );
		} else {
			esc_html_e( 'Geo Core', 'reactwoo-geocore' );
		}
		echo '</a>';
		echo '<span class="rwgc-hub-breadcrumb__sep" aria-hidden="true">›</span>';
		echo '<span class="rwgc-hub-breadcrumb__current">' . esc_html( (string) $extension_label ) . '</span>';
		echo '</p>';
	}
}
