<?php
/**
 * Variant contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What replaces or alters a slot.
 */
final class RWGC_Contract_Variant extends RWGC_Contract {

	const TYPE_DEFAULT             = 'default';
	const TYPE_CONTENT             = 'content';
	const TYPE_REACTWOO_COMPONENT  = 'reactwoo_component';
	const TYPE_NATIVE_REFERENCE    = 'native_reference';

	/** @var string */
	private $id;
	/** @var string */
	private $type;
	/** @var array<string, mixed> */
	private $payload;

	/**
	 * @param string               $id ID.
	 * @param string               $type Type.
	 * @param array<string, mixed> $payload Type-specific payload.
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct( $id, $type, array $payload, array $extras ) {
		$this->id      = $id;
		$this->type    = $type;
		$this->payload = $payload;
		$this->extras  = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition(
			$data,
			array( 'id', 'type', 'payload', 'content', 'component', 'native_reference', 'props', 'reference' )
		);

		$id   = self::require_string( $core, 'id' );
		$type = strtolower( self::optional_string( $core, 'type', self::TYPE_CONTENT ) );
		$allowed = array(
			self::TYPE_DEFAULT,
			self::TYPE_CONTENT,
			self::TYPE_REACTWOO_COMPONENT,
			self::TYPE_NATIVE_REFERENCE,
		);
		if ( ! in_array( $type, $allowed, true ) ) {
			throw new RWGC_Contract_Exception( 'Variant type is not supported.' );
		}

		$payload = isset( $core['payload'] ) && is_array( $core['payload'] ) ? $core['payload'] : array();
		if ( empty( $payload ) ) {
			if ( self::TYPE_CONTENT === $type && isset( $core['content'] ) && is_array( $core['content'] ) ) {
				$payload = array( 'content' => $core['content'] );
			} elseif ( self::TYPE_REACTWOO_COMPONENT === $type ) {
				$payload = array(
					'component' => isset( $core['component'] ) ? $core['component'] : '',
					'props'     => isset( $core['props'] ) && is_array( $core['props'] ) ? $core['props'] : array(),
				);
			} elseif ( self::TYPE_NATIVE_REFERENCE === $type ) {
				$payload = array(
					'reference' => isset( $core['native_reference'] ) ? $core['native_reference'] : ( isset( $core['reference'] ) ? $core['reference'] : '' ),
				);
			}
		}

		return new self( $id, $type, $payload, $extras );
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
		return $this->with_extras(
			array(
				'id'      => $this->id,
				'type'    => $this->type,
				'payload' => $this->payload,
			)
		);
	}
}
