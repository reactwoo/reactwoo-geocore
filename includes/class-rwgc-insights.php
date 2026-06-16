<?php
/**
 * Capability + Intelligence Centre — shared insights provider registry.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates satellite capability rows for the Insights dashboard and compact AI payloads.
 */
class RWGC_Insights {

	/**
	 * Provider status slugs.
	 */
	const STATUSES = array(
		'active',
		'inactive',
		'missing',
		'requires_license',
		'requires_dependency',
		'planned',
		'no_data',
	);

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_insights_providers', array( __CLASS__, 'register_core_provider' ), 5 );
	}

	/**
	 * Core Geo capability provider.
	 *
	 * @param array<int, callable(): array<string, mixed>> $providers Provider callables.
	 * @return array<int, callable(): array<string, mixed>>
	 */
	public static function register_core_provider( $providers ) {
		if ( ! is_array( $providers ) ) {
			$providers = array();
		}
		$providers[] = array( __CLASS__, 'build_core_provider' );
		return $providers;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build_core_provider() {
		$maxmind   = class_exists( 'RWGC_MaxMind', false ) ? RWGC_MaxMind::get_status() : array();
		$detected  = ! empty( $maxmind['exists'] );
		$rule_count = self::count_published_visibility_rules();
		$variants   = self::count_page_variants();
		$targeting_on = (bool) RWGC_Settings::get( 'enable_targeting', 0 );

		$capabilities = array(
			array(
				'label'  => __( 'Visitor country detection', 'reactwoo-geocore' ),
				'status' => $detected ? 'active' : 'inactive',
			),
			array(
				'label'  => __( 'Shortcodes', 'reactwoo-geocore' ),
				'status' => 'active',
			),
			array(
				'label'  => __( 'Gutenberg / Elementor targeting', 'reactwoo-geocore' ),
				'status' => $targeting_on ? 'active' : 'inactive',
			),
			array(
				'label'  => __( 'Page variants', 'reactwoo-geocore' ),
				'status' => $variants > 0 ? 'active' : 'inactive',
			),
			array(
				'label'  => __( 'Visibility rules', 'reactwoo-geocore' ),
				'status' => $rule_count > 0 ? 'active' : 'inactive',
			),
		);

		$missing = array();
		if ( ! $detected ) {
			$missing[] = __( 'MaxMind database', 'reactwoo-geocore' );
		}
		if ( $rule_count <= 0 ) {
			$missing[] = __( 'Published visibility rules', 'reactwoo-geocore' );
		}

		$recommendations = array();
		if ( ! $detected ) {
			$recommendations[] = array(
				'label'    => __( 'Connect MaxMind', 'reactwoo-geocore' ),
				'priority' => 1,
				'reason'   => __( 'Visitor country detection needs a GeoLite2 database.', 'reactwoo-geocore' ),
			);
		}
		if ( $rule_count <= 0 ) {
			$recommendations[] = array(
				'label'    => __( 'Create your first rule', 'reactwoo-geocore' ),
				'priority' => 2,
				'reason'   => __( 'Targeting and visibility start with a saved rule.', 'reactwoo-geocore' ),
			);
		}

		$status = 'active';
		if ( ! $detected ) {
			$status = 'inactive';
		} elseif ( $rule_count <= 0 && $variants <= 0 ) {
			$status = 'no_data';
		}

		$maxmind_url = function_exists( 'rw_geo_app_url' )
			? rw_geo_app_url( 'integrations', 'rwgc-maxmind' )
			: admin_url( 'admin.php?page=rwgc-maxmind' );
		$rules_url = function_exists( 'rw_geo_app_url' )
			? rw_geo_app_url( 'targeting', 'rwgc-targeting-rules' )
			: admin_url( 'admin.php?page=rwgc-targeting-rules' );

		return self::normalize_provider(
			array(
				'id'              => 'geo-core',
				'label'           => __( 'Geo Core', 'reactwoo-geocore' ),
				'status'          => $status,
				'summary'         => $detected
					? __( 'Geo detection and targeting engine is available on this site.', 'reactwoo-geocore' )
					: __( 'Geo Core is active but visitor detection is not configured yet.', 'reactwoo-geocore' ),
				'metrics'         => array(
					array(
						'label' => __( 'Active rules', 'reactwoo-geocore' ),
						'value' => (string) $rule_count,
					),
					array(
						'label' => __( 'Page variants', 'reactwoo-geocore' ),
						'value' => (string) $variants,
					),
					array(
						'label' => __( 'Targeting', 'reactwoo-geocore' ),
						'value' => $targeting_on ? __( 'On', 'reactwoo-geocore' ) : __( 'Off', 'reactwoo-geocore' ),
					),
				),
				'capabilities'    => $capabilities,
				'missing_setup'   => $missing,
				'recommendations' => $recommendations,
				'actions'         => array(
					array(
						'url'     => $rules_url,
						'label'   => __( 'Manage rules', 'reactwoo-geocore' ),
						'primary' => $rule_count <= 0,
					),
					array(
						'url'   => $maxmind_url,
						'label' => __( 'MaxMind settings', 'reactwoo-geocore' ),
					),
				),
				'empty_state'     => ! $detected
					? array(
						'type'  => 'not_configured',
						'title' => __( 'Connect MaxMind to start detecting visitors', 'reactwoo-geocore' ),
						'body'  => __( 'Download or upload the GeoLite2 Country database so Geo Core can resolve visitor countries.', 'reactwoo-geocore' ),
					)
					: ( $rule_count <= 0 && $variants <= 0
						? array(
							'type'  => 'not_configured',
							'title' => __( 'Geo Core is ready — add your first rule or variant', 'reactwoo-geocore' ),
							'body'  => __( 'Detection works, but no targeting rules or page variants are published yet.', 'reactwoo-geocore' ),
						)
						: array() ),
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_providers() {
		/**
		 * Register insight capability providers (Core + satellites).
		 *
		 * Each callable must return a provider row:
		 * id, label, status, summary, metrics, capabilities, recommendations, actions.
		 *
		 * @param array<int, callable(): array<string, mixed>> $providers Callables.
		 */
		$callables = apply_filters( 'rwgc_insights_providers', array() );
		if ( ! is_array( $callables ) ) {
			$callables = array();
		}

		$rows = array();
		foreach ( $callables as $callable ) {
			if ( ! is_callable( $callable ) ) {
				continue;
			}
			$row = call_user_func( $callable );
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			$rows[] = self::normalize_provider( $row );
		}

		/**
		 * Filter normalized insight provider rows before render / compact export.
		 *
		 * @param array<int, array<string, mixed>> $rows Provider rows.
		 */
		return apply_filters( 'rwgc_insights_provider_rows', $rows );
	}

	/**
	 * @param array<string, mixed> $row Raw provider row.
	 * @return array<string, mixed>
	 */
	public static function normalize_provider( array $row ) {
		$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'inactive';
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status = 'inactive';
		}

		$defaults = array(
			'id'              => '',
			'label'           => '',
			'status'          => $status,
			'summary'         => '',
			'metrics'         => array(),
			'capabilities'    => array(),
			'missing_setup'   => array(),
			'recommendations' => array(),
			'actions'         => array(),
			'empty_state'     => array(),
		);

		$row = wp_parse_args( $row, $defaults );
		$row['status'] = $status;

		foreach ( array( 'metrics', 'capabilities', 'recommendations', 'actions' ) as $key ) {
			if ( ! is_array( $row[ $key ] ) ) {
				$row[ $key ] = array();
			}
		}
		if ( ! is_array( $row['missing_setup'] ) ) {
			$row['missing_setup'] = array();
		}
		if ( ! is_array( $row['empty_state'] ) ) {
			$row['empty_state'] = array();
		}

		return $row;
	}

	/**
	 * Compact semantic health chips for the Capability Map overview.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_platform_health_chips() {
		$providers = self::get_providers();
		$by_id     = array();
		foreach ( $providers as $p ) {
			$by_id[ (string) $p['id'] ] = $p;
		}

		$maxmind      = class_exists( 'RWGC_MaxMind', false ) ? RWGC_MaxMind::get_status() : array();
		$targeting_on = (bool) RWGC_Settings::get( 'enable_targeting', 0 );
		$detected     = ! empty( $maxmind['exists'] );

		$active_satellites = 0;
		foreach ( array( 'geocore-pro', 'geo-commerce', 'geo-optimise', 'geo-ai' ) as $sid ) {
			if ( ! isset( $by_id[ $sid ] ) || 'missing' === $by_id[ $sid ]['status'] ) {
				continue;
			}
			if ( in_array( $by_id[ $sid ]['status'], array( 'active', 'no_data', 'inactive' ), true ) ) {
				++$active_satellites;
			}
		}

		$setup_remaining = self::count_setup_tasks_remaining();
		$optimise        = isset( $by_id['geo-optimise'] ) ? $by_id['geo-optimise'] : array();
		$ai              = isset( $by_id['geo-ai'] ) ? $by_id['geo-ai'] : array();
		$exp_active      = self::metric_value( $optimise, __( 'Active tests', 'reactwoo-geocore' ), '0' );
		$ai_sync         = self::metric_value( $ai, __( 'Last sync', 'reactwoo-geocore' ), __( 'Never', 'reactwoo-geocore' ) );
		$ai_installed    = isset( $by_id['geo-ai'] ) && 'missing' !== $by_id['geo-ai']['status'];

		$geo_working = $detected && ( $targeting_on || self::count_published_visibility_rules() > 0 );
		$chips       = array(
			array(
				'id'    => 'geo_targeting',
				'label' => __( 'Geo targeting', 'reactwoo-geocore' ),
				'value' => $geo_working
					? __( 'Working', 'reactwoo-geocore' )
					: ( $detected ? __( 'Needs rules', 'reactwoo-geocore' ) : __( 'Needs setup', 'reactwoo-geocore' ) ),
				'tone'  => $geo_working ? 'success' : 'warning',
			),
			array(
				'id'    => 'satellites',
				'label' => __( 'Satellites', 'reactwoo-geocore' ),
				'value' => $active_satellites > 0
					/* translators: %d: satellite count */
					? sprintf( _n( '%d active', '%d active', $active_satellites, 'reactwoo-geocore' ), $active_satellites )
					: __( 'None active', 'reactwoo-geocore' ),
				'tone'  => $active_satellites > 0 ? 'success' : 'neutral',
			),
			array(
				'id'    => 'setup',
				'label' => __( 'Setup tasks', 'reactwoo-geocore' ),
				'value' => 0 === $setup_remaining
					? __( 'Complete', 'reactwoo-geocore' )
					/* translators: %d: pending task count */
					: sprintf( _n( '%d remaining', '%d remaining', $setup_remaining, 'reactwoo-geocore' ), $setup_remaining ),
				'tone'  => 0 === $setup_remaining ? 'success' : 'warning',
			),
		);

		if ( $ai_installed ) {
			$ai_ok = __( 'Never', 'reactwoo-geocore' ) !== $ai_sync && '—' !== $ai_sync;
			$chips[] = array(
				'id'    => 'ai_sync',
				'label' => __( 'AI sync', 'reactwoo-geocore' ),
				'value' => $ai_ok ? __( 'Enabled', 'reactwoo-geocore' ) : __( 'Not synced', 'reactwoo-geocore' ),
				'tone'  => $ai_ok ? 'success' : 'warning',
			);
		}

		$exp_count = is_numeric( $exp_active ) ? (int) $exp_active : 0;
		$chips[]   = array(
			'id'    => 'experiments',
			'label' => __( 'Experiments', 'reactwoo-geocore' ),
			'value' => $exp_count > 0
				/* translators: %d: running experiment count */
				? sprintf( _n( '%d running', '%d running', $exp_count, 'reactwoo-geocore' ), $exp_count )
				: __( 'None running', 'reactwoo-geocore' ),
			'tone'  => $exp_count > 0 ? 'success' : 'neutral',
		);

		return $chips;
	}

	/**
	 * Pending setup checklist items.
	 *
	 * @return int
	 */
	public static function count_setup_tasks_remaining() {
		$count = 0;
		foreach ( self::get_data_readiness() as $item ) {
			if ( empty( $item['done'] ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Admin URL for full provider detail view.
	 *
	 * @param string $provider_id Provider id slug.
	 * @return string
	 */
	public static function get_provider_details_url( $provider_id ) {
		$provider_id = sanitize_key( (string) $provider_id );
		if ( function_exists( 'rw_geo_app_url' ) ) {
			return add_query_arg( 'provider', $provider_id, rw_geo_app_url( 'insights', 'rwgc-insights-provider-detail' ) );
		}
		return add_query_arg(
			array(
				'page'     => 'rwgc-insights-provider-detail',
				'provider' => $provider_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Find a provider row by id.
	 *
	 * @param string $provider_id Provider id.
	 * @return array<string, mixed>|null
	 */
	public static function get_provider_by_id( $provider_id ) {
		$provider_id = sanitize_key( (string) $provider_id );
		foreach ( self::get_providers() as $provider ) {
			if ( isset( $provider['id'] ) && (string) $provider['id'] === $provider_id ) {
				return $provider;
			}
		}
		return null;
	}

	/**
	 * AI-focused recommendations for the AI Opportunities tab.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_ai_recommendations() {
		$all = self::get_recommendations( 20 );
		$ai  = array();
		foreach ( $all as $rec ) {
			$provider_id = isset( $rec['provider_id'] ) ? (string) $rec['provider_id'] : '';
			if ( 'geo-ai' === $provider_id ) {
				$ai[] = $rec;
				continue;
			}
			$haystack = strtolower( (string) ( $rec['label'] ?? '' ) . ' ' . (string) ( $rec['reason'] ?? '' ) );
			if ( false !== strpos( $haystack, 'ai' ) || false !== strpos( $haystack, 'sync' ) || false !== strpos( $haystack, 'intelligence' ) ) {
				$ai[] = $rec;
			}
		}
		return $ai;
	}

	/**
	 * Top health summary cards for the Insights dashboard.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_health_row() {
		$providers = self::get_providers();
		$by_id     = array();
		foreach ( $providers as $p ) {
			$by_id[ (string) $p['id'] ] = $p;
		}

		$core       = isset( $by_id['geo-core'] ) ? $by_id['geo-core'] : array();
		$commerce   = isset( $by_id['geo-commerce'] ) ? $by_id['geo-commerce'] : array();
		$optimise   = isset( $by_id['geo-optimise'] ) ? $by_id['geo-optimise'] : array();
		$ai         = isset( $by_id['geo-ai'] ) ? $by_id['geo-ai'] : array();

		$maxmind = class_exists( 'RWGC_MaxMind', false ) ? RWGC_MaxMind::get_status() : array();
		$rules   = self::count_published_visibility_rules();
		$targeting_on = (bool) RWGC_Settings::get( 'enable_targeting', 0 );

		$active_satellites = 0;
		foreach ( array( 'geocore-pro', 'geo-commerce', 'geo-optimise', 'geo-ai' ) as $sid ) {
			if ( isset( $by_id[ $sid ] ) && 'missing' !== $by_id[ $sid ]['status'] ) {
				if ( in_array( $by_id[ $sid ]['status'], array( 'active', 'no_data', 'inactive' ), true ) ) {
					++$active_satellites;
				}
			}
		}

		$exp_active = self::metric_value( $optimise, __( 'Active tests', 'reactwoo-geocore' ), '0' );
		$attr_value = self::metric_value( $commerce, __( 'Attributed orders', 'reactwoo-geocore' ), '—' );
		$ai_sync    = self::metric_value( $ai, __( 'Last sync', 'reactwoo-geocore' ), __( 'Never', 'reactwoo-geocore' ) );

		$data_sources = 0;
		if ( ! empty( $maxmind['exists'] ) ) {
			++$data_sources;
		}
		if ( class_exists( 'WooCommerce', false ) ) {
			++$data_sources;
		}
		if ( isset( $by_id['geocore-pro'] ) && 'missing' !== $by_id['geocore-pro']['status'] ) {
			++$data_sources;
		}

		$next_action = self::pick_top_recommendation( $providers );

		return array(
			array(
				'id'    => 'targeting',
				'label' => __( 'Visitor targeting', 'reactwoo-geocore' ),
				'value' => $targeting_on ? __( 'Active', 'reactwoo-geocore' ) : __( 'Inactive', 'reactwoo-geocore' ),
				'tone'  => $targeting_on ? 'success' : 'warning',
				'hint'  => ! empty( $maxmind['exists'] )
					? __( 'MaxMind configured', 'reactwoo-geocore' )
					: __( 'MaxMind not configured', 'reactwoo-geocore' ),
			),
			array(
				'id'    => 'rules',
				'label' => __( 'Active rules', 'reactwoo-geocore' ),
				'value' => (string) $rules,
				'tone'  => $rules > 0 ? 'success' : 'neutral',
			),
			array(
				'id'    => 'satellites',
				'label' => __( 'Active satellites', 'reactwoo-geocore' ),
				'value' => (string) $active_satellites,
				'tone'  => $active_satellites > 0 ? 'success' : 'neutral',
			),
			array(
				'id'    => 'data_sources',
				'label' => __( 'Data sources connected', 'reactwoo-geocore' ),
				'value' => (string) $data_sources,
				'tone'  => $data_sources >= 2 ? 'success' : 'neutral',
			),
			array(
				'id'    => 'experiments',
				'label' => __( 'Experiments', 'reactwoo-geocore' ),
				'value' => $exp_active,
				'tone'  => is_numeric( $exp_active ) && (int) $exp_active > 0 ? 'success' : 'neutral',
			),
			array(
				'id'    => 'commerce',
				'label' => __( 'Commerce attribution', 'reactwoo-geocore' ),
				'value' => $attr_value,
				'tone'  => is_numeric( $attr_value ) && (int) $attr_value > 0 ? 'success' : 'neutral',
			),
			array(
				'id'    => 'ai_sync',
				'label' => __( 'AI sync status', 'reactwoo-geocore' ),
				'value' => $ai_sync,
				'tone'  => ( __( 'Never', 'reactwoo-geocore' ) !== $ai_sync && '—' !== $ai_sync ) ? 'success' : 'warning',
			),
			array(
				'id'    => 'next_action',
				'label' => __( 'Recommended next action', 'reactwoo-geocore' ),
				'value' => ! empty( $next_action['label'] ) ? (string) $next_action['label'] : __( 'Review capability map', 'reactwoo-geocore' ),
				'tone'  => 'default',
				'hint'  => ! empty( $next_action['reason'] ) ? (string) $next_action['reason'] : '',
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $providers Provider rows.
	 * @return array<string, string>
	 */
	public static function pick_top_recommendation( array $providers ) {
		$all = array();
		foreach ( $providers as $provider ) {
			if ( empty( $provider['recommendations'] ) || ! is_array( $provider['recommendations'] ) ) {
				continue;
			}
			foreach ( $provider['recommendations'] as $rec ) {
				if ( ! is_array( $rec ) || empty( $rec['label'] ) ) {
					continue;
				}
				$priority = isset( $rec['priority'] ) ? (int) $rec['priority'] : 50;
				$all[]    = array(
					'priority' => $priority,
					'label'    => (string) $rec['label'],
					'reason'   => isset( $rec['reason'] ) ? (string) $rec['reason'] : '',
				);
			}
		}
		if ( empty( $all ) ) {
			return array();
		}
		usort(
			$all,
			static function ( $a, $b ) {
				return ( $a['priority'] <=> $b['priority'] );
			}
		);
		return $all[0];
	}

	/**
	 * @param array<string, mixed> $provider Provider row.
	 * @param string               $label    Metric label to find.
	 * @param string               $fallback Fallback value.
	 * @return string
	 */
	private static function metric_value( array $provider, $label, $fallback ) {
		if ( empty( $provider['metrics'] ) || ! is_array( $provider['metrics'] ) ) {
			return $fallback;
		}
		foreach ( $provider['metrics'] as $metric ) {
			if ( ! is_array( $metric ) ) {
				continue;
			}
			if ( isset( $metric['label'] ) && $metric['label'] === $label && isset( $metric['value'] ) ) {
				return (string) $metric['value'];
			}
		}
		return $fallback;
	}

	/**
	 * Merged recommendations for the dashboard panel.
	 *
	 * @param int $limit Max items.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recommendations( $limit = 6 ) {
		$providers = self::get_providers();
		$all       = array();
		foreach ( $providers as $provider ) {
			if ( empty( $provider['recommendations'] ) ) {
				continue;
			}
			foreach ( $provider['recommendations'] as $rec ) {
				if ( ! is_array( $rec ) || empty( $rec['label'] ) ) {
					continue;
				}
				$rec['provider_id']    = $provider['id'];
				$rec['provider_label'] = $provider['label'];
				$rec['priority']       = isset( $rec['priority'] ) ? (int) $rec['priority'] : 50;
				$all[]                 = $rec;
			}
		}
		usort(
			$all,
			static function ( $a, $b ) {
				return ( $a['priority'] <=> $b['priority'] );
			}
		);
		return array_slice( $all, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Setup checklist for “What this insight is based on”.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_data_readiness() {
		$maxmind = class_exists( 'RWGC_MaxMind', false ) ? RWGC_MaxMind::get_status() : array();
		$rules   = self::count_published_visibility_rules();
		$providers = self::get_providers();
		$by_id = array();
		foreach ( $providers as $p ) {
			$by_id[ (string) $p['id'] ] = $p;
		}

		$items = array(
			array(
				'label' => __( 'MaxMind configured', 'reactwoo-geocore' ),
				'done'  => ! empty( $maxmind['exists'] ),
				'url'   => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'integrations', 'rwgc-maxmind' ) : admin_url( 'admin.php?page=rwgc-maxmind' ),
			),
			array(
				'label' => sprintf(
					/* translators: %d: rule count */
					__( '%d visibility rules found', 'reactwoo-geocore' ),
					$rules
				),
				'done'  => $rules > 0,
				'url'   => function_exists( 'rw_geo_app_url' ) ? rw_geo_app_url( 'targeting', 'rwgc-targeting-rules' ) : admin_url( 'admin.php?page=rwgc-targeting-rules' ),
			),
			array(
				'label' => class_exists( 'WooCommerce', false )
					? __( 'WooCommerce active', 'reactwoo-geocore' )
					: __( 'WooCommerce not active', 'reactwoo-geocore' ),
				'done'  => class_exists( 'WooCommerce', false ),
			),
		);

		if ( isset( $by_id['geo-optimise'] ) && 'missing' !== $by_id['geo-optimise']['status'] ) {
			$exp_total = self::metric_value( $by_id['geo-optimise'], __( 'Experiments', 'reactwoo-geocore' ), '0' );
			$conv      = self::metric_value( $by_id['geo-optimise'], __( 'Experiments with conversion data', 'reactwoo-geocore' ), '0' );
			$items[]   = array(
				'label' => sprintf(
					/* translators: 1: experiment count, 2: experiments with data */
					__( '%1$s experiments · %2$s with conversion data', 'reactwoo-geocore' ),
					$exp_total,
					$conv
				),
				'done'  => is_numeric( $conv ) && (int) $conv > 0,
			);
		}

		if ( isset( $by_id['geo-ai'] ) && 'missing' !== $by_id['geo-ai']['status'] ) {
			$sync = self::metric_value( $by_id['geo-ai'], __( 'Last sync', 'reactwoo-geocore' ), __( 'Never', 'reactwoo-geocore' ) );
			$items[] = array(
				'label' => sprintf(
					/* translators: %s: last sync time or Never */
					__( 'AI sync last run: %s', 'reactwoo-geocore' ),
					$sync
				),
				'done'  => __( 'Never', 'reactwoo-geocore' ) !== $sync,
			);
		}

		/**
		 * Filter data-readiness checklist rows on the Insights dashboard.
		 *
		 * @param array<int, array<string, mixed>> $items Checklist rows.
		 */
		return apply_filters( 'rwgc_insights_data_readiness', $items );
	}

	/**
	 * Compact JSON-friendly payload for Geo AI audits (no page content).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_compact_payload() {
		$providers = self::get_providers();
		$compact   = array(
			'generated_at_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'health'           => self::get_health_row(),
			'providers'        => array(),
			'recommendations'  => self::get_recommendations( 8 ),
			'readiness'        => self::get_data_readiness(),
		);

		foreach ( $providers as $provider ) {
			$compact['providers'][] = array(
				'id'              => $provider['id'],
				'label'           => $provider['label'],
				'status'          => $provider['status'],
				'summary'         => $provider['summary'],
				'metrics'         => $provider['metrics'],
				'capabilities'    => $provider['capabilities'],
				'missing_setup'   => $provider['missing_setup'],
				'recommendations' => $provider['recommendations'],
			);
		}

		/**
		 * Filter compact capability insights exported for Geo AI workflows.
		 *
		 * @param array<string, mixed> $compact Normalized payload.
		 */
		return apply_filters( 'rwgc_insights_compact_payload', $compact );
	}

	/**
	 * @return int
	 */
	public static function count_published_visibility_rules() {
		if ( ! class_exists( 'RWGC_Visibility_Rule_CPT', false ) ) {
			return 0;
		}
		$counts = wp_count_posts( RWGC_Visibility_Rule_CPT::POST_TYPE );
		if ( ! is_object( $counts ) || ! isset( $counts->publish ) ) {
			return 0;
		}
		return (int) $counts->publish;
	}

	/**
	 * @return int
	 */
	public static function count_page_variants() {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return 0;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				RWGC_Routing::META_ROLE,
				'variant'
			)
		);
		return max( 0, (int) $count );
	}

	/**
	 * Render the Capability + Intelligence Centre dashboard.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}

		$providers       = self::get_providers();
		$health          = self::get_platform_health_chips();
		$recommendations = self::get_recommendations( 3 );
		$activity        = class_exists( 'RWGC_Onboarding', false ) ? RWGC_Onboarding::get_activity() : array();

		include RWGC_PATH . 'admin/views/insights-capability-page.php';
	}

	/**
	 * Setup & readiness tab.
	 *
	 * @return void
	 */
	public static function render_readiness_page() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}

		$readiness = self::get_data_readiness();
		$providers = self::get_providers();

		include RWGC_PATH . 'admin/views/insights-readiness-page.php';
	}

	/**
	 * AI opportunities tab.
	 *
	 * @return void
	 */
	public static function render_ai_opportunities_page() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}

		$recommendations = self::get_ai_recommendations();
		$ai_provider     = self::get_provider_by_id( 'geo-ai' );

		include RWGC_PATH . 'admin/views/insights-ai-opportunities-page.php';
	}

	/**
	 * Full provider capability detail (progressive disclosure).
	 *
	 * @return void
	 */
	public static function render_provider_detail() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provider_id = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
		$provider    = '' !== $provider_id ? self::get_provider_by_id( $provider_id ) : null;

		include RWGC_PATH . 'admin/views/insights-provider-detail-page.php';
	}
}
