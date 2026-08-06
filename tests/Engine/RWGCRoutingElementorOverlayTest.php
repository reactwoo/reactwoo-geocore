<?php
/**
 * Regression: Elementor route overlays must not reclassify Suite-authored masters.
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$store = isset( $GLOBALS['rwgc_test_post_meta'] ) && is_array( $GLOBALS['rwgc_test_post_meta'] )
			? $GLOBALS['rwgc_test_post_meta']
			: array();
		$post_id = (int) $post_id;
		if ( ! isset( $store[ $post_id ] ) || ! is_array( $store[ $post_id ] ) ) {
			return $single ? '' : array();
		}
		if ( '' === $key ) {
			return $store[ $post_id ];
		}
		if ( ! array_key_exists( $key, $store[ $post_id ] ) ) {
			return $single ? '' : array();
		}
		$value = $store[ $post_id ][ $key ];
		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * @param mixed $post Post ID.
	 * @return object|null
	 */
	function get_post( $post ) {
		$id = (int) $post;
		if ( $id <= 0 ) {
			return null;
		}
		$obj              = new stdClass();
		$obj->ID          = $id;
		$obj->post_type   = 'page';
		$obj->post_status = 'publish';
		return $obj;
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	/**
	 * @param mixed $post Post ID or object.
	 * @return string
	 */
	function get_post_type( $post = null ) {
		if ( is_object( $post ) && isset( $post->post_type ) ) {
			return (string) $post->post_type;
		}
		return (int) $post > 0 ? 'page' : '';
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, int>
	 */
	function get_posts( $args = array() ) {
		unset( $args );
		return array();
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-routing.php';

/**
 * @covers RWGC_Routing::get_page_route_config
 */
final class RWGCRoutingElementorOverlayTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_post_meta'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_post_meta'] );
	}

	public function test_suite_master_keeps_role_when_elementor_says_variant(): void {
		$page_id = 501;
		$GLOBALS['rwgc_test_post_meta'][ $page_id ] = array(
			RWGC_Routing::META_ENABLED         => '1',
			RWGC_Routing::META_ROLE            => 'master',
			RWGC_Routing::META_DEFAULT_PAGE_ID => '0',
			RWGC_Routing::META_COUNTRY_ISO2    => '',
			RWGC_Routing::META_COUNTRY_PAGE_ID => '0',
			RWGC_Routing::META_MASTER_PAGE_ID  => '0',
			'_elementor_page_settings'         => array(
				'rwgc_route_enabled'         => 'yes',
				'rwgc_route_role'            => 'variant',
				'rwgc_route_master_page_id'  => '999',
				'rwgc_route_country_iso2'    => 'DE',
			),
		);

		$config = RWGC_Routing::get_page_route_config( $page_id );

		$this->assertTrue( ! empty( $config['enabled'] ) );
		$this->assertSame( 'master', $config['role'] );
		$this->assertSame( 0, (int) $config['master_page_id'] );
		$this->assertSame( '', (string) $config['country_iso2'] );
	}

	public function test_suite_master_keeps_inline_country_when_elementor_country_differs(): void {
		$page_id = 502;
		$GLOBALS['rwgc_test_post_meta'][ $page_id ] = array(
			RWGC_Routing::META_ENABLED         => '1',
			RWGC_Routing::META_ROLE            => 'master',
			RWGC_Routing::META_DEFAULT_PAGE_ID => '0',
			RWGC_Routing::META_COUNTRY_ISO2    => 'US',
			RWGC_Routing::META_COUNTRY_PAGE_ID => '456',
			RWGC_Routing::META_MASTER_PAGE_ID  => '0',
			'_elementor_page_settings'         => array(
				'rwgc_route_enabled'      => 'yes',
				'rwgc_route_role'         => 'master',
				'rwgc_route_country_iso2' => 'DE',
			),
		);

		$config = RWGC_Routing::get_page_route_config( $page_id );

		$this->assertTrue( ! empty( $config['enabled'] ) );
		$this->assertSame( 'master', $config['role'] );
		$this->assertSame( 'US', (string) $config['country_iso2'] );
		$this->assertSame( 456, (int) $config['country_page_id'] );
	}

	public function test_elementor_only_still_overlays_when_post_meta_not_enabled(): void {
		$page_id = 503;
		$GLOBALS['rwgc_test_post_meta'][ $page_id ] = array(
			RWGC_Routing::META_ENABLED         => '0',
			RWGC_Routing::META_ROLE            => 'master',
			RWGC_Routing::META_DEFAULT_PAGE_ID => '0',
			RWGC_Routing::META_COUNTRY_ISO2    => '',
			RWGC_Routing::META_COUNTRY_PAGE_ID => '0',
			RWGC_Routing::META_MASTER_PAGE_ID  => '0',
			'_elementor_page_settings'         => array(
				'rwgc_route_enabled'        => 'yes',
				'rwgc_route_role'           => 'variant',
				'rwgc_route_master_page_id' => '100',
				'rwgc_route_country_iso2'   => 'FR',
			),
		);

		$config = RWGC_Routing::get_page_route_config( $page_id );

		$this->assertTrue( ! empty( $config['enabled'] ) );
		$this->assertSame( 'variant', $config['role'] );
		$this->assertSame( 100, (int) $config['master_page_id'] );
		$this->assertSame( 'FR', (string) $config['country_iso2'] );
	}
}
