<?php
/**
 * Experience builder — visitor conditions, rule library integration, variant content modes.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates the Create Geo Rule / experience workflow form.
 */
class RWGC_Experience_Workflow {

	const META_VISIBILITY_RULE = '_rwgc_experience_visibility_rule_id';
	const META_EXPERIENCE_NAME   = '_rwgc_experience_name';

	/**
	 * @return string[]
	 */
	public static function get_condition_types() {
		return array( 'everyone', 'countries', 'saved_rule', 'create_rule' );
	}

	/**
	 * @return string[]
	 */
	public static function get_content_modes() {
		$modes = array( 'duplicate', 'existing', 'blank' );
		if ( class_exists( 'RWGA_Plugin', false ) ) {
			$modes[] = 'ai_adapt';
		}
		return $modes;
	}

	/**
	 * @return array<int, array{id:int,title:string,summary:string}>
	 */
	public static function get_library_rule_options() {
		$out = array();
		if ( ! class_exists( 'RWGC_Rule_Registry', false ) ) {
			return $out;
		}
		foreach ( RWGC_Rule_Registry::get_portable_library_picker_rows() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' === $id || ! is_numeric( $id ) ) {
				continue;
			}
			$rule_id = (int) $id;
			$out[]   = array(
				'id'      => $rule_id,
				'title'   => isset( $row['title'] ) ? (string) $row['title'] : get_the_title( $rule_id ),
				'summary' => self::summarize_rule_set( RWGC_Visibility_Rule_Repository::get_rule_set( $rule_id ) ),
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed>|null $set Rule set.
	 * @return string
	 */
	public static function summarize_rule_set( $set ) {
		if ( ! is_array( $set ) || empty( $set['rules'] ) || ! is_array( $set['rules'] ) ) {
			return '';
		}
		$parts = array();
		foreach ( $set['rules'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
				continue;
			}
			foreach ( $rule['conditions'] as $cond ) {
				if ( ! is_array( $cond ) || empty( $cond['type'] ) ) {
					continue;
				}
				$type = sanitize_key( (string) $cond['type'] );
				if ( 'country' === $type && ! empty( $cond['value'] ) && is_array( $cond['value'] ) ) {
					$codes = array_slice( array_map( 'strtoupper', array_map( 'strval', $cond['value'] ) ), 0, 4 );
					$parts[] = implode( ', ', $codes );
				} else {
					$parts[] = $type;
				}
			}
		}
		return implode( ' · ', array_unique( array_filter( $parts ) ) );
	}

	/**
	 * @param array<string, mixed> $countries Country ISO2 list.
	 * @return string|null JSON
	 */
	public static function build_country_rule_json( array $countries ) {
		$clean = array();
		foreach ( $countries as $code ) {
			$iso = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
			if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
				$clean[] = $iso;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		if ( empty( $clean ) ) {
			return null;
		}
		if ( ! class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return null;
		}
		$payload = array(
			'schema_version' => RWGC_Targeting_Rule_Set_Schema::VERSION,
			'enabled'        => true,
			'mode'           => 'show_if',
			'match'          => 'any',
			'rules'          => array(
				array(
					'id'         => 'exp_' . wp_generate_password( 8, false, false ),
					'label'      => '',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'country',
							'operator' => 'in',
							'value'    => $clean,
						),
					),
				),
			),
		);
		$san = RWGC_Targeting_Rule_Set_Schema::sanitize( $payload );
		if ( ! is_array( $san ) ) {
			return null;
		}
		$json = wp_json_encode( $san );
		return is_string( $json ) ? $json : null;
	}

	/**
	 * @param string               $title     Rule title.
	 * @param array<string, mixed> $countries ISO2 codes.
	 * @return int Rule post ID or 0.
	 */
	public static function create_country_library_rule( $title, array $countries ) {
		$json = self::build_country_rule_json( $countries );
		if ( null === $json || ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return 0;
		}
		$title = sanitize_text_field( (string) $title );
		if ( '' === $title ) {
			$title = __( 'Experience targeting rule', 'reactwoo-geocore' );
		}
		$rule_id = RWGC_Visibility_Rule_Repository::save( $title, 'publish', $json, 0 );
		if ( $rule_id > 0 && class_exists( 'RWGC_Variant_Rule_Applications', false ) ) {
			RWGC_Variant_Rule_Applications::save_provenance(
				$rule_id,
				array(
					'sourceType'    => 'experience_builder',
					'createdFrom'   => 'experience_workflow',
					'sourcePageId'  => 0,
					'sourceVariant' => '',
				)
			);
		}
		return $rule_id;
	}

	/**
	 * @param int $rule_id Library rule post ID.
	 * @return string Primary ISO2 or empty.
	 */
	public static function extract_primary_country_from_rule( $rule_id ) {
		$set = RWGC_Visibility_Rule_Repository::get_rule_set( $rule_id );
		if ( ! is_array( $set ) || empty( $set['rules'] ) ) {
			return '';
		}
		foreach ( $set['rules'] as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['conditions'] ) ) {
				continue;
			}
			foreach ( $rule['conditions'] as $cond ) {
				if ( ! is_array( $cond ) || 'country' !== ( $cond['type'] ?? '' ) ) {
					continue;
				}
				$value = isset( $cond['value'] ) && is_array( $cond['value'] ) ? $cond['value'] : array();
				foreach ( $value as $iso ) {
					$iso = strtoupper( substr( sanitize_text_field( (string) $iso ), 0, 2 ) );
					if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
						return $iso;
					}
				}
			}
		}
		return '';
	}

	/**
	 * URL to create a visibility rule and return to the experience builder.
	 *
	 * @return string
	 */
	public static function get_create_rule_url() {
		return add_query_arg(
			array(
				'page'        => 'rwgc-visibility-rules',
				'rwgc_edit'   => 'new',
				'rwgc_return' => rawurlencode( admin_url( 'admin.php?page=rwgc-workflow-variant' ) ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param int $master_page_id Master page ID.
	 * @return array<int, array{id:int,title:string}>
	 */
	public static function get_linkable_variant_pages( $master_page_id ) {
		$master_page_id = absint( $master_page_id );
		$pages          = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post__not_in'   => $master_page_id > 0 ? array( $master_page_id ) : array(),
			)
		);
		$out = array();
		if ( ! class_exists( 'RWGC_Routing', false ) ) {
			return $out;
		}
		foreach ( $pages as $page ) {
			if ( ! ( $page instanceof WP_Post ) ) {
				continue;
			}
			$pid  = (int) $page->ID;
			$cfg  = RWGC_Routing::get_page_route_config( $pid );
			$role = isset( $cfg['role'] ) ? (string) $cfg['role'] : '';
			if ( 'master' === $role ) {
				continue;
			}
			if ( 'variant' === $role ) {
				$linked_master = isset( $cfg['master_page_id'] ) ? (int) $cfg['master_page_id'] : 0;
				if ( $linked_master > 0 && $linked_master !== $master_page_id ) {
					continue;
				}
			}
			$out[] = array(
				'id'    => $pid,
				'title' => get_the_title( $page ),
			);
		}
		return $out;
	}

	/**
	 * @param int    $page_id Page ID.
	 * @param int    $rule_id Visibility rule ID.
	 * @param string $experience_name Optional label.
	 * @return void
	 */
	public static function attach_visibility_rule_to_page( $page_id, $rule_id, $experience_name = '' ) {
		$page_id = absint( $page_id );
		$rule_id = absint( $rule_id );
		if ( $page_id <= 0 || $rule_id <= 0 ) {
			return;
		}
		update_post_meta( $page_id, self::META_VISIBILITY_RULE, $rule_id );
		if ( '' !== trim( (string) $experience_name ) ) {
			update_post_meta( $page_id, self::META_EXPERIENCE_NAME, sanitize_text_field( (string) $experience_name ) );
		}
	}

	/**
	 * @param array<string, mixed> $input Sanitized POST input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function submit( array $input ) {
		$master          = isset( $input['master_page_id'] ) ? absint( $input['master_page_id'] ) : 0;
		$condition_type  = isset( $input['condition_type'] ) ? sanitize_key( (string) $input['condition_type'] ) : 'countries';
		$content_mode    = isset( $input['content_mode'] ) ? sanitize_key( (string) $input['content_mode'] ) : 'duplicate';
		$experience_name = isset( $input['experience_name'] ) ? sanitize_text_field( (string) $input['experience_name'] ) : '';
		$saved_rule_id   = isset( $input['saved_rule_id'] ) ? absint( $input['saved_rule_id'] ) : 0;
		$existing_id     = isset( $input['existing_variant_id'] ) ? absint( $input['existing_variant_id'] ) : 0;
		$save_as_rule    = ! empty( $input['save_countries_as_rule'] );
		$countries       = isset( $input['countries'] ) && is_array( $input['countries'] ) ? $input['countries'] : array();

		if ( $master <= 0 ) {
			return new WP_Error( 'rwgc_exp_master', __( 'Select a default page for this experience.', 'reactwoo-geocore' ) );
		}
		if ( ! in_array( $condition_type, self::get_condition_types(), true ) ) {
			$condition_type = 'countries';
		}
		if ( ! in_array( $content_mode, self::get_content_modes(), true ) ) {
			$content_mode = 'duplicate';
		}

		$visibility_rule_id = 0;
		$country_iso2       = '';

		if ( 'create_rule' === $condition_type ) {
			return new WP_Error(
				'rwgc_exp_create_rule',
				__( 'Create a targeting rule first, then return here to select it under “Use saved rule”.', 'reactwoo-geocore' )
			);
		}

		if ( 'saved_rule' === $condition_type ) {
			if ( $saved_rule_id <= 0 || ! RWGC_Visibility_Rule_Repository::get_post( $saved_rule_id ) ) {
				return new WP_Error( 'rwgc_exp_rule', __( 'Choose a saved targeting rule.', 'reactwoo-geocore' ) );
			}
			$visibility_rule_id = $saved_rule_id;
			$country_iso2       = self::extract_primary_country_from_rule( $saved_rule_id );
		} elseif ( 'countries' === $condition_type ) {
			foreach ( $countries as $code ) {
				$iso = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
				if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
					$country_iso2 = $iso;
					break;
				}
			}
			if ( '' === $country_iso2 ) {
				return new WP_Error( 'rwgc_exp_country', __( 'Select at least one country.', 'reactwoo-geocore' ) );
			}
			if ( $save_as_rule ) {
				$rule_title = '' !== $experience_name ? $experience_name : sprintf(
					/* translators: %s: country codes */
					__( 'Experience — %s', 'reactwoo-geocore' ),
					implode( ', ', array_map( 'strtoupper', $countries ) )
				);
				$visibility_rule_id = self::create_country_library_rule( $rule_title, $countries );
			}
		} elseif ( 'everyone' === $condition_type ) {
			if ( in_array( $content_mode, array( 'duplicate', 'blank', 'ai_adapt' ), true ) ) {
				return new WP_Error(
					'rwgc_exp_everyone_variant',
					__( 'To create a local page version, choose Selected countries or Use saved rule. Everyone keeps the default page for all visitors.', 'reactwoo-geocore' )
				);
			}
		}

		if ( in_array( $content_mode, array( 'duplicate', 'blank', 'ai_adapt', 'existing' ), true ) && 'everyone' !== $condition_type ) {
			if ( '' === $country_iso2 && 'existing' !== $content_mode ) {
				return new WP_Error(
					'rwgc_exp_country_route',
					__( 'Page routing needs a country from your selection or saved rule. Add a country condition to the rule, or use Selected countries.', 'reactwoo-geocore' )
				);
			}
		}

		if ( 'everyone' === $condition_type && 'existing' === $content_mode ) {
			if ( $existing_id <= 0 ) {
				return new WP_Error( 'rwgc_exp_existing', __( 'Select an existing page to link.', 'reactwoo-geocore' ) );
			}
			$res = RWGC_Variant_Manager::link_existing_variant( $master, $existing_id, '' );
		} elseif ( 'existing' === $content_mode ) {
			if ( $existing_id <= 0 ) {
				return new WP_Error( 'rwgc_exp_existing', __( 'Select an existing page to link as the local version.', 'reactwoo-geocore' ) );
			}
			$res = RWGC_Variant_Manager::link_existing_variant( $master, $existing_id, $country_iso2 );
		} else {
			$variant_mode = 'blank' === $content_mode ? 'blank' : 'duplicate';
			$res          = RWGC_Variant_Manager::create_country_variant( $master, $country_iso2, $variant_mode );
		}

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		if ( $visibility_rule_id > 0 ) {
			$attach_page = isset( $res['variant_page_id'] ) ? (int) $res['variant_page_id'] : $master;
			self::attach_visibility_rule_to_page( $attach_page, $visibility_rule_id, $experience_name );
			if ( $master !== $attach_page ) {
				self::attach_visibility_rule_to_page( $master, $visibility_rule_id, $experience_name );
			}
			$res['visibility_rule_id'] = $visibility_rule_id;
		}

		if ( '' !== $experience_name && isset( $res['variant_page_id'] ) ) {
			update_post_meta( (int) $res['variant_page_id'], self::META_EXPERIENCE_NAME, $experience_name );
		}

		$res['content_mode']    = $content_mode;
		$res['condition_type']  = $condition_type;
		$res['experience_name'] = $experience_name;

		if ( 'ai_adapt' === $content_mode && ! empty( $res['variant_page_id'] ) ) {
			$res['ai_handoff_url'] = self::build_ai_adapt_handoff_url( $master, (int) $res['variant_page_id'], $country_iso2 );
		}

		return $res;
	}

	/**
	 * @param int    $master_page_id Master page.
	 * @param int    $variant_page_id Variant page.
	 * @param string $country_iso2 Geo hint.
	 * @return string
	 */
	public static function build_ai_adapt_handoff_url( $master_page_id, $variant_page_id, $country_iso2 = '' ) {
		$args = array(
			'page'                 => class_exists( 'RWGA_Admin', false ) ? RWGA_Admin::MENU_PARENT : 'rwga-dashboard',
			'rwgc_handoff'         => '1',
			'rwgc_from'            => 'experience_builder',
			'rwgc_launcher'        => 'ai_adapt',
			'rwgc_master_page_id'  => absint( $master_page_id ),
			'rwgc_variant_page_id' => absint( $variant_page_id ),
		);
		if ( '' !== $country_iso2 ) {
			$args['rwga_geo_target'] = strtoupper( substr( sanitize_text_field( $country_iso2 ), 0, 2 ) );
		}
		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		if ( class_exists( 'RWGC_Workflows', false ) ) {
			$url = RWGC_Workflows::add_handoff_query_args( $url, 'ai_adapt' );
		}
		return $url;
	}

	/**
	 * @param string               $name Field name.
	 * @param array<string, mixed> $selected Selected ISO2 codes.
	 * @param array<string, mixed> $args Args.
	 * @return void
	 */
	public static function render_country_multi_select( $name, array $selected, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'    => $name,
				'class' => 'rwgc-select-country widefat',
				'size'  => 8,
			)
		);
		$selected_map = array();
		foreach ( $selected as $code ) {
			$iso = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
			if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
				$selected_map[ $iso ] = true;
			}
		}
		$countries = class_exists( 'RWGC_Countries', false ) ? RWGC_Countries::get_options() : array();
		printf(
			'<select name="%1$s[]" id="%2$s" class="%3$s" multiple size="%4$d">',
			esc_attr( $name ),
			esc_attr( (string) $args['id'] ),
			esc_attr( (string) $args['class'] ),
			(int) $args['size']
		);
		foreach ( $countries as $code => $label ) {
			$code = strtoupper( (string) $code );
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $code ),
				isset( $selected_map[ $code ] ) ? ' selected="selected"' : '',
				esc_html( $label . ' (' . $code . ')' )
			);
		}
		echo '</select>';
	}
}
