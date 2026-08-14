<?php
/**
 * Advisory recommendation contract (WP20).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloud/local AI suggestion. Never implies a live-site change.
 */
final class RWGC_Contract_Recommendation extends RWGC_Contract {

	/** @var string */
	private $id;
	/** @var string */
	private $status;
	/** @var string */
	private $observation;
	/** @var array<string, mixed> */
	private $evidence;
	/** @var string */
	private $suggested_action;
	/** @var array<string, mixed>|null */
	private $proposed_experience;
	/** @var array<string, mixed>|null */
	private $proposed_variant;
	/** @var array<string, mixed> */
	private $confidence;
	/** @var array<string, mixed> */
	private $provenance;

	/**
	 * @param string                    $id ID.
	 * @param string                    $status Status.
	 * @param string                    $observation Observation.
	 * @param array<string, mixed>      $evidence Evidence.
	 * @param string                    $suggested_action Action text.
	 * @param array<string, mixed>|null $proposed_experience Draft experience.
	 * @param array<string, mixed>|null $proposed_variant Draft variant.
	 * @param array<string, mixed>      $confidence Confidence.
	 * @param array<string, mixed>      $provenance Provenance.
	 * @param array<string, mixed>      $extras Extras.
	 */
	private function __construct(
		$id,
		$status,
		$observation,
		array $evidence,
		$suggested_action,
		$proposed_experience,
		$proposed_variant,
		array $confidence,
		array $provenance,
		array $extras
	) {
		$this->id                    = $id;
		$this->status                = $status;
		$this->observation           = $observation;
		$this->evidence              = $evidence;
		$this->suggested_action      = $suggested_action;
		$this->proposed_experience   = $proposed_experience;
		$this->proposed_variant      = $proposed_variant;
		$this->confidence            = $confidence;
		$this->provenance            = $provenance;
		$this->extras                = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition(
			$data,
			array(
				'id',
				'status',
				'observation',
				'evidence',
				'suggested_action',
				'suggestedAction',
				'proposed_experience',
				'proposedExperience',
				'proposed_variant',
				'proposedVariant',
				'confidence',
				'provenance',
				'live',
			)
		);

		$id = self::require_string( $core, 'id', 'id' );
		$observation = self::require_string( $core, 'observation', 'observation' );
		$action      = self::optional_string( $core, 'suggested_action' );
		if ( '' === $action ) {
			$action = self::optional_string( $core, 'suggestedAction' );
		}
		if ( '' === $action ) {
			throw new RWGC_Contract_Exception( 'Missing required field: suggested_action.' );
		}

		$status = strtolower( self::optional_string( $core, 'status', 'proposed' ) );
		if ( ! in_array( $status, array( 'proposed', 'approved', 'dismissed' ), true ) ) {
			$status = 'proposed';
		}

		$evidence = array();
		if ( isset( $core['evidence'] ) && is_array( $core['evidence'] ) ) {
			$evidence = $core['evidence'];
		}

		$proposed_experience = null;
		$key_exp             = isset( $core['proposed_experience'] ) ? 'proposed_experience' : 'proposedExperience';
		if ( isset( $core[ $key_exp ] ) && is_array( $core[ $key_exp ] ) ) {
			$proposed_experience = $core[ $key_exp ];
			$proposed_experience['status'] = 'draft';
		}

		$proposed_variant = null;
		$key_var          = isset( $core['proposed_variant'] ) ? 'proposed_variant' : 'proposedVariant';
		if ( isset( $core[ $key_var ] ) && is_array( $core[ $key_var ] ) ) {
			$proposed_variant = $core[ $key_var ];
			$proposed_variant['status'] = 'draft';
		}

		$confidence = array(
			'score'       => 0.0,
			'explanation' => '',
		);
		if ( isset( $core['confidence'] ) && is_array( $core['confidence'] ) ) {
			$score = isset( $core['confidence']['score'] ) ? (float) $core['confidence']['score'] : 0.0;
			if ( $score < 0 ) {
				$score = 0.0;
			}
			if ( $score > 1 ) {
				$score = 1.0;
			}
			$confidence['score']       = $score;
			$confidence['explanation'] = isset( $core['confidence']['explanation'] ) ? (string) $core['confidence']['explanation'] : '';
		}

		$provenance = array();
		if ( isset( $core['provenance'] ) && is_array( $core['provenance'] ) ) {
			$provenance = array(
				'provider'     => isset( $core['provenance']['provider'] ) ? (string) $core['provenance']['provider'] : '',
				'model'        => isset( $core['provenance']['model'] ) ? (string) $core['provenance']['model'] : '',
				'generated_at' => isset( $core['provenance']['generated_at'] ) ? (string) $core['provenance']['generated_at'] : '',
				'dataset_hash' => isset( $core['provenance']['dataset_hash'] ) ? (string) $core['provenance']['dataset_hash'] : '',
				'action'       => isset( $core['provenance']['action'] ) ? (string) $core['provenance']['action'] : '',
			);
		}

		unset( $extras['live'] );

		return new self(
			$id,
			$status,
			$observation,
			$evidence,
			$action,
			$proposed_experience,
			$proposed_variant,
			$confidence,
			$provenance,
			$extras
		);
	}

	/** @return string */
	public function id() {
		return $this->id;
	}

	/** @return string */
	public function status() {
		return $this->status;
	}

	/** @return string */
	public function observation() {
		return $this->observation;
	}

	/** @return array<string, mixed> */
	public function evidence() {
		return $this->evidence;
	}

	/** @return string */
	public function suggested_action() {
		return $this->suggested_action;
	}

	/** @return array<string, mixed>|null */
	public function proposed_experience() {
		return $this->proposed_experience;
	}

	/** @return array<string, mixed>|null */
	public function proposed_variant() {
		return $this->proposed_variant;
	}

	/** @return array<string, mixed> */
	public function confidence() {
		return $this->confidence;
	}

	/** @return array<string, mixed> */
	public function provenance() {
		return $this->provenance;
	}

	/**
	 * Recommendations never describe a live mutation.
	 *
	 * @return bool
	 */
	public function is_live() {
		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		$out = array(
			'id'                   => $this->id,
			'status'               => $this->status,
			'observation'          => $this->observation,
			'evidence'             => $this->evidence,
			'suggested_action'     => $this->suggested_action,
			'proposed_experience'  => $this->proposed_experience,
			'proposed_variant'     => $this->proposed_variant,
			'confidence'           => $this->confidence,
			'provenance'           => $this->provenance,
			'live'                 => false,
		);
		return $this->with_extras( $out );
	}
}
