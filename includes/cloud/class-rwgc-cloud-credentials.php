<?php
/**
 * Encrypted Cloud site credentials.
 *
 * @package ReactWoo_Geo_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores revocable site_id + site_secret. Never logs the secret.
 */
final class RWGC_Cloud_Credentials {

	const OPTION = 'rwgc_cloud_credentials';

	/**
	 * @return array{site_id: string, site_secret: string, api_base: string}|null
	 */
	public static function get() {
		$raw = get_option( self::OPTION, null );
		if ( ! is_array( $raw ) || empty( $raw['site_id'] ) || empty( $raw['cipher'] ) ) {
			return null;
		}
		$secret = self::decrypt( (string) $raw['cipher'] );
		if ( '' === $secret ) {
			return null;
		}
		return array(
			'site_id'     => (string) $raw['site_id'],
			'site_secret' => $secret,
			'api_base'    => isset( $raw['api_base'] ) ? (string) $raw['api_base'] : RWGC_Cloud_Config::api_base(),
		);
	}

	/**
	 * @param string $site_id Site ID.
	 * @param string $site_secret Secret.
	 * @param string $api_base API base.
	 * @return bool
	 */
	public static function store( $site_id, $site_secret, $api_base = '' ) {
		$site_id     = trim( (string) $site_id );
		$site_secret = trim( (string) $site_secret );
		if ( '' === $site_id || '' === $site_secret ) {
			return false;
		}
		$cipher = self::encrypt( $site_secret );
		if ( '' === $cipher ) {
			return false;
		}
		$row = array(
			'site_id'    => $site_id,
			'cipher'     => $cipher,
			'api_base'   => '' !== $api_base ? untrailingslashit( (string) $api_base ) : RWGC_Cloud_Config::api_base(),
			'updated_at' => gmdate( 'c' ),
		);
		update_option( self::OPTION, $row, false );
		return true;
	}

	/**
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * @return bool
	 */
	public static function has() {
		return null !== self::get();
	}

	/**
	 * @param string $plain Plaintext.
	 * @return string
	 */
	private static function encrypt( $plain ) {
		$key = self::key();
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$iv = function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
		if ( false === $iv || 16 !== strlen( $iv ) ) {
			return '';
		}
		$enc = openssl_encrypt( (string) $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $enc ) {
			return '';
		}
		$mac = hash_hmac( 'sha256', $iv . $enc, $key, true );
		return 'v2.' . base64_encode( $iv . $enc . $mac ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @param string $cipher Ciphertext.
	 * @return string
	 */
	private static function decrypt( $cipher ) {
		$cipher = (string) $cipher;
		$key    = self::key();
		if ( 0 === strpos( $cipher, 'v2.' ) ) {
			$raw = base64_decode( substr( $cipher, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $raw || strlen( $raw ) < 48 ) {
				return '';
			}
			$mac   = substr( $raw, -32 );
			$ivenc = substr( $raw, 0, -32 );
			$calc  = hash_hmac( 'sha256', $ivenc, $key, true );
			if ( ! hash_equals( $mac, $calc ) ) {
				return '';
			}
			$iv  = substr( $ivenc, 0, 16 );
			$enc = substr( $ivenc, 16 );
			$plain = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			return is_string( $plain ) ? $plain : '';
		}

		$raw = base64_decode( $cipher, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || '' === $raw ) {
			return '';
		}
		if ( function_exists( 'openssl_decrypt' ) && strlen( $raw ) > 16 ) {
			$iv  = substr( $raw, 0, 16 );
			$enc = substr( $raw, 16 );
			$plain = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			return is_string( $plain ) ? $plain : '';
		}
		return '';
	}

	/**
	 * @return string Binary key.
	 */
	private static function key() {
		$material = '';
		if ( function_exists( 'wp_salt' ) ) {
			$material = (string) wp_salt( 'auth' ) . (string) wp_salt( 'secure_auth' );
		}
		if ( '' === $material ) {
			$material = 'rwgc-cloud-fallback-key';
		}
		return hash( 'sha256', $material, true );
	}
}
