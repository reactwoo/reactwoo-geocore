<?php
/**
 * Geo Suite — module metadata and readiness (satellites register via filters).
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry for suite modules and readiness checks.
 */
class RWGC_Module_Registry {

	/**
	 * Option key for persisted module hints (optional).
	 */
	const OPTION_KEY = 'rwgc_module_registry_cache';

	/**
	 * Built-in module ids (core + common satellites).
	 */
	const MODULE_GEOCORE     = 'geocore';
	const MODULE_MAXMIND     = 'maxmind';
	const MODULE_DB          = 'ip_database';
	const MODULE_REST        = 'rest_api';
	const MODULE_GUTENBERG   = 'gutenberg';
	const MODULE_ELEMENTOR   = 'elementor';
	const MODULE_WOOCOMMERCE = 'woocommerce';
	const MODULE_GEO_AI      = 'geo_ai';
	const MODULE_GEO_OPT     = 'geo_optimise';
	const MODULE_GEO_COMM    = 'geo_commerce';
	const MODULE_GEO_ELEM    = 'geoelementor';

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_registered_modules() {
		$modules = array(
			self::build_core_module(),
			self::build_maxmind_module(),
			self::build_ip_database_module(),
			self::build_rest_module(),
			self::build_gutenberg_module(),
			self::build_elementor_module(),
			self::build_woocommerce_module(),
			self::build_satellite_module(
				self::MODULE_GEO_AI,
				__( 'Geo AI', 'reactwoo-geocore' ),
				__( 'AI-assisted page variants and drafts.', 'reactwoo-geocore' ),
				class_exists( 'RWGA_Plugin', false ),
				'rwga-dashboard',
				array( 'reactwoo-geo-ai/reactwoo-geo-ai.php' )
			),
			self::build_satellite_module(
				self::MODULE_GEO_OPT,
				__( 'Geo Optimise', 'reactwoo-geocore' ),
				__( 'Experiments, assignments, and results.', 'reactwoo-geocore' ),
				class_exists( 'RWGO_Plugin', false ),
				'rwgo-dashboard',
				array( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' )
			),
			self::build_satellite_module(
				self::MODULE_GEO_COMM,
				__( 'Geo Commerce', 'reactwoo-geocore' ),
				__( 'WooCommerce pricing, fees, and geo context.', 'reactwoo-geocore' ),
				class_exists( 'RWGCM_Plugin', false ),
				'rwgcm-dashboard',
				array( 'reactwoo-geo-commerce/reactwoo-geo-commerce.php' )
			),
			self::build_geoelementor_module(),
		);

		/**
		 * Register or replace Geo Suite modules (readiness, labels, deep links).
		 *
		 * @param array<int, array<string, mixed>> $modules Module rows.
		 */
		return apply_filters( 'rwgc_register_modules', $modules );
	}

	/**
	 * Readiness rows with status: ready | needs_setup | optional | missing.
	 *
	 * @param string $goal Optional wizard goal slug for contextual requirements.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_readiness_rows( $goal = '' ) {
		$goal = sanitize_key( (string) $goal );
		$rows = array();
		foreach ( self::get_registered_modules() as $mod ) {
			if ( empty( $mod['id'] ) ) {
				continue;
			}
			$check = self::evaluate_module( $mod, $goal );
			$rows[] = array_merge( $mod, $check );
		}

		/**
		 * Filter readiness snapshot rows.
		 *
		 * @param array<int, array<string, mixed>> $rows Readiness rows.
		 * @param string                           $goal Goal slug.
		 */
		return apply_filters( 'rwgc_readiness_rows', $rows, $goal );
	}

