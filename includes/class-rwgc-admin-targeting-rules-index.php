<?php
/**
 * Unified targeting rules index (Targeting → Rules).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates portable, Elementor, routing, and commerce targeting rules for one list UI.
 */
class RWGC_Admin_Targeting_Rules_Index {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_rows() {
		$rows = array();

		// Builder-attached rules only — portable library CPT is listed separately on the same screen.
		$rows = array_merge( $rows, self::get_geo_elementor_rows() );
		$rows = array_merge( $rows, self::get_gutenberg_post_rows() );
		$rows = array_merge( $rows, self::get_page_routing_rows() );
		$rows = array_merge( $rows, self::get_commerce_rule_rows() );

		/**
		 * @param array<int, array<string, mixed>> $rows Rule rows for Targeting → Rules.
		 */
		$rows = apply_filters( 'rwgc_targeting_rules_index_rows', $rows );

		usort(
			$rows,
			static function ( $a, $b ) {
				$ta = isset( $a['updated_ts'] ) ? (int) $a['updated_ts'] : 0;
				$tb = isset( $b['updated_ts'] ) ? (int) $b['updated_ts'] : 0;
				return $tb <=> $ta;
			}
		);

		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_geo_elementor_rows() {
		if ( ! post_type_exists( 'geo_rule' ) ) {
			return array();
		}

		$out   = array();
		$posts = get_posts(
			array(
				'post_type'      => 'geo_rule',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		foreach ( $posts as $rule_post ) {
			if ( ! $rule_post instanceof WP_Post ) {
				continue;
			}

			$target_type = (string) get_post_meta( (int) $rule_post->ID, 'egp_target_type', true );
			$target_id   = (int) get_post_meta( (int) $rule_post->ID, 'egp_target_id', true );
			$countries   = get_post_meta( (int) $rule_post->ID, 'egp_countries', true );
			$is_active   = '1' === (string) get_post_meta( (int) $rule_post->ID, 'egp_active', true ) || 'publish' === $rule_post->post_status;

			$country_label = self::summarize_portable_meta( (int) $rule_post->ID, 'egp_portable_targeting' );
			if ( __( 'No conditions saved', 'reactwoo-geocore' ) === $country_label && is_array( $countries ) && ! empty( $countries ) ) {
				$country_label = implode( ', ', array_map( 'strval', $countries ) );
			} elseif ( __( 'No conditions saved', 'reactwoo-geocore' ) === $country_label ) {
				$country_label = __( 'Any visitor', 'reactwoo-geocore' );
			}

			$location   = self::format_elementor_target( $target_type, $target_id );
			$edit       = self::resolve_builder_edit_action( 'elementor', (int) $rule_post->ID, $target_type, $target_id );

			$out[] = array(
				'name'         => $rule_post->post_title,
				'source'       => __( 'Elementor', 'reactwoo-geocore' ),
				'source_key'   => 'elementor',
				'rule_scope'   => __( 'Builder', 'reactwoo-geocore' ),
				'location'     => $location,
				'targeting'    => $country_label,
				'status'       => $is_active ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'      => get_the_modified_date( '', $rule_post ),
				'updated_ts'   => (int) get_post_modified_time( 'U', true, $rule_post ),
				'edit_url'     => $edit['url'],
				'edit_label'   => $edit['label'],
				'action_note'  => $edit['note'],
			);
		}

		return $out;
	}

	/**
	 * Gutenberg / block editor post-level geo visibility (not the portable library).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_gutenberg_post_rows() {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_rwgc_post_country_enabled',
						'value' => 'yes',
					),
					array(
						'key'   => '_rwgc_post_visibility_rules_enabled',
						'value' => 'yes',
					),
					array(
						'key'   => '_rwgc_post_geo_enabled',
						'value' => 'yes',
					),
				),
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$summary = __( 'Post geo visibility', 'reactwoo-geocore' );
			if ( class_exists( 'RWGC_Surface_Settings', false ) ) {
				$settings = RWGC_Surface_Settings::from_post_meta( (int) $post->ID );
				if ( is_array( $settings ) && class_exists( 'RWGC_Targeting_Surface_Evaluator', false )
					&& RWGC_Targeting_Surface_Evaluator::is_surface_active( $settings ) ) {
					$raw = '';
					if ( ! empty( $settings['rwgc_portable_geo_targeting'] ) ) {
						$raw = (string) $settings['rwgc_portable_geo_targeting'];
					}
					if ( '' !== trim( $raw ) ) {
						$summary = self::summarize_portable_meta( (int) $post->ID, '_rwgc_post_portable_targeting' );
					} elseif ( ! empty( $settings['egp_countries'] ) && is_array( $settings['egp_countries'] ) ) {
						$summary = implode( ', ', array_map( 'strval', $settings['egp_countries'] ) );
					}
				}
			}

			$edit = self::resolve_builder_edit_action( 'gutenberg', (int) $post->ID, 'post', (int) $post->ID );

			$out[] = array(
				'name'         => $post->post_title,
				'source'       => __( 'Gutenberg', 'reactwoo-geocore' ),
				'source_key'   => 'gutenberg',
				'rule_scope'   => __( 'Builder', 'reactwoo-geocore' ),
				'location'     => sprintf(
					/* translators: %s: post type singular label */
					__( 'Post — %s', 'reactwoo-geocore' ),
					$post->post_type
				),
				'targeting'    => $summary,
				'status'       => 'publish' === $post->post_status ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'      => get_the_modified_date( '', $post ),
				'updated_ts'   => (int) get_post_modified_time( 'U', true, $post ),
				'edit_url'     => $edit['url'],
				'edit_label'   => $edit['label'],
				'action_note'  => $edit['note'],
			);
		}

