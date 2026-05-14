<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param mixed $args     Args.
	 * @param array $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, is_array( $args ) ? $args : array() );
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	/**
	 * @param string $content Content.
	 * @return string
	 */
	function do_shortcode( $content ) {
		return $content;
	}
}

if ( ! function_exists( 'rwgc_get_visitor_country' ) ) {
	/**
	 * @return string
	 */
	function rwgc_get_visitor_country() {
		return isset( $GLOBALS['rwgc_test_visitor_country'] ) ? (string) $GLOBALS['rwgc_test_visitor_country'] : 'US';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return false;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * @return bool
	 */
	function wp_doing_ajax() {
		return false;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * @return bool
	 */
	function is_singular() {
		return true;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * @return int
	 */
	function get_queried_object_id() {
		return 123;
	}
}

if ( ! function_exists( 'rwgc_is_builder_edit_request' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function rwgc_is_builder_edit_request( $post_id ) {
		unset( $post_id );
		return false;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single flag.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $post_id, $single );
		return isset( $GLOBALS['rwgc_test_post_meta'][ $key ] ) ? $GLOBALS['rwgc_test_post_meta'][ $key ] : null;
	}
}

$base = dirname( __DIR__ ) . '/includes/';
require_once $base . 'targeting/class-rwgc-target-operators.php';
require_once $base . 'targeting/class-rwgc-targeting-rule-set-schema.php';
require_once $base . 'targeting/class-rwgc-context-snapshot.php';
require_once $base . 'targeting/class-rwgc-rule-evaluator.php';
require_once $base . 'targeting/class-rwgc-context-resolver.php';
require_once $base . 'class-rwgc-gutenberg.php';
require_once $base . 'class-rwgc-elementor.php';

/**
 * @covers RWGC_Gutenberg
 * @covers RWGC_Elementor
 */
final class PortableTargetingRenderTest extends TestCase {

	private function stripped_pro_only_rules_json(): string {
		return wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show',
				'rules'   => array(
					array(
						'id'         => 'rule_main',
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'campaign',
								'operator' => 'in',
								'value'    => array( 'spring-sale' ),
							),
						),
					),
				),
			)
		);
	}

	public function test_geo_content_block_hides_when_non_empty_portable_rules_sanitize_away(): void {
		$html = RWGC_Gutenberg::render_geo_content_block(
			array(
				'portableTargeting' => $this->stripped_pro_only_rules_json(),
			),
			'<p>private launch</p>'
		);

		$this->assertSame( '', $html );
	}

	public function test_geo_content_block_keeps_legacy_fallback_when_portable_rules_are_empty(): void {
		$html = RWGC_Gutenberg::render_geo_content_block(
			array(
				'portableTargeting' => '',
			),
			'<p>public fallback</p>'
		);

		$this->assertSame( '<div class="rwgc-geo-content"><p>public fallback</p></div>', $html );
	}

	public function test_elementor_document_hides_when_non_empty_portable_rules_sanitize_away(): void {
		$GLOBALS['rwgc_test_post_meta'] = array(
			'_elementor_page_settings' => array(
				'egp_enable_geo_targeting'          => 'yes',
				'rwgc_use_portable_geo_targeting'   => 'yes',
				'rwgc_portable_geo_targeting'       => $this->stripped_pro_only_rules_json(),
				'egp_countries'                    => array(),
			),
		);

		$this->assertSame( '', RWGC_Elementor::filter_document_content( '<p>private launch</p>' ) );
	}

	public function test_elementor_document_keeps_legacy_fallback_when_portable_rules_are_empty(): void {
		$GLOBALS['rwgc_test_post_meta'] = array(
			'_elementor_page_settings' => array(
				'egp_enable_geo_targeting'          => 'yes',
				'rwgc_use_portable_geo_targeting'   => 'yes',
				'rwgc_portable_geo_targeting'       => '',
				'egp_countries'                    => array(),
			),
		);

		$this->assertSame( '<p>public fallback</p>', RWGC_Elementor::filter_document_content( '<p>public fallback</p>' ) );
	}
}
