<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-side AI orchestration: api.reactwoo.com only, draft outputs, no publish.
 */
class RWGC_AI_Orchestrator {

	/**
	 * POST /ai/health (unauthenticated).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function ai_health() {
		$url  = RWGC_Platform_Client::get_api_base() . '/ai/health';
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Requested-With' => 'XMLHttpRequest',
				),
				'body'    => '{}',
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		return array(
			'code' => $code,
			'data' => is_array( $data ) ? $data : null,
		);
	}

	/**
	 * GET /api/v5/ai/assistant/usage (authenticated).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_usage() {
		$result = RWGC_Platform_Client::request( 'GET', '/api/v5/ai/assistant/usage', null, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$parsed = self::parse_json_response( $result );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		return array(
			'success' => true,
			'http_code' => $parsed['code'],
			'body'    => $parsed['json'],
		);
	}

	/**
	 * Request a draft geo variant from the API (does not save or publish).
	 *
	 * @param int                  $page_id     Source page ID.
	 * @param array<string, mixed> $context     e.g. RWGC_Context or country_iso2.
	 * @param string               $instructions Optional extra instructions.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function request_variant_draft( $page_id, $context = array(), $instructions = '' ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 ) {
			return new WP_Error( 'rwgc_bad_page', __( 'A valid page ID is required.', 'reactwoo-geocore' ) );
		}

		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return new WP_Error( 'rwgc_not_page', __( 'Geo AI drafts apply to pages only.', 'reactwoo-geocore' ) );
		}

		$ctx = array();
		if ( isset( $context['country_iso2'] ) && is_string( $context['country_iso2'] ) ) {
			$ctx['country_iso2'] = strtoupper( substr( sanitize_text_field( $context['country_iso2'] ), 0, 2 ) );
		}
		if ( isset( $context['language'] ) && is_string( $context['language'] ) ) {
			$ctx['language'] = sanitize_text_field( $context['language'] );
		}

		$payload = array(
			'source_page_id' => $page_id,
			'page_title'     => isset( $post->post_title ) ? $post->post_title : '',
			'page_excerpt'   => isset( $post->post_excerpt ) ? $post->post_excerpt : '',
			'context'        => $ctx,
			'instructions'   => sanitize_textarea_field( (string) $instructions ),
		);

		/**
		 * Filter payload sent to POST /api/v5/ai/geo/variant-draft.
		 *
		 * @param array<string, mixed> $payload  Payload.
		 * @param int                  $page_id Page ID.
		 */
		$payload = apply_filters( 'rwgc_ai_variant_draft_payload', $payload, $page_id );

		$result = RWGC_Platform_Client::request( 'POST', '/api/v5/ai/geo/variant-draft', $payload, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$parsed = self::parse_json_response( $result );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$json = $parsed['json'];
		if ( ! is_array( $json ) || empty( $json['success'] ) ) {
			$msg = __( 'ReactWoo API returned an error.', 'reactwoo-geocore' );
			if ( is_array( $json ) && isset( $json['error'] ) && is_string( $json['error'] ) ) {
				$msg = $json['error'];
			}
			return new WP_Error( 'rwgc_api_error', $msg, array( 'status' => $parsed['code'], 'data' => $json ) );
		}

		$inner = isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : array();

		$out = array(
			'success'        => true,
			'variant_draft'  => isset( $inner['variant_draft'] ) ? $inner['variant_draft'] : null,
			'tokens_used'    => isset( $inner['tokens_used'] ) ? $inner['tokens_used'] : null,
			'draft_only'     => ! empty( $inner['draft_only'] ),
			'http_code'      => $parsed['code'],
		);

		/**
		 * Filter successful variant draft response.
		 *
		 * @param array<string, mixed> $out     Response for PHP/REST consumers.
		 * @param int                  $page_id Page ID.
		 */
		return apply_filters( 'rwgc_ai_variant_draft_response', $out, $page_id );
	}

	/**
	 * @param array<string, mixed> $result From RWGC_Platform_Client::request.
	 * @return array{code: int, json: array|null}|\WP_Error
	 */
	private static function parse_json_response( $result ) {
		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;

		if ( $code >= 200 && $code < 300 ) {
			return array(
				'code' => $code,
				'json' => $data,
			);
		}

		$msg = __( 'ReactWoo API request failed.', 'reactwoo-geocore' );
		if ( is_array( $data ) ) {
			if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
				$msg = $data['message'];
			} elseif ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
				$msg = $data['error'];
			}
		}

		return new WP_Error(
			'rwgc_api_error',
			$msg,
			array(
				'status' => $code,
				'data'   => $data,
			)
		);
	}
}
