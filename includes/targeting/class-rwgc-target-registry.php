<?php
/**
 * Registry of target types (suite-wide targeting vocabulary metadata).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and exposes target definitions for satellites.
 */
class RWGC_Target_Registry {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private $targets = array();

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot: register default providers once.
	 *
	 * @return void
	 */
	public static function init() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$registry = self::instance();
		$providers = self::default_providers();

		/**
		 * Filter registered target provider class names (FQCNs implementing {@see RWGC_Target_Provider_Interface}).
		 *
		 * @param string[] $providers Class names.
		 */
		$providers = apply_filters( 'rwgc_target_provider_classes', $providers );

		foreach ( $providers as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			$obj = new $class();
			if ( ! $obj instanceof RWGC_Target_Provider_Interface ) {
				continue;
			}
			if ( ! $obj->is_available() ) {
				continue;
			}
			$obj->register_targets( $registry );
		}

		/**
		 * Fires after default target types are registered (extensions may add more).
		 *
		 * @param RWGC_Target_Registry $registry Registry.
		 */
		do_action( 'rwgc_target_registry_init', $registry );
	}

	/**
	 * @return string[]
	 */
	private static function default_providers() {
		return array(
			'RWGC_Target_Provider_Geo',
			'RWGC_Target_Provider_Language',
			'RWGC_Target_Provider_Time',
			'RWGC_Target_Provider_Device',
			'RWGC_Target_Provider_Weather',
			'RWGC_Target_Provider_Analytics',
			'RWGC_Target_Provider_Commerce',
		);
	}

	/**
	 * Register a target definition (see spec for keys).
	 *
	 * @param array<string, mixed> $definition Definition.
	 * @return void
	 */
	public function register_target_type( array $definition ) {
		$key = isset( $definition['key'] ) ? sanitize_key( (string) $definition['key'] ) : '';
		if ( '' === $key ) {
			return;
		}
		$defaults = array(
			'label'                => $key,
			'group'                => 'general',
			'description'          => '',
			'operators'            => array( 'is', 'is_not' ),
			'value_mode'           => 'single',
			'provider'             => '',
			'supports_simulation'  => true,
			'is_available_callback'=> null,
			'get_choices_callback' => null,
			'resolve_callback'     => null,
		);
		$this->targets[ $key ] = array_merge( $defaults, $definition, array( 'key' => $key ) );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function get_target_types() {
		return $this->targets;
	}

	/**
	 * @param string $key Target key.
	 * @return array<string, mixed>|null
	 */
	public function get_target_type( $key ) {
		$k = sanitize_key( (string) $key );
		return isset( $this->targets[ $k ] ) ? $this->targets[ $k ] : null;
	}

	/**
	 * @return array<string, list<array<string, mixed>>>
	 */
	public function get_target_types_by_group() {
		$out = array();
		foreach ( $this->targets as $def ) {
			$g = isset( $def['group'] ) ? (string) $def['group'] : 'general';
			if ( ! isset( $out[ $g ] ) ) {
				$out[ $g ] = array();
			}
			$out[ $g ][] = $def;
		}
		return $out;
	}

	/**
	 * Targets that pass availability for the current request/site.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_available_target_types() {
		$out = array();
		foreach ( $this->targets as $key => $def ) {
			if ( RWGC_Target_Availability::is_available( $def ) ) {
				$out[ $key ] = $def;
			}
		}
		return $out;
	}
}
