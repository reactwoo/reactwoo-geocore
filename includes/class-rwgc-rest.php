<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API endpoints for Geo Core.
 */
class RWGC_REST {

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! RWGC_Settings::get( 'rest_enabled', 1 ) ) {
			return;
		}

		register_rest_route(
			'reactwoo-geocore/v1',
			'/location',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_location' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/capabilities',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_capabilities' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/ai/variant-draft',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_ai_variant_draft' ),
				'permission_callback' => array( __CLASS__, 'permissions_ai_draft' ),
				'args'                => array(
					'page_id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'instructions' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'country_iso2' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targeting/interpret',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_targeting_interpret' ),
				'permission_callback' => array( __CLASS__, 'permissions_targeting_assistant' ),
				'args'                => array(
					'phrase'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'context' => array(
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targets/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_targets_search' ),
				'permission_callback' => array( __CLASS__, 'permissions_targeting_assistant' ),
				'args'                => array(
					'target_type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'q'           => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targets/create',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_targets_create' ),
				'permission_callback' => array( __CLASS__, 'permissions_targeting_assistant' ),
				'args'                => array(
					'target_type' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'title'       => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'      => array(
						'type'              => 'string',
						'default'           => 'draft',
						'sanitize_callback' => 'sanitize_key',
					),
					'proposal_id' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'action_id'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'attach_to_action' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'force_create' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targeting/preview-rule',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_targeting_preview_rule' ),
				'permission_callback' => array( __CLASS__, 'permissions_visibility_rule_editor' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targeting/rule-tester/rule/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_rule_tester_rule' ),
				'permission_callback' => array( __CLASS__, 'permissions_visibility_rule_editor' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targeting/rule-tester/assignments',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_rule_tester_assignments' ),
				'permission_callback' => array( __CLASS__, 'permissions_visibility_rule_editor' ),
				'args'                => array(
					'content_id'   => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'content_type' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => 'page',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targeting/preview-assignment',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_targeting_preview_assignment' ),
				'permission_callback' => array( __CLASS__, 'permissions_visibility_rule_editor' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targeting/rule-compatibility-check',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_rule_compatibility_check' ),
				'permission_callback' => array( __CLASS__, 'permissions_visibility_rule_editor' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			'reactwoo-geocore/v1',
			'/targeting/rule-tester/preview-url',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_rule_tester_preview_url' ),
				'permission_callback' => array( __CLASS__, 'permissions_visibility_rule_editor' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * Visibility rule editor: manage Geo settings.
	 *
	 * @return bool
	 */
	public static function permissions_visibility_rule_editor() {
		return class_exists( 'RWGC_Admin', false ) && RWGC_Admin::can_manage();
	}

	/**
	 * POST portable JSON + scenario → match result and logic preview lines.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function post_targeting_preview_rule( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		// Modal tester payload (rule_id + content + context).
		if ( isset( $params['rule_id'] ) || isset( $params['context'] ) || isset( $params['content'] ) ) {
			$result = class_exists( 'RWGC_Visibility_Rule_Tester', false )
				? RWGC_Visibility_Rule_Tester::run( $params )
				: array(
					'status'  => 'error',
					'matches' => false,
					'error'   => '',
				);
			return new \WP_REST_Response( $result, 200 );
		}

		// Legacy editor logic preview refresh.
		$json     = isset( $params['portable_json'] ) ? (string) $params['portable_json'] : (string) $request->get_param( 'portable_json' );
		$target   = isset( $params['target_label'] ) ? (string) $params['target_label'] : (string) $request->get_param( 'target_label' );
		$scenario = isset( $params['scenario'] ) ? $params['scenario'] : $request->get_param( 'scenario' );
		if ( ! is_array( $scenario ) ) {
			$scenario = array();
		}
		$scenario = array(
			'country'     => isset( $scenario['country'] ) ? (string) $scenario['country'] : '',
			'device'      => isset( $scenario['device'] ) ? (string) $scenario['device'] : '',
			'page_type'   => isset( $scenario['page_type'] ) ? (string) $scenario['page_type'] : '',
			'request_uri' => isset( $scenario['request_uri'] ) ? (string) $scenario['request_uri'] : '',
			'utm_source'  => isset( $scenario['utm_source'] ) ? (string) $scenario['utm_source'] : '',
			'utm_medium'  => isset( $scenario['utm_medium'] ) ? (string) $scenario['utm_medium'] : '',
		);

		$eval = class_exists( 'RWGC_Visibility_Rule_Preview', false )
			? RWGC_Visibility_Rule_Preview::evaluate( $json, $scenario )
			: array( 'matches' => false, 'error' => '' );

		$decoded = json_decode( $json, true );
		$logic   = class_exists( 'RWGC_Visibility_Rule_Logic_Preview', false )
			? RWGC_Visibility_Rule_Logic_Preview::build( is_array( $decoded ) ? $decoded : null, $target )
			: array( 'intro' => '', 'lines' => array() );

		return new \WP_REST_Response(
			array(
				'matches'       => ! empty( $eval['matches'] ),
				'error'         => (string) ( $eval['error'] ?? '' ),
				'logic_preview' => $logic,
			),
			200
		);
	}

	/**
	 * GET rule payload for the tester modal.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_rule_tester_rule( $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( ! class_exists( 'RWGC_Visibility_Rule_Tester', false ) ) {
			return new \WP_Error( 'rwgc_tester_unavailable', __( 'Rule tester is unavailable.', 'reactwoo-geocore' ), array( 'status' => 500 ) );
		}
		$payload = RWGC_Visibility_Rule_Tester::get_rule_payload( $id );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		return new \WP_REST_Response( $payload, 200 );
	}

	/**
	 * GET assignments for rule tester applied-target mode.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_rule_tester_assignments( $request ) {
		$content_id   = absint( $request->get_param( 'content_id' ) );
		$content_type = sanitize_key( (string) $request->get_param( 'content_type' ) );
		if ( '' === $content_type ) {
			$content_type = 'page';
		}
		$result = class_exists( 'RWGC_Visibility_Rule_Tester', false )
			? RWGC_Visibility_Rule_Tester::get_assignments( $content_id, $content_type )
			: array(
				'content_id'   => $content_id,
				'content_type' => $content_type,
				'assignments'  => array(),
			);
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST assignment + context → element visibility outcome.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function post_targeting_preview_assignment( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$result = class_exists( 'RWGC_Visibility_Rule_Tester', false )
			? RWGC_Visibility_Rule_Tester::run_assignment_preview( $params )
			: array(
				'status'  => 'error',
				'matches' => false,
				'error'   => '',
			);
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST rule + content → compatibility metadata for tester warnings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function post_rule_compatibility_check( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$result = class_exists( 'RWGC_Visibility_Rule_Tester', false )
			? RWGC_Visibility_Rule_Tester::check_compatibility( $params )
			: array(
				'status'  => 'compatible',
				'reasons' => array(),
			);
		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * POST tester payload → signed frontend preview URL.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function post_rule_tester_preview_url( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		if ( ! class_exists( 'RWGC_Visibility_Rule_Tester', false ) || ! class_exists( 'RWGC_Rule_Tester_Frontend_Preview', false ) ) {
			return new \WP_Error( 'rwgc_preview_unavailable', __( 'Preview is unavailable.', 'reactwoo-geocore' ), array( 'status' => 500 ) );
		}

		$rule_id    = isset( $params['rule_id'] ) ? absint( $params['rule_id'] ) : 0;
		$rule_label = '';
		if ( $rule_id > 0 ) {
			$post = RWGC_Visibility_Rule_Repository::get_post( $rule_id );
			if ( $post instanceof WP_Post ) {
				$rule_label = (string) $post->post_title;
			}
		}

		$norm = RWGC_Visibility_Rule_Tester::normalize_tester_request( $params );
		$url  = RWGC_Rule_Tester_Frontend_Preview::build_preview_url(
			array(
				'rule_id'    => $rule_id,
				'rule_label' => $rule_label,
				'content'    => $norm['content'],
				'context'    => $norm['context'],
				'assignment' => isset( $params['assignment'] ) && is_array( $params['assignment'] ) ? $params['assignment'] : array(),
			)
		);

		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return new \WP_REST_Response(
			array(
				'url'     => (string) $url,
				'expires' => time() + RWGC_Rule_Tester_Frontend_Preview::TOKEN_TTL,
			),
			200
		);
	}

	/**
	 * Targeting assistant: editors who can manage pages.
	 *
	 * @return bool
	 */
	public static function permissions_targeting_assistant() {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * POST natural-language phrase → structured targeting proposal (Geo AI interpreter).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function post_targeting_interpret( $request ) {
		if ( ! class_exists( 'RWGA_Local_Intent_Interpreter', false ) ) {
			return new WP_Error(
				'rwgc_geo_ai_required',
				__( 'Install and activate ReactWoo Geo AI to use natural-language targeting commands.', 'reactwoo-geocore' ),
				array( 'status' => 503 )
			);
		}

		$phrase  = trim( (string) $request->get_param( 'phrase' ) );
		$context = $request->get_param( 'context' );
		if ( ! is_array( $context ) ) {
			$context = array();
		}

		if ( class_exists( 'RWGA_Context_Resolver', false ) ) {
			$context = RWGA_Context_Resolver::resolve( $context );
		}

		if ( function_exists( 'rwgc_get_portable_targeting_editor_context' ) ) {
			$editor_ctx = rwgc_get_portable_targeting_editor_context();
			if ( is_array( $editor_ctx ) ) {
				$context['pro'] = ! empty( $editor_ctx['pro'] );
			}
		}

		$result = RWGA_Local_Intent_Interpreter::interpret( $phrase, $context );

		if ( ! empty( $context['country_override'] ) && is_string( $context['country_override'] ) ) {
			if ( empty( $result['params'] ) || ! is_array( $result['params'] ) ) {
				$result['params'] = array();
			}
			if ( empty( $result['params']['countries'] ) ) {
				$result['params']['countries'] = array( strtoupper( $context['country_override'] ) );
			}
		}
		if ( ! empty( $context['device_override'] ) && is_string( $context['device_override'] ) ) {
			if ( empty( $result['params'] ) || ! is_array( $result['params'] ) ) {
				$result['params'] = array();
			}
			if ( empty( $result['params']['device'] ) ) {
				$result['params']['device'] = sanitize_key( $context['device_override'] );
			}
		}
		/**
		 * Filter targeting assistant interpretation before returning to the admin UI.
		 *
		 * @param array<string,mixed> $result  Interpretation payload.
		 * @param string              $phrase  Raw user phrase.
		 * @param array<string,mixed> $context Resolved admin context.
		 */
		$result = apply_filters( 'rwgc_targeting_interpret_result', $result, $phrase, $context );

		if ( function_exists( 'rwgc_get_portable_targeting_editor_context' ) ) {
			$result['editor_context'] = rwgc_get_portable_targeting_editor_context();
		}

		return rest_ensure_response( $result );
	}

	/**
	 * GET popup (and future) target search for the assistant resolver.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_targets_search( $request ) {
		$type = (string) $request->get_param( 'target_type' );
		if ( 'popup' !== $type ) {
			return new WP_Error(
				'rwgc_unsupported_target_type',
				__( 'This target type is not supported yet.', 'reactwoo-geocore' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'RWGC_Assistant_Target_Service', false ) ) {
			return new WP_Error( 'rwgc_service_missing', __( 'Target service unavailable.', 'reactwoo-geocore' ), array( 'status' => 500 ) );
		}

		$result = RWGC_Assistant_Target_Service::search_popups( (string) $request->get_param( 'q' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Structured failure payload for POST /targets/create.
	 *
	 * @param string               $code    Machine-readable code.
	 * @param string               $message Human-readable reason.
	 * @param int                  $status  HTTP status.
	 * @param array<string, mixed> $details Extra context.
	 * @return \WP_REST_Response
	 */
	private static function target_create_failure( $code, $message, $status = 400, $details = array() ) {
		return new \WP_REST_Response(
			array(
				'success' => false,
				'code'    => (string) $code,
				'message' => (string) $message,
				'details' => is_array( $details ) ? $details : array(),
			),
			(int) $status
		);
	}

	/**
	 * Map a WP_Error from the target service into structured JSON.
	 *
	 * @param \WP_Error $error Error object.
	 * @return \WP_REST_Response
	 */
	private static function target_create_failure_from_error( $error ) {
		$map = array(
			'rwgc_popup_unavailable'        => 'unsupported_target_type',
			'rwgc_forbidden'                => 'capability_failed',
			'rwgc_invalid_title'            => 'create_failed',
			'rwgc_popup_create_failed'      => 'create_failed',
			'rwgc_invalid_attach_context'   => 'attach_failed',
			'rwgc_unsupported_target_type'  => 'unsupported_target_type',
			'rwgc_service_missing'          => 'create_failed',
		);

		$status  = 500;
		$details = array();
		$data    = $error->get_error_data();
		if ( is_array( $data ) ) {
			if ( isset( $data['status'] ) ) {
				$status = (int) $data['status'];
			}
			$details = $data;
		}

		$code = isset( $map[ $error->get_error_code() ] ) ? $map[ $error->get_error_code() ] : 'create_failed';

		return self::target_create_failure( $code, $error->get_error_message(), $status, $details );
	}

	/**
	 * POST create a target (Elementor popup draft) from the assistant.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function post_targets_create( $request ) {
		$type = (string) $request->get_param( 'target_type' );
		if ( 'popup' !== $type ) {
			return self::target_create_failure(
				'unsupported_target_type',
				__( 'This target type is not supported yet.', 'reactwoo-geocore' ),
				400
			);
		}

		if ( ! class_exists( 'RWGC_Assistant_Target_Service', false ) ) {
			return self::target_create_failure(
				'create_failed',
				__( 'Target service unavailable.', 'reactwoo-geocore' ),
				500
			);
		}

		$attach = (bool) $request->get_param( 'attach_to_action' );
		if ( $attach ) {
			$proposal_id = trim( (string) $request->get_param( 'proposal_id' ) );
			$action_id   = trim( (string) $request->get_param( 'action_id' ) );
			if ( '' === $proposal_id || '' === $action_id ) {
				return self::target_create_failure(
					'attach_failed',
					__( 'Proposal and action are required when attaching a new popup to an action.', 'reactwoo-geocore' ),
					400,
					array(
						'proposal_id' => $proposal_id,
						'action_id'   => $action_id,
					)
				);
			}
		}

		$result = RWGC_Assistant_Target_Service::create_popup(
			(string) $request->get_param( 'title' ),
			(string) $request->get_param( 'status' ),
			array(
				'force_create' => (bool) $request->get_param( 'force_create' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::target_create_failure_from_error( $result );
		}

		if ( empty( $result['success'] ) ) {
			$code    = isset( $result['code'] ) ? (string) $result['code'] : 'create_failed';
			$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'Could not create the popup.', 'reactwoo-geocore' );
			$details = isset( $result['details'] ) && is_array( $result['details'] ) ? $result['details'] : array();
			if ( empty( $details['matches'] ) && ! empty( $result['matches'] ) && is_array( $result['matches'] ) ) {
				$details['matches'] = $result['matches'];
			}
			if ( 'possible_duplicate' === ( $result['reason'] ?? '' ) && 'duplicate_found' !== $code ) {
				$code = 'duplicate_found';
			}

			$body = array(
				'success' => false,
				'code'    => $code,
				'message' => $message,
				'details' => $details,
			);
			if ( ! empty( $result['matches'] ) ) {
				$body['matches'] = $result['matches'];
				$body['reason']  = 'possible_duplicate';
			}

			return new \WP_REST_Response( $body, 'duplicate_found' === $code ? 200 : 400 );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Permissions check for location endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function permissions_check( $request ) {
		// This endpoint is intentionally public; site owners can disable it in settings.
		return true;
	}

	/**
	 * AI draft endpoints require an editor who can change pages (no automatic publish).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function permissions_ai_draft( $request ) {
		return current_user_can( 'edit_pages' );
	}

	/**
	 * Get visitor location payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_location( $request ) {
		$data = RWGC_API::get_visitor_data();
		return rest_ensure_response( $data );
	}

	/**
	 * Non-sensitive discovery payload for satellite plugins (Geo Optimise, etc.).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_capabilities( $request ) {
		$payload = array(
			'plugin_slug'    => defined( 'RWGC_PLUGIN_SLUG' ) ? RWGC_PLUGIN_SLUG : 'reactwoo-geocore',
			'text_domain'    => defined( 'RWGC_TEXT_DOMAIN' ) ? RWGC_TEXT_DOMAIN : 'reactwoo-geocore',
			'plugin_version' => defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '',
			'geo_ready'      => function_exists( 'rwgc_is_ready' ) ? rwgc_is_ready() : false,
			'woocommerce_active' => function_exists( 'rwgc_is_woocommerce_active' ) ? rwgc_is_woocommerce_active() : false,
			'event_types'    => class_exists( 'RWGC_Event', false ) ? RWGC_Event::known_event_types() : array(),
			'hooks'          => array(
				'geo_event_action' => 'rwgc_geo_event',
				'geo_event_filter' => 'rwgc_geo_event',
				'route_resolved'   => 'rwgc_route_variant_resolved',
			),
			// ReactWoo Geo AI / Optimise / Commerce: ready only after each plugin’s load hook fired (deps satisfied).
			'satellites'     => self::get_satellites_status(),
			// Primary extension points for satellite plugins (not exhaustive); see get_capabilities_integration_contract().
			'integration'    => self::get_capabilities_integration_contract(),
		);
		/**
		 * Filter REST capabilities discovery payload (`GET /capabilities`).
		 *
		 * @param array<string, mixed> $payload Discovery data. Includes `satellites` (per-plugin ready + version).
		 */
		return rest_ensure_response( apply_filters( 'rwgc_rest_capabilities', $payload ) );
	}

	/**
	 * Whether each official satellite finished boot (load hooks), plus semver when known.
	 *
	 * @return array<string, array{ready: bool, version: string}>
	 */
	private static function get_satellites_status() {
		$ai_ready = did_action( 'rwga_loaded' );
		$opt_ready = did_action( 'rwgo_loaded' );
		$woo_ready = did_action( 'rwgcm_loaded' );
		return array(
			'geo_ai' => array(
				'ready'   => $ai_ready > 0,
				'version' => ( $ai_ready && defined( 'RWGA_VERSION' ) ) ? (string) RWGA_VERSION : '',
			),
			'geo_optimise' => array(
				'ready'   => $opt_ready > 0,
				'version' => ( $opt_ready && defined( 'RWGO_VERSION' ) ) ? (string) RWGO_VERSION : '',
			),
			'geo_commerce' => array(
				'ready'   => $woo_ready > 0,
				'version' => ( $woo_ready && defined( 'RWGCM_VERSION' ) ) ? (string) RWGCM_VERSION : '',
			),
		);
	}

	/**
	 * Curated filter/action names for integrations (documentation contract; extend via rwgc_rest_capabilities).
	 *
	 * @return array<string, mixed> Keys: filters, actions, ai_filters (string lists).
	 */
	private static function get_capabilities_integration_contract() {
		return array(
			'filters' => array(
				'rwgc_geo_data',
				'rwgc_page_route_bundle',
				'rwgc_route_variant_decision',
				'rwgc_geo_event',
				'rwgc_geo_event_known_types',
				'rwgc_emit_route_redirect_event',
				'rwgc_rest_capabilities',
				'rwgc_rest_v1_url',
			),
			'actions' => array(
				'rwgc_loaded',
				'rwgc_geo_resolved',
				'rwgc_geo_event',
				'rwgc_route_variant_resolved',
			),
			'ai_filters' => array(
				'rwgc_ai_variant_draft_payload',
				'rwgc_ai_variant_draft_response',
			),
			'satellite_actions' => array(
				'rwga_loaded',
				'rwgo_loaded',
				'rwgcm_loaded',
				'rwgo_geo_event',
				'rwgo_route_variant_resolved',
				'rwgcm_before_cart_totals',
				'rwgo_variant_assigned',
				'rwgcm_order_attributed',
			),
			'satellite_filters' => array(
				'rwgcm_geo_data',
				'rwgo_stats_snapshot',
				'rwgcm_adjusted_unit_price',
				'rwgcm_apply_catalog_price',
				'rwga_stats_snapshot',
				'rwga_usage_display_rows',
				'rwgcm_order_visitor_geo',
				'rwgcm_checkout_order_meta',
				'rwgcm_cart_fees',
				'rwgcm_fee_rule_rows',
				'rwgcm_skip_pricing_for_cart_item',
				'rwgcm_package_rates',
				'rwgcm_coupon_allowed_for_visitor',
				'rwgcm_coupon_valid_when_country_unknown',
				'rwgcm_store_utm_on_orders',
				'rwgcm_attribution_query_keys',
				'rwgo_emit_assignment_geo_event',
				'rwgo_export_csv_filename',
			),
		);
	}

	/**
	 * POST draft geo variant suggestion (returns data only; does not save).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function post_ai_variant_draft( $request ) {
		$page_id = (int) $request->get_param( 'page_id' );
		$instructions = (string) $request->get_param( 'instructions' );
		$country      = (string) $request->get_param( 'country_iso2' );
		$context      = array();
		if ( '' !== $country ) {
			$context['country_iso2'] = $country;
		} elseif ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$context['country_iso2'] = rwgc_get_visitor_country();
		}

		$result = RWGC_AI_Orchestrator::request_variant_draft( $page_id, $context, $instructions );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}

