<?php
/**
 * Build typed Variant objects from contracts / arrays.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory.
 */
final class RWGC_Variant_Factory {

	/**
	 * @param RWGC_Contract_Variant $contract Contract.
	 * @return RWGC_Variant_Interface|null
	 */
	public static function from_contract( RWGC_Contract_Variant $contract ) {
		switch ( $contract->type() ) {
			case RWGC_Contract_Variant::TYPE_DEFAULT:
				return new RWGC_Default_Variant( $contract );
			case RWGC_Contract_Variant::TYPE_CONTENT:
				return new RWGC_Content_Variant( $contract );
			case RWGC_Contract_Variant::TYPE_REACTWOO_COMPONENT:
				return new RWGC_Component_Variant( $contract );
			case RWGC_Contract_Variant::TYPE_NATIVE_REFERENCE:
				return new RWGC_Native_Reference_Variant( $contract );
			default:
				return null;
		}
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return RWGC_Variant_Interface|null
	 */
	public static function from_array( array $data ) {
		try {
			$contract = RWGC_Contract_Variant::from_array( $data );
			return self::from_contract( $contract );
		} catch ( RWGC_Contract_Exception $e ) {
			return null;
		}
	}
}
