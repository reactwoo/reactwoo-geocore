<?php
/**
 * Experience Slot contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where content may change.
 */
final class RWGC_Contract_Experience_Slot extends RWGC_Contract {

	/** @var string */
	private $id;
	/** @var string */
	private $name;
	/** @var string */
	private $page;
	/** @var string */
	private $adapter;
	/** @var string */
	private $status;
	/** @var list<string> */
	private $variant_types;
	/** @var array<string, mixed> */
	private $metadata;

	/**
	 * @param string               $id ID.
	 * @param string               $name Name.
	 * @param string               $page Page reference.
	 * @param string               $adapter Adapter.
	 * @param string               $status Status.
	 * @param list<string>         $variant_types Allowed variant types.
	 * @param array<string, mixed> $metadata Metadata.
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct( $id, $name, $page, $adapter, $status, array $variant_types, array $metadata, array $extras ) {
		$this->id            = $id;
		$this->name          = $name;
		$this->page          = $page;
		$this->adapter       = $adapter;
		$this->status        = $status;
		$this->variant_types = $variant_types;
		$this->metadata      = $metadata;
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
			array( 'id', 'name', 'page', 'adapter', 'status', 'variant_types', 'available_variant_types', 'metadata' )
		);

		$id       = self::require_string( $core, 'id' );
		$name     = self::require_string( $core, 'name' );
		$adapter  = strtolower( self::optional_string( $core, 'adapter', 'elementor' ) );
		$status   = strtolower( self::optional_string( $core, 'status', 'active' ) );
		$types_raw = isset( $core['variant_types'] ) && is_array( $core['variant_types'] )
			? $core['variant_types']
			: ( isset( $core['available_variant_types'] ) && is_array( $core['available_variant_types'] ) ? $core['available_variant_types'] : array( 'content', 'reactwoo_component', 'native_reference' ) );
		$types = array();
		foreach ( $types_raw as $t ) {
			$t = strtolower( trim( (string) $t ) );
			if ( '' !== $t ) {
				$types[] = $t;
			}
		}

		return new self(
			$id,
			$name,
			self::optional_string( $core, 'page' ),
			$adapter,
			$status,
			array_values( array_unique( $types ) ),
			isset( $core['metadata'] ) && is_array( $core['metadata'] ) ? $core['metadata'] : array(),
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
	public function page() {
		return $this->page;
	}

	/** @return string */
	public function adapter() {
		return $this->adapter;
	}

	/** @return string */
	public function status() {
		return $this->status;
	}

	/**
	 * @return list<string>
	 */
	public function variant_types() {
		return $this->variant_types;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function metadata() {
		return $this->metadata;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'id'            => $this->id,
				'name'          => $this->name,
				'page'          => $this->page,
				'adapter'       => $this->adapter,
				'status'        => $this->status,
				'variant_types' => $this->variant_types,
				'metadata'      => $this->metadata,
			)
		);
	}
}
