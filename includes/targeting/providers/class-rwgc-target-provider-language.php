<?php
/**
 * Language / locale targets.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress locale-based language signals.
 */
class RWGC_Target_Provider_Language implements RWGC_Target_Provider_Interface {

	/**
	 * @inheritDoc
	 */
	public function get_provider_key() {
		return 'language';
	}

	/**
	 * @inheritDoc
	 */
	public function is_available() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function register_targets( RWGC_Target_Registry $registry ) {
		$ops = array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains' );
		$registry->register_target_type(
			array(
				'key'           => 'language',
				'label'         => __( 'Language', 'reactwoo-geocore' ),
				'group'         => 'language',
				'description'   => __( 'Primary language code from the site locale (e.g. de, en).', 'reactwoo-geocore' ),
				'operators'     => $ops,
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'locale',
				'label'         => __( 'Locale', 'reactwoo-geocore' ),
				'group'         => 'language',
				'description'   => __( 'Full WordPress locale string (e.g. de_DE).', 'reactwoo-geocore' ),
				'operators'     => $ops,
				'value_mode'    => 'text',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$locale = is_string( $locale ) ? $locale : '';
		$lang   = $locale;
		if ( strpos( $lang, '_' ) !== false ) {
			$lang = substr( $lang, 0, strpos( $lang, '_' ) );
		}
		if ( strpos( $lang, '-' ) !== false ) {
			$lang = substr( $lang, 0, strpos( $lang, '-' ) );
		}
		return array(
			'language' => strtolower( sanitize_text_field( $lang ) ),
			'locale'   => sanitize_text_field( $locale ),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_admin_status() {
		return array(
			'label'  => __( 'Language (WordPress locale)', 'reactwoo-geocore' ),
			'state'  => 'ok',
			'detail' => __( 'Uses determine_locale() / get_locale() for the current request.', 'reactwoo-geocore' ),
		);
	}
}
