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
	 * Settings as stored now, not the copy WordPress cached at the start of this request.
	 *
	 * `rwgc_settings` is autoloaded, so get_option() keeps returning the alloptions
	 * snapshot from the first read. A MaxMind download holds that snapshot across
	 * the HTTP call. Writing it back afterwards replaces a license key or fallback
	 * country an admin saved while the download was in flight. Drop the cached
	 * option first. Deleting alloptions (the same call WordPress uses) forces the
	 * next read to load the database row. Do not write the stale alloptions array
	 * back into the cache.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings_fresh() {
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( self::OPTION_KEY, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}
		return self::get_settings();
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

		/*
		 * Settings → General posts only its own fields to options.php. Keep the
		 * values that live on other screens (MaxMind credentials, database state)
		 * instead of resetting them to defaults.
		 */
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_KEY, array() ) : array();
		if ( is_array( $stored ) ) {
			foreach ( self::get_preserved_keys() as $key ) {
				if ( ! array_key_exists( $key, $settings ) && array_key_exists( $key, $stored ) ) {
					$settings[ $key ] = $stored[ $key ];
				}
			}
		}

		$out = $defaults;

		$out['enabled']             = ! empty( $settings['enabled'] ) ? 1 : 0;
		$out['quic_cloud_cdn']      = ! empty( $settings['quic_cloud_cdn'] ) ? 1 : 0;
		$out['maxmind_account_id']  = isset( $settings['maxmind_account_id'] ) ? self::sanitize_credential( $settings['maxmind_account_id'] ) : '';
		$out['maxmind_license_key'] = isset( $settings['maxmind_license_key'] ) ? self::sanitize_credential( $settings['maxmind_license_key'] ) : '';
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
		$out['db_last_success_at']  = isset( $settings['db_last_success_at'] ) ? sanitize_text_field( $settings['db_last_success_at'] ) : '';
		$out['db_last_failure_at']  = isset( $settings['db_last_failure_at'] ) ? sanitize_text_field( $settings['db_last_failure_at'] ) : '';
		$out['db_file_path']        = isset( $settings['db_file_path'] ) ? sanitize_text_field( $settings['db_file_path'] ) : '';
		$out['db_last_error']       = isset( $settings['db_last_error'] ) ? self::sanitize_status_message( $settings['db_last_error'] ) : '';
		$out['migration_completed'] = ! empty( $settings['migration_completed'] ) ? 1 : 0;

		return $out;
	}

	/**
	 * Keys that are not on the Settings → General form and must survive its save.
	 *
	 * @return string[]
	 */
	public static function get_preserved_keys() {
		return array(
			'maxmind_account_id',
			'maxmind_license_key',
			'auto_update_db',
			'db_last_updated',
			'db_last_success_at',
			'db_last_failure_at',
			'db_file_path',
			'db_last_error',
			'migration_completed',
		);
	}

	/**
	 * Keep a download error readable. sanitize_text_field() drops the whole
	 * message when the MaxMind body contains a byte that is not valid UTF-8,
	 * which left the failure time on screen and cleared the 401 text.
	 *
	 * @param mixed $value Raw message.
	 * @return string
	 */
	public static function sanitize_status_message( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$clean = wp_check_invalid_utf8( $value, true );
			$value = is_string( $clean ) ? $clean : '';
		} elseif ( function_exists( 'iconv' ) ) {
			$clean = iconv( 'UTF-8', 'UTF-8//IGNORE', $value );
			$value = false === $clean ? '' : $clean;
		}
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( $value );
		$value = preg_replace( '/[\r\n\t ]+/', ' ', (string) $value );
		return trim( (string) $value );
	}

	/**
	 * Sanitize a MaxMind credential (strip whitespace and invisible characters).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_credential( $value ) {
		$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		if ( class_exists( 'RWGC_MaxMind', false ) ) {
			return RWGC_MaxMind::normalize_credential( $value );
		}
		return trim( $value );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'             => 1,
			'quic_cloud_cdn'      => 0,
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
			'db_last_success_at'  => '',
			'db_last_failure_at'  => '',
			'db_file_path'        => '',
			'db_last_error'       => '',
			'migration_completed' => 0,
		);
	}
}

