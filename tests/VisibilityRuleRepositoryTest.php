<?php
/**
 * PHPUnit coverage for visibility rule library persistence.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * @return string
	 */
	function wp_generate_password() {
		return 'generated';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * @param array<string, mixed> $data Post data.
	 * @param bool                 $wp_error Whether to return WP_Error.
	 * @return int
	 */
	function wp_insert_post( $data, $wp_error = false ) {
		unset( $wp_error );
		$GLOBALS['rwgc_test_post_inserts'][] = $data;
		return 456;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * @param array<string, mixed> $data Post data.
	 * @param bool                 $wp_error Whether to return WP_Error.
	 * @return int
	 */
	function wp_update_post( $data, $wp_error = false ) {
		unset( $wp_error );
		$GLOBALS['rwgc_test_post_updates'][] = $data;
		return isset( $data['ID'] ) ? (int) $data['ID'] : 0;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['rwgc_test_post_meta'][ (int) $post_id ][ (string) $key ] = $value;
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-cpt.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-repository.php';

/**
 * @covers RWGC_Visibility_Rule_Repository
 */
class VisibilityRuleRepositoryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_post_inserts'] = array();
		$GLOBALS['rwgc_test_post_updates'] = array();
		$GLOBALS['rwgc_test_post_meta']    = array();
	}

	public function test_save_rejects_non_empty_json_that_sanitizes_to_no_rules() {
		$post_id      = 123;
		$existing_raw = '{"enabled":true,"mode":"show","match":"any","rules":[{"id":"old","match":"all","conditions":[{"type":"country","operator":"in","value":["GB"]}]}]}';
		$GLOBALS['rwgc_test_post_meta'][ $post_id ][ RWGC_Visibility_Rule_CPT::META_PORTABLE ] = $existing_raw;

		$pro_only_json = wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'campaign_rule',
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'campaign',
								'operator' => 'in',
								'value'    => array( 'spring_sale' ),
							),
						),
					),
				),
			)
		);

		$result = RWGC_Visibility_Rule_Repository::save( 'Existing rule', 'publish', $pro_only_json, $post_id );

		$this->assertSame( 0, $result );
		$this->assertSame( array(), $GLOBALS['rwgc_test_post_updates'] );
		$this->assertSame( $existing_raw, $GLOBALS['rwgc_test_post_meta'][ $post_id ][ RWGC_Visibility_Rule_CPT::META_PORTABLE ] );
	}

	public function test_save_persists_valid_sanitized_rule_json() {
		$valid_json = wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'country_rule',
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'country',
								'operator' => 'in',
								'value'    => array( 'GB' ),
							),
						),
					),
				),
			)
		);

		$result = RWGC_Visibility_Rule_Repository::save( 'Valid rule', 'publish', $valid_json, 123 );

		$this->assertSame( 123, $result );
		$this->assertCount( 1, $GLOBALS['rwgc_test_post_updates'] );
		$this->assertNotEmpty( $GLOBALS['rwgc_test_post_meta'][ 123 ][ RWGC_Visibility_Rule_CPT::META_PORTABLE ] );
		$decoded = json_decode( $GLOBALS['rwgc_test_post_meta'][ 123 ][ RWGC_Visibility_Rule_CPT::META_PORTABLE ], true );
		$this->assertSame( 'country', $decoded['rules'][0]['conditions'][0]['type'] );
	}
}
