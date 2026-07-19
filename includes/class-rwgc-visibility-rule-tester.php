<?php
/**
 * Modal rule tester: content + visitor context → detailed evaluator results.
 *
 * @package ReactWooGeoCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates visibility rule test runs for the admin modal tester.
 */
class RWGC_Visibility_Rule_Tester {

	/**
	 * @return array<string,mixed>
	 */
	public static function bootstrap_config() {
		$rules = array();
		foreach ( RWGC_Visibility_Rule_Repository::query() as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$rules[] = array(
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			);
		}

		$countries = array();
		if ( class_exists( 'RWGC_Countries', false ) ) {
			foreach ( RWGC_Countries::get_options() as $code => $label ) {
				$countries[] = array(
					'code'  => strtoupper( (string) $code ),
					'label' => (string) $label,
				);
			}
		}

		return array(
			'rules'     => $rules,
			'pages'     => self::content_options( 'page' ),
			'posts'     => self::content_options( 'post' ),
			'products'  => function_exists( 'wc_get_product' ) ? self::content_options( 'product' ) : array(),
			'countries' => $countries,
		);
	}

	/**
	 * @param int $rule_id Rule post ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_rule_payload( $rule_id ) {
		$rule_id = absint( $rule_id );
		$post    = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
		if ( ! $post ) {
			return new WP_Error( 'rwgc_rule_not_found', __( 'Rule not found.', 'reactwoo-geocore' ), array( 'status' => 404 ) );
		}

		$raw     = (string) get_post_meta( $rule_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
		$decoded = json_decode( $raw, true );
		$set     = is_array( $decoded ) ? $decoded : null;
		$target  = '';
		if ( class_exists( 'RWGC_Visibility_Rule_Editor_Presenter', false ) ) {
			$presenter = RWGC_Visibility_Rule_Editor_Presenter::build( $rule_id, $post->post_title, $post->post_status, $raw );
			$target    = (string) ( $presenter['target_label'] ?? '' );
		}

		$scope_summary = class_exists( 'RWGC_Rule_Context_Compatibility', false )
			? RWGC_Rule_Context_Compatibility::scope_summary( $set )
			: '';

		return array(
			'id'                 => $rule_id,
			'title'              => (string) $post->post_title,
			'target_label'       => $target,
			'scope_summary'      => $scope_summary,
			'portable_json'      => $raw,
			'conditions'         => class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false )
				? RWGC_Visibility_Rule_Logic_Preview::build_compact( $set )
				: array(),
			'default_context'    => self::default_context_from_rule( $set ),
			'presets'            => self::presets_for_rule( $set ),
			'included_countries' => self::rule_included_countries( $set ),
			'excluded_countries' => self::rule_excluded_countries( $set ),
		);
	}

	/**
	 * @param array<string,mixed> $request Test request body.
	 * @return array<string,mixed>
	 */
	public static function run( array $request ) {
		$portable = self::resolve_portable_json( $request );
		if ( is_wp_error( $portable ) ) {
			return array(
				'status'  => 'error',
				'matches' => false,
				'error'   => $portable->get_error_message(),
			);
		}

		$decoded = json_decode( $portable, true );
		if ( ! is_array( $decoded ) ) {
			return array(
				'status'  => 'error',
				'matches' => false,
				'error'   => __( 'Rule data is not valid JSON.', 'reactwoo-geocore' ),
			);
		}

		$norm     = self::normalize_tester_request( $request );
		$resolved = $norm['context'];
		$missing  = self::missing_context_fields( $decoded, $resolved );

		if ( ! empty( $missing ) ) {
			return array(
				'status'            => 'incomplete',
				'matches'           => false,
				'error'             => __( 'Cannot test — required context is missing.', 'reactwoo-geocore' ),
				'missing'           => $missing,
				'condition_results' => array(),
			);
		}

		$detailed = RWGC_Visibility_Rule_Preview::evaluate_detailed( $decoded, $resolved );
		$target   = isset( $request['target_label'] ) ? sanitize_text_field( (string) $request['target_label'] ) : '';
		$logic    = class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false )
			? RWGC_Visibility_Rule_Logic_Preview::build( $decoded, $target )
			: array( 'intro' => '', 'lines' => array() );

		$compat  = self::compatibility_for_tester( $decoded, $request );
		$rule_id = isset( $request['rule_id'] ) ? absint( $request['rule_id'] ) : 0;
		$matched = ! empty( $detailed['matches'] );

		$applied_targets  = self::build_applied_targets( $request, $rule_id, $matched );
		$rendered         = class_exists( 'RWGC_Rule_Tester_Rendered_Impacts', false )
			? RWGC_Rule_Tester_Rendered_Impacts::collect( $request, $rule_id, $norm, $matched )
			: array(
				'impacts'                => array(),
				'dynamic_query_detected' => false,
				'note'                   => '',
			);
		$rendered_impacts = isset( $rendered['impacts'] ) && is_array( $rendered['impacts'] ) ? $rendered['impacts'] : array();

