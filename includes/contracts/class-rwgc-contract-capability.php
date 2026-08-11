<?php
/**
 * Capability contract (condition / action / context / goal).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a registerable platform capability (WP1 data shape only).
 */
final class RWGC_Contract_Capability extends RWGC_Contract {

	const TYPE_CONDITION = 'condition';
	const TYPE_ACTION    = 'action';
	const TYPE_CONTEXT   = 'context';
	const TYPE_GOAL      = 'goal';

	/** @var string */
	private $id;
	/** @var string */
	private $type;
	/** @var string */
	private $label;
	/** @var string */
	private $description;
	/** @var array<string, mixed> */
	private $input_schema;
	/** @var array<string, mixed> */
	private $output_schema;
	/** @var string */
	private $provider;
	/** @var string */
	private $version;
	/** @var string */
	private $entitlement;

	/**
	 * @param string               $id ID.
	 * @param string               $type Type.
	 * @param string               $label Label.
	 * @param string               $description Description.
	 * @param array<string, mixed> $input_schema Input schema.
	 * @param array<string, mixed> $output_schema Output schema.
	 * @param string               $provider Provider plugin slug.
	 * @param string               $version Capability version.
	 * @param string               $entitlement Entitlement key (optional).
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct(
		$id,
		$type,
		$label,
		$description,
		array $input_schema,
		array $output_schema,
		$provider,
		$version,
		$entitlement,
		array $extras
	) {
		$this->id            = $id;
		$this->type          = $type;
		$this->label         = $label;
		$this->description   = $description;
		$this->input_schema  = $input_schema;
		$this->output_schema = $output_schema;
		$this->provider      = $provider;
		$this->version       = $version;
		$this->entitlement   = $entitlement;
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
			array( 'id', 'type', 'label', 'description', 'input_schema', 'output_schema', 'provider', 'version', 'entitlement', 'entitlement_requirement' )
		);

		$id = RWGC_Schema::normalize_capability_id( isset( $core['id'] ) ? $core['id'] : '' );
		if ( '' === $id ) {
			throw new RWGC_Contract_Exception( 'Capability id is required and must be a dotted capability ID.' );
		}

		$type = strtolower( self::optional_string( $core, 'type' ) );
		$allowed = array( self::TYPE_CONDITION, self::TYPE_ACTION, self::TYPE_CONTEXT, self::TYPE_GOAL );
		if ( ! in_array( $type, $allowed, true ) ) {
			throw new RWGC_Contract_Exception( 'Capability type must be condition, action, context, or goal.' );
		}

		$label = self::require_string( $core, 'label' );
		$entitlement = self::optional_string( $core, 'entitlement' );
		if ( '' === $entitlement ) {
			$entitlement = self::optional_string( $core, 'entitlement_requirement' );
		}

		return new self(
			$id,
			$type,
			$label,
			self::optional_string( $core, 'description' ),
			isset( $core['input_schema'] ) && is_array( $core['input_schema'] ) ? $core['input_schema'] : array(),
			isset( $core['output_schema'] ) && is_array( $core['output_schema'] ) ? $core['output_schema'] : array(),
			self::optional_string( $core, 'provider' ),
			self::optional_string( $core, 'version', '1' ),
			$entitlement,
			$extras
		);
	}

	/** @return string */
	public function id() {
		return $this->id;
	}

	/** @return string */
	public function type() {
		return $this->type;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		$out = array(
			'id'            => $this->id,
			'type'          => $this->type,
			'label'         => $this->label,
			'description'   => $this->description,
			'input_schema'  => $this->input_schema,
			'output_schema' => $this->output_schema,
			'provider'      => $this->provider,
			'version'       => $this->version,
		);
		if ( '' !== $this->entitlement ) {
			$out['entitlement'] = $this->entitlement;
		}
		return $this->with_extras( $out );
	}
}
