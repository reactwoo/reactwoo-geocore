<?php
/**
 * Request-time Decision Runtime for Experience Slots (Gate D).
 *
 * Evaluates the cached Cloud manifest locally. Never calls Cloud HTTP.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides {@see apply_filters( 'reactwoo_current_decision_result' )} from the local cache.
 */
final class RWGC_Request_Decision {

	/** @var bool */
	private static $computed = false;

	/** @var RWGC_Decision_Result|null */
	private static $result = null;

	/** @var bool */
	private static $hooks = false;

	/**
	 * @return void
	 */
	public static function init() {
		if ( self::$hooks ) {
			return;
		}
		self::$hooks = true;
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'reactwoo_current_decision_result', array( __CLASS__, 'current' ), 10, 1 );
			add_filter( 'reactwoo_decision_context_resolvers', array( __CLASS__, 'context_resolvers' ), 10, 1 );
		}
	}

	/**
	 * Reset per-request memo (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$computed = false;
		self::$result   = null;
		if ( class_exists( 'RWGC_Decision_Runtime', false ) ) {
			RWGC_Decision_Runtime::reset_request_cache();
		}
		if ( class_exists( 'RWGC_Context_Value_Cache', false ) ) {
			RWGC_Context_Value_Cache::reset();
		}
	}

	/**
	 * Lazy context providers used by {@see RWGC_Decision_Context_Factory::for_request()}.
	 *
	 * @param mixed $resolvers Resolvers.
	 * @return array<string, callable>
	 */
	public static function context_resolvers( $resolvers ) {
		if ( ! is_array( $resolvers ) ) {
			$resolvers = array();
		}
		if ( ! isset( $resolvers['geo.country'] ) ) {
			$resolvers['geo.country'] = static function () {
				if ( function_exists( 'rwgc_get_visitor_country' ) ) {
					return strtoupper( (string) rwgc_get_visitor_country() );
				}
				return '';
			};
		}
		return $resolvers;
	}

	/**
	 * Filter callback: cached-manifest evaluation, or null when none.
	 *
	 * @param mixed $prior Prior value.
	 * @return RWGC_Decision_Result|null
	 */
	public static function current( $prior = null ) {
		if ( $prior instanceof RWGC_Decision_Result ) {
			return $prior;
		}
		if ( self::$computed ) {
			return self::$result;
		}
		self::$computed = true;
		self::$result   = self::evaluate();
		return self::$result;
	}

	/**
	 * @return RWGC_Decision_Result|null
	 */
	private static function evaluate() {
		if ( ! class_exists( 'RWGC_Cloud_Manifest_Store', false ) || ! class_exists( 'RWGC_Decision_Runtime', false ) ) {
			return null;
		}

		try {
			$manifest = RWGC_Cloud_Manifest_Store::current();
			if ( ! $manifest ) {
				return null;
			}

			self::hydrate_runtime( $manifest );

			$eager = array();
			if ( function_exists( 'rwgc_get_visitor_country' ) ) {
				$country = strtoupper( (string) rwgc_get_visitor_country() );
				if ( '' !== $country ) {
					$eager['geo.country'] = $country;
				}
			}

			$context = RWGC_Decision_Context_Factory::for_request( $eager );
			return RWGC_Decision_Runtime::evaluate(
				$manifest,
				$context,
				array(
					'visitor_id' => self::visitor_id(),
				)
			);
		} catch ( \Throwable $e ) { // phpcs:ignore WordPress.CodeAnalysis.ExceptionDocumented
			if ( function_exists( 'error_log' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'RWGC_Request_Decision: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return null;
		}
	}

	/**
	 * Inject manifest variants/slots for this request only. No option writes. No Cloud HTTP.
	 *
	 * @param RWGC_Contract_Manifest $manifest Manifest.
	 * @return void
	 */
	private static function hydrate_runtime( RWGC_Contract_Manifest $manifest ) {
		if ( class_exists( 'RWGC_Variant_Store', false ) ) {
			foreach ( $manifest->variants() as $variant ) {
				RWGC_Variant_Store::put_runtime( $variant->to_array() );
			}
		}

		if ( ! class_exists( 'RWGC_Experience_Slot_Registry', false ) ) {
			return;
		}

		foreach ( $manifest->slots() as $slot ) {
			self::overlay_slot( $slot->to_array() );
		}

		foreach ( $manifest->experiences() as $exp ) {
			$sid = $exp->slot_id();
			if ( '' === $sid || '_default' === $sid || 'pending' === $sid ) {
				continue;
			}
			if ( null !== RWGC_Experience_Slot_Registry::get( $sid ) ) {
				continue;
			}
			self::overlay_slot(
				array(
					'id'      => $sid,
					'name'    => $exp->name() !== '' ? $exp->name() : $sid,
					'status'  => 'active',
					'adapter' => 'gutenberg',
				)
			);
		}
	}

	/**
	 * Stable anonymous visitor id for experiment assignment.
	 *
	 * Uses the Cloud telemetry cookie when present so impressions and buckets share an id.
	 *
	 * @return string
	 */
	private static function visitor_id() {
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'reactwoo_decision_visitor_id', '' );
			if ( is_string( $filtered ) ) {
				$filtered = trim( $filtered );
				if ( '' !== $filtered ) {
					return $filtered;
				}
			}
		}

		if ( class_exists( 'RWGC_Cloud_Telemetry', false ) && method_exists( 'RWGC_Cloud_Telemetry', 'anonymous_visitor_id' ) ) {
			return (string) RWGC_Cloud_Telemetry::anonymous_visitor_id();
		}

		if ( isset( $_COOKIE['rwgc_vid'] ) && is_string( $_COOKIE['rwgc_vid'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$existing = preg_replace( '/[^a-zA-Z0-9._:-]/', '', (string) $_COOKIE['rwgc_vid'] );
			if ( is_string( $existing ) && strlen( $existing ) >= 8 && strlen( $existing ) <= 64 ) {
				return $existing;
			}
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $data Slot row.
	 * @return void
	 */
	private static function overlay_slot( array $data ) {
		if ( ! method_exists( 'RWGC_Experience_Slot_Registry', 'put_runtime' ) ) {
			return;
		}
		RWGC_Experience_Slot_Registry::put_runtime( $data );
	}
}
