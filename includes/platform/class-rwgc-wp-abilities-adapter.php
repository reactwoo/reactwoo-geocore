<?php
/**
 * Optional bridge from ReactWoo capabilities → WordPress Abilities API (WP 6.9+).
 *
 * Internal registry remains authoritative. MCP clients see three read-only admin
 * tools. Conditions and actions stay on the Decision Runtime.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers ReactWoo discovery tools as WP Abilities when the API exists.
 */
final class RWGC_WP_Abilities_Adapter {

	const TOOL_LIST     = 'reactwoo/list-capabilities';
	const TOOL_DESCRIBE = 'reactwoo/describe-capability';
	const TOOL_SITE     = 'reactwoo/site-context';

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_supported() ) {
			return;
		}

		// Categories must be registered on this hook (WP 6.9+).
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		// Abilities must be registered on this hook — never on plain `init`.
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * @return bool
	 */
	public static function is_supported() {
		return function_exists( 'wp_register_ability' )
			&& function_exists( 'wp_register_ability_category' );
	}

	/**
	 * Tool ids exposed to MCP. Individual capability ids are not tools.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function tools() {
		return array(
			self::TOOL_LIST     => array(
				'label'       => __( 'List ReactWoo capabilities', 'reactwoo-geocore' ),
				'description' => __( 'List installed ReactWoo capabilities (id, type, provider, entitlement, availability). Does not run them.', 'reactwoo-geocore' ),
			),
			self::TOOL_DESCRIBE => array(
				'label'       => __( 'Describe a ReactWoo capability', 'reactwoo-geocore' ),
				'description' => __( 'Describe one installed capability, including its input and output schema. Does not run it.', 'reactwoo-geocore' ),
			),
			self::TOOL_SITE     => array(
				'label'       => __( 'ReactWoo site context', 'reactwoo-geocore' ),
				'description' => __( 'Read whether Geo is ready, which satellites are licensed, and which experiences are active.', 'reactwoo-geocore' ),
			),
		);
	}

	/**
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		if ( ! apply_filters( 'reactwoo_bridge_wp_abilities', true ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WP core API.
		wp_register_ability_category(
			'reactwoo',
			array(
				'label'       => __( 'ReactWoo', 'reactwoo-geocore' ),
				'description' => __( 'ReactWoo platform discovery for administrators.', 'reactwoo-geocore' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		if ( ! apply_filters( 'reactwoo_bridge_wp_abilities', true ) ) {
			return;
		}

		foreach ( self::tools() as $name => $tool ) {
			$args = array(
				'label'               => $tool['label'],
				'description'         => $tool['description'],
				'category'            => 'reactwoo',
				'input_schema'        => self::input_schema( $name ),
				'output_schema'       => self::output_schema(),
				'execute_callback'    => static function ( $input = null ) use ( $name ) {
					return self::execute( $name, $input );
				},
				'permission_callback' => static function ( $input = null ) {
					unset( $input );
					return self::permission_check();
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			);

			/**
			 * Filter ability args before registration.
			 *
			 * @param array<string, mixed>|null $args Null to skip.
			 * @param string                    $name Tool name.
			 */
			$args = apply_filters( 'reactwoo_wp_ability_args', $args, $name, array() );
			if ( ! is_array( $args ) ) {
				continue;
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WP core API when present.
			wp_register_ability( $name, $args );
		}
	}

	/**
	 * Map a dotted capability ID to a WP 6.9+ ability name.
	 *
	 * Kept for callers and tests. Capability ids are not registered as tools.
	 *
	 * @param string $capability_id Capability ID.
	 * @return string Empty when the ID cannot be mapped safely.
	 */
	public static function ability_name( $capability_id ) {
		$slug = strtolower( (string) $capability_id );
		$slug = str_replace( array( '.', '_' ), '-', $slug );
		$slug = preg_replace( '/[^a-z0-9-]+/', '-', $slug );
		$slug = preg_replace( '/-+/', '-', (string) $slug );
		$slug = trim( (string) $slug, '-' );
		if ( '' === $slug ) {
			return '';
		}
		$name = 'reactwoo/' . $slug;
		if ( ! preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name ) ) {
			return '';
		}
		return $name;
	}

	/**
	 * JSON Schema for one tool.
	 *
	 * List and site context are valid with no arguments. WordPress passes null
	 * when a readonly ability is called without `input`, then rejects null
	 * against `type: object` unless the schema has an object default. That is
	 * the same default core uses for `core/get-site-info`. Describe still
	 * requires `id`, so it has no default.
	 *
	 * @param string $name Tool name.
	 * @return array<string, mixed>
	 */
	public static function input_schema( $name ) {
		if ( self::TOOL_LIST === $name ) {
			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'default'              => array(),
				'properties'           => array(
					'type' => array(
						'type'        => 'string',
						'description' => 'Optional filter: condition, action, context, or goal.',
						'enum'        => array( 'condition', 'action', 'context', 'goal' ),
					),
				),
			);
		}
		if ( self::TOOL_DESCRIBE === $name ) {
			return array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'string',
						'description' => 'Dotted capability id, for example geo.country.',
					),
				),
			);
		}
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'default'              => array(),
			'properties'           => array(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function output_schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
		);
	}

	/**
	 * @return bool
	 */
	public static function permission_check() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * @param string $name  Tool name.
	 * @param mixed  $input Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( $name, $input = null ) {
		if ( ! self::permission_check() ) {
			return new \WP_Error(
				'reactwoo_ability_forbidden',
				__( 'You do not have permission to inspect ReactWoo.', 'reactwoo-geocore' ),
				array( 'status' => 403 )
			);
		}
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		if ( self::TOOL_LIST === $name ) {
			$type = isset( $input['type'] ) ? strtolower( (string) $input['type'] ) : '';
			$type = preg_replace( '/[^a-z_]/', '', $type );
			$allowed = array( 'condition', 'action', 'context', 'goal' );
			if ( '' !== $type && ! in_array( $type, $allowed, true ) ) {
				return new \WP_Error(
					'reactwoo_ability_bad_type',
					__( 'Unknown capability type.', 'reactwoo-geocore' ),
					array( 'status' => 400 )
				);
			}
			return array(
				'capabilities' => self::list_capabilities( '' === $type ? null : $type ),
			);
		}

		if ( self::TOOL_DESCRIBE === $name ) {
			$id = isset( $input['id'] ) ? trim( (string) $input['id'] ) : '';
			return self::describe_capability( $id );
		}

		if ( self::TOOL_SITE === $name ) {
			return self::site_context();
		}

		return new \WP_Error(
			'reactwoo_ability_unknown',
			__( 'Unknown ReactWoo tool.', 'reactwoo-geocore' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * @param string|null $type Optional type filter.
	 * @return list<array<string, mixed>>
	 */
	public static function list_capabilities( $type = null ) {
		if ( ! class_exists( 'RWGC_Platform_Capability_Registry', false ) ) {
			return array();
		}
		$out = array();
		foreach ( RWGC_Platform_Capability_Registry::all( $type ) as $id => $row ) {
			if ( ! is_string( $id ) || ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'id'          => $id,
				'type'        => isset( $row['type'] ) ? (string) $row['type'] : '',
				'label'       => isset( $row['label'] ) ? (string) $row['label'] : '',
				'provider'    => isset( $row['provider'] ) ? (string) $row['provider'] : '',
				'entitlement' => isset( $row['entitlement'] ) ? (string) $row['entitlement'] : '',
				'available'   => RWGC_Platform_Capability_Registry::is_available( $id ),
			);
		}
		return $out;
	}

	/**
	 * @param string $id Capability id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function describe_capability( $id ) {
		if ( ! class_exists( 'RWGC_Platform_Capability_Registry', false ) ) {
			return new \WP_Error(
				'reactwoo_ability_missing',
				__( 'Unknown ReactWoo capability.', 'reactwoo-geocore' ),
				array( 'status' => 404 )
			);
		}
		$row = RWGC_Platform_Capability_Registry::get( (string) $id );
		if ( ! is_array( $row ) ) {
			return new \WP_Error(
				'reactwoo_ability_missing',
				__( 'Unknown ReactWoo capability.', 'reactwoo-geocore' ),
				array( 'status' => 404 )
			);
		}
		$resolved = isset( $row['id'] ) ? (string) $row['id'] : (string) $id;
		return array(
			'id'            => $resolved,
			'type'          => isset( $row['type'] ) ? (string) $row['type'] : '',
			'label'         => isset( $row['label'] ) ? (string) $row['label'] : '',
			'description'   => isset( $row['description'] ) ? (string) $row['description'] : '',
			'provider'      => isset( $row['provider'] ) ? (string) $row['provider'] : '',
			'version'       => isset( $row['version'] ) ? (string) $row['version'] : '',
			'entitlement'   => isset( $row['entitlement'] ) ? (string) $row['entitlement'] : '',
			'available'     => RWGC_Platform_Capability_Registry::is_available( $resolved ),
			'input_schema'  => isset( $row['input_schema'] ) && is_array( $row['input_schema'] ) ? $row['input_schema'] : array(),
			'output_schema' => isset( $row['output_schema'] ) && is_array( $row['output_schema'] ) ? $row['output_schema'] : array(),
		);
	}

	/**
	 * Safe admin snapshot. No licence secrets and no visitor payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function site_context() {
		$map = function_exists( 'rwgc_get_suite_capability_map' ) ? rwgc_get_suite_capability_map() : array();
		if ( ! is_array( $map ) ) {
			$map = array();
		}

		$keys = array(
			'geocore_version',
			'geocore_pro_active',
			'geocore_pro_licensed',
			'geo_optimise_active',
			'geo_optimise_licensed',
			'geo_commerce_active',
			'geo_commerce_licensed',
			'woocommerce_active',
		);
		$satellites = array();
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $map ) ) {
				$satellites[ $key ] = $map[ $key ];
			}
		}

		return array(
			'geo_ready'          => function_exists( 'rwgc_is_ready' ) ? (bool) rwgc_is_ready() : false,
			'satellites'         => $satellites,
			'active_experiences' => self::active_experiences(),
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function active_experiences() {
		if ( ! class_exists( 'RWGC_Cloud_Manifest_Store', false ) ) {
			return array();
		}
		$manifest = RWGC_Cloud_Manifest_Store::current();
		if ( ! is_object( $manifest ) || ! method_exists( $manifest, 'experiences' ) ) {
			return array();
		}
		$out = array();
		foreach ( $manifest->experiences() as $experience ) {
			if ( ! is_object( $experience ) || ! method_exists( $experience, 'status' ) ) {
				continue;
			}
			if ( 'active' !== (string) $experience->status() ) {
				continue;
			}
			$out[] = array(
				'id'      => method_exists( $experience, 'id' ) ? (string) $experience->id() : '',
				'name'    => method_exists( $experience, 'name' ) ? (string) $experience->name() : '',
				'status'  => 'active',
				'slot_id' => method_exists( $experience, 'slot_id' ) ? (string) $experience->slot_id() : '',
			);
			if ( count( $out ) >= 50 ) {
				break;
			}
		}
		return $out;
	}
}
