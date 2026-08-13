<?php
/**
 * Optional bridge from ReactWoo capabilities → WordPress Abilities API (WP 6.9+).
 *
 * Internal registry remains authoritative. This adapter is best-effort only.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a subset of ReactWoo capabilities as WP Abilities when the API exists.
 */
final class RWGC_WP_Abilities_Adapter {

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
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		if ( self::should_skip_registration() ) {
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
				'description' => __( 'ReactWoo platform context, conditions, actions, and goals.', 'reactwoo-geocore' ),
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
		if ( self::should_skip_registration() ) {
			return;
		}
		if ( ! apply_filters( 'reactwoo_bridge_wp_abilities', true ) ) {
			return;
		}
		if ( ! class_exists( 'RWGC_Platform_Capability_Registry', false ) ) {
			return;
		}

		$used_names = array();
		foreach ( RWGC_Platform_Capability_Registry::all() as $id => $row ) {
			if ( ! is_string( $id ) || '' === $id || ! is_array( $row ) ) {
				continue;
			}

			$ability_name = self::ability_name( $id );
			if ( '' === $ability_name || isset( $used_names[ $ability_name ] ) ) {
				continue;
			}
			$used_names[ $ability_name ] = true;
			$label        = isset( $row['label'] ) ? (string) $row['label'] : $id;
			$description  = isset( $row['description'] ) ? (string) $row['description'] : '';
			if ( '' === $description ) {
				$description = $label;
			}

			$cap_id = $id;
			/**
			 * Filter ability args before registration.
			 *
			 * @param array<string, mixed>|null $args Null to skip.
			 * @param string                    $id   Capability ID.
			 * @param array<string, mixed>      $row  Registry row.
			 */
			$args = apply_filters(
				'reactwoo_wp_ability_args',
				array(
					'label'               => $label,
					'description'         => $description,
					'category'            => 'reactwoo',
					'execute_callback'    => static function ( $input = null ) use ( $cap_id ) {
						return self::execute_capability( $cap_id, $input );
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
						'show_in_rest' => false,
					),
				),
				$id,
				$row
			);
			if ( ! is_array( $args ) ) {
				continue;
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WP core API when present.
			wp_register_ability( $ability_name, $args );
		}
	}

	/**
	 * Abilities are unused while Elementor builds widget stacks.
	 * Registering them on that AJAX path can print `_doing_it_wrong`
	 * HTML into JSON (Elements panel spinner) when WP_DEBUG is on.
	 *
	 * @return bool
	 */
	public static function should_skip_registration() {
		if ( class_exists( 'RWGC_Elementor_Ajax', false ) && RWGC_Elementor_Ajax::is_elementor_ajax() ) {
			return true;
		}
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only context detect.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		return ( 'elementor_ajax' === $action );
	}

	/**
	 * Map a dotted capability ID to a WP 6.9+ ability name.
	 *
	 * Core allows only `namespace/slug` with lowercase a-z, 0-9, and dashes.
	 * Underscores in IDs such as `geo.country_group` must become dashes.
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
	 * @return bool
	 */
	public static function permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Readonly discovery of a platform capability (not visitor-critical-path execution).
	 *
	 * @param string $id    Capability ID.
	 * @param mixed  $input Unused.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute_capability( $id, $input = null ) {
		unset( $input );

		if ( ! self::permission_check() ) {
			return new \WP_Error(
				'reactwoo_ability_forbidden',
				__( 'You do not have permission to inspect ReactWoo capabilities.', 'reactwoo-geocore' ),
				array( 'status' => 403 )
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

		return array(
			'id'          => (string) $id,
			'type'        => isset( $row['type'] ) ? (string) $row['type'] : '',
			'label'       => isset( $row['label'] ) ? (string) $row['label'] : '',
			'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
			'provider'    => isset( $row['provider'] ) ? (string) $row['provider'] : '',
			'version'     => isset( $row['version'] ) ? (string) $row['version'] : '',
		);
	}
}
