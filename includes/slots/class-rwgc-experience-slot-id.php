<?php
/**
 * Experience Slot ID generation and validation.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable IDs shaped like `rw_homepage_hero_8ds91`.
 */
final class RWGC_Experience_Slot_Id {

	/**
	 * @param mixed $id Candidate ID.
	 * @return bool
	 */
	public static function is_valid( $id ) {
		$id = (string) $id;
		return (bool) preg_match( '/^rw_[a-z0-9]+(?:_[a-z0-9]+)*_[a-z0-9]{5}$/', $id );
	}

	/**
	 * Generate a new slot ID from a human name.
	 *
	 * @param string $name Human name.
	 * @return string
	 */
	public static function generate( $name = '' ) {
		$slug = self::slugify( $name );
		if ( '' === $slug ) {
			$slug = 'slot';
		}
		return 'rw_' . $slug . '_' . self::random_suffix();
	}

	/**
	 * @param string $name Name.
	 * @return string
	 */
	public static function slugify( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = preg_replace( '/[^a-z0-9]+/', '_', $name );
		$name = trim( (string) $name, '_' );
		if ( strlen( $name ) > 40 ) {
			$name = substr( $name, 0, 40 );
			$name = rtrim( $name, '_' );
		}
		return $name;
	}

	/**
	 * @return string
	 */
	public static function random_suffix() {
		$alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$out      = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$out .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
		}
		return $out;
	}
}
