<?php
/**
 * Geo Suite — task-first workflow launchers and deep links.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers default launchers; satellites extend via {@see 'rwgc_workflow_launchers'}.
 */
class RWGC_Workflows {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_launchers() {
		$launchers = array(
			array(
				'id'          => 'create_variant',
				'title'       => __( 'Create a country-specific page version', 'reactwoo-geocore' ),
				'description' => __( 'Pick a page, choose a country, and publish a linked local version — without editing meta boxes first.', 'reactwoo-geocore' ),
				'url'         => admin_url( 'admin.php?page=rwgc-workflow-variant' ),
				'primary'     => true,
				'icon'        => 'dashicons-admin-page',
			),
			array(
				'id'          => 'ai_draft',
				'title'       => __( 'Generate a localised draft with AI', 'reactwoo-geocore' ),
				'description' => __( 'Open Geo AI to adapt copy for a country or audience.', 'reactwoo-geocore' ),
				'url'         => class_exists( 'RWGA_Plugin', false ) ? admin_url( 'admin.php?page=rwga-dashboard' ) : admin_url( 'admin.php?page=rwgc-addons' ),
				'primary'     => false,
				'icon'        => 'dashicons-lightbulb',
				'requires'    => 'geo_ai',
			),
			array(
				'id'          => 'experiment',
				'title'       => __( 'Start a geo split test', 'reactwoo-geocore' ),
				'description' => __( 'Create a page test in Geo Optimise: duplicate a variant, route traffic, and review assignments.', 'reactwoo-geocore' ),
				'url'         => class_exists( 'RWGO_Plugin', false ) ? admin_url( 'admin.php?page=rwgo-create-test' ) : admin_url( 'admin.php?page=rwgc-addons' ),
				'primary'     => false,
				'icon'        => 'dashicons-chart-area',
				'requires'    => 'geo_optimise',
			),
			array(
				'id'          => 'commerce_rule',
				'title'       => __( 'Personalise WooCommerce by country', 'reactwoo-geocore' ),
				'description' => __( 'Create pricing or fee rules tied to visitor location.', 'reactwoo-geocore' ),
				'url'         => ( function_exists( 'rwgc_is_woocommerce_active' ) && rwgc_is_woocommerce_active() && class_exists( 'RWGCM_Plugin', false ) )
					? admin_url( 'admin.php?page=rwgcm-pricing' )
					: admin_url( 'admin.php?page=rwgc-addons' ),
				'primary'     => false,
				'icon'        => 'dashicons-cart',
				'requires'    => 'geo_commerce',
			),
		);

		/**
		 * Register Geo Suite workflow launchers (cards on Suite Home / Getting Started).
		 *
		 * @param array<int, array<string, mixed>> $launchers Each: id, title, description, url, primary?, icon (dashicons class suffix), requires?.
		 */
		$launchers = apply_filters( 'rwgc_workflow_launchers', $launchers );

		foreach ( $launchers as $k => $launcher ) {
			if ( empty( $launcher['url'] ) || empty( $launcher['id'] ) ) {
				continue;
			}
			$launchers[ $k ]['url'] = self::add_handoff_query_args( (string) $launcher['url'], (string) $launcher['id'] );
		}

		return $launchers;
	}

	/**
	 * Append suite handoff query args so satellites can detect entry from Geo Core.
	 *
	 * @param string $url         Destination admin URL.
	 * @param string $launcher_id Launcher id (e.g. ai_draft).
	 * @return string
	 */
	public static function add_handoff_query_args( $url, $launcher_id ) {
		$args = array(
			'rwgc_handoff'  => '1',
			'rwgc_from'     => 'suite',
			'rwgc_launcher' => sanitize_key( $launcher_id ),
		);

		/**
		 * Extra query arguments for workflow deep links (Geo AI, Optimise, Commerce may read these).
		 *
		 * @param array<string, string> $args        Query args to merge.
		 * @param string                $launcher_id Launcher id.
		 * @param string                $url         Original URL.
		 */
		$args = apply_filters( 'rwgc_workflow_handoff_query_args', $args, sanitize_key( $launcher_id ), $url );

		if ( ! is_array( $args ) || empty( $args ) ) {
			return $url;
		}

		return add_query_arg( $args, $url );
	}

