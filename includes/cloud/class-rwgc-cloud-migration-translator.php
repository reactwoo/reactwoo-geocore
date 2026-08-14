<?php
/**
 * Translate local Geo config into Cloud platform contracts (WP16).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure translation: portable visibility rules → Audiences; slots/variants pass through.
 */
final class RWGC_Cloud_Migration_Translator {

	/**
	 * @param array<string, mixed> $inventory Detected local items.
	 * @return array{supported: array<int, array<string, mixed>>, unsupported: array<int, array<string, mixed>>, resources: array<string, array<int, array<string, mixed>>>}
	 */
	public static function preview( array $inventory ) {
		$supported   = array();
		$unsupported = array();
		$resources   = array(
			'audiences'   => array(),
			'slots'       => array(),
			'variants'    => array(),
			'experiments' => array(),
		);

		foreach ( self::as_list( $inventory['visibility_rules'] ?? array() ) as $row ) {
			$result = self::translate_visibility_rule( $row );
			if ( $result['ok'] ) {
				$supported[]             = $result['item'];
				$resources['audiences'][] = $result['resource'];
			} else {
				$unsupported[] = $result['item'];
			}
		}

		foreach ( self::as_list( $inventory['slots'] ?? array() ) as $row ) {
			$result = self::translate_slot( $row );
			if ( $result['ok'] ) {
				$supported[]          = $result['item'];
				$resources['slots'][] = $result['resource'];
			} else {
				$unsupported[] = $result['item'];
			}
		}

		foreach ( self::as_list( $inventory['variants'] ?? array() ) as $row ) {
			$result = self::translate_variant( $row );
			if ( $result['ok'] ) {
				$supported[]             = $result['item'];
				$resources['variants'][] = $result['resource'];
			} else {
				$unsupported[] = $result['item'];
			}
		}

		foreach ( self::as_list( $inventory['experiments'] ?? array() ) as $row ) {
			$unsupported[] = array(
				'kind'   => 'experiment',
				'id'     => (string) ( $row['id'] ?? '' ),
				'name'   => (string) ( $row['name'] ?? $row['title'] ?? '' ),
				'reason' => 'experiment_needs_review',
			);
		}

		foreach ( self::as_list( $inventory['commerce_rules'] ?? array() ) as $row ) {
			$unsupported[] = array(
				'kind'   => 'commerce_rule',
				'id'     => (string) ( $row['id'] ?? '' ),
				'name'   => (string) ( $row['name'] ?? $row['title'] ?? '' ),
				'reason' => 'commerce_outcomes_stay_local',
			);
		}

		return array(
			'supported'   => $supported,
			'unsupported' => $unsupported,
			'resources'   => $resources,
		);
	}

	/**
	 * @param array<string, mixed> $row Registry row.
	 * @return array{ok: bool, item: array<string, mixed>, resource?: array<string, mixed>}
	 */
	public static function translate_visibility_rule( array $row ) {
		$id    = (string) ( $row['id'] ?? '' );
		$name  = (string) ( $row['label'] ?? $row['name'] ?? $id );
		$kind  = 'visibility_rule';
		$set   = isset( $row['rules'] ) && is_array( $row['rules'] ) ? $row['rules'] : array();
		$mode  = isset( $set['mode'] ) ? (string) $set['mode'] : 'show_if';
		$rules = isset( $set['rules'] ) && is_array( $set['rules'] ) ? $set['rules'] : array();

		if ( 'hide_if' === $mode ) {
			return array(
				'ok'   => false,
				'item' => array(
					'kind'   => $kind,
					'id'     => $id,
					'name'   => $name,
					'reason' => 'hide_if_not_imported',
				),
			);
		}

		$groups = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$translated = self::translate_condition_list(
				isset( $rule['conditions'] ) && is_array( $rule['conditions'] ) ? $rule['conditions'] : array(),
				isset( $rule['match'] ) ? (string) $rule['match'] : 'all'
			);
			if ( null === $translated ) {
				return array(
					'ok'   => false,
					'item' => array(
						'kind'   => $kind,
						'id'     => $id,
						'name'   => $name,
						'reason' => 'unsupported_condition',
					),
				);
			}
			$groups[] = $translated;
		}

		if ( empty( $groups ) ) {
			return array(
				'ok'   => false,
				'item' => array(
					'kind'   => $kind,
					'id'     => $id,
					'name'   => $name,
					'reason' => 'empty_rule',
				),
			);
		}

		$top = ( isset( $set['match'] ) && 'all' === $set['match'] ) ? 'all' : 'any';
		$conditions = 1 === count( $groups ) ? $groups[0] : array( $top => $groups );

