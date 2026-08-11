<?php
/**
 * Register built-in Geo Core platform capabilities.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seeds Core conditions/context providers; fires extension hook.
 */
final class RWGC_Platform_Capabilities_Bootstrap {

	/**
	 * @var bool
	 */
	private static $done = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_all' ), 5 );
	}

	/**
	 * @return void
	 */
	public static function register_all() {
		if ( self::$done ) {
			return;
		}
		self::$done = true;

		RWGC_Contracts::load();

		$core = array(
			'geo.country'       => __( 'Country', 'reactwoo-geocore' ),
			'geo.country_group' => __( 'Country group', 'reactwoo-geocore' ),
			'geo.region'        => __( 'Region', 'reactwoo-geocore' ),
			'geo.city'          => __( 'City', 'reactwoo-geocore' ),
			'visitor.language'  => __( 'Language', 'reactwoo-geocore' ),
			'visitor.locale'    => __( 'Locale', 'reactwoo-geocore' ),
			'visitor.device'    => __( 'Device', 'reactwoo-geocore' ),
			'visitor.logged_in' => __( 'Logged in', 'reactwoo-geocore' ),
			'time.of_day'       => __( 'Time of day', 'reactwoo-geocore' ),
			'time.day_of_week'  => __( 'Day of week', 'reactwoo-geocore' ),
			'time.local'        => __( 'Local time', 'reactwoo-geocore' ),
			'time.day'          => __( 'Day', 'reactwoo-geocore' ),
			'time.date'         => __( 'Date', 'reactwoo-geocore' ),
			'traffic.utm_source'   => __( 'UTM source', 'reactwoo-geocore' ),
			'traffic.utm_medium'   => __( 'UTM medium', 'reactwoo-geocore' ),
			'traffic.utm_campaign' => __( 'UTM campaign', 'reactwoo-geocore' ),
			'traffic.source'    => __( 'Traffic source', 'reactwoo-geocore' ),
			'traffic.medium'    => __( 'Traffic medium', 'reactwoo-geocore' ),
			'page.type'         => __( 'Page type', 'reactwoo-geocore' ),
			'page.request_uri'  => __( 'Request URI', 'reactwoo-geocore' ),
			'page.version_url'  => __( 'Page version URL', 'reactwoo-geocore' ),
		);

		foreach ( $core as $id => $label ) {
			// One ID per capability; conditions are also the context keys they evaluate.
			reactwoo_register_condition(
				$id,
				array(
					'label'       => $label,
					'description' => $label,
					'provider'    => 'reactwoo-geocore',
					'version'     => '1',
				)
			);
		}

		/**
		 * Register extension capabilities (Pro, Commerce, Optimise, …).
		 *
		 * Use reactwoo_register_condition / reactwoo_register_action / etc.
		 */
		do_action( 'reactwoo_register_capabilities' );
	}
}
