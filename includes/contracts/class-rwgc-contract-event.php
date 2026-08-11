<?php
/**
 * Measurement event contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telemetry event payload (local queue / Cloud batch).
 */
final class RWGC_Contract_Event extends RWGC_Contract {

	/** @var string */
	private $type;
	/** @var string */
	private $experience;
	/** @var string */
	private $variant;
	/** @var mixed */
	private $value;
	/** @var string */
	private $timestamp;
	/** @var string */
	private $audience;
	/** @var string */
	private $anonymous_visitor_id;

	/**
	 * @param string               $type Type.
	 * @param string               $experience Experience ID.
	 * @param string               $variant Variant ID.
	 * @param mixed                $value Value.
	 * @param string               $timestamp Timestamp.
	 * @param string               $audience Audience ID.
	 * @param string               $anonymous_visitor_id Visitor ID.
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct( $type, $experience, $variant, $value, $timestamp, $audience, $anonymous_visitor_id, array $extras ) {
		$this->type                   = $type;
		$this->experience             = $experience;
		$this->variant                = $variant;
		$this->value                  = $value;
		$this->timestamp              = $timestamp;
		$this->audience               = $audience;
		$this->anonymous_visitor_id   = $anonymous_visitor_id;
		$this->extras                 = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition(
			$data,
			array( 'type', 'experience', 'variant', 'value', 'timestamp', 'audience', 'anonymous_visitor_id', 'visitor_id' )
		);

		$type = RWGC_Schema::normalize_capability_id( isset( $core['type'] ) ? $core['type'] : '' );
		if ( '' === $type ) {
			$raw = self::optional_string( $core, 'type' );
			if ( '' === $raw || ! preg_match( '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', strtolower( $raw ) ) ) {
				throw new RWGC_Contract_Exception( 'Event type must be a dotted capability ID.' );
			}
			$type = strtolower( $raw );
		}

		$visitor = self::optional_string( $core, 'anonymous_visitor_id' );
		if ( '' === $visitor ) {
			$visitor = self::optional_string( $core, 'visitor_id' );
		}

		return new self(
			$type,
			self::optional_string( $core, 'experience' ),
			self::optional_string( $core, 'variant' ),
			array_key_exists( 'value', $core ) ? $core['value'] : null,
			self::optional_string( $core, 'timestamp' ),
			self::optional_string( $core, 'audience' ),
			$visitor,
			$extras
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->with_extras(
			array(
				'type'                  => $this->type,
				'experience'            => $this->experience,
				'variant'               => $this->variant,
				'value'                 => $this->value,
				'timestamp'             => $this->timestamp,
				'audience'              => $this->audience,
				'anonymous_visitor_id'  => $this->anonymous_visitor_id,
			)
		);
	}
}