	/**
	 * @param array<string, mixed> $mod Module definition.
	 * @param string               $goal Goal slug.
	 * @return array<string, mixed> Status keys: status, detail, configure_url.
	 */
	private static function evaluate_module( $mod, $goal ) {
		$id = (string) $mod['id'];
		$defaults = array(
			'status'        => 'ready',
			'detail'        => '',
			'configure_url' => '',
			'consequence'   => '',
		);

		switch ( $id ) {
			case self::MODULE_GEOCORE:
				return array_merge(
					$defaults,
					array(
						'status'      => 'ready',
						'detail'      => __( 'Geo Core is active.', 'reactwoo-geocore' ),
						'consequence' => '',
					)
				);

			case self::MODULE_MAXMIND:
				$settings = class_exists( 'RWGC_Settings', false ) ? RWGC_Settings::get_settings() : array();
				$has_key  = ! empty( $settings['maxmind_license_key'] );
				return array_merge(
					$defaults,
					array(
						'status'        => $has_key ? 'ready' : 'needs_setup',
						'detail'        => $has_key ? __( 'MaxMind credentials saved.', 'reactwoo-geocore' ) : __( 'Add your GeoLite2 license key.', 'reactwoo-geocore' ),
						'configure_url' => admin_url( 'admin.php?page=rwgc-settings' ),
						'consequence'   => $has_key ? '' : __( 'Without credentials, downloads and accurate lookups may not work.', 'reactwoo-geocore' ),
					)
				);

			case self::MODULE_DB:
				$status = class_exists( 'RWGC_MaxMind', false ) ? RWGC_MaxMind::get_status() : array( 'exists' => false );
				$ok     = ! empty( $status['exists'] );
				return array_merge(
					$defaults,
					array(
						'status'        => $ok ? 'ready' : 'needs_setup',
						'detail'        => $ok ? __( 'Country database is on disk.', 'reactwoo-geocore' ) : __( 'Download or upload the GeoLite2 Country database.', 'reactwoo-geocore' ),
						'configure_url' => admin_url( 'admin.php?page=rwgc-tools' ),
						'consequence'   => $ok ? '' : __( 'Visitor country may fall back until the database is available.', 'reactwoo-geocore' ),
					)
				);

			case self::MODULE_REST:
				$on = class_exists( 'RWGC_Settings', false ) && (bool) RWGC_Settings::get( 'rest_enabled', 1 );
				return array_merge(
					$defaults,
					array(
						'status'        => $on ? 'ready' : 'needs_setup',
						'detail'        => $on ? __( 'REST routes are enabled.', 'reactwoo-geocore' ) : __( 'REST routes are turned off.', 'reactwoo-geocore' ),
						'configure_url' => admin_url( 'admin.php?page=rwgc-settings' ),
						'consequence'   => $on ? '' : __( 'Geo AI and integrations that rely on REST will not work until this is on.', 'reactwoo-geocore' ),
					)
				);

			case self::MODULE_GUTENBERG:
				return array_merge(
					$defaults,
					array(
						'status' => 'optional',
						'detail' => __( 'Block editor is available for page content.', 'reactwoo-geocore' ),
					)
				);

			case self::MODULE_ELEMENTOR:
				$active = did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' );
				return array_merge(
					$defaults,
					array(
						'status' => $active ? 'optional' : 'optional',
						'detail' => $active ? __( 'Elementor is active.', 'reactwoo-geocore' ) : __( 'Elementor is not active (optional).', 'reactwoo-geocore' ),
					)
				);

			case self::MODULE_WOOCOMMERCE:
				$ok = function_exists( 'rwgc_is_woocommerce_active' ) && rwgc_is_woocommerce_active();
				return array_merge(
					$defaults,
					array(
						'status' => $ok ? 'ready' : 'missing',
						'detail' => $ok ? __( 'WooCommerce is active.', 'reactwoo-geocore' ) : __( 'WooCommerce is not active.', 'reactwoo-geocore' ),
						'consequence' => self::goal_needs_woo( $goal )
							? __( 'Geo Commerce workflows need WooCommerce.', 'reactwoo-geocore' )
							: '',
					)
				);

			default:
				if ( isset( $mod['is_active_callback'] ) && is_callable( $mod['is_active_callback'] ) ) {
					$active = (bool) call_user_func( $mod['is_active_callback'] );
				} else {
					$active = ! empty( $mod['active'] );
				}
				$install = isset( $mod['install_url'] ) ? (string) $mod['install_url'] : admin_url( 'plugin-install.php' );
				$admin   = isset( $mod['admin_url'] ) ? (string) $mod['admin_url'] : '';
				return array_merge(
					$defaults,
					array(
						'status'        => $active ? 'ready' : 'missing',
						'detail'        => $active ? ( isset( $mod['label'] ) ? (string) $mod['label'] : '' ) : __( 'Not installed or inactive.', 'reactwoo-geocore' ),
						'configure_url' => $active ? $admin : $install,
					)
				);
		}
	}

