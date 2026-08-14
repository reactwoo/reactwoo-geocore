<?php
/**
 * Composite: Cloud cache when connected, otherwise standalone licenses.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single facade target for feature code.
 */
final class RWGC_Composite_Entitlement_Provider implements RWGC_Entitlement_Provider_Interface {

	/** @var RWGC_Standalone_License_Provider */
	private $standalone;
	/** @var RWGC_Cloud_Entitlement_Provider */
	private $cloud;

	public function __construct( RWGC_Standalone_License_Provider $standalone, RWGC_Cloud_Entitlement_Provider $cloud ) {
		$this->standalone = $standalone;
		$this->cloud      = $cloud;
	}

	/**
	 * {@inheritdoc}
	 */
	public function allows( $key ) {
		if ( $this->cloud->is_active() ) {
			return $this->cloud->allows( $key );
		}
		return $this->standalone->allows( $key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function limit( $key ) {
		if ( $this->cloud->is_active() ) {
			return $this->cloud->limit( $key );
		}
		return $this->standalone->limit( $key );
	}

	/**
	 * {@inheritdoc}
	 */
	public function source() {
		return $this->cloud->is_active() ? 'cloud' : 'standalone';
	}

	/**
	 * {@inheritdoc}
	 */
	public function all() {
		if ( $this->cloud->is_active() ) {
			return $this->cloud->all();
		}
		return $this->standalone->all();
	}
}