		$audience_id = 'aud_local_' . preg_replace( '/[^a-zA-Z0-9_-]/', '_', $id );
		return array(
			'ok'       => true,
			'item'     => array(
				'kind'   => $kind,
				'id'     => $id,
				'name'   => $name,
				'reason' => '',
			),
			'resource' => array(
				'id'             => $audience_id,
				'name'           => $name,
				'local_source'   => 'rwgc_visibility_rule',
				'local_id'       => $id,
				'conditions'     => $conditions,
			),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $conditions Portable conditions.
	 * @param string                           $match all|any.
	 * @return array<string, mixed>|null
	 */
	public static function translate_condition_list( array $conditions, $match ) {
		$out = array();
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				return null;
			}
			$translated = self::translate_condition( $condition );
			if ( null === $translated ) {
				return null;
			}
			$out[] = $translated;
		}
		if ( empty( $out ) ) {
			return null;
		}
		$key = 'all' === $match ? 'all' : 'any';
		return array( $key => $out );
	}

	/**
	 * @param array<string, mixed> $condition Portable condition.
	 * @return array<string, mixed>|null
	 */
	public static function translate_condition( array $condition ) {
		$type = isset( $condition['type'] ) ? (string) $condition['type'] : '';
		if ( 'condition_group' === $type ) {
			$value = isset( $condition['value'] ) && is_array( $condition['value'] ) ? $condition['value'] : array();
			$match = isset( $value['match'] ) ? (string) $value['match'] : 'all';
			$inner = isset( $value['conditions'] ) && is_array( $value['conditions'] ) ? $value['conditions'] : array();
			return self::translate_condition_list( $inner, $match );
		}
		if ( 'page_version_url' === $type ) {
			return null;
		}
		$capability = class_exists( 'RWGC_Schema', false )
			? RWGC_Schema::normalize_capability_id( $type )
			: '';
		if ( '' === $capability ) {
			return null;
		}
		$operator = self::normalize_operator( isset( $condition['operator'] ) ? (string) $condition['operator'] : 'in' );
		if ( '' === $operator ) {
			return null;
		}
		return array(
			'capability' => $capability,
			'operator'   => $operator,
			'value'      => isset( $condition['value'] ) ? $condition['value'] : array(),
		);
	}

	/**
	 * @param array<string, mixed> $row Slot row.
	 * @return array{ok: bool, item: array<string, mixed>, resource?: array<string, mixed>}
	 */
	public static function translate_slot( array $row ) {
		$id   = (string) ( $row['id'] ?? '' );
		$name = (string) ( $row['name'] ?? $id );
		if ( '' === $id || '' === $name ) {
			return array(
				'ok'   => false,
				'item' => array(
					'kind'   => 'slot',
					'id'     => $id,
					'name'   => $name,
					'reason' => 'invalid_slot',
				),
			);
		}
		return array(
			'ok'       => true,
			'item'     => array(
				'kind'   => 'slot',
				'id'     => $id,
				'name'   => $name,
				'reason' => '',
			),
			'resource' => array(
				'id'     => $id,
				'name'   => $name,
				'status' => (string) ( $row['status'] ?? 'available' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $row Variant row.
	 * @return array{ok: bool, item: array<string, mixed>, resource?: array<string, mixed>}
	 */
	public static function translate_variant( array $row ) {
		$id   = (string) ( $row['id'] ?? '' );
		$name = (string) ( $row['name'] ?? $id );
		$type = self::normalize_variant_type( isset( $row['type'] ) ? (string) $row['type'] : '' );
		if ( '' === $id || '' === $type ) {
			return array(
				'ok'   => false,
				'item' => array(
					'kind'   => 'variant',
					'id'     => $id,
					'name'   => $name,
					'reason' => 'unsupported_variant_type',
				),
			);
		}
		$resource         = $row;
		$resource['id']   = $id;
		$resource['type'] = $type;
		$resource['name'] = $name;
		return array(
			'ok'       => true,
			'item'     => array(
				'kind'   => 'variant',
				'id'     => $id,
				'name'   => $name,
				'reason' => '',
			),
			'resource' => $resource,
		);
	}

	/**
	 * @param string $type Local or contract type.
	 * @return string
	 */
	public static function normalize_variant_type( $type ) {
		$key = strtolower( str_replace( array( '-', ' ' ), '_', trim( (string) $type ) ) );
		$map = array(
			'default'            => 'default',
			'content'            => 'content',
			'component'          => 'reactwoo_component',
			'reactwoo_component' => 'reactwoo_component',
			'native'             => 'native_reference',
			'native_reference'   => 'native_reference',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}

	/**
	 * @param string $operator Portable operator.
	 * @return string
	 */
	public static function normalize_operator( $operator ) {
		$op = strtolower( trim( (string) $operator ) );
		$map = array(
			'in'      => 'in',
			'not_in'  => 'not_in',
			'equals'  => 'equals',
			'eq'      => 'equals',
			'is'      => 'equals',
			'is_not'  => 'not_in',
		);
		return isset( $map[ $op ] ) ? $map[ $op ] : '';
	}

	/**
	 * @param mixed $value Value.
	 * @return array<int, mixed>
	 */
	private static function as_list( $value ) {
		return is_array( $value ) ? array_values( $value ) : array();
	}
}
