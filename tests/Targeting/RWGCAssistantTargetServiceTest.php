<?php
/**
 * Assistant target service tests.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers RWGC_Assistant_Target_Service
 */
class RWGCAssistantTargetServiceTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$path = dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-assistant-target-service.php';
		require_once $path;
	}

	public function test_format_popup_row_shape(): void {
		if ( ! class_exists( 'WP_Post', false ) ) {
			// phpcs:ignore Generic.Files.OneObjectStructurePerFile
			class WP_Post {
				/** @var int */
				public $ID;
				/** @var string */
				public $post_status;
				/** @var string */
				public $post_title;
			}
		}
		if ( ! function_exists( '__' ) ) {
			/**
			 * @param string $text Text.
			 * @return string
			 */
			function __( $text ) { // phpcs:ignore WordPress.NamingConventions
				return $text;
			}
		}
		if ( ! function_exists( 'get_the_title' ) ) {
			/**
			 * @param mixed $post Post.
			 * @return string
			 */
			function get_the_title( $post = 0 ) { // phpcs:ignore WordPress.NamingConventions
				return is_object( $post ) && isset( $post->post_title ) ? (string) $post->post_title : '';
			}
		}
		if ( ! function_exists( 'get_post_modified_time' ) ) {
			/**
			 * @param string $d Format.
			 * @param bool   $gmt GMT.
			 * @param mixed  $post Post.
			 * @param bool   $translate Translate.
			 * @return string
			 */
			function get_post_modified_time( $d, $gmt, $post, $translate ) { // phpcs:ignore WordPress.NamingConventions
				unset( $d, $gmt, $post, $translate );
				return '2026-06-01 12:00';
			}
		}
		if ( ! function_exists( 'admin_url' ) ) {
			/**
			 * @param string $path Path.
			 * @return string
			 */
			function admin_url( $path = '' ) { // phpcs:ignore WordPress.NamingConventions
				return 'http://example.test/wp-admin/' . ltrim( $path, '/' );
			}
		}

		$post              = new WP_Post();
		$post->ID          = 42;
		$post->post_status = 'draft';
		$post->post_title  = 'Popup America';

		$row = RWGC_Assistant_Target_Service::format_popup_row( $post );

		$this->assertSame( 42, $row['id'] );
		$this->assertSame( 'Popup America', $row['label'] );
		$this->assertSame( 'draft', $row['status'] );
		$this->assertStringContainsString( 'post.php?post=42', $row['edit_url'] );
	}
}
