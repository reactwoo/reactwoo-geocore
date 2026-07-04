<?php
/**
 * Regression tests for the admin visibility rule tester.
 *
 * @package ReactWooGeoCore
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WP_Post', false ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile
	class WP_Post {
		/** @var int */
		public $ID;
		/** @var string */
		public $post_type;
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

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) { // phpcs:ignore WordPress.NamingConventions
		return $GLOBALS['rwgc_test_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	function get_post( $post_id ) { // phpcs:ignore WordPress.NamingConventions
		return $GLOBALS['rwgc_test_posts'][ (int) $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key Meta key.
	 * @param bool   $single Single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) { // phpcs:ignore WordPress.NamingConventions
		unset( $single );
		return $GLOBALS['rwgc_test_post_meta'][ (int) $post_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function get_the_title( $post_id = 0 ) { // phpcs:ignore WordPress.NamingConventions
		$post = get_post( $post_id );
		return $post instanceof WP_Post ? (string) $post->post_title : '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * @param WP_Post $post Post.
	 * @return string
	 */
	function get_permalink( $post ) { // phpcs:ignore WordPress.NamingConventions
		return $post instanceof WP_Post ? 'https://example.test/' . $post->post_type . '/' . $post->ID : '';
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url URL.
	 * @param int    $component URL component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) { // phpcs:ignore WordPress.NamingConventions
		return parse_url( $url, $component );
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/functions-rwgc.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-rule-context-compatibility.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-visibility-rule-tester.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-surface-settings.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-elementor-assignment-discovery.php';

/**
 * @covers RWGC_Visibility_Rule_Tester
 * @covers RWGC_Elementor_Assignment_Discovery
 */
final class RWGCVisibilityRuleTesterRegressionTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_options']   = array();
		$GLOBALS['rwgc_test_posts']     = array();
		$GLOBALS['rwgc_test_post_meta'] = array();
	}

	private function post( $id, $type, $title = '' ): WP_Post {
		$post             = new WP_Post();
		$post->ID         = (int) $id;
		$post->post_type  = (string) $type;
		$post->post_title = (string) $title;
		return $post;
	}

	public function test_page_type_for_normal_pages_and_posts_matches_rule_context_slugs(): void {
		$page = $this->post( 11, 'page', 'Landing' );
		$post = $this->post( 12, 'post', 'News' );

		$this->assertSame( 'page', RWGC_Visibility_Rule_Tester::page_type_for_post_public( $page ) );
		$this->assertSame( 'post', RWGC_Visibility_Rule_Tester::page_type_for_post_public( $post ) );
	}

	public function test_elementor_inline_portable_assignment_is_discovered_without_library_rule_id(): void {
		$content = $this->post( 44, 'page', 'Landing' );
		$inline  = wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show_if',
				'rules'   => array(),
			)
		);
		$this->assertIsString( $inline );

		$GLOBALS['rwgc_test_posts'][44] = $content;
		$GLOBALS['rwgc_test_post_meta'][44]['_elementor_data'] = wp_json_encode(
			array(
				array(
					'id'       => 'abc123',
					'elType'   => 'widget',
					'settings' => array(
						'_title'                         => 'Hero CTA',
						'rwgc_enable_visibility_rules'   => 'yes',
						'rwgc_portable_geo_targeting'    => $inline,
						'rwgc_visibility_rules_mode'     => 'hide_if',
					),
				),
			)
		);

		$result = RWGC_Elementor_Assignment_Discovery::get_assignments_for_content( 44, 'page' );

		$this->assertCount( 1, $result['assignments'] );
		$this->assertSame( 0, $result['assignments'][0]['rule_id'] );
		$this->assertSame( $inline, $result['assignments'][0]['portable_json'] );
		$this->assertSame( 'Hero CTA', $result['assignments'][0]['element_label'] );
		$this->assertSame( 'hide_if', $result['assignments'][0]['mode_internal'] );
	}
}
