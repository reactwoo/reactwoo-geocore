<?php
/**
 * Variant rule provenance, surface applications, and reference discovery.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks where a visibility rule was created vs where it is applied.
 */
class RWGC_Variant_Rule_Applications {

	const META_SOURCE_TYPE     = '_rwgc_rule_source_type';
	const META_SOURCE_PAGE_ID  = '_rwgc_rule_source_page_id';
	const META_SOURCE_VARIANT  = '_rwgc_rule_source_variant';
	const META_SOURCE_URL      = '_rwgc_rule_source_url';
	const META_CREATED_FROM    = '_rwgc_rule_created_from';
	const META_APPLICATIONS    = '_rwgc_rule_applications';
	const META_LIFECYCLE       = '_rwgc_rule_lifecycle';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 11 );
		add_action( 'admin_post_rwgc_sync_variant_rule_surfaces', array( __CLASS__, 'handle_sync_surfaces' ) );
	}

	/**
	 * @return void
	 */
	public static function register_meta() {
		if ( ! class_exists( 'RWGC_Visibility_Rule_CPT', false ) ) {
			return;
		}

		$post_type = RWGC_Visibility_Rule_CPT::POST_TYPE;
		$auth      = static function () {
			if ( class_exists( 'RWGC_Admin', false ) ) {
				return current_user_can( RWGC_Admin::required_capability() );
			}
			return current_user_can( 'manage_options' );
		};

		foreach (
			array(
				self::META_SOURCE_TYPE    => 'string',
				self::META_SOURCE_VARIANT => 'string',
				self::META_SOURCE_URL     => 'string',
				self::META_CREATED_FROM   => 'string',
				self::META_LIFECYCLE      => 'string',
			) as $key => $type
		) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => $auth,
					'show_in_rest'      => false,
				)
			);
		}

		register_post_meta(
			$post_type,
			self::META_SOURCE_PAGE_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			)
		);

		register_post_meta(
			$post_type,
			self::META_APPLICATIONS,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_applications_json' ),
				'auth_callback'     => $auth,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * @param mixed $value Raw JSON.
	 * @return string
	 */
	public static function sanitize_applications_json( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '[]';
		}
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			return '[]';
		}
		$out = array();
		foreach ( $decoded as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$surface = sanitize_key( (string) ( $row['surface'] ?? '' ) );
			$id      = absint( $row['id'] ?? 0 );
			if ( '' === $surface || $id <= 0 ) {
				continue;
			}
			$out[] = array(
				'surface'        => $surface,
				'id'             => $id,
				'visibilityMode' => function_exists( 'rwgc_normalize_visibility_mode' )
					? rwgc_normalize_visibility_mode( (string) ( $row['visibilityMode'] ?? 'show_if' ) )
					: 'show_if',
				'status'         => sanitize_key( (string) ( $row['status'] ?? 'applied' ) ),
			);
		}
		$encoded = wp_json_encode( array_values( $out ) );
		return is_string( $encoded ) ? $encoded : '[]';
	}

	/**
	 * @param int $rule_id Visibility rule post ID.
	 * @return array<string, mixed>
	 */
	public static function get_provenance( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 ) {
			return array();
		}

		return array(
			'ruleId'         => $rule_id,
			'sourceType'     => (string) get_post_meta( $rule_id, self::META_SOURCE_TYPE, true ),
			'sourcePageId'   => (int) get_post_meta( $rule_id, self::META_SOURCE_PAGE_ID, true ),
			'sourceVariant'  => (string) get_post_meta( $rule_id, self::META_SOURCE_VARIANT, true ),
			'sourceUrl'      => (string) get_post_meta( $rule_id, self::META_SOURCE_URL, true ),
			'createdFrom'    => (string) get_post_meta( $rule_id, self::META_CREATED_FROM, true ),
			'lifecycle'      => (string) get_post_meta( $rule_id, self::META_LIFECYCLE, true ),
			'applications'   => self::get_applications( $rule_id ),
		);
	}

	/**
	 * @param int $rule_id Rule post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_applications( $rule_id ) {
		$raw = get_post_meta( absint( $rule_id ), self::META_APPLICATIONS, true );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param int                  $rule_id Rule post ID.
	 * @param array<string, mixed> $provenance Provenance fields.
	 * @return void
	 */
	public static function save_provenance( $rule_id, array $provenance ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 ) {
			return;
		}

		if ( isset( $provenance['sourceType'] ) ) {
			update_post_meta( $rule_id, self::META_SOURCE_TYPE, sanitize_key( (string) $provenance['sourceType'] ) );
		}
		if ( isset( $provenance['sourcePageId'] ) ) {
			update_post_meta( $rule_id, self::META_SOURCE_PAGE_ID, absint( $provenance['sourcePageId'] ) );
		}
		if ( isset( $provenance['sourceVariant'] ) ) {
			update_post_meta( $rule_id, self::META_SOURCE_VARIANT, sanitize_key( (string) $provenance['sourceVariant'] ) );
		}
		if ( isset( $provenance['sourceUrl'] ) ) {
			update_post_meta( $rule_id, self::META_SOURCE_URL, esc_url_raw( (string) $provenance['sourceUrl'] ) );
		}
		if ( isset( $provenance['createdFrom'] ) ) {
			update_post_meta( $rule_id, self::META_CREATED_FROM, sanitize_key( (string) $provenance['createdFrom'] ) );
		}
		if ( isset( $provenance['lifecycle'] ) ) {
			update_post_meta( $rule_id, self::META_LIFECYCLE, sanitize_key( (string) $provenance['lifecycle'] ) );
		}
		if ( isset( $provenance['applications'] ) && is_array( $provenance['applications'] ) ) {
			update_post_meta( $rule_id, self::META_APPLICATIONS, self::sanitize_applications_json( wp_json_encode( $provenance['applications'] ) ) );
		}
	}

	/**
	 * Discover Elementor popups (and pages) that reference a library rule ID.
	 *
	 * @param int $rule_id Visibility rule post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function discover_references( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 ) {
			return array();
		}

		$rule_key = (string) $rule_id;
		$refs     = array();

		$popups = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_elementor_template_type',
						'value' => 'popup',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $popups as $popup_id ) {
			$popup_id = (int) $popup_id;
			$settings = get_post_meta( $popup_id, '_elementor_page_settings', true );
			if ( ! is_array( $settings ) ) {
				continue;
			}
			$lib = isset( $settings['rwgc_visibility_rule_library'] ) ? (string) $settings['rwgc_visibility_rule_library'] : '';
			$app = isset( $settings['rwgc_applied_visibility_rule_id'] ) ? (string) $settings['rwgc_applied_visibility_rule_id'] : '';
			if ( $lib !== $rule_key && $app !== $rule_key ) {
				continue;
			}
			$refs[] = array(
				'type'           => 'popup',
				'id'             => $popup_id,
				'title'          => get_the_title( $popup_id ),
				'status'         => 'rule_applied',
				'visibilityMode' => isset( $settings['rwgc_visibility_rules_mode'] )
					? (string) $settings['rwgc_visibility_rules_mode']
					: ( isset( $settings['rwgc_visibility_mode'] ) ? (string) $settings['rwgc_visibility_mode'] : 'show_if' ),
			);
		}

		/**
		 * @param array<int, array<string, mixed>> $refs    Discovered references.
		 * @param int                               $rule_id Rule post ID.
		 */
		return apply_filters( 'rwgc_variant_rule_discover_references', $refs, $rule_id );
	}

	/**
	 * Merge discovered references with stored applications and label status.
	 *
	 * @param int $rule_id Rule post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_reference_status_rows( $rule_id ) {
		$discovered   = self::discover_references( $rule_id );
		$applications = self::get_applications( $rule_id );
		$applied_ids  = array();

		foreach ( $applications as $row ) {
			if ( ! is_array( $row ) || empty( $row['surface'] ) || empty( $row['id'] ) ) {
				continue;
			}
			$applied_ids[ sanitize_key( (string) $row['surface'] ) . ':' . (int) $row['id'] ] = true;
		}

		$rows = array();
		foreach ( $discovered as $ref ) {
			$key    = sanitize_key( (string) ( $ref['type'] ?? 'surface' ) ) . ':' . (int) ( $ref['id'] ?? 0 );
			$status = isset( $applied_ids[ $key ] ) ? 'rule_applied' : 'rule_not_applied';
			$ref['status'] = $status;
			$rows[]        = $ref;
		}

		return $rows;
	}

	/**
	 * @param int   $page_id  Base page ID.
	 * @param string $variant Version slug.
	 * @return void
	 */
	public static function mark_variant_archived( $page_id, $variant ) {
		$page_id = absint( $page_id );
		$variant = sanitize_key( (string) $variant );
		if ( $page_id <= 0 || '' === $variant ) {
			return;
		}

		$rules = get_posts(
			array(
				'post_type'      => RWGC_Visibility_Rule_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => self::META_SOURCE_PAGE_ID,
						'value' => (string) $page_id,
					),
					array(
						'key'   => self::META_SOURCE_VARIANT,
						'value' => $variant,
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $rules as $rule_id ) {
			update_post_meta( (int) $rule_id, self::META_LIFECYCLE, 'archived' );
		}

		/**
		 * Fires when variant-specific visibility rules are archived (disable, delete, unlink).
		 *
		 * @param int    $page_id Page ID the variant belonged to.
		 * @param string $variant Version slug.
		 * @param int[]  $rule_ids Affected visibility rule post IDs.
		 */
		do_action( 'rwgc_variant_rules_archived', $page_id, $variant, array_map( 'intval', $rules ) );
	}

	/**
	 * Whether a library rule should participate in front-end evaluation.
	 *
	 * @param int $rule_id Visibility rule post ID.
	 * @return bool
	 */
	public static function is_rule_active_for_frontend( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 ) {
			return true;
		}

		$lifecycle = sanitize_key( (string) get_post_meta( $rule_id, self::META_LIFECYCLE, true ) );
		if ( in_array( $lifecycle, array( 'archived', 'disabled' ), true ) ) {
			return false;
		}

		$source_type = sanitize_key( (string) get_post_meta( $rule_id, self::META_SOURCE_TYPE, true ) );
		if ( 'page_variant' !== $source_type ) {
			return true;
		}

		$page_id = (int) get_post_meta( $rule_id, self::META_SOURCE_PAGE_ID, true );
		if ( $page_id > 0 ) {
			$post = get_post( $page_id );
			if ( ! $post || ! in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
				return false;
			}
			if ( 'trash' === $post->post_status ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Infer variant provenance from portable rule JSON (page_version_url conditions).
	 *
	 * @param int                  $rule_id Rule post ID (for updates).
	 * @param array<string, mixed> $set     Sanitized portable rule set.
	 * @return array<string, mixed>
	 */
	public static function infer_provenance_from_rule_set( $rule_id, array $set ) {
		$existing = $rule_id > 0 ? self::get_provenance( $rule_id ) : array();
		if ( ! empty( $existing['sourceType'] ) && 'page_variant' === $existing['sourceType'] ) {
			return $existing;
		}

		$conditions = array();
		if ( ! empty( $set['conditions'] ) && is_array( $set['conditions'] ) ) {
			$conditions = $set['conditions'];
		} elseif ( ! empty( $set['groups'] ) && is_array( $set['groups'] ) ) {
			foreach ( $set['groups'] as $group ) {
				if ( is_array( $group ) && ! empty( $group['conditions'] ) && is_array( $group['conditions'] ) ) {
					$conditions = array_merge( $conditions, $group['conditions'] );
				}
			}
		}

		foreach ( $conditions as $cond ) {
			if ( ! is_array( $cond ) || 'page_version_url' !== ( $cond['type'] ?? '' ) ) {
				continue;
			}
			$val = isset( $cond['value'] ) && is_array( $cond['value'] ) ? $cond['value'] : array();
			$page_id = isset( $val['page_id'] ) ? absint( $val['page_id'] ) : 0;
			$version = isset( $val['version'] ) ? sanitize_key( (string) $val['version'] ) : '';
			if ( $page_id <= 0 || '' === $version ) {
				continue;
			}
			$url = class_exists( 'RWGC_Page_Version', false )
				? RWGC_Page_Version::build_version_url( $page_id, $version )
				: '';

			return array(
				'sourceType'    => 'page_variant',
				'sourcePageId'  => $page_id,
				'sourceVariant' => $version,
				'sourceUrl'     => $url,
				'createdFrom'   => ! empty( $existing['createdFrom'] ) ? (string) $existing['createdFrom'] : 'variant_builder',
				'lifecycle'     => ! empty( $existing['lifecycle'] ) ? (string) $existing['lifecycle'] : 'active',
			);
		}

		return $existing;
	}

	/**
	 * Attach a library rule to an Elementor popup's page settings.
	 *
	 * @param int    $rule_id Rule post ID.
	 * @param int    $popup_id Elementor popup template ID.
	 * @param string $visibility_mode show_if|hide_if.
	 * @return bool
	 */
	public static function apply_rule_to_popup( $rule_id, $popup_id, $visibility_mode = 'show_if' ) {
		$rule_id = absint( $rule_id );
		$popup_id = absint( $popup_id );
		if ( $rule_id <= 0 || $popup_id <= 0 ) {
			return false;
		}

		$settings = get_post_meta( $popup_id, '_elementor_page_settings', true );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$mode = function_exists( 'rwgc_normalize_visibility_mode' )
			? rwgc_normalize_visibility_mode( $visibility_mode )
			: ( 'hide_if' === sanitize_key( $visibility_mode ) ? 'hide_if' : 'show_if' );

		$settings['rwgc_enable_visibility_rules']     = 'yes';
		$settings['rwgc_visibility_rule_library']     = (string) $rule_id;
		$settings['rwgc_applied_visibility_rule_id']  = (string) $rule_id;
		$settings['rwgc_visibility_rules_mode']       = $mode;
		$settings['rwgc_visibility_mode']           = $mode;

		update_post_meta( $popup_id, '_elementor_page_settings', $settings );

		$applications = self::get_applications( $rule_id );
		$key          = 'popup:' . $popup_id;
		$found        = false;
		foreach ( $applications as $idx => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row_key = sanitize_key( (string) ( $row['surface'] ?? '' ) ) . ':' . (int) ( $row['id'] ?? 0 );
			if ( $row_key === $key ) {
				$applications[ $idx ]['visibilityMode'] = $mode;
				$applications[ $idx ]['status']         = 'applied';
				$found                                  = true;
				break;
			}
		}
		if ( ! $found ) {
			$applications[] = array(
				'surface'        => 'popup',
				'id'             => $popup_id,
				'visibilityMode' => $mode,
				'status'         => 'applied',
			);
		}

		self::save_provenance(
			$rule_id,
			array(
				'applications' => $applications,
			)
		);

		return true;
	}

	/**
	 * Apply rule to all discovered popups that reference it but are not yet recorded as applied.
	 *
	 * @param int $rule_id Rule post ID.
	 * @return int Number of popups updated.
	 */
	public static function sync_rule_to_discovered_popups( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 ) {
			return 0;
		}

		$count = 0;
		foreach ( self::discover_references( $rule_id ) as $ref ) {
			if ( ( $ref['type'] ?? '' ) !== 'popup' ) {
				continue;
			}
			$popup_id = (int) ( $ref['id'] ?? 0 );
			if ( $popup_id <= 0 ) {
				continue;
			}
			$mode = isset( $ref['visibilityMode'] ) ? (string) $ref['visibilityMode'] : 'show_if';
			if ( self::apply_rule_to_popup( $rule_id, $popup_id, $mode ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Rules tied to missing pages or archived lifecycle.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function find_orphaned_rules() {
		if ( ! class_exists( 'RWGC_Visibility_Rule_CPT', false ) ) {
			return array();
		}

		$rules = get_posts(
			array(
				'post_type'      => RWGC_Visibility_Rule_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => self::META_SOURCE_TYPE,
						'value'   => 'page_variant',
						'compare' => '=',
					),
				),
			)
		);

		$orphans = array();
		foreach ( $rules as $rule ) {
			if ( ! $rule instanceof WP_Post ) {
				continue;
			}
			$rule_id = (int) $rule->ID;
			if ( self::is_rule_active_for_frontend( $rule_id ) ) {
				continue;
			}
			$prov = self::get_provenance( $rule_id );
			$orphans[] = array(
				'rule_id'   => $rule_id,
				'title'     => $rule->post_title,
				'lifecycle' => (string) ( $prov['lifecycle'] ?? '' ),
				'page_id'   => (int) ( $prov['sourcePageId'] ?? 0 ),
				'variant'   => (string) ( $prov['sourceVariant'] ?? '' ),
				'edit_url'  => admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=' . $rule_id ),
			);
		}

		return $orphans;
	}

	/**
	 * @return void
	 */
	public static function handle_sync_surfaces() {
		if ( ! class_exists( 'RWGC_Admin', false ) || ! RWGC_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geocore' ) );
		}

		$rule_id = isset( $_GET['rule_id'] ) ? absint( wp_unslash( $_GET['rule_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $rule_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgc-visibility-rules' ) );
			exit;
		}
		check_admin_referer( 'rwgc_sync_variant_rule_' . $rule_id );

		$updated = self::sync_rule_to_discovered_popups( $rule_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'rwgc-visibility-rules',
					'rwgc_edit'  => $rule_id,
					'updated'    => '1',
					'rwgc_synced' => (string) $updated,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
