<?php
/**
 * Time-based targets (site timezone).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves time_of_day and day_of_week in the WordPress timezone.
 */
class RWGC_Target_Provider_Time implements RWGC_Target_Provider_Interface {

	/**
	 * @inheritDoc
	 */
	public function get_provider_key() {
		return 'time';
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
				'key'           => 'time_of_day',
				'label'         => __( 'Time of day', 'reactwoo-geocore' ),
				'group'         => 'time',
				'description'   => __( 'Bucket: morning, afternoon, evening, night (site timezone).', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'day_of_week',
				'label'         => __( 'Day of week', 'reactwoo-geocore' ),
				'group'         => 'time',
				'description'   => __( 'Lowercase English weekday (monday..sunday).', 'reactwoo-geocore' ),
				'operators'     => array( 'is', 'is_not', 'in', 'not_in' ),
				'value_mode'    => 'single',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
			)
		);
		$registry->register_target_type(
			array(
				'key'           => 'date_range',
				'label'         => __( 'Date range', 'reactwoo-geocore' ),
				'group'         => 'time',
				'description'   => __( 'Reserved for scheduled windows; not auto-resolved yet.', 'reactwoo-geocore' ),
				'operators'     => array( 'between' ),
				'value_mode'    => 'range',
				'provider'      => $this->get_provider_key(),
				'supports_simulation' => true,
				'is_available_callback' => '__return_false',
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_context_values( array $base = array() ) {
		if ( function_exists( 'wp_timezone' ) ) {
			$tz = wp_timezone();
		} else {
			$tz = new DateTimeZone( wp_timezone_string() ? wp_timezone_string() : 'UTC' );
		}
		$now   = new DateTimeImmutable( 'now', $tz );
		$hour  = (int) $now->format( 'G' );
		$bucket = 'night';
		if ( $hour >= 5 && $hour < 12 ) {
			$bucket = 'morning';
		} elseif ( $hour >= 12 && $hour < 17 ) {
			$bucket = 'afternoon';
		} elseif ( $hour >= 17 && $hour < 22 ) {
			$bucket = 'evening';
		}
		$dow = strtolower( $now->format( 'l' ) );
		return array(
			'time_of_day' => $bucket,
			'day_of_week' => $dow,
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_admin_status() {
		return array(
			'label'  => __( 'Time (site timezone)', 'reactwoo-geocore' ),
			'state'  => 'ok',
			'detail' => __( 'Uses wp_timezone() for bucketing.', 'reactwoo-geocore' ),
		);
	}
}
