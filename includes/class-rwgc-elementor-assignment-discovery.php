<?php
/**
 * Discover Elementor visibility rule assignments on a document.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads stored Elementor JSON for geo visibility assignments (no duplicate evaluator logic).
 */
class RWGC_Elementor_Assignment_Discovery {

	/**
	 * @param int    $content_id   Post ID.
	 * @param string $content_type page|post|product|elementor_library.
	 * @return array<string,mixed>
	 */
	public static function get_assignments_for_content( $content_id, $content_type = 'page' ) {
		$content_id   = absint( $content_id );
		$content_type = sanitize_key( (string) $content_type );
		$out          = array(
			'content_id'   => $content_id,
			'content_type' => $content_type,
			'assignments'  => array(),
		);
		if ( $content_id <= 0 ) {
			return $out;
		}

		$raw = (string) get_post_meta( $content_id, '_elementor_data', true );
		if ( '' !== trim( $raw ) ) {
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				self::walk_elements( $data, $out['assignments'] );
			}
		}

		$page_settings = get_post_meta( $content_id, '_elementor_page_settings', true );
		if ( is_array( $page_settings ) ) {
			self::append_surface_assignment(
				$out['assignments'],
				'elementor:document:' . $content_id,
				'document',
				get_the_title( $content_id ) ?: __( 'Document', 'reactwoo-geocore' ),
				$page_settings
			);
		}

		/**
		 * @param array<int,array<string,mixed>> $assignments Assignments discovered so far.
		 * @param int                            $content_id  Post ID.
		 * @param string                         $content_type Content type.
		 */
		$out['assignments'] = apply_filters( 'rwgc_rule_tester_assignments', $out['assignments'], $content_id, $content_type );

		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $elements    Elementor nodes.
	 * @param array<int,array<string,mixed>> $assignments Output list.
	 * @return void
	 */
	private static function walk_elements( array $elements, array &$assignments ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$el_id   = isset( $element['id'] ) ? (string) $element['id'] : '';
			$el_type = isset( $element['elType'] ) ? (string) $element['elType'] : 'element';
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			if ( class_exists( 'RWGC_Surface_Settings', false ) ) {
				$settings = RWGC_Surface_Settings::normalize( $settings );
			}

			$label = self::element_label( $element, $settings );
			self::append_surface_assignment(
				$assignments,
				'elementor:' . $el_type . ':' . $el_id,
				$el_type,
				$label,
				$settings
			);

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk_elements( $element['elements'], $assignments );
			}
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $assignments Output.
	 * @param string                         $assignment_id Stable id.
	 * @param string                         $element_type  section|container|widget|document.
	 * @param string                         $label         Human label.
	 * @param array<string,mixed>            $settings      Elementor settings.
	 * @return void
	 */
	private static function append_surface_assignment( array &$assignments, $assignment_id, $element_type, $label, array $settings ) {
		if ( ! self::has_visibility_rule_assignment( $settings ) ) {
			return;
		}
		$rule_id = self::assigned_rule_id( $settings );
		if ( $rule_id <= 0 ) {
			return;
		}
		$mode = 'show_if';
		if ( class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			$mode = RWGC_Targeting_Surface_Evaluator::get_visibility_rules_mode( $settings, null );
		} elseif ( ! empty( $settings['rwgc_visibility_rules_mode'] ) ) {
			$mode = (string) $settings['rwgc_visibility_rules_mode'];
		}

		$post = get_post( $rule_id );
		$assignments[] = array(
			'assignment_id' => $assignment_id,
			'source'        => 'elementor',
			'element_type'  => sanitize_key( $element_type ),
			'element_label' => $label,
			'rule_id'       => $rule_id,
			'rule_label'    => $post instanceof WP_Post ? (string) $post->post_title : (string) $rule_id,
			'mode'          => self::mode_api_key( $mode ),
			'mode_internal' => self::mode_key( $mode ),
			'mode_label'    => self::mode_label( $mode ),
		);
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @return bool
	 */
	private static function has_visibility_rule_assignment( array $settings ) {
		if ( class_exists( 'RWGC_Targeting_Surface_Evaluator', false ) ) {
			if ( ! RWGC_Targeting_Surface_Evaluator::is_visibility_rules_enabled( $settings ) ) {
				return false;
			}
		} elseif ( empty( $settings['rwgc_enable_visibility_rules'] ) || 'yes' !== (string) $settings['rwgc_enable_visibility_rules'] ) {
			if ( empty( $settings['rwgc_use_portable_geo_targeting'] ) || 'yes' !== (string) $settings['rwgc_use_portable_geo_targeting'] ) {
				return false;
			}
		}
		return self::assigned_rule_id( $settings ) > 0;
	}

	/**
	 * @param array<string,mixed> $settings Settings.
	 * @return int
	 */
	private static function assigned_rule_id( array $settings ) {
		if ( ! empty( $settings['rwgc_visibility_rule_library'] ) ) {
			return absint( $settings['rwgc_visibility_rule_library'] );
		}
		if ( ! empty( $settings['rwgc_applied_visibility_rule_id'] ) ) {
			return absint( $settings['rwgc_applied_visibility_rule_id'] );
		}
		return 0;
	}

	/**
	 * @param array<string,mixed> $element  Element node.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private static function element_label( array $element, array $settings ) {
		if ( ! empty( $settings['_title'] ) ) {
			return (string) $settings['_title'];
		}
		if ( ! empty( $settings['title'] ) ) {
			return (string) $settings['title'];
		}
		$widget = isset( $element['widgetType'] ) ? (string) $element['widgetType'] : '';
		$type   = isset( $element['elType'] ) ? (string) $element['elType'] : 'element';
		if ( '' !== $widget ) {
			return ucwords( str_replace( '-', ' ', $widget ) );
		}
		return ucfirst( $type );
	}

	/**
	 * @param string $mode show_if|hide_if.
	 * @return string
	 */
	public static function mode_api_key( $mode ) {
		$mode = self::mode_key( $mode );
		if ( 'hide_if' === $mode ) {
			return 'hide_when_rule_matches';
		}
		return 'show_only_when_rule_matches';
	}

	/**
	 * @param string $mode show_if|hide_if|API key.
	 * @return string Internal mode.
	 */
	public static function mode_from_api_key( $mode ) {
		$mode = sanitize_key( (string) $mode );
		if ( in_array( $mode, array( 'hide_when_rule_matches', 'hide_if', 'hide' ), true ) ) {
			return 'hide_if';
		}
		return 'show_if';
	}

	/**
	 * @param string $mode show_if|hide_if.
	 * @return string
	 */
	private static function mode_key( $mode ) {
		return function_exists( 'rwgc_normalize_visibility_mode' )
			? rwgc_normalize_visibility_mode( $mode )
			: ( 'hide_if' === $mode ? 'hide_if' : 'show_if' );
	}

	/**
	 * @param string $mode show_if|hide_if.
	 * @return string
	 */
	public static function mode_label( $mode ) {
		$mode = self::mode_key( $mode );
		if ( 'hide_if' === $mode ) {
			return __( 'Hide when rule matches', 'reactwoo-geocore' );
		}
		return __( 'Show only when rule matches', 'reactwoo-geocore' );
	}
}
