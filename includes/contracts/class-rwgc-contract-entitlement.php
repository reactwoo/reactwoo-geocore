<?php
/**
 * Entitlement contract.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feature/limit grant from standalone license or Cloud subscription.
 */
final class RWGC_Contract_Entitlement extends RWGC_Contract {

	/** @var string */
	private $key;
	/** @var bool */
	private $allowed;
	/** @var mixed */
	private $limit;
	/** @var string */
	private $source;

	/**
	 * @param string               $key Key.
	 * @param bool                 $allowed Allowed.
	 * @param mixed                $limit Optional numeric/string limit.
	 * @param string               $source standalone|cloud.
	 * @param array<string, mixed> $extras Extras.
	 */
	private function __construct( $key, $allowed, $limit, $source, array $extras ) {
		$this->key     = $key;
		$this->allowed = $allowed;
		$this->limit   = $limit;
		$this->source  = $source;
		$this->extras  = $extras;
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return self
	 * @throws RWGC_Contract_Exception On invalid input.
	 */
	public static function from_array( array $data ) {
		list( $core, $extras ) = RWGC_Schema::partition( $data, array( 'key', 'id', 'allowed', 'limit', 'value', 'source' ) );
		$key = self::optional_string( $core, 'key' );
		if ( '' === $key ) {
			$key = self::optional_string( $core, 'id' );
		}
		if ( '' === $key || ! preg_match( '/^[a-z][a-z0-9_.]*$/', $key ) ) {
			throw new RWGC_Contract_Exception( 'Entitlement key is required.' );
		}

		$allowed = true;
		if ( array_key_exists( 'allowed', $core ) ) {
			$allowed = (bool) $core['allowed'];
		}

		$limit = null;
		if ( array_key_exists( 'limit', $core ) ) {
			$limit = $core['limit'];
		} elseif ( array_key_exists( 'value', $core ) ) {
			$limit = $core['value'];
		}

		$source = strtolower( self::optional_string( $core, 'source', 'standalone' ) );
		if ( ! in_array( $source, array( 'standalone', 'cloud' ), true ) ) {
			$source = 'standalone';
		}

		return new self( $key, $allowed, $limit, $source, $extras );
	}

	/** @return string */
	public function key() {
		return $this->key;
	}

	/** @return bool */
	public function allowed() {
		return $this->allowed;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		$out = array(
			'key'     => $this->key,
			'allowed' => $this->allowed,
			'source'  => $this->source,
		);
		if ( null !== $this->limit ) {
			$out['limit'] = $this->limit;
		}
		return $this->with_extras( $out );
	}
}