	/**
	 * Short copy for Getting Started when a goal is selected (filterable).
	 *
	 * @param string $goal Goal slug from wizard (variants, ai, tests, commerce, foundation).
	 * @return array{headline: string, body: string}
	 */
	public static function get_goal_guidance( $goal ) {
		$goal = sanitize_key( (string) $goal );
		$out  = array(
			'headline' => '',
			'body'     => '',
		);

		switch ( $goal ) {
			case 'variants':
				$out['headline'] = __( 'Path: country-specific pages', 'reactwoo-geocore' );
				$out['body']     = __( 'You need visitor country detection working, then you can add a linked “local version” of a page for one extra country (free tier). Use Create page version when you are ready — routing is configured for you.', 'reactwoo-geocore' );
				break;
			case 'ai':
				$out['headline'] = __( 'Path: AI-assisted localisation', 'reactwoo-geocore' );
				$out['body']     = __( 'Geo AI needs REST enabled in Geo Core and an active Geo AI license for API usage. Create or open a page variant, then use Geo AI to draft localised copy.', 'reactwoo-geocore' );
				break;
			case 'tests':
				$out['headline'] = __( 'Path: split tests', 'reactwoo-geocore' );
				$out['body']     = __( 'Install Geo Optimise, then use Create Test: pick a page, publish a variant, and read reports — Geo Core supplies visitor context for routing and targeting.', 'reactwoo-geocore' );
				break;
			case 'commerce':
				$out['headline'] = __( 'Path: WooCommerce by country', 'reactwoo-geocore' );
				$out['body']     = __( 'You need WooCommerce and Geo Commerce. Pricing and fees are configured in Geo Commerce — not in Geo Core settings.', 'reactwoo-geocore' );
				break;
			case 'foundation':
				$out['headline'] = __( 'Path: geolocation first', 'reactwoo-geocore' );
				$out['body']     = __( 'Save MaxMind credentials, download the country database from Tools, then confirm visitor country on the Dashboard. Other workflows build on this.', 'reactwoo-geocore' );
				break;
			default:
				break;
		}

		/**
		 * Filter goal guidance shown on Getting Started.
		 *
		 * @param array{headline: string, body: string} $out  Guidance.
		 * @param string                                 $goal Goal slug.
		 */
		return apply_filters( 'rwgc_goal_guidance', $out, $goal );
	}

	/**
	 * Re-order launchers so the closest match to the wizard goal appears first (soft UX hint).
	 *
	 * @param array<int, array<string, mixed>> $launchers From get_launchers().
	 * @param string                           $goal      Goal slug.
	 * @return array<int, array<string, mixed>>
	 */
	public static function order_launchers_for_goal( $launchers, $goal ) {
		if ( ! is_array( $launchers ) || '' === (string) $goal ) {
			return $launchers;
		}
		$goal  = sanitize_key( (string) $goal );
		$first = null;
		switch ( $goal ) {
			case 'variants':
				$first = 'create_variant';
				break;
			case 'ai':
				$first = 'ai_draft';
				break;
			case 'tests':
				$first = 'experiment';
				break;
			case 'commerce':
				$first = 'commerce_rule';
				break;
			default:
				return $launchers;
		}
		$idx = null;
		foreach ( $launchers as $i => $l ) {
			if ( ! empty( $l['id'] ) && $first === $l['id'] ) {
				$idx = $i;
				break;
			}
		}
		if ( null === $idx || 0 === $idx ) {
			return $launchers;
		}
		$pick = $launchers[ $idx ];
		unset( $launchers[ $idx ] );
		array_unshift( $launchers, $pick );

		/**
		 * Filter ordered launchers after goal-based reorder.
		 *
		 * @param array<int, array<string, mixed>> $launchers Launchers.
		 * @param string                           $goal      Goal slug.
		 */
		return apply_filters( 'rwgc_workflow_launchers_for_goal', array_values( $launchers ), $goal );
	}

	/**
	 * Recommended next steps after a workflow (placeholders; satellites may filter).
	 *
	 * @param string               $context Context id, e.g. variant_created.
	 * @param array<string, mixed> $ctx Optional payload (page ids, etc.).
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_next_steps( $context, $ctx = array() ) {
		$context = sanitize_key( (string) $context );
		$ctx     = is_array( $ctx ) ? $ctx : array();
		$steps   = array();

		switch ( $context ) {
			case 'variant_created':
				if ( ! empty( $ctx['edit_url'] ) ) {
					$steps[] = array(
						'label' => __( 'Edit local version', 'reactwoo-geocore' ),
						'url'   => (string) $ctx['edit_url'],
						'style' => 'primary',
					);
				}
				if ( class_exists( 'RWGA_Plugin', false ) ) {
					$ai_url = self::add_handoff_query_args( admin_url( 'admin.php?page=rwga-dashboard' ), 'ai_draft' );
					if ( ! empty( $ctx['variant_page_id'] ) ) {
						$ai_url = add_query_arg( 'rwgc_variant_page_id', (int) $ctx['variant_page_id'], $ai_url );
					}
					$steps[] = array(
						'label' => __( 'Generate copy in Geo AI', 'reactwoo-geocore' ),
						'url'   => $ai_url,
						'style' => 'secondary',
					);
				}
				if ( class_exists( 'RWGO_Plugin', false ) ) {
					$ex_url = self::add_handoff_query_args( admin_url( 'admin.php?page=rwgo-create-test' ), 'experiment' );
					if ( ! empty( $ctx['variant_page_id'] ) ) {
						$ex_url = add_query_arg( 'rwgc_variant_page_id', (int) $ctx['variant_page_id'], $ex_url );
					}
					$steps[] = array(
						'label' => __( 'Start a split test', 'reactwoo-geocore' ),
						'url'   => $ex_url,
						'style' => 'secondary',
					);
				}
				$steps[] = array(
					'label' => __( 'Suite Home', 'reactwoo-geocore' ),
					'url'   => admin_url( 'admin.php?page=rwgc-suite-home' ),
					'style' => 'secondary',
				);
				break;
			default:
				break;
		}

		/**
		 * Register contextual next-step links for the suite shell.
		 *
		 * @param array<int, array<string, mixed>> $steps   Each: label, url, style primary|secondary.
		 * @param string                           $context Context slug.
		 * @param array<string, mixed>             $ctx     Arbitrary payload.
		 */
		return apply_filters( 'rwgc_next_steps', $steps, $context, $ctx );
	}
}
