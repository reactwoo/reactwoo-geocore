<?php
/**
 * CONTENT variant — trusted HTML / structured content payload.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders author-supplied content HTML.
 */
final class RWGC_Content_Variant extends RWGC_Abstract_Variant {

	/**
	 * Extract HTML string from payload.
	 *
	 * @return string
	 */
	public function html() {
		$payload = $this->payload();
		if ( isset( $payload['html'] ) && is_string( $payload['html'] ) ) {
			return $payload['html'];
		}
		if ( isset( $payload['content'] ) ) {
			if ( is_string( $payload['content'] ) ) {
				return $payload['content'];
			}
			if ( is_array( $payload['content'] ) && isset( $payload['content']['html'] ) && is_string( $payload['content']['html'] ) ) {
				return $payload['content']['html'];
			}
		}
		return '';
	}
}