		return $out;
	}

	/**
	 * Resolve where to edit a builder-attached rule (deep link when possible).
	 *
	 * @param string $builder   elementor|gutenberg.
	 * @param int    $context_id Rule post id (elementor) or content post id (gutenberg).
	 * @param string $target_type Elementor target type slug.
	 * @param int    $target_id   Elementor target post id.
	 * @return array{url:string,label:string,note:string}
	 */
	private static function resolve_builder_edit_action( $builder, $context_id, $target_type, $target_id ) {
		$builder     = sanitize_key( (string) $builder );
		$context_id  = absint( $context_id );
		$target_type = sanitize_key( (string) $target_type );
		$target_id   = absint( $target_id );

		if ( 'gutenberg' === $builder && $context_id > 0 ) {
			$edit_link = get_edit_post_link( $context_id, 'raw' );
			return array(
				'url'   => is_string( $edit_link ) ? $edit_link : '',
				'label' => __( 'Edit in editor', 'reactwoo-geocore' ),
				'note'  => __( 'Open the post and use the Geo panel in the block editor sidebar.', 'reactwoo-geocore' ),
			);
		}

		if ( 'elementor' !== $builder ) {
			return array(
				'url'   => '',
				'label' => '',
				'note'  => __( 'Edit this rule in the builder where it was created.', 'reactwoo-geocore' ),
			);
		}

		$document_id = (int) get_post_meta( $context_id, 'egp_elementor_document_id', true );
		$element_ref = trim( (string) get_post_meta( $context_id, 'egp_element_id', true ) );
		$canvas_id   = $document_id > 0 ? $document_id : $target_id;

		if ( $canvas_id > 0 && self::post_supports_elementor_canvas( $canvas_id ) ) {
			$url = admin_url( 'post.php?post=' . $canvas_id . '&action=elementor' );
			if ( '' !== $element_ref ) {
				$url .= '#element-' . rawurlencode( $element_ref );
			}
			$label = '' !== $element_ref
				? __( 'Edit in Elementor', 'reactwoo-geocore' )
				: __( 'Open in Elementor', 'reactwoo-geocore' );
			$note  = __( 'Geo targeting is on the selected element or document — use the Geo Targeting panel in Elementor.', 'reactwoo-geocore' );
			if ( 'popup' === $target_type && $target_id > 0 && $target_id !== $canvas_id ) {
				$note = __( 'Opens the page that hosts this popup trigger; edit the popup template from Elementor → Templates if needed.', 'reactwoo-geocore' );
			}
			return array(
				'url'   => $url,
				'label' => $label,
				'note'  => $note,
			);
		}

		if ( $target_id > 0 ) {
			$view = get_permalink( $target_id );
			if ( is_string( $view ) && '' !== $view ) {
				return array(
					'url'   => $view,
					'label' => __( 'View page', 'reactwoo-geocore' ),
					'note'  => __( 'This rule is tied to live content — edit geo settings in Elementor on that page or template.', 'reactwoo-geocore' ),
				);
			}
			$edit_link = get_edit_post_link( $target_id, 'raw' );
			if ( is_string( $edit_link ) && '' !== $edit_link ) {
				return array(
					'url'   => $edit_link,
					'label' => __( 'Edit page', 'reactwoo-geocore' ),
					'note'  => __( 'Open the WordPress editor for the target, then launch Elementor to adjust geo targeting.', 'reactwoo-geocore' ),
				);
			}
		}

		return array(
			'url'   => $context_id > 0 ? (string) get_edit_post_link( $context_id, 'raw' ) : '',
			'label' => __( 'Edit rule record', 'reactwoo-geocore' ),
			'note'  => __( 'This legacy rule record does not have a canvas link — recreate or edit targeting in Elementor on the live page.', 'reactwoo-geocore' ),
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function post_supports_elementor_canvas( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( class_exists( '\Elementor\Plugin', false ) ) {
			$document = \Elementor\Plugin::$instance->documents->get( $post_id );
			if ( $document && method_exists( $document, 'is_built_with_elementor' ) && $document->is_built_with_elementor() ) {
				return true;
			}
		}
		return 'builder' === (string) get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	/**
	 * @param string $target_type Target type slug.
	 * @param int    $target_id   Target post/term id.
	 * @return string
	 */
	private static function format_elementor_target( $target_type, $target_id ) {
		$type_label = $target_type ? ucwords( str_replace( '_', ' ', $target_type ) ) : __( 'Content', 'reactwoo-geocore' );
		if ( $target_id > 0 ) {
			$target_post = get_post( $target_id );
			if ( $target_post ) {
				return sprintf(
					/* translators: 1: content type 2: title */
					__( '%1$s — %2$s', 'reactwoo-geocore' ),
					$type_label,
					$target_post->post_title
				);
			}
		}
		return $type_label;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_page_routing_rows() {
		if ( ! class_exists( 'RWGC_Variant_Manager', false ) ) {
			return array();
		}

		$overview = RWGC_Variant_Manager::get_routing_overview_rows();
		if ( ! is_array( $overview ) ) {
			return array();
		}

		$out = array();
		foreach ( $overview as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$condition = ! empty( $row['variant']['country_iso2'] )
				? (string) $row['variant']['country_iso2']
				: __( 'Default visitor', 'reactwoo-geocore' );
			$target    = ! empty( $row['variant']['variant_title'] )
				? (string) $row['variant']['variant_title']
				: __( 'Default page', 'reactwoo-geocore' );

			$master_id = (int) ( $row['master_id'] ?? 0 );
			$out[]     = array(
				'name'         => sprintf(
					/* translators: %s page title */
					__( 'Route %s', 'reactwoo-geocore' ),
					(string) ( $row['master_title'] ?? '' )
				),
				'source'       => __( 'Geo Core', 'reactwoo-geocore' ),
				'source_key'   => 'geocore',
				'rule_scope'   => __( 'Builder', 'reactwoo-geocore' ),
				'location'     => (string) ( $row['master_title'] ?? __( 'Page', 'reactwoo-geocore' ) ),
				'targeting'    => $condition,
				'status'       => __( 'Active', 'reactwoo-geocore' ),
				'updated'      => '',
				'updated_ts'   => 0,
				'edit_url'     => admin_url( 'admin.php?page=rwgc-workflow-variant&rwgc_master_page_id=' . $master_id ),
				'edit_label'   => __( 'Edit route', 'reactwoo-geocore' ),
				'action_note'  => $master_id > 0
					? __( 'Page version routing is configured on the master page record.', 'reactwoo-geocore' )
					: '',
			);
		}

		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_commerce_rule_rows() {
		if ( ! class_exists( 'RWGCM_Rule_Store', false ) ) {
			return array();
		}

		$out = array();
		foreach ( RWGCM_Rule_Store::get_all_rules() as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$name = isset( $rule['name'] ) ? (string) $rule['name'] : __( 'Commerce rule', 'reactwoo-geocore' );
			$type = isset( $rule['rule_type'] ) ? (string) $rule['rule_type'] : '';
			$out[] = array(
				'name'         => $name,
				'source'       => __( 'Geo Commerce', 'reactwoo-geocore' ),
				'source_key'   => 'commerce',
				'rule_scope'   => __( 'Builder', 'reactwoo-geocore' ),
				'location'     => $type ? ucwords( str_replace( '_', ' ', $type ) ) : __( 'Store', 'reactwoo-geocore' ),
				'targeting'    => self::summarize_commerce_rule( $rule ),
				'status'       => ( ! empty( $rule['status'] ) && 'active' === $rule['status'] ) ? __( 'Active', 'reactwoo-geocore' ) : __( 'Draft', 'reactwoo-geocore' ),
				'updated'      => isset( $rule['updated_at'] ) ? (string) $rule['updated_at'] : '',
				'updated_ts'   => isset( $rule['updated_at'] ) ? strtotime( (string) $rule['updated_at'] ) : 0,
				'edit_url'     => admin_url( 'admin.php?page=rwgcm-pricing' ),
				'edit_label'   => __( 'Open commerce rules', 'reactwoo-geocore' ),
				'action_note'  => __( 'Managed in the Geo Commerce plugin.', 'reactwoo-geocore' ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $rule Commerce rule row.
	 * @return string
	 */
	private static function summarize_commerce_rule( array $rule ) {
		if ( ! empty( $rule['conditions'] ) && is_array( $rule['conditions'] ) ) {
			return sprintf(
				/* translators: %d condition count */
				_n( '%d condition', '%d conditions', count( $rule['conditions'] ), 'reactwoo-geocore' ),
				count( $rule['conditions'] )
			);
		}
		return __( 'Geo visitor conditions', 'reactwoo-geocore' );
	}

	/**
	 * @param int    $post_id Post id.
	 * @param string $meta_key Meta key for portable JSON.
	 * @return string
	 */
	public static function summarize_portable_meta( $post_id, $meta_key ) {
		$raw = (string) get_post_meta( $post_id, $meta_key, true );
		if ( '' === trim( $raw ) ) {
			return __( 'No conditions saved', 'reactwoo-geocore' );
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return __( 'Custom conditions', 'reactwoo-geocore' );
		}
		if ( ! empty( $decoded['groups'] ) && is_array( $decoded['groups'] ) ) {
			$count = 0;
			foreach ( $decoded['groups'] as $group ) {
				if ( is_array( $group ) && ! empty( $group['conditions'] ) && is_array( $group['conditions'] ) ) {
					$count += count( $group['conditions'] );
				}
			}
			if ( $count > 0 ) {
				return sprintf(
					/* translators: %d condition count */
					_n( '%d condition', '%d conditions', $count, 'reactwoo-geocore' ),
					$count
				);
			}
		}
		return __( 'Portable targeting rules', 'reactwoo-geocore' );
	}
}
