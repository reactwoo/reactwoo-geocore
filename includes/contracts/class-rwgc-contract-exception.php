<?php
/**
 * Contract validation errors.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown when a platform contract cannot be constructed from input.
 */
class RWGC_Contract_Exception extends Exception {

	/**
	 * @param string $message Message.
	 * @param int    $code    Code.
	 */
	public function __construct( $message = '', $code = 0 ) {
		parent::__construct( (string) $message, (int) $code );
	}
}
