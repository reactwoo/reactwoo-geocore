<?php
/**
 * Commerce / WordPress user targets (WooCommerce when available).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers logged-in / role / segment targets for storefront contexts.
 */
class RWGC_Target_Provider_Commerce implements RWGC_Target_Provider_Interface {

	/**
	 * @inheritDoc
	 */
	public function get_provider_key() {
		return 'commerce';
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
		$registry->register_target_type(
			array(
				'key'           => 'logged_in',
				'label'         => __( 'Logged in', 'reactwoo-geocore' ),
				'group'         => 'commerce_user',
				'description'   => __( 'Whether the visitor is authenticated in WordPress.', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not' ),
				'value_mode'    => 'boolean',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'user_role',
				'label'         => __( 'User role', 'reactwoo-geocore' ),
				'group'         => 'commerce_user',
				'description'   => __( 'Primary WordPress role slug for the current user.', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'customer_segment',
				'label'         => __( 'Customer segment', 'reactwoo-geocore' ),
				'group'         => 'commerce_user',
				'description'   => __( 'CRM / segment slug when an integration supplies it.', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not', 'in', 'not_in', 'contains', 'not_contains' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
				'is_available_callback' => array( __CLASS__, 'segment_configured' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $definition Definition.
	 * @return bool
	 */
	public static function segment_configured( $definition ) {
		$slug = apply_filters( 'rwgc_customer_segment_slug', '', get_current_user_id() );
		return is_string( $slug ) && '' !== $slug;
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		$logged = is_user_logged_in();
		$role   = '';
		if ( $logged ) {
			$user = wp_get_current_user();
			if ( $user && ! empty( $user->roles[0] ) ) {
				$role = sanitize_key( (string) $user->roles[0] );
			}
		}
		$segment = apply_filters( 'rwgc_customer_segment_slug', '', get_current_user_id() );
		$segment = is_string( $segment ) ? sanitize_key( $segment ) : '';

		return array(
			'logged_in'        => $logged,
			'user_role'        => $role,
			'customer_segment' => $segment,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_admin_status() {
		$wc = function_exists( 'rwgc_is_woocommerce_active' ) && rwgc_is_woocommerce_active();
		return array(
			'label'  => __( 'Commerce / user', 'reactwoo-geocore' ),
			'state'  => 'ok',
			'detail' => $wc
				? __( 'WooCommerce active; storefront context available.', 'reactwoo-geocore' )
				: __( 'WordPress user signals; WooCommerce optional for storefront satellites.', 'reactwoo-geocore' ),
		);
	}
}
