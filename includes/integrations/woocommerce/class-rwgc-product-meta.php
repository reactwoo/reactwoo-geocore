<?php
/**
 * Namespaced WooCommerce product meta for GeoCore product-level settings.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product meta accessors — weather facets remain on {@see RWGCM_Weather_Affinity::META_KEY}.
 */
class RWGC_Product_Meta {

	const META_WEATHER_TAGS      = '_geocore_product_weather_tags';
	const META_GEO_MODE          = '_geocore_product_geo_mode';
	const META_COUNTRIES         = '_geocore_product_countries';
	const META_RULE_IDS          = '_geocore_product_rule_ids';
	const META_BOOST_ENABLED     = '_geocore_product_boost_enabled';
	const META_VISIBILITY_MODE   = '_geocore_product_visibility_mode';

	const GEO_MODE_GLOBAL       = 'global';
	const GEO_MODE_HIDE_IN      = 'hide_in';
	const GEO_MODE_SHOW_ONLY_IN = 'show_only_in';

	const BOOST_INHERIT = 'inherit';
	const BOOST_YES     = 'yes';
	const BOOST_NO      = 'no';

	/**
	 * @param int $product_id Product ID.
	 * @return string global|hide_in|show_only_in
	 */
	public static function get_geo_mode( $product_id ) {
		$mode = sanitize_key( (string) get_post_meta( absint( $product_id ), self::META_GEO_MODE, true ) );
		if ( in_array( $mode, array( self::GEO_MODE_HIDE_IN, self::GEO_MODE_SHOW_ONLY_IN ), true ) ) {
			return $mode;
		}
		return self::GEO_MODE_GLOBAL;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	public static function get_countries( $product_id ) {
		return self::sanitize_countries( get_post_meta( absint( $product_id ), self::META_COUNTRIES, true ) );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public static function get_rule_ids( $product_id ) {
		$raw = get_post_meta( absint( $product_id ), self::META_RULE_IDS, true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array( $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string inherit|yes|no
	 */
	public static function get_boost_enabled( $product_id ) {
		$value = sanitize_key( (string) get_post_meta( absint( $product_id ), self::META_BOOST_ENABLED, true ) );
		if ( in_array( $value, array( self::BOOST_YES, self::BOOST_NO ), true ) ) {
			return $value;
		}
		return self::BOOST_INHERIT;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string show_if|hide_if
	 */
	public static function get_visibility_mode( $product_id ) {
		$mode = sanitize_key( (string) get_post_meta( absint( $product_id ), self::META_VISIBILITY_MODE, true ) );
		return 'hide_if' === $mode ? 'hide_if' : 'show_if';
	}

	/**
	 * Whether product-level geo targeting overrides are configured.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function has_geo_override( $product_id ) {
		$mode = self::get_geo_mode( $product_id );
		if ( self::GEO_MODE_GLOBAL !== $mode && ! empty( self::get_countries( $product_id ) ) ) {
			return true;
		}
		return ! empty( self::get_rule_ids( $product_id ) );
	}

	/**
	 * Map product meta into surface evaluator settings.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public static function to_surface_settings( $product_id ) {
		$settings = array();
		$mode     = self::get_geo_mode( $product_id );
		$countries = self::get_countries( $product_id );

		if ( self::GEO_MODE_HIDE_IN === $mode && ! empty( $countries ) ) {
			$settings['egp_enable_geo_targeting']     = 'yes';
			$settings['rwgc_country_visibility_mode'] = 'hide_if';
			$settings['egp_countries']              = $countries;
		} elseif ( self::GEO_MODE_SHOW_ONLY_IN === $mode && ! empty( $countries ) ) {
			$settings['egp_enable_geo_targeting']     = 'yes';
			$settings['rwgc_country_visibility_mode'] = 'show_if';
			$settings['egp_countries']              = $countries;
		}

		$rule_ids = self::get_rule_ids( $product_id );
		if ( ! empty( $rule_ids ) ) {
			$settings['rwgc_enable_visibility_rules']    = 'yes';
			$settings['rwgc_visibility_rules_mode']      = self::get_visibility_mode( $product_id );
			$settings['rwgc_visibility_rule_library']    = (string) $rule_ids[0];
			$settings['rwgc_applied_visibility_rule_id'] = (string) $rule_ids[0];
		}

		if ( class_exists( 'RWGC_Surface_Settings', false ) ) {
			return RWGC_Surface_Settings::normalize( $settings );
		}
		return $settings;
	}

	/**
	 * @param mixed $raw Raw meta or POST.
	 * @return string[]
	 */
	public static function sanitize_countries( $raw ) {
		$items = array();
		if ( is_array( $raw ) ) {
			$items = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$items   = is_array( $decoded ) ? $decoded : explode( ',', $raw );
		}
		$out = array();
		foreach ( $items as $item ) {
			$iso = strtoupper( substr( sanitize_text_field( (string) $item ), 0, 2 ) );
			if ( preg_match( '/^[A-Z]{2}$/', $iso ) ) {
				$out[ $iso ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $input      POST subset.
	 * @return void
	 */
	public static function save_from_request( $product_id, array $input ) {
		$product_id = absint( $product_id );
		if ( $product_id <= 0 ) {
			return;
		}

		$geo_mode = isset( $input['geo_mode'] ) ? sanitize_key( (string) $input['geo_mode'] ) : self::GEO_MODE_GLOBAL;
		if ( ! in_array( $geo_mode, array( self::GEO_MODE_GLOBAL, self::GEO_MODE_HIDE_IN, self::GEO_MODE_SHOW_ONLY_IN ), true ) ) {
			$geo_mode = self::GEO_MODE_GLOBAL;
		}
		update_post_meta( $product_id, self::META_GEO_MODE, $geo_mode );

		$countries = isset( $input['countries'] ) ? self::sanitize_countries( $input['countries'] ) : array();
		if ( empty( $countries ) ) {
			delete_post_meta( $product_id, self::META_COUNTRIES );
		} else {
			update_post_meta( $product_id, self::META_COUNTRIES, $countries );
		}

		$rule_ids = array();
		if ( isset( $input['rule_ids'] ) ) {
			$raw_rules = is_array( $input['rule_ids'] ) ? $input['rule_ids'] : array( $input['rule_ids'] );
			foreach ( $raw_rules as $rule_id ) {
				$rule_id = absint( $rule_id );
				if ( $rule_id > 0 ) {
					$rule_ids[] = $rule_id;
				}
			}
		} elseif ( isset( $input['rule_id'] ) ) {
			$rule_id = absint( $input['rule_id'] );
			if ( $rule_id > 0 ) {
				$rule_ids[] = $rule_id;
			}
		}
		$rule_ids = array_values( array_unique( $rule_ids ) );
		if ( empty( $rule_ids ) ) {
			delete_post_meta( $product_id, self::META_RULE_IDS );
		} else {
			update_post_meta( $product_id, self::META_RULE_IDS, $rule_ids );
		}

		$visibility_mode = isset( $input['visibility_mode'] ) ? sanitize_key( (string) $input['visibility_mode'] ) : 'show_if';
		update_post_meta( $product_id, self::META_VISIBILITY_MODE, 'hide_if' === $visibility_mode ? 'hide_if' : 'show_if' );

		$boost = isset( $input['boost_enabled'] ) ? sanitize_key( (string) $input['boost_enabled'] ) : self::BOOST_INHERIT;
		if ( ! in_array( $boost, array( self::BOOST_INHERIT, self::BOOST_YES, self::BOOST_NO ), true ) ) {
			$boost = self::BOOST_INHERIT;
		}
		if ( self::BOOST_INHERIT === $boost ) {
			delete_post_meta( $product_id, self::META_BOOST_ENABLED );
		} else {
			update_post_meta( $product_id, self::META_BOOST_ENABLED, $boost );
		}
	}
}
