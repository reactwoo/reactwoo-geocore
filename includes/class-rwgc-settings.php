<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings manager for ReactWoo Geo Core.
 */
class RWGC_Settings {

	const OPTION_KEY = 'rwgc_settings';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'option_page_capability_rwgc_settings_group', array( __CLASS__, 'filter_option_page_capability' ) );
	}

	/**
	 * options.php defaults to manage_options; align with RWGC_Admin::required_capability().
	 *
	 * @param string $cap Default manage_options.
	 * @return string
	 */
	public static function filter_option_page_capability( $cap ) {
		if ( class_exists( 'RWGC_Admin', false ) ) {
			return RWGC_Admin::required_capability();
		}
		return $cap;
	}

	/**
	 * Ensure defaults exist in the database.
	 *
	 * @return void
	 */
	public static function ensure_defaults() {
		$current = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $current ) || empty( $current ) ) {
			update_option( self::OPTION_KEY, self::get_defaults() );
		}
	}

	/**
	 * Register settings with WordPress.
	 *
	 * @return void
	 */
	public static function register_settings() {
		$cap = 'manage_options';
		if ( class_exists( 'RWGC_Admin', false ) ) {
			$cap = RWGC_Admin::required_capability();
		}
		register_setting(
			'rwgc_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
				'capability'        => $cap,
			)
		);
	}

	/**
	 * Get all settings (merged with defaults).
	 *
	 * @return array
	 */
	public static function get_settings() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$merged = array_merge( $defaults, $stored );
		unset( $merged['reactwoo_api_base'], $merged['reactwoo_license_key'] );
		return $merged;
	}

	/**
	 * Get single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default if not set.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Overwrite all settings.
	 *
	 * @param array $settings Settings array.
	 * @return void
	 */
	public static function update( $settings ) {
		if ( ! is_array( $settings ) ) {
			return;
		}
		$settings = self::sanitize_settings( $settings );
		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Update a single setting key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function update_key( $key, $value ) {
		$settings         = self::get_settings();
		$settings[ $key ] = $value;
		self::update( $settings );
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$defaults = self::get_defaults();
		$settings = is_array( $input ) ? $input : array();

		$out = $defaults;

		$out['enabled']             = ! empty( $settings['enabled'] ) ? 1 : 0;
		$out['maxmind_account_id']  = isset( $settings['maxmind_account_id'] ) ? sanitize_text_field( $settings['maxmind_account_id'] ) : '';
		$out['maxmind_license_key'] = isset( $settings['maxmind_license_key'] ) ? sanitize_text_field( $settings['maxmind_license_key'] ) : '';
		$out['auto_update_db']      = ! empty( $settings['auto_update_db'] ) ? 1 : 0;
		$out['cache_enabled']       = ! empty( $settings['cache_enabled'] ) ? 1 : 0;
		$out['cache_ttl']           = isset( $settings['cache_ttl'] ) ? max( 60, (int) $settings['cache_ttl'] ) : $defaults['cache_ttl'];
		$fc = isset( $settings['fallback_country'] ) ? strtoupper( substr( sanitize_text_field( $settings['fallback_country'] ), 0, 2 ) ) : $defaults['fallback_country'];
		$country_opts               = class_exists( 'RWGC_Countries', false ) ? RWGC_Countries::get_options() : array();
		$out['fallback_country']    = isset( $country_opts[ $fc ] ) ? $fc : $defaults['fallback_country'];

		$fcur = isset( $settings['fallback_currency'] ) ? strtoupper( substr( sanitize_text_field( $settings['fallback_currency'] ), 0, 3 ) ) : $defaults['fallback_currency'];
		$currency_opts              = class_exists( 'RWGC_Countries', false ) ? RWGC_Countries::get_currency_options() : array();
		$out['fallback_currency']     = isset( $currency_opts[ $fcur ] ) ? $fcur : $defaults['fallback_currency'];
		$out['rest_enabled']        = ! empty( $settings['rest_enabled'] ) ? 1 : 0;
		$out['debug_mode']          = ! empty( $settings['debug_mode'] ) ? 1 : 0;
		$out['db_last_updated']     = isset( $settings['db_last_updated'] ) ? sanitize_text_field( $settings['db_last_updated'] ) : '';
		$out['db_file_path']        = isset( $settings['db_file_path'] ) ? sanitize_text_field( $settings['db_file_path'] ) : '';
		$out['db_last_error']       = isset( $settings['db_last_error'] ) ? sanitize_text_field( $settings['db_last_error'] ) : '';
		$out['migration_completed'] = ! empty( $settings['migration_completed'] ) ? 1 : 0;

		return $out;
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'             => 1,
			'maxmind_account_id'  => '',
			'maxmind_license_key' => '',
			'auto_update_db'      => 1,
			'cache_enabled'       => 1,
			'cache_ttl'           => 6 * HOUR_IN_SECONDS,
			'fallback_country'    => 'US',
			'fallback_currency'   => 'USD',
			'rest_enabled'        => 1,
			'debug_mode'          => 0,
			'db_last_updated'     => '',
			'db_file_path'        => '',
			'db_last_error'       => '',
			'migration_completed' => 0,
		);
	}
}

