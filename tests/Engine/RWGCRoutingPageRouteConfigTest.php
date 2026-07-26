<?php
/**
 * Regression: Elementor empty SWITCHER must not disable Suite/post-meta routing.
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

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-routing.php';

/**
 * @covers RWGC_Routing::get_page_route_config
 */
final class RWGCRoutingPageRouteConfigTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_post_meta'] = array();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_post_meta'] );
	}

	public function test_elementor_empty_switcher_defers_to_suite_post_meta_enabled(): void {
		$page_id = 42;
		$GLOBALS['rwgc_test_post_meta'][ $page_id ] = array(
			RWGC_Routing::META_ENABLED         => '1',
			RWGC_Routing::META_ROLE            => 'master',
			RWGC_Routing::META_DEFAULT_PAGE_ID => '0',
			RWGC_Routing::META_COUNTRY_ISO2    => '',
			RWGC_Routing::META_COUNTRY_PAGE_ID => '0',
			RWGC_Routing::META_MASTER_PAGE_ID  => '0',
			// Elementor SWITCHER off/default persists as empty string on Update.
			'_elementor_page_settings'         => array(
				'rwgc_route_enabled' => '',
			),
		);

		$config = RWGC_Routing::get_page_route_config( $page_id );

		$this->assertTrue(
			! empty( $config['enabled'] ),
			'Suite-enabled routing must remain enabled when Elementor switcher is empty/default'
		);
		$this->assertSame( 'master', $config['role'] );
	}

	public function test_elementor_yes_enables_routing_without_post_meta(): void {
		$page_id = 77;
		$GLOBALS['rwgc_test_post_meta'][ $page_id ] = array(
			RWGC_Routing::META_ENABLED         => '0',
			RWGC_Routing::META_ROLE            => 'master',
			RWGC_Routing::META_DEFAULT_PAGE_ID => '0',
			RWGC_Routing::META_COUNTRY_ISO2    => '',
			RWGC_Routing::META_COUNTRY_PAGE_ID => '0',
			RWGC_Routing::META_MASTER_PAGE_ID  => '0',
			'_elementor_page_settings'         => array(
				'rwgc_route_enabled' => 'yes',
				'rwgc_route_role'    => 'master',
			),
		);

		$config = RWGC_Routing::get_page_route_config( $page_id );

		$this->assertTrue( ! empty( $config['enabled'] ) );
		$this->assertSame( 'master', $config['role'] );
	}

	public function test_post_meta_disabled_and_elementor_empty_stays_disabled(): void {
		$page_id = 88;
		$GLOBALS['rwgc_test_post_meta'][ $page_id ] = array(
			RWGC_Routing::META_ENABLED         => '0',
			RWGC_Routing::META_ROLE            => 'master',
			RWGC_Routing::META_DEFAULT_PAGE_ID => '0',
			RWGC_Routing::META_COUNTRY_ISO2    => '',
			RWGC_Routing::META_COUNTRY_PAGE_ID => '0',
			RWGC_Routing::META_MASTER_PAGE_ID  => '0',
			'_elementor_page_settings'         => array(
				'rwgc_route_enabled' => '',
			),
		);

		$config = RWGC_Routing::get_page_route_config( $page_id );

		$this->assertFalse( ! empty( $config['enabled'] ) );
	}
}
