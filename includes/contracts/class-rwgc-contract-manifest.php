<?php
/**
 * Compiled site manifest contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versioned document Cloud compiles and Core caches.
 *
 * Unknown non-critical top-level keys are retained in extras and do not invalidate the manifest.
 */
final class RWGC_Contract_Manifest extends RWGC_Contract {

	/** @var string */
	private $schema;
	/** @var int */
	private $revision;
	/** @var string */
	private $site;
	/** @var list<RWGC_Contract_Audience> */
	private $audiences;
	/** @var list<RWGC_Contract_Experience> */
	private $experiences;
	/** @var list<RWGC_Contract_Variant> */
	private $variants;
	/** @var list<RWGC_Contract_Experiment> */
	private $experiments;
	/** @var list<RWGC_Contract_Goal> */
	private $goals;
	/** @var list<RWGC_Contract_Experience_Slot> */
	private $slots;

	/**
	 * @param string                               $schema Schema.
	 * @param int                                  $revision Revision.
	 * @param string                               $site Site ID.
	 * @param list<RWGC_Contract_Audience>         $audiences Audiences.
	 * @param list<RWGC_Contract_Experience>       $experiences Experiences.
	 * @param list<RWGC_Contract_Variant>          $variants Variants.
	 * @param list<RWGC_Contract_Experiment>       $experiments Experiments.
	 * @param list<RWGC_Contract_Goal>             $goals Goals.
	 * @param list<RWGC_Contract_Experience_Slot>  $slots Slots.
	 * @param array<string, mixed>                 $extras Extras.
	 */
	private function __construct(
		$schema,
		$revision,
		$site,
		array $audiences,
		array $experiences,
		array $variants,
		array $experiments,
		array $goals,
		array $slots,
		array $extras
	) {
		$this->schema      = $schema;
		$this->revision    = $revision;
		$this->site        = $site;
		$this->audiences   = $audiences;
		$this->experiences = $experiences;
		$this->variants    = $variants;
		$this->experiments = $experiments;
		$this->goals       = $goals;
		$this->slots       = $slots;
		$this->extras      = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid critical fields.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition(
			$data,
			array(
				'schema',
				'revision',
				'site',
				'reactwoo_schema_version',
				'audiences',
				'experiences',
				'variants',
				'experiments',
				'goals',
				'slots',
				'experience_slots',
			)
		);

		$schema = self::optional_string( $core, 'schema', RWGC_Schema::MANIFEST_SCHEMA );
		if ( ! self::is_compatible_schema( $schema ) ) {
			throw new RWGC_Contract_Exception( sprintf( 'Unsupported manifest schema: %s.', $schema ) );
		}

		if ( ! isset( $core['revision'] ) || ! is_numeric( $core['revision'] ) ) {
			throw new RWGC_Contract_Exception( 'Manifest revision is required.' );
		}
		$revision = (int) $core['revision'];
		if ( $revision < 1 ) {
			throw new RWGC_Contract_Exception( 'Manifest revision must be >= 1.' );
		}

		$site = self::require_string( $core, 'site' );

		$audiences = self::map_list( isset( $core['audiences'] ) ? $core['audiences'] : array(), array( 'RWGC_Contract_Audience', 'from_array' ), 'audiences' );
		$experiences = self::map_list( isset( $core['experiences'] ) ? $core['experiences'] : array(), array( 'RWGC_Contract_Experience', 'from_array' ), 'experiences' );
		$variants = self::map_list( isset( $core['variants'] ) ? $core['variants'] : array(), array( 'RWGC_Contract_Variant', 'from_array' ), 'variants' );
		$experiments = self::map_list( isset( $core['experiments'] ) ? $core['experiments'] : array(), array( 'RWGC_Contract_Experiment', 'from_array' ), 'experiments' );
		$goals = self::map_list( isset( $core['goals'] ) ? $core['goals'] : array(), array( 'RWGC_Contract_Goal', 'from_array' ), 'goals' );

		$slots_raw = isset( $core['slots'] ) && is_array( $core['slots'] )
			? $core['slots']
			: ( isset( $core['experience_slots'] ) && is_array( $core['experience_slots'] ) ? $core['experience_slots'] : array() );
		$slots = self::map_list( $slots_raw, array( 'RWGC_Contract_Experience_Slot', 'from_array' ), 'slots' );

		return new self( $schema, $revision, $site, $audiences, $experiences, $variants, $experiments, $goals, $slots, $extras );
	}

	/**
	 * @param string $json JSON.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid JSON or contract.
	 */
	public static function from_json( $json ) {
		return self::from_array( self::decode_json_object( $json ) );
	}

	/**
	 * Compatible with 1.x schema strings.
	 *
	 * @param string $schema Schema.
	 * @return bool
	 */
	public static function is_compatible_schema( $schema ) {
		$schema = trim( (string) $schema );
		if ( '' === $schema ) {
			return true;
		}
		if ( preg_match( '/^1(\.\d+)?$/', $schema ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param mixed  $raw Raw list.
	 * @param callable $mapper Mapper.
	 * @param string $label Label.
	 * @return array<int, mixed>
	 * @throws RWGC_Contract_Exception On invalid list.
	 */
	private static function map_list( $raw, $mapper, $label ) {
		if ( ! is_array( $raw ) ) {
			throw new RWGC_Contract_Exception( sprintf( 'Manifest %s must be an array.', $label ) );
		}
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				throw new RWGC_Contract_Exception( sprintf( 'Manifest %s entries must be objects.', $label ) );
			}
			$out[] = call_user_func( $mapper, $row );
		}
		return $out;
	}

	/** @return int */
	public function revision() {
		return $this->revision;
	}

	/** @return string */
	public function site() {
		return $this->site;
	}

	/**
	 * @return list<RWGC_Contract_Audience>
	 */
	public function audiences() {
		return $this->audiences;
	}

	/**
	 * @return list<RWGC_Contract_Experience>
	 */
	public function experiences() {
		return $this->experiences;
	}

	/**
	 * @return list<RWGC_Contract_Variant>
	 */
	public function variants() {
		return $this->variants;
	}

	/**
	 * @return list<RWGC_Contract_Experiment>
	 */
	public function experiments() {
		return $this->experiments;
	}

	/**
	 * @return list<RWGC_Contract_Goal>
	 */
	public function goals() {
		return $this->goals;
	}

	/**
	 * @return list<RWGC_Contract_Experience_Slot>
	 */
	public function slots() {
		return $this->slots;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'schema'                  => $this->schema,
				'reactwoo_schema_version' => RWGC_Schema::VERSION,
				'revision'                => $this->revision,
				'site'                    => $this->site,
				'audiences'               => array_map(
					static function ( $a ) {
						return $a->to_array();
					},
					$this->audiences
				),
				'experiences'             => array_map(
					static function ( $e ) {
						return $e->to_array();
					},
					$this->experiences
				),
				'variants'                => array_map(
					static function ( $v ) {
						return $v->to_array();
					},
					$this->variants
				),
				'experiments'             => array_map(
					static function ( $e ) {
						return $e->to_array();
					},
					$this->experiments
				),
				'goals'                   => array_map(
					static function ( $g ) {
						return $g->to_array();
					},
					$this->goals
				),
				'slots'                   => array_map(
					static function ( $s ) {
						return $s->to_array();
					},
					$this->slots
				),
			)
		);
	}
}
