<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geo analytics / experiment event envelope.
 *
 * Compatible with RWGC_Context snapshots and RWGC_Variant ids. See `docs/phases/phase-6.md`.
 */
class RWGC_Event {

	const TYPE_IMPRESSION = 'impression';
	const TYPE_CLICK      = 'click';
	const TYPE_CONVERSION = 'conversion';
	const TYPE_PURCHASE   = 'purchase';
	const TYPE_ASSIGNMENT = 'assignment';
	const TYPE_ROUTE_REDIRECT = 'route_redirect';

	/**
	 * One of self::TYPE_* or a custom sanitized key.
	 *
	 * @var string
	 */
	public $event_type = self::TYPE_IMPRESSION;

	/**
	 * RWGC_Variant::$id or empty when default / unknown.
	 *
	 * @var string
	 */
	public $variant_id = '';

	/**
	 * Optional experiment key (future A/B).
	 *
	 * @var string
	 */
	public $experiment_id = '';

	/**
	 * Optional stable assignment id for the visitor/session (future).
	 *
	 * @var string
	 */
	public $assignment_id = '';

	/**
	 * Snapshot from RWGC_Context::to_snapshot() or equivalent.
	 *
	 * @var array<string, mixed>
	 */
	public $context = array();

	/**
	 * Subject: what the event refers to (page, route, etc.).
	 *
	 * Typical keys: source_type, source_id, page_id, target_page_id, reason.
	 *
	 * @var array<string, mixed>
	 */
	public $subject = array();

	/**
	 * Extra vendor-neutral payload.
	 *
	 * @var array<string, mixed>
	 */
	public $meta = array();

	/**
	 * @param array<string, mixed> $data Raw data.
	 */
	public function __construct( $data = array() ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		$this->event_type    = isset( $data['event_type'] ) ? sanitize_key( (string) $data['event_type'] ) : self::TYPE_IMPRESSION;
		$this->variant_id    = isset( $data['variant_id'] ) ? sanitize_key( (string) $data['variant_id'] ) : '';
		$this->experiment_id = isset( $data['experiment_id'] ) ? sanitize_key( (string) $data['experiment_id'] ) : '';
		$this->assignment_id = isset( $data['assignment_id'] ) ? sanitize_text_field( (string) $data['assignment_id'] ) : '';
		$this->context       = isset( $data['context'] ) && is_array( $data['context'] ) ? $data['context'] : array();
		$this->subject       = isset( $data['subject'] ) && is_array( $data['subject'] ) ? $data['subject'] : array();
		$this->meta          = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
	}

	/**
	 * Build from a route decision + context (optional convenience).
	 *
	 * @param array<string, mixed> $decision From RWGC_Page_Route_Resolver / get_route_decision_for_page.
	 * @param RWGC_Context         $context  Context.
	 * @param string               $event_type Event type.
	 * @return self
	 */
	public static function from_route_decision( $decision, $context, $event_type = self::TYPE_IMPRESSION ) {
		$e = new self(
			array(
				'event_type' => $event_type,
				'context'    => $context instanceof RWGC_Context ? $context->to_snapshot() : array(),
			)
		);
		if ( is_array( $decision ) ) {
			$e->variant_id = isset( $decision['variant_id'] ) ? sanitize_key( (string) $decision['variant_id'] ) : '';
			$e->subject    = array(
				'page_id'        => isset( $decision['page_id'] ) ? absint( $decision['page_id'] ) : 0,
				'target_page_id' => isset( $decision['target_page_id'] ) ? absint( $decision['target_page_id'] ) : 0,
				'reason'         => isset( $decision['reason'] ) ? sanitize_key( (string) $decision['reason'] ) : '',
				'country'        => isset( $decision['country'] ) ? strtoupper( substr( sanitize_text_field( (string) $decision['country'] ), 0, 2 ) ) : '',
			);
		}
		return $e;
	}

	/**
	 * Portable array for filters and transports.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return array(
			'event_type'    => $this->event_type,
			'variant_id'    => $this->variant_id,
			'experiment_id' => $this->experiment_id,
			'assignment_id' => $this->assignment_id,
			'context'       => $this->context,
			'subject'       => $this->subject,
			'meta'          => $this->meta,
		);
	}

	/**
	 * @param array<string, mixed> $data Raw array.
	 * @return self
	 */
	public static function from_array( $data ) {
		return new self( is_array( $data ) ? $data : array() );
	}

	/**
	 * Canonical `event_type` strings used by Geo Core (for docs, REST discovery, satellite plugins).
	 *
	 * @return string[]
	 */
	public static function known_event_types() {
		$types = array(
			self::TYPE_IMPRESSION,
			self::TYPE_CLICK,
			self::TYPE_CONVERSION,
			self::TYPE_PURCHASE,
			self::TYPE_ASSIGNMENT,
			self::TYPE_ROUTE_REDIRECT,
		);
		/**
		 * Filter known geo event type strings (REST `/capabilities`, integrations).
		 *
		 * @param string[] $types Slugs; custom types should remain URL-safe keys.
		 */
		$filtered = apply_filters( 'rwgc_geo_event_known_types', $types );
		if ( ! is_array( $filtered ) ) {
			return $types;
		}
		$out = array();
		foreach ( $filtered as $t ) {
			$t = sanitize_key( (string) $t );
			if ( '' !== $t ) {
				$out[] = $t;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
