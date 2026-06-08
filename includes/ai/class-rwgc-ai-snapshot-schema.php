<?php
/**
 * Site intelligence snapshot schema for Geo AI cloud sync.
 *
 * Compact, non-PII structure describing geo targeting configuration — not page content.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioning, shape validation, and sensitive-field stripping for AI snapshots.
 */
class RWGC_AI_Snapshot_Schema {

	const VERSION = 1;

	/**
	 * Top-level keys expected in a v1 snapshot (order preserved for docs/tests).
	 *
	 * @var string[]
	 */
	const TOP_LEVEL_KEYS = array(
		'schema_version',
		'generated_at_gmt',
		'snapshot_hash',
		'site',
		'plugins',
		'modules',
		'target_providers',
		'rules',
		'conditions',
		'variants',
		'parent_pages',
		'popups',
		'forms',
		'tracking_events',
		'conversion_events',
		'relationships',
	);

	/**
	 * Field names stripped from any depth before export (keys, case-insensitive).
	 *
	 * @return string[]
	 */
	public static function default_excluded_fields() {
		$fields = array(
			'post_content',
			'page_content',
			'raw_content',
			'elementor_data',
			'_elementor_data',
			'elementor_json',
			'page_json',
			'ip',
			'ip_address',
			'visitor_ip',
			'remote_addr',
			'email',
			'user_email',
			'billing_email',
			'customer_email',
			'customer',
			'customers',
			'order',
			'orders',
			'billing',
			'shipping',
			'personal_data',
			'license_key',
			'api_key',
			'access_token',
			'refresh_token',
			'jwt',
			'password',
			'secret',
		);

		/**
		 * Extend or replace sensitive snapshot field names stripped before export.
		 *
		 * @param string[] $fields Field keys (matched case-insensitively at any depth).
		 */
		return apply_filters( 'rwgc_ai_snapshot_excluded_fields', $fields );
	}

	/**
	 * @return int
	 */
	public static function get_version() {
		return self::VERSION;
	}

	/**
	 * Strip excluded keys and scrub string values that look like emails or IPs.
	 *
	 * @param mixed $value Payload fragment.
	 * @return mixed
	 */
	public static function strip_sensitive( $value ) {
		$excluded = array_map( 'strtolower', self::default_excluded_fields() );

		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $child ) {
				$key_lower = is_string( $key ) ? strtolower( $key ) : '';
				if ( '' !== $key_lower && in_array( $key_lower, $excluded, true ) ) {
					continue;
				}
				$out[ $key ] = self::strip_sensitive( $child );
			}
			return $out;
		}

		if ( is_string( $value ) ) {
			return self::scrub_scalar_string( $value );
		}

		return $value;
	}

	/**
	 * @param string $value Raw string.
	 * @return string
	 */
	private static function scrub_scalar_string( $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^[a-f0-9]{32,64}$/i', $value ) ) {
			return $value;
		}
		if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
			return '[redacted_email]';
		}
		if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return '[redacted_ip]';
		}
		$scrubbed = preg_replace( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[redacted_email]', $value );
		if ( ! is_string( $scrubbed ) ) {
			$scrubbed = $value;
		}
		$scrubbed = preg_replace( '/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[redacted_ip]', $scrubbed );
		return is_string( $scrubbed ) ? $scrubbed : $value;
	}

	/**
	 * Ensure top-level shape and coerce schema_version.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $payload ) {
		$payload = self::strip_sensitive( $payload );
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$payload['schema_version'] = self::VERSION;

		if ( empty( $payload['generated_at_gmt'] ) ) {
			$payload['generated_at_gmt'] = gmdate( 'c' );
		}

		foreach ( self::TOP_LEVEL_KEYS as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				continue;
			}
			if ( 'snapshot_hash' === $key ) {
				$payload[ $key ] = '';
				continue;
			}
			$payload[ $key ] = array();
		}

		return $payload;
	}

	/**
	 * Validate required top-level keys exist after normalization.
	 *
	 * @param array<string, mixed> $payload Snapshot.
	 * @return bool
	 */
	public static function is_valid_shape( array $payload ) {
		foreach ( self::TOP_LEVEL_KEYS as $key ) {
			if ( ! array_key_exists( $key, $payload ) ) {
				return false;
			}
		}
		return isset( $payload['schema_version'] ) && (int) $payload['schema_version'] === self::VERSION;
	}

	/**
	 * Stable SHA-256 hash of canonical JSON (excludes snapshot_hash key).
	 *
	 * @param array<string, mixed> $payload Snapshot payload.
	 * @return string 64-char hex hash.
	 */
	public static function compute_hash( array $payload ) {
		$copy = $payload;
		unset( $copy['snapshot_hash'] );
		$json = wp_json_encode( $copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		return hash( 'sha256', $json );
	}

	/**
	 * Compact rule-set summary for AI (conditions only, no embedded page content).
	 *
	 * @param array<string, mixed>|null $rule_set Portable rule set.
	 * @return array<string, mixed>|null
	 */
	public static function summarize_rule_set( $rule_set ) {
		if ( ! is_array( $rule_set ) || empty( $rule_set ) ) {
			return null;
		}

		$summary = array(
			'enabled'    => ! empty( $rule_set['enabled'] ),
			'mode'       => isset( $rule_set['mode'] ) ? sanitize_key( (string) $rule_set['mode'] ) : 'show_if',
			'match'      => isset( $rule_set['match'] ) ? sanitize_key( (string) $rule_set['match'] ) : 'any',
			'rule_count' => 0,
			'conditions' => array(),
		);

		$rules = isset( $rule_set['rules'] ) && is_array( $rule_set['rules'] ) ? $rule_set['rules'] : array();
		$summary['rule_count'] = count( $rules );

		$condition_types = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['conditions'] ) || ! is_array( $rule['conditions'] ) ) {
				continue;
			}
			foreach ( $rule['conditions'] as $cond ) {
				if ( ! is_array( $cond ) ) {
					continue;
				}
				$type = isset( $cond['type'] ) ? sanitize_key( (string) $cond['type'] ) : '';
				if ( '' === $type ) {
					continue;
				}
				if ( ! isset( $condition_types[ $type ] ) ) {
					$condition_types[ $type ] = array(
						'type'     => $type,
						'operator' => isset( $cond['operator'] ) ? sanitize_key( (string) $cond['operator'] ) : '',
						'count'    => 0,
					);
				}
				$condition_types[ $type ]['count']++;
			}
		}

		$summary['conditions'] = array_values( $condition_types );
		return $summary;
	}
}
