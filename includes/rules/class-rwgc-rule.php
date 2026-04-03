<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule entity (canonical shape). Persistence is introduced in a later phase.
 */
class RWGC_Rule {

	const TYPE_VISIBILITY           = 'visibility';
	const TYPE_VARIANT_ASSIGNMENT   = 'variant_assignment';
	const TYPE_EXPERIMENT_ASSIGNMENT = 'experiment_assignment';
	const TYPE_PRICING_RULE         = 'pricing_rule';
	const TYPE_CURRENCY_RULE        = 'currency_rule';

	/**
	 * @var string
	 */
	public $id = '';

	/**
	 * @var string
	 */
	public $rule_type = self::TYPE_VARIANT_ASSIGNMENT;

	/**
	 * @var string
	 */
	public $source_type = 'page';

	/**
	 * @var int
	 */
	public $source_id = 0;

	/**
	 * @var int
	 */
	public $priority = 0;

	/**
	 * @var array<string, mixed>
	 */
	public $conditions_json = array();

	/**
	 * @var string
	 */
	public $status = 'draft';

	/**
	 * @var string|null
	 */
	public $start_at = null;

	/**
	 * @var string|null
	 */
	public $end_at = null;

	/**
	 * @param array<string, mixed> $data Raw data.
	 */
	public function __construct( $data = array() ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		$this->id              = isset( $data['id'] ) ? sanitize_text_field( (string) $data['id'] ) : '';
		$this->rule_type       = isset( $data['rule_type'] ) ? sanitize_key( (string) $data['rule_type'] ) : self::TYPE_VARIANT_ASSIGNMENT;
		$this->source_type     = isset( $data['source_type'] ) ? sanitize_key( (string) $data['source_type'] ) : 'page';
		$this->source_id       = isset( $data['source_id'] ) ? absint( $data['source_id'] ) : 0;
		$this->priority        = isset( $data['priority'] ) ? (int) $data['priority'] : 0;
		$this->conditions_json = isset( $data['conditions_json'] ) && is_array( $data['conditions_json'] ) ? $data['conditions_json'] : array();
		$this->status          = isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'draft';
		$this->start_at        = isset( $data['start_at'] ) ? sanitize_text_field( (string) $data['start_at'] ) : null;
		$this->end_at          = isset( $data['end_at'] ) ? sanitize_text_field( (string) $data['end_at'] ) : null;
	}

	/**
	 * Whether this rule applies to the given context (conditions + optional publish gate).
	 *
	 * Scheduling uses string timestamps if set; invalid dates are ignored.
	 *
	 * @param RWGC_Context $context             Visitor context.
	 * @param bool         $require_published When true, only `publish` or `active` status matches.
	 * @return bool
	 */
	public function matches_context( $context, $require_published = true ) {
		if ( ! $context instanceof RWGC_Context ) {
			return false;
		}

		if ( $require_published ) {
			$status = sanitize_key( (string) $this->status );
			if ( ! in_array( $status, array( 'publish', 'active' ), true ) ) {
				return false;
			}
		}

		if ( ! $this->is_within_schedule() ) {
			return false;
		}

		return RWGC_Rule_Condition_Evaluator::context_matches_conditions( $this->conditions_json, $context );
	}

	/**
	 * @return bool
	 */
	private function is_within_schedule() {
		$now = time();
		if ( is_string( $this->start_at ) && '' !== $this->start_at ) {
			$start = strtotime( $this->start_at );
			if ( false !== $start && $now < $start ) {
				return false;
			}
		}
		if ( is_string( $this->end_at ) && '' !== $this->end_at ) {
			$end = strtotime( $this->end_at );
			if ( false !== $end && $now > $end ) {
				return false;
			}
		}
		return true;
	}
}
