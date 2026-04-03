<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A content variant overlay (canonical model; storage may remain post meta until later phases).
 */
class RWGC_Variant {

	const TYPE_CONTENT = 'content';

	/**
	 * Stable id (string) for logging and future persistence.
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * WordPress object type this variant applies to (e.g. page, post).
	 *
	 * @var string
	 */
	public $source_type = 'page';

	/**
	 * Source object id (e.g. page ID for routed variants).
	 *
	 * @var int
	 */
	public $source_id = 0;

	/**
	 * Variant type discriminator.
	 *
	 * @var string
	 */
	public $variant_type = self::TYPE_CONTENT;

	/**
	 * Human label for admin/debug.
	 *
	 * @var string
	 */
	public $label = '';

	/**
	 * Target page ID when this variant wins (page routing).
	 *
	 * @var int
	 */
	public $target_page_id = 0;

	/**
	 * Conditions as a portable structure (Phase 1: country include list).
	 *
	 * @var array<string, mixed>
	 */
	public $conditions = array();

	/**
	 * Sort priority (higher wins first when using ordered lists).
	 *
	 * @var int
	 */
	public $priority = 0;

	/**
	 * @param array<string, mixed> $data Raw data.
	 */
	public function __construct( $data = array() ) {
		if ( ! is_array( $data ) ) {
			return;
		}
		$this->id             = isset( $data['id'] ) ? sanitize_key( (string) $data['id'] ) : '';
		$this->source_type    = isset( $data['source_type'] ) ? sanitize_key( (string) $data['source_type'] ) : 'page';
		$this->source_id      = isset( $data['source_id'] ) ? absint( $data['source_id'] ) : 0;
		$this->variant_type   = isset( $data['variant_type'] ) ? sanitize_key( (string) $data['variant_type'] ) : self::TYPE_CONTENT;
		$this->label          = isset( $data['label'] ) ? sanitize_text_field( (string) $data['label'] ) : '';
		$this->target_page_id = isset( $data['target_page_id'] ) ? absint( $data['target_page_id'] ) : 0;
		$this->conditions     = isset( $data['conditions'] ) && is_array( $data['conditions'] ) ? $data['conditions'] : array();
		$this->priority       = isset( $data['priority'] ) ? (int) $data['priority'] : 0;
	}

	/**
	 * Whether the variant matches the given context ({@see RWGC_Rule_Condition_Evaluator}).
	 *
	 * @param RWGC_Context $context Context.
	 * @return bool
	 */
	public function matches( $context ) {
		return RWGC_Rule_Condition_Evaluator::context_matches_conditions(
			is_array( $this->conditions ) ? $this->conditions : array(),
			$context
		);
	}
}
