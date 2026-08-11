<?php
/**
 * Decision Runtime result value object.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs from {@see RWGC_Decision_Runtime::evaluate()}.
 */
final class RWGC_Decision_Result {

	/** @var list<string> */
	private $matched_audiences;
	/** @var list<array<string, mixed>> */
	private $selected_experiences;
	/** @var array<string, string> slot_id => variant_id */
	private $selected_variants;
	/** @var list<array<string, mixed>> */
	private $actions;
	/** @var list<string> */
	private $reasons;
	/** @var array<string, mixed> */
	private $debug;
	/** @var float */
	private $elapsed_ms;

	/**
	 * @param list<string>                 $matched_audiences Matched audience IDs.
	 * @param list<array<string, mixed>>   $selected_experiences Selected experiences.
	 * @param array<string, string>        $selected_variants Slot → variant.
	 * @param list<array<string, mixed>>   $actions Actions.
	 * @param list<string>                 $reasons Reasons.
	 * @param array<string, mixed>         $debug Debug.
	 * @param float                        $elapsed_ms Elapsed ms.
	 */
	public function __construct(
		array $matched_audiences,
		array $selected_experiences,
		array $selected_variants,
		array $actions,
		array $reasons,
		array $debug,
		$elapsed_ms
	) {
		$this->matched_audiences    = $matched_audiences;
		$this->selected_experiences = $selected_experiences;
		$this->selected_variants    = $selected_variants;
		$this->actions              = $actions;
		$this->reasons              = $reasons;
		$this->debug                = $debug;
		$this->elapsed_ms           = (float) $elapsed_ms;
	}

	/**
	 * @return list<string>
	 */
	public function matched_audiences() {
		return $this->matched_audiences;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function selected_experiences() {
		return $this->selected_experiences;
	}

	/**
	 * @return array<string, string>
	 */
	public function selected_variants() {
		return $this->selected_variants;
	}

	/**
	 * @param string $slot_id Slot ID.
	 * @return string Empty when none.
	 */
	public function variant_for_slot( $slot_id ) {
		$slot_id = (string) $slot_id;
		return isset( $this->selected_variants[ $slot_id ] ) ? (string) $this->selected_variants[ $slot_id ] : '';
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function actions() {
		return $this->actions;
	}

	/**
	 * @return list<string>
	 */
	public function reasons() {
		return $this->reasons;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function debug() {
		return $this->debug;
	}

	/**
	 * @return float
	 */
	public function elapsed_ms() {
		return $this->elapsed_ms;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return array(
			'matched_audiences'    => $this->matched_audiences,
			'selected_experiences' => $this->selected_experiences,
			'selected_variants'    => $this->selected_variants,
			'actions'              => $this->actions,
			'reasons'              => $this->reasons,
			'debug'                => $this->debug,
			'elapsed_ms'           => $this->elapsed_ms,
		);
	}
}
