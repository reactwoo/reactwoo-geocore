<?php
/**
 * Normalised visitor/request context for targeting (portable array shape).
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds resolved targeting fields. Unknown keys may appear under `custom`.
 */
class RWGC_Context_Snapshot {

	/**
	 * @var array<string, mixed>
	 */
	private $data = array();

	/**
	 * @param array<string, mixed> $data Keyed snapshot.
	 */
	public function __construct( array $data = array() ) {
		$this->data = is_array( $data ) ? $data : array();
	}

	/**
	 * @param array<string, mixed> $data Raw.
	 * @return self
	 */
	public static function from_array( array $data ) {
		return new self( $data );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array() {
		return $this->data;
	}

	/**
	 * @param string $key Target key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$key = (string) $key;
		if ( isset( $this->data[ $key ] ) ) {
			return $this->data[ $key ];
		}
		if ( isset( $this->data['custom'] ) && is_array( $this->data['custom'] ) && isset( $this->data['custom'][ $key ] ) ) {
			return $this->data['custom'][ $key ];
		}
		return $default;
	}

	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->data[ (string) $key ] = $value;
	}

	/**
	 * Shallow merge: overrides win.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return self New instance.
	 */
	public function merge( array $overrides ) {
		$next = $this->data;
		foreach ( $overrides as $k => $v ) {
			if ( 'custom' === $k && isset( $next['custom'] ) && is_array( $next['custom'] ) && is_array( $v ) ) {
				$next['custom'] = array_merge( $next['custom'], $v );
			} else {
				$next[ (string) $k ] = $v;
			}
		}
		return new self( $next );
	}
}
