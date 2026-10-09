<?php
/**
 * Capture experience/variant/goal events into the Cloud queue (no Cloud HTTP).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor-path capture writes locally only. Flush is cron/admin.
 */
final class RWGC_Cloud_Telemetry {

	const COOKIE = 'rwgc_vid';

	/**
	 * Option name. Absent or empty means telemetry is off.
	 */
	const OPT_IN_OPTION = 'rwgc_cloud_telemetry_opt_in';

	/** @var array{experience: string, variant: string, audience: string, goal: string} */
	private static $last = array(
		'experience' => '',
		'variant'    => '',
		'audience'   => '',
		'goal'       => '',
	);

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'reactwoo_experience_slot_render_variant', array( __CLASS__, 'observe_slot_render' ), 20, 6 );
		add_action( 'rwgc_geo_event', array( __CLASS__, 'observe_geo_event' ), 40 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'observe_add_to_cart' ), 40, 0 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'observe_purchase' ), 40, 1 );
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_policy' ) );
	}

	/**
	 * Record a Cloud event locally. No HTTP.
	 *
	 * @param string               $type Type.
	 * @param array<string, mixed> $attrs Attributes.
	 * @return bool
	 */
	public static function record( $type, array $attrs = array() ) {
		if ( ! self::allowed() ) {
			return false;
		}
		$event = array_merge(
			self::$last,
			$attrs,
			array(
				'type'                 => (string) $type,
				'timestamp'            => gmdate( 'c' ),
				'anonymous_visitor_id' => self::visitor_id(),
			)
		);
		$ok = RWGC_Cloud_Event_Queue::enqueue( $event );
		if ( $ok ) {
			if ( ! empty( $event['experience'] ) ) {
				self::$last['experience'] = (string) $event['experience'];
			}
			if ( ! empty( $event['variant'] ) ) {
				self::$last['variant'] = (string) $event['variant'];
			}
			if ( ! empty( $event['audience'] ) ) {
				self::$last['audience'] = (string) $event['audience'];
			}
			if ( ! empty( $event['goal'] ) ) {
				self::$last['goal'] = (string) $event['goal'];
			}
		}
		return $ok;
	}

	/**
	 * After a non-default variant renders, enqueue impressions.
	 *
	 * @param string|null                        $html HTML.
	 * @param string                             $slot_id Slot.
	 * @param string                             $variant_id Variant.
	 * @param string                             $default_html Default.
	 * @param RWGC_Decision_Result|null          $decision Decision.
	 * @param RWGC_Contract_Experience_Slot|null $slot Slot.
	 * @return string|null
	 */
	public static function observe_slot_render( $html, $slot_id, $variant_id, $default_html, $decision, $slot ) {
		unset( $slot, $slot_id );
		if ( ! is_string( $html ) || '' === $html || $html === $default_html ) {
			return $html;
		}
		$variant_id = is_string( $variant_id ) ? $variant_id : '';
		if ( '' === $variant_id || 'default' === $variant_id || 'variant_original' === $variant_id ) {
			return $html;
		}

		$experience = '';
		$audience   = '';
		$goal       = '';
		if ( $decision instanceof RWGC_Decision_Result ) {
			foreach ( $decision->selected_experiences() as $row ) {
				if ( isset( $row['variant_id'] ) && (string) $row['variant_id'] === $variant_id ) {
					$experience = isset( $row['id'] ) ? (string) $row['id'] : '';
					$audience   = isset( $row['audience_id'] ) ? (string) $row['audience_id'] : '';
					$goal       = isset( $row['goal_id'] ) ? (string) $row['goal_id'] : '';
					break;
				}
			}
			if ( '' === $audience ) {
				$matched = $decision->matched_audiences();
				$audience = isset( $matched[0] ) ? (string) $matched[0] : '';
			}
		}

		self::record(
			'variant.impression',
			array(
				'experience' => $experience,
				'variant'    => $variant_id,
				'audience'   => $audience,
				'goal'       => $goal,
			)
		);
		return $html;
	}

	/**
	 * Map a subset of Geo Core events onto Cloud types.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return void
	 */
	public static function observe_geo_event( $payload ) {
		if ( ! is_array( $payload ) ) {
			return;
		}
		$raw = isset( $payload['event_type'] ) ? (string) $payload['event_type'] : '';
		$map = array(
			'goal_click'     => 'goal.click',
			'goal.click'     => 'goal.click',
			'goal_lead'      => 'goal.lead',
			'goal.lead'      => 'goal.lead',
			'add_to_cart'    => 'commerce.add_to_cart',
			'purchase'       => 'commerce.purchase',
		);
		if ( ! isset( $map[ $raw ] ) ) {
			return;
		}
		$attrs = array();
		if ( isset( $payload['value'] ) ) {
			$attrs['value'] = $payload['value'];
		}
		if ( ! empty( $payload['experience'] ) ) {
			$attrs['experience'] = $payload['experience'];
		}
		if ( ! empty( $payload['variant'] ) ) {
			$attrs['variant'] = $payload['variant'];
		}
		self::record( $map[ $raw ], $attrs );
	}

	/**
	 * @return void
	 */
	public static function observe_add_to_cart() {
		self::record( 'commerce.add_to_cart' );
	}

	/**
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function observe_purchase( $order_id ) {
		$value = null;
		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order && method_exists( $order, 'get_total' ) ) {
				$value = (float) $order->get_total();
			}
		}
		self::record( 'commerce.purchase', array( 'value' => $value ) );
	}

	/**
	 * @return bool
	 */
	/**
	 * Whether an administrator has opted in. The filter default is that option, which is off.
	 *
	 * @return bool
	 */
	public static function is_opted_in() {
		$opted = false;
		if ( function_exists( 'get_option' ) ) {
			$opted = (bool) get_option( self::OPT_IN_OPTION, false );
		}
		/**
		 * Consent gate for Cloud telemetry. Default is the stored opt-in, which is off.
		 *
		 * @param bool $allowed Allowed.
		 */
		return (bool) apply_filters( 'rwgc_cloud_telemetry_allowed', $opted );
	}

	/**
	 * @return bool
	 */
	private static function allowed() {
		if ( ! self::is_opted_in() ) {
			return false;
		}
		if ( class_exists( 'RWGC_Cloud_Connection', false ) && ! RWGC_Cloud_Connection::is_connected() ) {
			return false;
		}
		if ( function_exists( 'is_admin' ) && is_admin() && ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Suggested privacy-policy text for Settings → Privacy.
	 *
	 * @return void
	 */
	public static function register_privacy_policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'ReactWoo Geo Core stores first-party cookies on this site for country cache variation (rwgc_cc, rwgc_pv), returning-visitor detection (rwgc_returning, rwgc_rv), and campaign parameters from the request (rwgc_ft, rwgc_st). Those cookies are not uploaded to ReactWoo.', 'reactwoo-geocore' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Anonymous Cloud telemetry is off unless an administrator enables it on ReactWoo Cloud. When enabled, and only while the site is paired, the plugin sets rwgc_vid (a random id, about one year) and later sends experience, variant, audience, and goal ids, the event type, and an optional order total to https://decision.reactwoo.com. Email addresses are not included. Turning telemetry off clears rwgc_vid.', 'reactwoo-geocore' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If an administrator downloads the GeoLite2 database, the MaxMind account ID and license key are sent to download.maxmind.com. If QUIC.cloud CDN mode is enabled, the site requests QUIC.cloud’s public edge IP list. Optional AI calls https://api.reactwoo.com only when a commercial add-on supplies a license key. The WordPress.org build of Geo Core does not check api.reactwoo.com for plugin updates.', 'reactwoo-geocore' ) . '</p>';
		wp_add_privacy_policy_content(
			'ReactWoo Geo Core',
			wp_kses_post( $content )
		);
	}

	/**
	 * Expire the anonymous visitor cookie after opt-out.
	 *
	 * @return void
	 */
	public static function clear_visitor_cookie() {
		if ( function_exists( 'headers_sent' ) && headers_sent() ) {
			return;
		}
		if ( function_exists( 'setcookie' ) ) {
			$secure = function_exists( 'is_ssl' ) ? is_ssl() : false;
			$past   = time() - ( defined( 'YEAR_IN_SECONDS' ) ? YEAR_IN_SECONDS : 31536000 );
			setcookie( self::COOKIE, '', $past, '/', '', $secure, true );
		}
		unset( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * Anonymous visitor id (cookie). Never email.
	 *
	 * @return string
	 */
	private static function visitor_id() {
		if ( isset( $_COOKIE[ self::COOKIE ] ) && is_string( $_COOKIE[ self::COOKIE ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$existing = preg_replace( '/[^a-zA-Z0-9._:-]/', '', (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			if ( is_string( $existing ) && strlen( $existing ) >= 8 && strlen( $existing ) <= 64 ) {
				return $existing;
			}
		}
		$id = bin2hex( function_exists( 'random_bytes' ) ? random_bytes( 12 ) : openssl_random_pseudo_bytes( 12 ) );
		if ( function_exists( 'headers_sent' ) && ! headers_sent() && function_exists( 'setcookie' ) ) {
			$expire = time() + ( defined( 'YEAR_IN_SECONDS' ) ? YEAR_IN_SECONDS : 31536000 );
			$secure = function_exists( 'is_ssl' ) ? is_ssl() : false;
			setcookie( self::COOKIE, $id, $expire, '/', '', $secure, true );
		}
		$_COOKIE[ self::COOKIE ] = $id;
		return $id;
	}
}
