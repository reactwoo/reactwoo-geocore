<?php

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param array<mixed>|mixed $args     Args.
	 * @param array<mixed>       $defaults Defaults.
	 * @return array<mixed>
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
		return isset( $GLOBALS['rwgc_test_visitor_country'] ) ? (string) $GLOBALS['rwgc_test_visitor_country'] : '';
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

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single flag.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$meta = isset( $GLOBALS['rwgc_test_post_meta'][ $post_id ] ) ? $GLOBALS['rwgc_test_post_meta'][ $post_id ] : array();
		if ( '' === $key ) {
			return $meta;
		}
		if ( array_key_exists( $key, $meta ) ) {
			return $meta[ $key ];
		}
		return $single ? '' : array();
	}
}

$base = dirname( __DIR__ ) . '/includes/';
require_once $base . 'targeting/interface-rwgc-target-provider.php';
require_once $base . 'targeting/class-rwgc-context-snapshot.php';
require_once $base . 'targeting/class-rwgc-target-operators.php';
require_once $base . 'targeting/class-rwgc-target-registry.php';
require_once $base . 'targeting/class-rwgc-targeting-rule-set-schema.php';
require_once $base . 'targeting/class-rwgc-rule-evaluator.php';
require_once $base . 'targeting/class-rwgc-context-resolver.php';
require_once $base . 'class-rwgc-gutenberg.php';
require_once $base . 'class-rwgc-elementor.php';

/**
 * @covers RWGC_Gutenberg
 * @covers RWGC_Elementor
 */
final class RWGC_PortableTargetingFailClosedTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_visitor_country'] = 'US';
		$GLOBALS['rwgc_test_post_meta']       = array();
	}

	public function test_gutenberg_hides_when_portable_rules_are_invalid(): void {
		$html = RWGC_Gutenberg::render_geo_content_block(
			array(
				'usePortableTargeting' => true,
				'portableTargeting'    => '{not json',
				'showCountries'        => array(),
			),
			'Secret block'
		);

		$this->assertSame( '', $html );
	}

	public function test_gutenberg_hides_when_pro_only_rules_are_stripped(): void {
		$rules = array(
			'enabled' => true,
			'mode'    => 'show',
			'match'   => 'all',
			'rules'   => array(
				array(
					'id'         => 'rule_main',
					'match'      => 'all',
					'conditions' => array(
						array(
							'type'     => 'audience',
							'operator' => 'in',
							'value'    => array( 'vip_customers' ),
						),
					),
				),
			),
		);

		$html = RWGC_Gutenberg::render_geo_content_block(
			array(
				'usePortableTargeting' => true,
				'portableTargeting'    => wp_json_encode( $rules ),
				'showCountries'        => array(),
			),
			'Secret block'
		);

		$this->assertSame( '', $html );
	}

	public function test_elementor_hides_when_portable_rules_are_invalid(): void {
		$GLOBALS['rwgc_test_post_meta'][123] = array(
			'_elementor_page_settings' => array(
				'egp_enable_geo_targeting'        => 'yes',
				'rwgc_use_portable_geo_targeting' => 'yes',
				'rwgc_portable_geo_targeting'     => '{not json',
				'egp_countries'                   => array(),
			),
		);

		$this->assertSame( '', RWGC_Elementor::filter_document_content( 'Secret document' ) );
	}
}
