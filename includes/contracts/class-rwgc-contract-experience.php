<?php
/**
 * Experience contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What should happen for an audience at a slot.
 */
final class RWGC_Contract_Experience extends RWGC_Contract {

	/** @var string */
	private $id;
	/** @var string */
	private $name;
	/** @var string */
	private $audience_id;
	/** @var string */
	private $slot_id;
	/** @var string */
	private $variant_id;
	/** @var string */
	private $status;
	/** @var int */
	private $priority;
	/** @var array<string, mixed> */
	private $schedule;
	/** @var string */
	private $experiment_id;
	/** @var string */
	private $goal_id;

	/**
	 * @param string               $id ID.
	 * @param string               $name Name.
	 * @param string               $audience_id Audience ID.
	 * @param string               $slot_id Slot ID.
	 * @param string               $variant_id Variant ID.
	 * @param string               $status Status.
	 * @param int                  $priority Priority 0–100.
	 * @param array<string, mixed> $schedule Schedule.
	 * @param string               $experiment_id Experiment ID.
	 * @param string               $goal_id Goal ID.
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct(
		$id,
		$name,
		$audience_id,
		$slot_id,
		$variant_id,
		$status,
		$priority,
		array $schedule,
		$experiment_id,
		$goal_id,
		array $extras
	) {
		$this->id            = $id;
		$this->name          = $name;
		$this->audience_id   = $audience_id;
		$this->slot_id       = $slot_id;
		$this->variant_id    = $variant_id;
		$this->status        = $status;
		$this->priority      = $priority;
		$this->schedule      = $schedule;
		$this->experiment_id = $experiment_id;
		$this->goal_id       = $goal_id;
		$this->extras        = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition(
			$data,
			array( 'id', 'name', 'audienceId', 'audience_id', 'slotId', 'slot_id', 'variantId', 'variant_id', 'status', 'priority', 'schedule', 'experimentId', 'experiment_id', 'goalId', 'goal_id' )
		);

		$id          = self::require_string( $core, 'id' );
		$name        = self::require_string( $core, 'name' );
		$audience_id = self::optional_string( $core, 'audience_id' );
		if ( '' === $audience_id ) {
			$audience_id = self::optional_string( $core, 'audienceId' );
		}
		if ( '' === $audience_id ) {
			throw new RWGC_Contract_Exception( 'Experience audience_id is required.' );
		}

		$slot_id = self::optional_string( $core, 'slot_id' );
		if ( '' === $slot_id ) {
			$slot_id = self::optional_string( $core, 'slotId' );
		}
		$variant_id = self::optional_string( $core, 'variant_id' );
		if ( '' === $variant_id ) {
			$variant_id = self::optional_string( $core, 'variantId' );
		}

		$status   = strtolower( self::optional_string( $core, 'status', 'draft' ) );
		$priority = isset( $core['priority'] ) ? (int) $core['priority'] : 50;
		if ( $priority < 0 ) {
			$priority = 0;
		}
		if ( $priority > 100 ) {
			$priority = 100;
		}

		$experiment_id = self::optional_string( $core, 'experiment_id' );
		if ( '' === $experiment_id ) {
			$experiment_id = self::optional_string( $core, 'experimentId' );
		}
		$goal_id = self::optional_string( $core, 'goal_id' );
		if ( '' === $goal_id ) {
			$goal_id = self::optional_string( $core, 'goalId' );
		}

		return new self(
			$id,
			$name,
			$audience_id,
			$slot_id,
			$variant_id,
			$status,
			$priority,
			isset( $core['schedule'] ) && is_array( $core['schedule'] ) ? $core['schedule'] : array(),
			$experiment_id,
			$goal_id,
			$extras
		);
	}

	/** @return string */
	public function id() {
		return $this->id;
	}

	/** @return string */
	public function name() {
		return $this->name;
	}

	/** @return string */
	public function audience_id() {
		return $this->audience_id;
	}

	/** @return string */
	public function slot_id() {
		return $this->slot_id;
	}

	/** @return string */
	public function variant_id() {
		return $this->variant_id;
	}

	/** @return string */
	public function status() {
		return $this->status;
	}

	/** @return int */
	public function priority() {
		return $this->priority;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function schedule() {
		return $this->schedule;
	}

	/** @return string */
	public function experiment_id() {
		return $this->experiment_id;
	}

	/** @return string */
	public function goal_id() {
		return $this->goal_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'id'            => $this->id,
				'name'          => $this->name,
				'audience_id'   => $this->audience_id,
				'slot_id'       => $this->slot_id,
				'variant_id'    => $this->variant_id,
				'status'        => $this->status,
				'priority'      => $this->priority,
				'schedule'      => $this->schedule,
				'experiment_id' => $this->experiment_id,
				'goal_id'       => $this->goal_id,
			)
		);
	}
}