	/**
	 * @param string $goal Goal slug.
	 * @return bool
	 */
	private static function goal_needs_woo( $goal ) {
		return in_array( $goal, array( 'commerce', 'woocommerce', 'woo' ), true );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_core_module() {
		return array(
			'id'          => self::MODULE_GEOCORE,
			'label'       => __( 'Geo Core', 'reactwoo-geocore' ),
			'description' => __( 'Visitor country detection and shared engine.', 'reactwoo-geocore' ),
			'category'    => 'core',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_maxmind_module() {
		return array(
			'id'          => self::MODULE_MAXMIND,
			'label'       => __( 'MaxMind (GeoLite2)', 'reactwoo-geocore' ),
			'description' => __( 'License credentials for database downloads.', 'reactwoo-geocore' ),
			'category'    => 'foundation',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_ip_database_module() {
		return array(
			'id'          => self::MODULE_DB,
			'label'       => __( 'IP country database', 'reactwoo-geocore' ),
			'description' => __( 'GeoLite2 Country .mmdb on disk.', 'reactwoo-geocore' ),
			'category'    => 'foundation',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_rest_module() {
		return array(
			'id'          => self::MODULE_REST,
			'label'       => __( 'REST API', 'reactwoo-geocore' ),
			'description' => __( 'Integrations and AI routes.', 'reactwoo-geocore' ),
			'category'    => 'foundation',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_gutenberg_module() {
		return array(
			'id'          => self::MODULE_GUTENBERG,
			'label'       => __( 'Block editor', 'reactwoo-geocore' ),
			'description' => __( 'Edit page variants in Gutenberg.', 'reactwoo-geocore' ),
			'category'    => 'optional',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_elementor_module() {
		return array(
			'id'          => self::MODULE_ELEMENTOR,
			'label'       => __( 'Elementor', 'reactwoo-geocore' ),
			'description' => __( 'Optional: advanced geo rules in Elementor.', 'reactwoo-geocore' ),
			'category'    => 'optional',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_woocommerce_module() {
		return array(
			'id'          => self::MODULE_WOOCOMMERCE,
			'label'       => __( 'WooCommerce', 'reactwoo-geocore' ),
			'description' => __( 'Store catalog and checkout (for Geo Commerce).', 'reactwoo-geocore' ),
			'category'    => 'commerce',
		);
	}

	/**
	 * @param string              $id Module id.
	 * @param string              $label Label.
	 * @param string              $description Short text.
	 * @param bool                $active Whether plugin class is present.
	 * @param string              $page Admin page slug.
	 * @param array<int, string>  $files Plugin basename paths.
	 * @return array<string, mixed>
	 */
	private static function build_satellite_module( $id, $label, $description, $active, $page, $files ) {
		$url = $page ? admin_url( 'admin.php?page=' . sanitize_key( $page ) ) : '';
		return array(
			'id'            => $id,
			'label'         => $label,
			'description'   => $description,
			'category'      => 'satellite',
			'active'        => $active,
			'admin_url'     => $url,
			'plugin_files'  => $files,
			'install_url'   => admin_url( 'plugin-install.php' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function build_geoelementor_module() {
		$defs = class_exists( 'RWGC_Admin_UI', false ) ? RWGC_Admin_UI::get_suite_satellite_definitions() : array();
		$def  = null;
		foreach ( $defs as $d ) {
			if ( isset( $d['slug'] ) && 'geoelementor' === $d['slug'] ) {
				$def = $d;
				break;
			}
		}
		$active = is_array( $def ) && class_exists( 'RWGC_Admin_UI', false )
			? RWGC_Admin_UI::satellite_plugin_is_active( $def )
			: false;
		$url = ( is_array( $def ) && ! empty( $def['url'] ) ) ? (string) $def['url'] : admin_url( 'plugin-install.php' );
		return array(
			'id'          => self::MODULE_GEO_ELEM,
			'label'       => __( 'GeoElementor', 'reactwoo-geocore' ),
			'description' => __( 'Advanced Elementor geo targeting (optional).', 'reactwoo-geocore' ),
			'category'    => 'optional',
			'active'      => $active,
			'admin_url'   => $active ? $url : admin_url( 'plugin-install.php' ),
		);
	}
}