		return array_merge(
			$detailed,
			array(
				'logic_preview'          => $logic,
				'compatibility'          => $compat,
				'applied_targets'          => $applied_targets,
				'rendered_impacts'       => $rendered_impacts,
				'rendered_impacts_meta'  => array(
					'dynamic_query_detected' => ! empty( $rendered['dynamic_query_detected'] ),
					'note'                   => (string) ( $rendered['note'] ?? '' ),
				),
				'result_summary'         => self::build_result_summary(
					$matched,
					$applied_targets,
					$rendered_impacts,
					$norm,
					$detailed
				),
				'preview'                => self::build_preview_response( $request, $rule_id ),
				'document_context'       => $norm['document_context'],
			)
		);
	}

	/**
	 * @param array<string,mixed> $request Assignment preview request.
	 * @return array<string,mixed>
	 */
	public static function run_assignment_preview( array $request ) {
		$assignment_id = isset( $request['assignment_id'] ) ? sanitize_text_field( (string) $request['assignment_id'] ) : '';
		$mode          = isset( $request['mode'] ) ? (string) $request['mode'] : 'show_if';
		if ( class_exists( 'RWGC_Elementor_Assignment_Discovery', false ) ) {
			$mode = RWGC_Elementor_Assignment_Discovery::mode_from_api_key( $mode );
		} elseif ( function_exists( 'rwgc_normalize_visibility_mode' ) ) {
			$mode = rwgc_normalize_visibility_mode( $mode );
		}
		if ( '' === $assignment_id ) {
			return array(
				'status'  => 'error',
				'matches' => false,
				'error'   => __( 'Select an applied target to test.', 'reactwoo-geocore' ),
			);
		}

		$rule_id = isset( $request['rule_id'] ) ? absint( $request['rule_id'] ) : 0;
		if ( $rule_id <= 0 ) {
			return array(
				'status'  => 'error',
				'matches' => false,
				'error'   => __( 'Assignment is missing a visibility rule.', 'reactwoo-geocore' ),
			);
		}

		$rule_run = self::run( $request );
		if ( 'error' === ( $rule_run['status'] ?? '' ) ) {
			return $rule_run;
		}

		$matched  = ! empty( $rule_run['matches'] );
		$visible  = function_exists( 'rwgc_visibility_mode_allows_render' )
			? rwgc_visibility_mode_allows_render( $mode, $matched )
			: $matched;
		$mode_lbl = class_exists( 'RWGC_Elementor_Assignment_Discovery', false )
			? RWGC_Elementor_Assignment_Discovery::mode_label( $mode )
			: ( 'hide_if' === $mode ? __( 'Hide when rule matches', 'reactwoo-geocore' ) : __( 'Show only when rule matches', 'reactwoo-geocore' ) );

		$reason = self::assignment_visibility_reason( $mode, $matched, $visible );

		$conditions = array();
		foreach ( (array) ( $rule_run['condition_results'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$conditions[] = array(
				'label'   => (string) ( $row['label'] ?? '' ),
				'passed'  => 'pass' === ( $row['status'] ?? '' ),
				'message' => (string) ( $row['detail'] ?? '' ),
			);
		}

		return array_merge(
			$rule_run,
			array(
				'assignment_id' => $assignment_id,
				'rule_match'    => $matched,
				'visibility'    => $visible ? 'visible' : 'hidden',
				'mode'          => class_exists( 'RWGC_Elementor_Assignment_Discovery', false )
					? RWGC_Elementor_Assignment_Discovery::mode_api_key( $mode )
					: $mode,
				'mode_label'    => $mode_lbl,
				'reason'        => $reason,
				'conditions'    => $conditions,
			)
		);
	}

	/**
	 * @param int    $content_id   Post ID.
	 * @param string $content_type Content type slug.
	 * @return array<string,mixed>
	 */
	public static function get_assignments( $content_id, $content_type = 'page' ) {
		if ( ! class_exists( 'RWGC_Elementor_Assignment_Discovery', false ) ) {
			return array(
				'content_id'   => absint( $content_id ),
				'content_type' => sanitize_key( (string) $content_type ),
				'assignments'  => array(),
			);
		}
		return RWGC_Elementor_Assignment_Discovery::get_assignments_for_content( $content_id, $content_type );
	}

	/**
	 * @param array<string,mixed> $request rule_id + content.
	 * @return array<string,mixed>
	 */
	public static function check_compatibility( array $request ) {
		$portable = self::resolve_portable_json( $request );
		if ( is_wp_error( $portable ) ) {
			return array(
				'status'  => 'compatible',
				'reasons' => array(),
			);
		}
		$decoded = json_decode( $portable, true );
		if ( ! is_array( $decoded ) ) {
			return array(
				'status'  => 'compatible',
				'reasons' => array(),
			);
		}
		return self::compatibility_for_tester( $decoded, $request );
	}

	/**
	 * Normalize content + simulated visitor context for all tester endpoints.
	 *
	 * @param array<string,mixed> $request Tester request body.
	 * @return array{content:array<string,mixed>,context:array<string,string>,document_context:array<string,mixed>}
	 */
	public static function normalize_tester_request( array $request ) {
		$content = isset( $request['content'] ) && is_array( $request['content'] ) ? $request['content'] : array();
		$context = isset( $request['context'] ) && is_array( $request['context'] ) ? $request['context'] : array();

		if ( '' === trim( (string) ( $context['page_type'] ?? '' ) ) && ! empty( $content['page_type'] ) ) {
			$context['page_type'] = (string) $content['page_type'];
		}

		$merged_context   = self::merge_content_into_context( $content, $context );
		$document_context = self::build_document_context( $content, $merged_context );

		return array(
			'content'          => $content,
			'context'          => $merged_context,
			'document_context' => $document_context,
		);
	}

	/**
	 * @param array<string,mixed> $rule_set Portable rule set.
	 * @param array<string,mixed> $request  Full tester request (content + context).
	 * @return array<string,mixed>
	 */
	public static function compatibility_for_tester( $rule_set, array $request ) {
		if ( ! class_exists( 'RWGC_Rule_Context_Compatibility', false ) || ! is_array( $rule_set ) ) {
			return array(
				'status'        => 'compatible',
				'reasons'       => array(),
				'scope_summary' => '',
			);
		}
		$norm   = self::normalize_tester_request( $request );
		$compat = RWGC_Rule_Context_Compatibility::evaluate( $rule_set, $norm['document_context'] );
		$result = array(
			'status'           => (string) ( $compat['status'] ?? 'compatible' ),
			'reasons'          => isset( $compat['reasons'] ) && is_array( $compat['reasons'] ) ? $compat['reasons'] : array(),
			'scope_summary'    => (string) ( $compat['scope_summary'] ?? '' ),
			'required_context' => isset( $compat['required_context'] ) && is_array( $compat['required_context'] ) ? $compat['required_context'] : array(),
			'actual_context'   => isset( $compat['actual_context'] ) && is_array( $compat['actual_context'] ) ? $compat['actual_context'] : array(),
			'document_context' => $norm['document_context'],
		);
		if ( ! empty( $norm['document_context']['content_note'] ) ) {
			$result['content_note'] = (string) $norm['document_context']['content_note'];
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $rule_set Portable rule set.
	 * @param array<string,mixed> $content  Content selector.
	 * @param array<string,mixed> $context  Optional simulated visitor context.
	 * @return array<string,mixed>
	 */
	public static function compatibility_for_content( $rule_set, array $content, array $context = array() ) {
		return self::compatibility_for_tester(
			$rule_set,
			array(
				'content' => $content,
				'context' => $context,
			)
		);
	}

	/**
	 * @param array<string,mixed> $content        Content payload.
	 * @param array<string,string> $merged_context Resolved visitor context.
	 * @return array<string,mixed>
	 */
	private static function build_document_context( array $content, array $merged_context ) {
		$doc               = self::document_context_from_content( $content );
		$natural_page_type = sanitize_key( (string) ( $doc['page_type'] ?? '' ) );

		if ( '' !== trim( (string) ( $merged_context['page_type'] ?? '' ) ) ) {
			$doc['page_type']           = sanitize_key( (string) $merged_context['page_type'] );
			$doc['simulated_page_type'] = $doc['page_type'];
		}
		if ( '' !== trim( (string) ( $merged_context['request_uri'] ?? '' ) ) ) {
			$doc['request_uri'] = (string) $merged_context['request_uri'];
		}

		$doc['natural_page_type'] = $natural_page_type;
		$doc['content_label']     = self::content_label( $content );
		$doc['content_type']      = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$doc['content_note']      = self::content_simulation_note(
			$content,
			$natural_page_type,
			(string) ( $doc['page_type'] ?? '' )
		);

		return $doc;
	}

	/**
	 * @param array<string,mixed> $request  Tester request.
	 * @param int                 $rule_id  Rule post ID.
	 * @param bool                $matched  Whether the rule matched.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_applied_targets( array $request, $rule_id, $matched ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 ) {
			return array();
		}

		$content = isset( $request['content'] ) && is_array( $request['content'] ) ? $request['content'] : array();
		$type    = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$id      = isset( $content['id'] ) ? absint( $content['id'] ) : 0;
		if ( $id <= 0 || ! in_array( $type, array( 'page', 'post', 'product' ), true ) ) {
			return array();
		}

		$data        = self::get_assignments( $id, $type );
		$assignments = isset( $data['assignments'] ) && is_array( $data['assignments'] ) ? $data['assignments'] : array();
		$norm        = self::normalize_tester_request( $request );
		$context     = isset( $norm['context'] ) && is_array( $norm['context'] ) ? $norm['context'] : array();
		$rule_cache  = self::assignment_rule_match_cache( $assignments, $context );
		$visibility  = self::assignment_render_visibility_map( $assignments, $rule_cache );
		$targets     = array();

		foreach ( $assignments as $row ) {
			if ( ! is_array( $row ) || absint( $row['rule_id'] ?? 0 ) !== $rule_id ) {
				continue;
			}
			$assignment_id = (string) ( $row['assignment_id'] ?? '' );
			$mode          = (string) ( $row['mode_internal'] ?? $row['mode'] ?? 'show_if' );
			if ( class_exists( 'RWGC_Elementor_Assignment_Discovery', false ) ) {
				$mode = RWGC_Elementor_Assignment_Discovery::mode_from_api_key( $mode );
			} elseif ( function_exists( 'rwgc_normalize_visibility_mode' ) ) {
				$mode = rwgc_normalize_visibility_mode( $mode );
			}
			$element_matched = ! empty( $rule_cache[ $rule_id ] );
			$visible         = isset( $visibility[ $assignment_id ] ) && 'visible' === $visibility[ $assignment_id ];
			$reason          = self::assignment_visibility_reason( $mode, $element_matched, $visible );
			if ( $visible && self::assignment_hidden_by_ancestor( $row, $visibility, $assignments ) ) {
				$visible = false;
				$reason  = __( 'Hidden because a parent Elementor section/container is not rendered for this visitor.', 'reactwoo-geocore' );
			}

			$source = sanitize_key( (string) ( $row['source'] ?? 'elementor' ) );
			$targets[] = array(
				'assignment_id'        => $assignment_id,
				'parent_assignment_id' => (string) ( $row['parent_assignment_id'] ?? '' ),
				'target_label'         => (string) ( $row['element_label'] ?? '' ),
				'target_type'          => (string) ( $row['element_type'] ?? '' ),
				'source'               => $source,
				'source_label'         => self::assignment_source_label( $source ),
				'mode'                 => class_exists( 'RWGC_Elementor_Assignment_Discovery', false )
					? RWGC_Elementor_Assignment_Discovery::mode_api_key( $mode )
					: $mode,
				'mode_label'           => (string) ( $row['mode_label'] ?? '' ),
				'rule_matches'         => (bool) $element_matched,
				'visibility'           => $visible ? 'visible' : 'hidden',
				'reason'               => $reason,
			);
		}

		return $targets;
	}

	/**
	 * @param array<int,array<string,mixed>> $assignments Elementor assignments.
	 * @param array<string,mixed>            $context     Simulated visitor context.
	 * @return array<int,bool>
	 */
	private static function assignment_rule_match_cache( array $assignments, array $context ) {
		$cache = array();
		foreach ( $assignments as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rule_id = absint( $row['rule_id'] ?? 0 );
			if ( $rule_id <= 0 || isset( $cache[ $rule_id ] ) ) {
				continue;
			}
			$raw     = (string) get_post_meta( $rule_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) || ! class_exists( 'RWGC_Visibility_Rule_Preview', false ) ) {
				$cache[ $rule_id ] = false;
				continue;
			}
			$detailed          = RWGC_Visibility_Rule_Preview::evaluate_detailed( $decoded, $context );
			$cache[ $rule_id ] = ! empty( $detailed['matches'] );
		}
		return $cache;
	}

	/**
	 * @param array<int,array<string,mixed>> $assignments Elementor assignments.
	 * @param array<int,bool>                $rule_cache  Rule match cache.
	 * @return array<string,string> assignment_id => visible|hidden
	 */
	private static function assignment_render_visibility_map( array $assignments, array $rule_cache ) {
		$map = array();
		foreach ( $assignments as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$assignment_id = (string) ( $row['assignment_id'] ?? '' );
			$rule_id       = absint( $row['rule_id'] ?? 0 );
			if ( '' === $assignment_id || $rule_id <= 0 ) {
				continue;
			}
			$mode = (string) ( $row['mode_internal'] ?? $row['mode'] ?? 'show_if' );
			if ( class_exists( 'RWGC_Elementor_Assignment_Discovery', false ) ) {
				$mode = RWGC_Elementor_Assignment_Discovery::mode_from_api_key( $mode );
			} elseif ( function_exists( 'rwgc_normalize_visibility_mode' ) ) {
				$mode = rwgc_normalize_visibility_mode( $mode );
			}
			$matched = ! empty( $rule_cache[ $rule_id ] );
			$visible = function_exists( 'rwgc_visibility_mode_allows_render' )
				? rwgc_visibility_mode_allows_render( $mode, $matched )
				: $matched;
			$map[ $assignment_id ] = $visible ? 'visible' : 'hidden';
		}
		return $map;
	}

	/**
	 * @param array<string,mixed>            $row          Assignment row.
	 * @param array<string,string>           $visibility   Render map.
	 * @param array<int,array<string,mixed>> $assignments  All assignments (parent chain lookup).
	 * @return bool
	 */
	private static function assignment_hidden_by_ancestor( array $row, array $visibility, array $assignments ) {
		$parent_map = array();
		foreach ( $assignments as $assignment_row ) {
			if ( ! is_array( $assignment_row ) ) {
				continue;
			}
			$aid = (string) ( $assignment_row['assignment_id'] ?? '' );
			if ( '' === $aid ) {
				continue;
			}
			$parent_map[ $aid ] = (string) ( $assignment_row['parent_assignment_id'] ?? '' );
		}

		$ancestor = (string) ( $row['parent_assignment_id'] ?? '' );
		$depth    = 0;
		while ( '' !== $ancestor && $depth < 24 ) {
			if ( isset( $visibility[ $ancestor ] ) && 'hidden' === $visibility[ $ancestor ] ) {
				return true;
			}
			$ancestor = isset( $parent_map[ $ancestor ] ) ? (string) $parent_map[ $ancestor ] : '';
			++$depth;
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $request Tester request.
	 * @param int                 $rule_id Rule post ID.
	 * @return array<string,mixed>
	 */
	private static function build_preview_response( array $request, $rule_id ) {
		if ( ! class_exists( 'RWGC_Rule_Tester_Frontend_Preview', false ) ) {
			return array(
				'url'     => '',
				'expires' => 0,
			);
		}

		$rule_id    = absint( $rule_id );
		$rule_label = '';
		if ( $rule_id > 0 ) {
			$post = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
			if ( $post instanceof WP_Post ) {
				$rule_label = (string) $post->post_title;
			}
		}

		$norm = self::normalize_tester_request( $request );
		$url  = RWGC_Rule_Tester_Frontend_Preview::build_preview_url(
			array(
				'rule_id'    => $rule_id,
				'rule_label' => $rule_label,
				'content'    => $norm['content'],
				'context'    => $norm['context'],
				'assignment' => isset( $request['assignment'] ) && is_array( $request['assignment'] ) ? $request['assignment'] : array(),
			)
		);

		if ( is_wp_error( $url ) ) {
			return array(
				'url'     => '',
				'expires' => 0,
				'error'   => $url->get_error_message(),
			);
		}

		return array(
			'url'     => (string) $url,
			'expires' => time() + RWGC_Rule_Tester_Frontend_Preview::TOKEN_TTL,
		);
	}

	/**
	 * @param array<string,mixed> $content Content selector.
	 * @return string
	 */
	private static function content_label( array $content ) {
		$type = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$id   = isset( $content['id'] ) ? absint( $content['id'] ) : 0;
		if ( $id > 0 && in_array( $type, array( 'page', 'post', 'product' ), true ) ) {
			$title = get_the_title( $id );
			return $title ? (string) $title : (string) $id;
		}
		if ( 'manual' === $type && ! empty( $content['url'] ) ) {
			return (string) $content['url'];
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $content       Content selector.
	 * @param string              $natural_pt    Natural page type from content record.
	 * @param string              $simulated_pt  Simulated page type from tester form.
	 * @return string
	 */
	private static function content_simulation_note( array $content, $natural_pt, $simulated_pt ) {
		$natural_pt   = sanitize_key( (string) $natural_pt );
		$simulated_pt = sanitize_key( (string) $simulated_pt );
		if ( '' === $simulated_pt || $natural_pt === $simulated_pt ) {
			return '';
		}

		$label = self::content_label( $content );
		if ( '' === $label ) {
			$label = __( 'Selected content', 'reactwoo-geocore' );
		}

		if ( 'product' === $simulated_pt && in_array( $natural_pt, array( 'other', 'homepage', 'page' ), true ) ) {
			return sprintf(
				/* translators: %s: content title */
				__( '%1$s is a page/variant, not a product page. Product-level assignments found inside this page are evaluated separately below.', 'reactwoo-geocore' ),
				$label
			);
		}

		$natural_lbl = $natural_pt && class_exists( 'RWGC_Rule_Context_Compatibility', false )
			? RWGC_Rule_Context_Compatibility::page_type_label( $natural_pt )
			: ( $natural_pt ? $natural_pt : __( 'page/variant', 'reactwoo-geocore' ) );
		$sim_lbl       = class_exists( 'RWGC_Rule_Context_Compatibility', false )
			? RWGC_Rule_Context_Compatibility::page_type_label( $simulated_pt )
			: $simulated_pt;

		return sprintf(
			/* translators: 1: natural page type label, 2: simulated page type label */
			__( 'Selected content is %1$s; simulating %2$s page type for this test.', 'reactwoo-geocore' ),
			$natural_lbl,
			$sim_lbl
		);
	}

	/**
	 * @param array<string,mixed> $content Content payload.
	 * @return array<string,mixed>
	 */
	private static function document_context_from_content( array $content ) {
		$type = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$id   = isset( $content['id'] ) ? absint( $content['id'] ) : 0;
		if ( in_array( $type, array( 'page', 'post', 'product' ), true ) && $id > 0 ) {
			return RWGC_Rule_Context_Compatibility::document_context_from_post( $id );
		}
		return array(
			'post_id'       => 0,
			'post_type'     => '',
			'page_type'     => '',
			'request_uri'   => isset( $content['url'] ) ? (string) $content['url'] : '',
			'document_type' => 'manual',
		);
	}

	/**
	 * @param string $source Source slug (elementor, etc.).
	 * @return string
	 */
	private static function assignment_source_label( $source ) {
		$labels = array(
			'elementor' => __( 'Elementor', 'reactwoo-geocore' ),
		);
		$source = sanitize_key( (string) $source );
		if ( isset( $labels[ $source ] ) ) {
			return $labels[ $source ];
		}
		if ( '' === $source ) {
			return '';
		}
		return ucwords( str_replace( array( '_', '-' ), ' ', $source ) );
	}

	/**
	 * @param string $mode     show_if|hide_if.
	 * @param bool   $matched  Rule matched.
	 * @param bool   $visible  Element visible.
	 * @return string
	 */
	private static function assignment_visibility_reason( $mode, $matched, $visible ) {
		$mode = function_exists( 'rwgc_normalize_visibility_mode' ) ? rwgc_normalize_visibility_mode( $mode ) : $mode;
		if ( 'hide_if' === $mode ) {
			if ( $matched ) {
				return __( 'The rule matched, so this hide-on-match target is hidden.', 'reactwoo-geocore' );
			}
			return __( 'The rule did not match, so this hide-on-match target remains visible.', 'reactwoo-geocore' );
		}
		if ( $visible ) {
			return __( 'The rule matched, so this show-on-match target is visible.', 'reactwoo-geocore' );
		}
		return __( 'The rule did not match, so this show-on-match target is hidden.', 'reactwoo-geocore' );
	}

	/**
	 * @param bool                           $matched          Rule matched simulated visitor.
	 * @param array<int,array<string,mixed>> $applied_targets  Direct assignments.
	 * @param array<int,array<string,mixed>> $rendered_impacts Rendered product impacts.
	 * @param array<string,mixed>            $norm             Normalized tester payload.
	 * @param array<string,mixed>            $detailed         Detailed evaluator output.
	 * @return array<string,mixed>
	 */
	private static function build_result_summary( $matched, array $applied_targets, array $rendered_impacts, array $norm, array $detailed ) {
		$visible_outcomes = 0;
		$hidden_outcomes  = 0;

		foreach ( $applied_targets as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'visible' === ( $row['visibility'] ?? '' ) ) {
				++$visible_outcomes;
			} elseif ( 'hidden' === ( $row['visibility'] ?? '' ) ) {
				++$hidden_outcomes;
			}
		}
		foreach ( $rendered_impacts as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'visible' === ( $row['outcome'] ?? '' ) ) {
				++$visible_outcomes;
			} elseif ( 'hidden' === ( $row['outcome'] ?? '' ) ) {
				++$hidden_outcomes;
			}
		}

		$why_no_match = array();
		if ( ! $matched ) {
			$doc = isset( $norm['document_context'] ) && is_array( $norm['document_context'] ) ? $norm['document_context'] : array();
			if ( ! empty( $doc['content_note'] ) ) {
				$why_no_match[] = (string) $doc['content_note'];
			}
			foreach ( (array) ( $detailed['condition_results'] ?? array() ) as $cond ) {
				if ( ! is_array( $cond ) || 'pass' === ( $cond['status'] ?? '' ) ) {
					continue;
				}
				$line = trim( (string) ( $cond['detail'] ?? '' ) );
				if ( '' === $line ) {
					$line = trim( (string) ( $cond['label'] ?? '' ) );
				}
				if ( '' !== $line ) {
					$why_no_match[] = $line;
				}
			}
			if ( empty( $why_no_match ) && ! empty( $detailed['summary_lines'] ) ) {
				foreach ( (array) $detailed['summary_lines'] as $line ) {
					$line = trim( (string) $line );
					if ( '' !== $line && false === stripos( $line, 'matched' ) ) {
						$why_no_match[] = $line;
					}
				}
			}
		}

		return array(
			'page_match'               => (bool) $matched,
			'page_match_label'         => $matched ? 'YES' : 'NO',
			'applied_targets_count'    => count( $applied_targets ),
			'rendered_impacts_count'   => count( $rendered_impacts ),
			'visible_outcomes'         => $visible_outcomes,
			'hidden_outcomes'          => $hidden_outcomes,
			'why_page_no_match'        => array_values( array_unique( array_filter( $why_no_match ) ) ),
		);
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function page_type_for_post_public( WP_Post $post ) {
		return self::page_type_for_post( $post );
	}

	/**
	 * @param array<string,mixed> $request Request.
	 * @return string|\WP_Error
	 */
	private static function resolve_portable_json( array $request ) {
		if ( ! empty( $request['portable_json'] ) && is_string( $request['portable_json'] ) && '' !== trim( $request['portable_json'] ) ) {
			return (string) $request['portable_json'];
		}
		$rule_id = isset( $request['rule_id'] ) ? absint( $request['rule_id'] ) : 0;
		if ( $rule_id <= 0 ) {
			return new WP_Error( 'rwgc_rule_required', __( 'Select a visibility rule to test.', 'reactwoo-geocore' ) );
		}
		$post = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
		if ( ! $post ) {
			return new WP_Error( 'rwgc_rule_not_found', __( 'Rule not found.', 'reactwoo-geocore' ) );
		}
		return (string) get_post_meta( $rule_id, RWGC_Visibility_Rule_CPT::META_PORTABLE, true );
	}

	/**
	 * @param string $post_type page|post|product.
	 * @return array<int,array<string,mixed>>
	 */
	private static function content_options( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );
		$posts     = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( (array) $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$out[] = array(
				'id'        => (int) $post->ID,
				'title'     => (string) $post->post_title,
				'type'      => $post_type,
				'page_type' => self::page_type_for_post( $post ),
				'url'       => self::path_from_post( $post ),
			);
		}
		return $out;
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function path_from_post( WP_Post $post ) {
		$link = get_permalink( $post );
		if ( ! is_string( $link ) || '' === $link ) {
			return '';
		}
		$path = wp_parse_url( $link, PHP_URL_PATH );
		return is_string( $path ) ? $path : '';
	}

	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	private static function page_type_for_post( WP_Post $post ) {
		if ( 'product' === $post->post_type ) {
			return 'product';
		}
		if ( 'page' === $post->post_type ) {
			if ( function_exists( 'wc_get_page_id' ) ) {
				foreach ( array( 'shop', 'cart', 'checkout' ) as $woocommerce_page_type ) {
					if ( (int) wc_get_page_id( $woocommerce_page_type ) === (int) $post->ID ) {
						return $woocommerce_page_type;
					}
				}
			}
			if ( (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
				return 'homepage';
			}
			return 'other';
		}
		if ( 'post' === $post->post_type ) {
			return 'other';
		}
		return 'other';
	}

	/**
	 * @param array<string,mixed> $content Content selector payload.
	 * @param array<string,mixed> $context Visitor context.
	 * @return array<string,string>
	 */
	private static function merge_content_into_context( array $content, array $context ) {
		$type = sanitize_key( (string) ( $content['type'] ?? '' ) );
		$id   = isset( $content['id'] ) ? absint( $content['id'] ) : 0;
		$url  = isset( $content['url'] ) ? (string) $content['url'] : '';

		if ( '' === trim( (string) ( $context['page_type'] ?? '' ) ) && ! empty( $content['page_type'] ) ) {
			$context['page_type'] = (string) $content['page_type'];
		}

		if ( in_array( $type, array( 'page', 'post', 'product' ), true ) && $id > 0 ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post ) {
				if ( '' === trim( (string) ( $context['page_type'] ?? '' ) ) ) {
					$context['page_type'] = self::page_type_for_post( $post );
				}
				if ( '' === trim( (string) ( $context['request_uri'] ?? '' ) ) ) {
					$context['request_uri'] = self::path_from_post( $post );
				}
			}
		} elseif ( 'manual' === $type && '' !== trim( $url ) ) {
			if ( '' === trim( (string) ( $context['request_uri'] ?? '' ) ) ) {
				$context['request_uri'] = $url;
			}
		}

		return array(
			'country'     => isset( $context['country'] ) ? (string) $context['country'] : '',
			'device'      => isset( $context['device'] ) ? (string) $context['device'] : '',
			'page_type'   => isset( $context['page_type'] ) ? (string) $context['page_type'] : '',
			'request_uri' => isset( $context['request_uri'] ) ? (string) $context['request_uri'] : '',
			'utm_source'  => isset( $context['utm_source'] ) ? (string) $context['utm_source'] : '',
			'utm_medium'  => isset( $context['utm_medium'] ) ? (string) $context['utm_medium'] : '',
			'gclid'       => ! empty( $context['gclid'] ) ? '1' : '',
		);
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @param array<string,string>     $context Context.
	 * @return array<int,string>
	 */
	private static function missing_context_fields( $set, array $context ) {
		unset( $set );
		$missing = array();
		if ( '' === trim( (string) ( $context['country'] ?? '' ) ) ) {
			$missing[] = 'country';
		}
		if ( '' === trim( (string) ( $context['device'] ?? '' ) ) ) {
			$missing[] = 'device';
		}
		if ( '' === trim( (string) ( $context['page_type'] ?? '' ) ) ) {
			$missing[] = 'page_type';
		}
		return $missing;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<string,string>
	 */
	public static function default_context_from_rule( $set ) {
		$defaults = array(
			'country'     => '',
			'device'      => '',
			'page_type'   => '',
			'request_uri' => '',
			'utm_source'  => '',
			'utm_medium'  => '',
		);
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $defaults;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$type = (string) ( $cond['type'] ?? '' );
			$op   = (string) ( $cond['operator'] ?? '' );
			$val  = $cond['value'] ?? array();
			$list = is_array( $val ) ? $val : array( $val );

			if ( 'country' === $type && in_array( $op, array( 'in', 'is' ), true ) && empty( $defaults['country'] ) ) {
				$defaults['country'] = strtoupper( substr( sanitize_text_field( (string) ( $list[0] ?? '' ) ), 0, 2 ) );
			}
			if ( in_array( $type, array( 'device', 'device_type' ), true ) && in_array( $op, array( 'in', 'is' ), true ) && empty( $defaults['device'] ) ) {
				$defaults['device'] = sanitize_key( (string) ( $list[0] ?? '' ) );
			}
			if ( 'page_type' === $type && in_array( $op, array( 'in', 'is' ), true ) && empty( $defaults['page_type'] ) ) {
				$defaults['page_type'] = sanitize_key( (string) ( $list[0] ?? '' ) );
			}
		}
		if ( self::rule_has_google_ads_branch( $set ) ) {
			$defaults['utm_source'] = 'google';
			$defaults['utm_medium'] = 'cpc';
		}
		return $defaults;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<int,array<string,mixed>>
	 */
	public static function presets_for_rule( $set ) {
		$presets = array();
		if ( ! is_array( $set ) ) {
			return $presets;
		}
		$defaults = self::default_context_from_rule( $set );
		$has_ads  = self::rule_has_google_ads_branch( $set );
		$has_sale = self::rule_has_winter_sale_branch( $set );
		$excluded = self::rule_excluded_countries( $set );

		if ( $has_ads ) {
			$presets[] = array(
				'id'      => 'google_ads_match',
				'label'   => __( 'Matching Google Ads visitor', 'reactwoo-geocore' ),
				'context' => array_merge(
					$defaults,
					array(
						'country'     => $defaults['country'] ?: 'IE',
						'device'      => $defaults['device'] ?: 'desktop',
						'page_type'   => $defaults['page_type'] ?: 'product',
						'utm_source'  => 'google',
						'utm_medium'  => 'cpc',
						'request_uri' => '',
					)
				),
			);
		}
		if ( $has_sale ) {
			$presets[] = array(
				'id'      => 'winter_sale_url',
				'label'   => __( 'Matching winter sale URL', 'reactwoo-geocore' ),
				'context' => array_merge(
					$defaults,
					array(
						'country'     => in_array( 'GB', self::rule_included_countries( $set ), true ) ? 'GB' : ( $defaults['country'] ?: 'GB' ),
						'device'      => $defaults['device'] ?: 'desktop',
						'page_type'   => $defaults['page_type'] ?: 'product',
						'utm_source'  => '',
						'utm_medium'  => '',
						'request_uri' => '/winter-sale',
					)
				),
			);
		}
		if ( ! empty( $excluded ) ) {
			$presets[] = array(
				'id'      => 'excluded_country',
				'label'   => __( 'Excluded country', 'reactwoo-geocore' ),
				'context' => array_merge(
					$defaults,
					array(
						'country'     => strtoupper( (string) $excluded[0] ),
						'device'      => $defaults['device'] ?: 'desktop',
						'page_type'   => $defaults['page_type'] ?: 'product',
						'utm_source'  => '',
						'utm_medium'  => '',
						'request_uri' => '/winter-sale',
					)
				),
			);
		}
		return $presets;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return bool
	 */
	private static function rule_has_google_ads_branch( $set ) {
		return self::rule_branch_matches( $set, static function ( array $branch ) {
			$conds = (array) ( $branch['conditions'] ?? array() );
			return class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false )
				&& RWGC_Visibility_Rule_Logic_Preview::is_google_ads_branch( $conds );
		} );
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return bool
	 */
	private static function rule_has_winter_sale_branch( $set ) {
		return self::rule_branch_matches(
			$set,
			static function ( array $branch ) {
				foreach ( (array) ( $branch['conditions'] ?? array() ) as $cond ) {
					if ( ! is_array( $cond ) || 'request_uri' !== (string) ( $cond['type'] ?? '' ) ) {
						continue;
					}
					$val = is_array( $cond['value'] ?? null ) ? implode( '', $cond['value'] ) : (string) ( $cond['value'] ?? '' );
					if ( false !== strpos( $val, 'winter-sale' ) ) {
						return true;
					}
				}
				return false;
			}
		);
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @param callable               $match Branch matcher.
	 * @return bool
	 */
	private static function rule_branch_matches( $set, callable $match ) {
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return false;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) || 'condition_group' !== (string) ( $cond['type'] ?? '' ) ) {
				continue;
			}
			$val = $cond['value'] ?? array();
			if ( ! is_array( $val ) ) {
				continue;
			}
			foreach ( (array) ( $val['branches'] ?? array() ) as $branch ) {
				if ( is_array( $branch ) && $match( $branch ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<int,string>
	 */
	private static function rule_included_countries( $set ) {
		$codes = array();
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $codes;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) || 'country' !== (string) ( $cond['type'] ?? '' ) ) {
				continue;
			}
			if ( ! in_array( (string) ( $cond['operator'] ?? '' ), array( 'in', 'is' ), true ) ) {
				continue;
			}
			foreach ( (array) ( $cond['value'] ?? array() ) as $code ) {
				$code = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
				if ( '' !== $code ) {
					$codes[] = $code;
				}
			}
		}
		return array_values( array_unique( $codes ) );
	}

	/**
	 * @param array<string,mixed>|null $set Rule set.
	 * @return array<int,string>
	 */
	private static function rule_excluded_countries( $set ) {
		$codes = array();
		if ( ! is_array( $set ) || empty( $set['rules'][0]['conditions'] ) ) {
			return $codes;
		}
		foreach ( (array) $set['rules'][0]['conditions'] as $cond ) {
			if ( ! is_array( $cond ) || 'country' !== (string) ( $cond['type'] ?? '' ) ) {
				continue;
			}
			if ( ! in_array( (string) ( $cond['operator'] ?? '' ), array( 'not_in', 'is_not' ), true ) ) {
				continue;
			}
			foreach ( (array) ( $cond['value'] ?? array() ) as $code ) {
				$code = strtoupper( substr( sanitize_text_field( (string) $code ), 0, 2 ) );
				if ( '' !== $code ) {
					$codes[] = $code;
				}
			}
		}
		return array_values( array_unique( $codes ) );
	}
}
