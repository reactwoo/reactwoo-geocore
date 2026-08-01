<?php
/**
 * Suite duplicate-variant must copy Elementor document meta.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$store = $GLOBALS['rwgc_test_post_meta'] ?? array();
		$pid   = (int) $post_id;
		if ( ! isset( $store[ $pid ] ) ) {
			return $single ? '' : array();
		}
		if ( '' === $key ) {
			return $store[ $pid ];
		}
		if ( ! array_key_exists( $key, $store[ $pid ] ) ) {
			return $single ? '' : array();
		}
		$value = $store[ $pid ][ $key ];
		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$pid = (int) $post_id;
		if ( ! isset( $GLOBALS['rwgc_test_post_meta'] ) || ! is_array( $GLOBALS['rwgc_test_post_meta'] ) ) {
			$GLOBALS['rwgc_test_post_meta'] = array();
		}
		if ( ! isset( $GLOBALS['rwgc_test_post_meta'][ $pid ] ) ) {
			$GLOBALS['rwgc_test_post_meta'][ $pid ] = array();
		}
		$GLOBALS['rwgc_test_post_meta'][ $pid ][ (string) $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	function delete_post_meta( $post_id, $key ) {
		$pid = (int) $post_id;
		if ( isset( $GLOBALS['rwgc_test_post_meta'][ $pid ][ $key ] ) ) {
			unset( $GLOBALS['rwgc_test_post_meta'][ $pid ][ $key ] );
		}
		return true;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-variant-manager.php';

/**
 * @covers RWGC_Variant_Manager::copy_elementor_document_meta
 */
final class RWGCVariantManagerElementorCopyTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_post_meta'] = array();
	}

	public function test_non_elementor_source_is_noop(): void {
		$copied = RWGC_Variant_Manager::copy_elementor_document_meta( 10, 20 );
		$this->assertFalse( $copied );
		$this->assertArrayNotHasKey( 20, $GLOBALS['rwgc_test_post_meta'] );
	}

	public function test_copies_builder_document_and_strips_route_keys(): void {
		$elementor_data = '[{"id":"abc123","elType":"section","elements":[]}]';
		update_post_meta(
			10,
			'_elementor_edit_mode',
			'builder'
		);
		update_post_meta( 10, '_elementor_data', $elementor_data );
		update_post_meta( 10, '_elementor_version', '3.21.0' );
		update_post_meta( 10, '_elementor_template_type', 'wp-page' );
		update_post_meta( 10, '_wp_page_template', 'elementor_canvas' );
		update_post_meta(
			10,
			'_elementor_page_settings',
			array(
				'background_color'      => '#fff',
				'rwgc_route_enabled'    => 'yes',
				'rwgc_route_role'       => 'master',
				'rwgc_route_country_iso2' => 'US',
				'rwgc_portable_geo_targeting' => '{"enabled":true}',
			)
		);
		// Stale CSS on destination must be cleared.
		update_post_meta( 20, '_elementor_css', array( 'status' => 'file', 'time' => 1 ) );

		$copied = RWGC_Variant_Manager::copy_elementor_document_meta( 10, 20 );
		$this->assertTrue( $copied );

		$this->assertSame( 'builder', get_post_meta( 20, '_elementor_edit_mode', true ) );
		$this->assertSame( $elementor_data, get_post_meta( 20, '_elementor_data', true ) );
		$this->assertSame( '3.21.0', get_post_meta( 20, '_elementor_version', true ) );
		$this->assertSame( 'wp-page', get_post_meta( 20, '_elementor_template_type', true ) );
		$this->assertSame( 'elementor_canvas', get_post_meta( 20, '_wp_page_template', true ) );

		$settings = get_post_meta( 20, '_elementor_page_settings', true );
		$this->assertIsArray( $settings );
		$this->assertSame( '#fff', $settings['background_color'] );
		$this->assertSame( '{"enabled":true}', $settings['rwgc_portable_geo_targeting'] );
		$this->assertArrayNotHasKey( 'rwgc_route_enabled', $settings );
		$this->assertArrayNotHasKey( 'rwgc_route_role', $settings );
		$this->assertArrayNotHasKey( 'rwgc_route_country_iso2', $settings );
		$this->assertArrayNotHasKey( '_elementor_css', $GLOBALS['rwgc_test_post_meta'][20] );
	}

	public function test_has_elementor_data_without_edit_mode_still_copies(): void {
		update_post_meta( 11, '_elementor_data', '[{"id":"x","elType":"section","elements":[]}]' );

		$copied = RWGC_Variant_Manager::copy_elementor_document_meta( 11, 21 );
		$this->assertTrue( $copied );
		$this->assertSame( 'builder', get_post_meta( 21, '_elementor_edit_mode', true ) );
		$this->assertNotSame( '', get_post_meta( 21, '_elementor_data', true ) );
	}

	public function test_rejects_invalid_ids(): void {
		$this->assertFalse( RWGC_Variant_Manager::copy_elementor_document_meta( 0, 5 ) );
		$this->assertFalse( RWGC_Variant_Manager::copy_elementor_document_meta( 5, 5 ) );
	}
}
