<?php
/**
 * Compact site intelligence snapshot for Geo AI cloud workflows.
 *
 * Collects geo configuration metadata only — never full page content, Elementor JSON, or PII.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds normalized, hashable site intelligence payloads for Geo AI.
 */
class RWGC_AI_Snapshot_Builder {

	/**
	 * Build a full site intelligence snapshot.
	 *
	 * @param array<string, mixed> $context Optional builder context (reserved for admin preview overrides).
	 * @return array<string, mixed>
	 */
	public static function build( array $context = array() ) {
		$payload = array(
			'schema_version'     => RWGC_AI_Snapshot_Schema::get_version(),
			'generated_at_gmt'   => gmdate( 'c' ),
			'site'               => self::collect_site_metadata(),
			'plugins'            => self::collect_plugin_versions(),
			'modules'            => self::collect_modules(),
			'target_providers'   => self::collect_target_providers(),
			'rules'              => self::collect_rules(),
			'conditions'         => self::collect_conditions_catalog(),
			'variants'           => self::collect_variants(),
			'parent_pages'       => self::collect_parent_pages(),
			'popups'             => self::collect_popups(),
			'forms'              => self::collect_forms(),
			'tracking_events'    => self::collect_tracking_events(),
			'conversion_events'  => self::collect_conversion_events(),
			'relationships'      => self::collect_relationships(),
		);

		/**
		 * Filter the site intelligence snapshot before normalization and hashing.
		 *
		 * Satellites may append compact rows; do not include page content, Elementor JSON, or PII.
		 *
		 * @param array<string, mixed> $payload Full snapshot payload.
		 * @param array<string, mixed> $context  Builder context.
		 */
		$payload = apply_filters( 'rwgc_ai_snapshot_payload', $payload, $context );

		$payload = RWGC_AI_Snapshot_Schema::normalize( $payload );
		$payload['snapshot_hash'] = RWGC_AI_Snapshot_Schema::compute_hash( $payload );

		RWGC_AI_Snapshot_Sync_Status::record_build( $payload['snapshot_hash'] );

		return $payload;
	}

