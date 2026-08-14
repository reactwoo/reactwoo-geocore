<?php
/**
 * Option providers for Geo Core's own Elementor controls.
 *
 * Every provider is memoized for the request and bounded, so registering Geo
 * Visibility never issues an unbounded query and never repeats a lookup once
 * per control stack. No provider performs a remote request.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request-scoped, bounded option maps for Geo Core Elementor controls.
 */
class RWGC_Elementor_Options {

	const MAX_LIBRARY_RULES = 200;
	const MAX_MASTER_PAGES  = 200;

	/**
	 * @var array<string, mixed>
	 */
	private static $memo = array();

	/**
	 * ISO country catalogue (code => label).
	 *
	 * @return array<string, string>
	 */
	public static function countries() {
		return self::remember(
			'countries',
			static function () {
				$list = class_exists( 'RWGC_Countries', false ) ? RWGC_Countries::get_options() : array();
				$list = is_array( $list ) ? $list : array();

				/**
				 * Filter the country catalogue used by Geo Core Elementor controls.
				 *
				 * @param array<string, string> $list Country code => label.
				 */
				return (array) apply_filters( 'rwgc_elementor_country_options', $list );
			}
		);
	}

	/**
	 * Country catalogue as Atomic `{ value, label }` rows.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function country_chips() {
		return self::remember(
			'country_chips',
			static function () {
				$rows = array();
				foreach ( self::countries() as $value => $label ) {
					$rows[] = array(
						'value' => (string) $value,
						'label' => (string) $label,
					);
				}
				return $rows;
			}
		);
	}

	/**
	 * Saved visibility rules for the library picker, bounded and enriched.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function visibility_library_rows() {
		return self::remember(
			'library_rows',
			static function () {
				$rows = self::raw_library_rows();
				if ( ! class_exists( 'RWGC_Rule_Context_Compatibility', false ) ) {
					return $rows;
				}

				$context  = class_exists( 'RWGC_Elementor_Elements', false )
					? RWGC_Elementor_Elements::get_editor_document_context()
					: array();
				$enriched = array();
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$json   = isset( $row['json'] ) ? (string) $row['json'] : '';
					$set    = '' !== trim( $json ) ? json_decode( $json, true ) : null;
					$compat = RWGC_Rule_Context_Compatibility::evaluate( is_array( $set ) ? $set : null, $context );
					$reason = ( ! empty( $compat['reasons'] ) && is_array( $compat['reasons'] ) )
						? implode( ' ', $compat['reasons'] )
						: '';

					$row['scope_summary'] = (string) ( $compat['scope_summary'] ?? '' );
					$row['compatibility'] = array(
						'status'  => (string) ( $compat['status'] ?? 'compatible' ),
						'reason'  => $reason,
						'reasons' => ( isset( $compat['reasons'] ) && is_array( $compat['reasons'] ) ) ? $compat['reasons'] : array(),
					);
					$enriched[] = $row;
				}
				return $enriched;
			}
		);
	}

	/**
	 * Library picker options for a classic SELECT (id => title).
	 *
	 * @return array<string, string>
	 */
	public static function visibility_library_select() {
		return self::remember(
			'library_select',
			static function () {
				$options = array( '' => __( '— Choose saved visibility rule —', 'reactwoo-geocore' ) );
				foreach ( self::raw_library_rows() as $row ) {
					if ( empty( $row['id'] ) ) {
						continue;
					}
					$key             = (string) $row['id'];
					$options[ $key ] = isset( $row['title'] ) ? (string) $row['title'] : $key;
				}
				return $options;
			}
		);
	}

	/**
	 * Library picker options as Atomic `{ value, label }` rows.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function visibility_library_chips() {
		return self::remember(
			'library_chips',
			static function () {
				$rows = array();
				foreach ( self::visibility_library_select() as $value => $label ) {
					$rows[] = array(
						'value' => (string) $value,
						'label' => (string) $label,
					);
				}
				return $rows;
			}
		);
	}

	/**
	 * Pages flagged as routing masters (bounded meta query, no per-page meta scan).
	 *
	 * @return array<string, string>
	 */
	public static function master_pages() {
		return self::remember(
			'master_pages',
			static function () {
				$options = array( '' => __( '-- Select master page --', 'reactwoo-geocore' ) );
				if ( ! class_exists( 'RWGC_Routing', false ) || ! function_exists( 'get_posts' ) ) {
					return $options;
				}

				$limit = (int) apply_filters( 'rwgc_elementor_max_master_pages', self::MAX_MASTER_PAGES );
				$ids   = get_posts(
					array(
						'post_type'              => 'page',
						'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
						'posts_per_page'         => max( 1, $limit ),
						'fields'                 => 'ids',
						'orderby'                => 'title',
						'order'                  => 'ASC',
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
						'suppress_filters'       => false,
						'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'AND',
							array(
								'key'   => RWGC_Routing::META_ENABLED,
								'value' => '1',
							),
							array(
								'key'   => RWGC_Routing::META_ROLE,
								'value' => 'master',
							),
						),
					)
				);

				$masters = array();
				foreach ( (array) $ids as $id ) {
					$id    = (int) $id;
					$title = get_the_title( $id );
					$masters[ (string) $id ] = ( '' !== $title ? $title : '#' . $id ) . ' (#' . $id . ')';
				}

				if ( array() === $masters ) {
					return array( '' => __( '-- No enabled master pages found --', 'reactwoo-geocore' ) );
				}
				return $options + $masters;
			}
		);
	}

	/**
	 * Editor "detected for your connection" panel.
	 *
	 * Resolving the visitor can hit a geo provider, so it happens at most once
	 * per request no matter how many control stacks are built.
	 *
	 * @param callable $build Markup producer.
	 * @return string
	 */
	public static function visitor_preview( $build ) {
		return (string) self::remember(
			'visitor_preview',
			static function () use ( $build ) {
				return is_callable( $build ) ? (string) call_user_func( $build ) : '';
			}
		);
	}

	/**
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$memo = array();
	}

	/**
	 * Bounded library rows straight from the registry.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function raw_library_rows() {
		return self::remember(
			'library_raw',
			static function () {
				$rows = array();
				if ( class_exists( 'RWGC_Rule_Registry', false ) ) {
					$rows = RWGC_Rule_Registry::get_library_picker_rows();
				} elseif ( class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
					$rows = RWGC_Visibility_Rule_Repository::get_library_picker_rows();
				}
				$rows = is_array( $rows ) ? array_values( $rows ) : array();

				$limit = (int) apply_filters( 'rwgc_elementor_max_library_rules', self::MAX_LIBRARY_RULES );
				if ( $limit > 0 && count( $rows ) > $limit ) {
					$rows = array_slice( $rows, 0, $limit );
				}
				return $rows;
			}
		);
	}

	/**
	 * @param string   $key   Memo key.
	 * @param callable $build Producer.
	 * @return mixed
	 */
	private static function remember( $key, $build ) {
		if ( array_key_exists( $key, self::$memo ) ) {
			return self::$memo[ $key ];
		}

		if ( class_exists( 'RWGC_Elementor_Profiler', false ) ) {
			self::$memo[ $key ] = RWGC_Elementor_Profiler::measure(
				'RWGC_Elementor_Options::' . $key,
				$build
			);
			return self::$memo[ $key ];
		}

		self::$memo[ $key ] = $build();
		return self::$memo[ $key ];
	}
}
