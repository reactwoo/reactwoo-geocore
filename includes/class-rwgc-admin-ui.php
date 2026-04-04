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
			$class   = $primary ? 'button button-primary' : 'button';
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
					'<a class="button button-primary" href="%1$s">%2$s</a>',
					esc_url( $def['url'] ),
					esc_html__( 'Open', 'reactwoo-geocore' )
				);
			} else {
				printf(
					'<a class="button" href="%1$s">%2$s</a>',
					esc_url( admin_url( 'plugin-install.php' ) ),
					esc_html__( 'Install plugins', 'reactwoo-geocore' )
				);
			}
			echo '</div></div>';
		}
		echo '</div>';
	}
}