	/**
	 * @return string
	 */
	public static function get_hash() {
		$payload = self::build();
		return isset( $payload['snapshot_hash'] ) ? (string) $payload['snapshot_hash'] : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_site_metadata() {
		$site = array(
			'url'       => home_url( '/' ),
			'name'      => get_bloginfo( 'name' ),
			'language'  => get_bloginfo( 'language' ),
			'timezone'  => wp_timezone_string(),
			'is_multisite' => is_multisite(),
			'pro_enabled'  => function_exists( 'rwgc_is_pro_enabled' ) && rwgc_is_pro_enabled(),
		);

		if ( function_exists( 'rwga_get_site_uuid' ) ) {
			$site['site_uuid'] = sanitize_text_field( (string) rwga_get_site_uuid() );
		}

		return $site;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collect_plugin_versions() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all = get_plugins();
		$geo_slugs = array(
			'reactwoo-geocore/reactwoo-geocore.php'         => 'geocore',
			'reactwoo-geocore-pro/reactwoo-geocore-pro.php' => 'geocore_pro',
			'reactwoo-geo-ai/reactwoo-geo-ai.php'           => 'geo_ai',
			'reactwoo-geo-optimise/reactwoo-geo-optimise.php' => 'geo_optimise',
			'reactwoo-geo-commerce/reactwoo-geo-commerce.php' => 'geo_commerce',
		);

		$satellites = array();
		foreach ( $geo_slugs as $file => $id ) {
			$active = function_exists( 'is_plugin_active' ) && is_plugin_active( $file );
			$version = '';
			if ( isset( $all[ $file ]['Version'] ) ) {
				$version = (string) $all[ $file ]['Version'];
			}
			$satellites[] = array(
				'id'      => $id,
				'active'  => $active,
				'version' => $version,
			);
		}

		return array(
			'geocore_version' => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
			'satellites'      => $satellites,
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_modules() {
		if ( ! class_exists( 'RWGC_Module_Registry', false ) ) {
			return array();
		}

		$rows = array();
		foreach ( RWGC_Module_Registry::get_registered_modules() as $mod ) {
			if ( ! is_array( $mod ) || empty( $mod['id'] ) ) {
				continue;
			}
			$active = false;
			if ( isset( $mod['is_active_callback'] ) && is_callable( $mod['is_active_callback'] ) ) {
				$active = (bool) call_user_func( $mod['is_active_callback'] );
			} else {
				$active = ! empty( $mod['active'] );
			}
			$rows[] = array(
				'id'       => sanitize_key( (string) $mod['id'] ),
				'label'    => isset( $mod['label'] ) ? (string) $mod['label'] : '',
				'category' => isset( $mod['category'] ) ? sanitize_key( (string) $mod['category'] ) : '',
				'active'   => $active,
			);
		}
		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_target_providers() {
		if ( ! class_exists( 'RWGC_Target_Registry', false ) ) {
			return array();
		}

		RWGC_Target_Registry::init();
		$rows    = array();
		$classes = apply_filters(
			'rwgc_target_provider_classes',
			array(
				'RWGC_Target_Provider_Geo',
				'RWGC_Target_Provider_Language',
				'RWGC_Target_Provider_Time',
				'RWGC_Target_Provider_Device',
				'RWGC_Target_Provider_Weather',
				'RWGC_Target_Provider_Analytics',
				'RWGC_Target_Provider_Commerce',
			)
		);

		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			$obj = new $class();
			if ( ! $obj instanceof RWGC_Target_Provider_Interface ) {
				continue;
			}
			$status = $obj->get_admin_status();
			$rows[] = array(
				'key'       => $obj->get_provider_key(),
				'available' => $obj->is_available(),
				'label'     => isset( $status['label'] ) ? (string) $status['label'] : $obj->get_provider_key(),
				'status'    => isset( $status['status'] ) ? sanitize_key( (string) $status['status'] ) : '',
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_rules() {
		$rules = array();

		if ( class_exists( 'RWGC_Rule_Registry', false ) ) {
			foreach ( RWGC_Rule_Registry::get_rules_for_builder() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$id = isset( $row['id'] ) ? (string) $row['id'] : '';
				if ( '' === $id ) {
					continue;
				}
				$summary = RWGC_AI_Snapshot_Schema::summarize_rule_set(
					isset( $row['rules'] ) && is_array( $row['rules'] ) ? $row['rules'] : null
				);
				$rules[] = array(
					'id'           => $id,
					'source'       => isset( $row['source'] ) ? sanitize_key( (string) $row['source'] ) : '',
					'label'        => isset( $row['label'] ) ? (string) $row['label'] : '',
					'enabled'      => $summary ? $summary['enabled'] : true,
					'mode'         => $summary ? $summary['mode'] : 'show_if',
					'rule_count'   => $summary ? $summary['rule_count'] : 0,
					'conditions'   => $summary ? $summary['conditions'] : array(),
				);
			}
		}

		if ( class_exists( 'RWGC_Admin_Targeting_Rules_Index', false ) ) {
			foreach ( RWGC_Admin_Targeting_Rules_Index::get_rows() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$rules[] = array(
					'id'         => isset( $row['source_key'] ) ? sanitize_key( (string) $row['source_key'] ) . ':' . sanitize_title( (string) ( $row['name'] ?? '' ) ) : '',
					'source'     => isset( $row['source_key'] ) ? sanitize_key( (string) $row['source_key'] ) : '',
					'label'      => isset( $row['name'] ) ? (string) $row['name'] : '',
					'location'   => isset( $row['location'] ) ? (string) $row['location'] : '',
					'targeting'  => isset( $row['targeting'] ) ? (string) $row['targeting'] : '',
					'status'     => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '',
					'rule_scope' => isset( $row['rule_scope'] ) ? sanitize_key( (string) $row['rule_scope'] ) : '',
				);
			}
		}

		return $rules;
	}

	/**
	 * Unique condition types used across portable rules.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_conditions_catalog() {
		$types = array();

		if ( class_exists( 'RWGC_Rule_Registry', false ) ) {
			foreach ( RWGC_Rule_Registry::get_rules_for_builder() as $row ) {
				if ( empty( $row['rules'] ) || ! is_array( $row['rules'] ) ) {
					continue;
				}
				$summary = RWGC_AI_Snapshot_Schema::summarize_rule_set( $row['rules'] );
				if ( empty( $summary['conditions'] ) ) {
					continue;
				}
				foreach ( $summary['conditions'] as $cond ) {
					$type = isset( $cond['type'] ) ? (string) $cond['type'] : '';
					if ( '' === $type ) {
						continue;
					}
					if ( ! isset( $types[ $type ] ) ) {
						$types[ $type ] = array(
							'type'  => $type,
							'count' => 0,
						);
					}
					$types[ $type ]['count'] += isset( $cond['count'] ) ? (int) $cond['count'] : 1;
				}
			}
		}

		if ( class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			$free = RWGC_Targeting_Rule_Set_Schema::FREE_CONDITION_TYPES;
			$pro  = RWGC_Targeting_Rule_Set_Schema::PRO_CONDITION_TYPES;
			foreach ( array_merge( $free, $pro ) as $builtin ) {
				if ( ! isset( $types[ $builtin ] ) ) {
					$types[ $builtin ] = array(
						'type'    => $builtin,
						'count'   => 0,
						'builtin' => true,
					);
				}
			}
		}

		return array_values( $types );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_variants() {
		if ( ! class_exists( 'RWGC_Variant_Manager', false ) ) {
			return array();
		}

		$rows = array();
		foreach ( RWGC_Variant_Manager::get_routing_overview_rows( 100 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$variant = isset( $row['variant'] ) && is_array( $row['variant'] ) ? $row['variant'] : array();
			$rows[]  = array(
				'master_page_id'  => isset( $row['master_id'] ) ? (int) $row['master_id'] : 0,
				'master_title'    => isset( $row['master_title'] ) ? (string) $row['master_title'] : '',
				'variant_page_id' => isset( $variant['variant_id'] ) ? (int) $variant['variant_id'] : 0,
				'variant_title'   => isset( $variant['variant_title'] ) ? (string) $variant['variant_title'] : '',
				'country_iso2'    => isset( $variant['country_iso2'] ) ? strtoupper( sanitize_text_field( (string) $variant['country_iso2'] ) ) : '',
			);
		}
		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_parent_pages() {
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return array();
		}

		$masters = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => RWGC_Routing::META_ENABLED,
						'value' => '1',
					),
					array(
						'key'   => RWGC_Routing::META_ROLE,
						'value' => 'master',
					),
				),
				'fields'         => 'ids',
			)
		);

		$rows = array();
		foreach ( $masters as $page_id ) {
			$page_id = (int) $page_id;
			$config  = RWGC_Routing::get_page_route_config( $page_id );
			$rows[]  = array(
				'page_id'    => $page_id,
				'title'      => get_the_title( $page_id ),
				'slug'       => get_post_field( 'post_name', $page_id ),
				'enabled'    => ! empty( $config['enabled'] ),
				'role'       => isset( $config['role'] ) ? sanitize_key( (string) $config['role'] ) : 'master',
			);
		}
		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_popups() {
		if ( ! post_type_exists( 'elementor_library' ) ) {
			return array();
		}

		$popups = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 100,
				'meta_query'     => array(
					array(
						'key'   => '_elementor_template_type',
						'value' => 'popup',
					),
				),
			)
		);

		$rows = array();
		foreach ( $popups as $popup ) {
			if ( ! $popup instanceof WP_Post ) {
				continue;
			}
			$settings = get_post_meta( (int) $popup->ID, '_elementor_page_settings', true );
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}

			$rule_id = '';
			if ( ! empty( $settings['rwgc_visibility_rule_library'] ) ) {
				$rule_id = (string) $settings['rwgc_visibility_rule_library'];
			} elseif ( ! empty( $settings['rwgc_applied_visibility_rule_id'] ) ) {
				$rule_id = (string) $settings['rwgc_applied_visibility_rule_id'];
			}

			$rows[] = array(
				'popup_id'   => (int) $popup->ID,
				'title'      => $popup->post_title,
				'status'     => $popup->post_status,
				'rule_id'    => $rule_id,
				'visibility_mode' => isset( $settings['rwgc_visibility_rules_mode'] )
					? sanitize_key( (string) $settings['rwgc_visibility_rules_mode'] )
					: 'show_if',
			);
		}
		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_forms() {
		$rows = array();

		if ( post_type_exists( 'wpcf7_contact_form' ) ) {
			$forms = get_posts(
				array(
					'post_type'      => 'wpcf7_contact_form',
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'fields'         => 'ids',
				)
			);
			foreach ( $forms as $form_id ) {
				$rows[] = array(
					'form_id' => (int) $form_id,
					'title'   => get_the_title( (int) $form_id ),
					'source'  => 'contact_form_7',
				);
			}
		}

		// Elementor form widgets — detect presence via meta LIKE without loading full JSON.
		$elementor_form_pages = get_posts(
			array(
				'post_type'      => array( 'page', 'post', 'elementor_library' ),
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 30,
				'meta_query'     => array(
					array(
						'key'     => '_elementor_data',
						'value'   => '"widgetType":"form"',
						'compare' => 'LIKE',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $elementor_form_pages as $page_id ) {
			$rows[] = array(
				'form_id' => (int) $page_id,
				'title'   => get_the_title( (int) $page_id ),
				'source'  => 'elementor_form_widget',
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_tracking_events() {
		$types = function_exists( 'rwgc_get_geo_event_types' ) ? rwgc_get_geo_event_types() : array();
		$conversion = array( 'conversion', 'purchase' );
		$rows = array();

		foreach ( $types as $type ) {
			$type = sanitize_key( (string) $type );
			if ( '' === $type || in_array( $type, $conversion, true ) ) {
				continue;
			}
			$rows[] = array(
				'event_type' => $type,
				'category'   => 'tracking',
			);
		}
		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_conversion_events() {
		$types = function_exists( 'rwgc_get_geo_event_types' ) ? rwgc_get_geo_event_types() : array();
		$conversion = array( 'conversion', 'purchase' );
		$rows = array();

		foreach ( $types as $type ) {
			$type = sanitize_key( (string) $type );
			if ( '' === $type || ! in_array( $type, $conversion, true ) ) {
				continue;
			}
			$rows[] = array(
				'event_type' => $type,
				'category'   => 'conversion',
			);
		}
		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_relationships() {
		$rels = array();

		// Master → variant edges.
		foreach ( self::collect_variants() as $variant ) {
			$master = isset( $variant['master_page_id'] ) ? (int) $variant['master_page_id'] : 0;
			$child  = isset( $variant['variant_page_id'] ) ? (int) $variant['variant_page_id'] : 0;
			if ( $master <= 0 || $child <= 0 ) {
				continue;
			}
			$rels[] = array(
				'type'       => 'variant_of',
				'from_type'  => 'page',
				'from_id'    => (string) $child,
				'to_type'    => 'page',
				'to_id'      => (string) $master,
				'meta'       => array(
					'country_iso2' => isset( $variant['country_iso2'] ) ? (string) $variant['country_iso2'] : '',
				),
			);
		}

		// Rule → popup edges.
		if ( class_exists( 'RWGC_Variant_Rule_Applications', false ) && class_exists( 'RWGC_Visibility_Rule_CPT', false ) ) {
			$rule_posts = get_posts(
				array(
					'post_type'      => RWGC_Visibility_Rule_CPT::POST_TYPE,
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => 100,
					'fields'         => 'ids',
				)
			);
			foreach ( $rule_posts as $rule_id ) {
				$rule_id = (int) $rule_id;
				foreach ( RWGC_Variant_Rule_Applications::discover_references( $rule_id ) as $ref ) {
					if ( ! is_array( $ref ) || empty( $ref['type'] ) || empty( $ref['id'] ) ) {
						continue;
					}
					$rels[] = array(
						'type'      => 'controls',
						'from_type' => 'rule',
						'from_id'   => (string) $rule_id,
						'to_type'   => sanitize_key( (string) $ref['type'] ),
						'to_id'     => (string) (int) $ref['id'],
					);
				}
			}
		}

		/**
		 * Append relationship edges for AI graph building.
		 *
		 * @param array<int, array<string, mixed>> $rels Relationship rows.
		 */
		return apply_filters( 'rwgc_ai_snapshot_relationships', $rels );
	}
}
