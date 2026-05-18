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
		return 'US';
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
	 * @param bool   $single  Single.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $post_id, $single );
		if ( '_elementor_page_settings' === $key ) {
			return isset( $GLOBALS['rwgc_test_elementor_settings'] ) ? $GLOBALS['rwgc_test_elementor_settings'] : array();
		}
		return '';
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-context-snapshot.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/interface-rwgc-target-provider.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-target-registry.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-context-resolver.php';
require_once dirname( __DIR__, 2 ) . '/includes/targeting/class-rwgc-rule-evaluator.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-gutenberg.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-elementor.php';

/**
 * @covers RWGC_Gutenberg
 * @covers RWGC_Elementor
 */
final class RWGC_PortableTargetingRenderTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_elementor_settings'] );
		RWGC_Context_Resolver::reset_cache();
		RWGC_Rule_Evaluator::reset_resolver_cache();
		parent::tearDown();
	}

	public function test_gutenberg_portable_rules_that_sanitize_to_empty_fail_closed(): void {
		$portable = wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show',
				'rules'   => array(
					array(
						'id'         => 'audience_rule',
						'conditions' => array(
							array(
								'type'     => 'audience',
								'operator' => 'in',
								'value'    => array( 'vip' ),
							),
						),
					),
				),
			)
		);

		$this->assertSame(
			'',
			RWGC_Gutenberg::render_geo_content_block(
				array(
					'usePortableTargeting' => true,
					'portableTargeting'    => $portable,
					'showCountries'        => array(),
				),
				'Private offer'
			)
		);
	}

	public function test_elementor_portable_rules_that_sanitize_to_empty_fail_closed(): void {
		$GLOBALS['rwgc_test_elementor_settings'] = array(
			'egp_enable_geo_targeting'        => 'yes',
			'rwgc_use_portable_geo_targeting' => 'yes',
			'rwgc_portable_geo_targeting'     => wp_json_encode(
				array(
					'enabled' => true,
					'mode'    => 'show',
					'rules'   => array(
						array(
							'id'         => 'audience_rule',
							'conditions' => array(
								array(
									'type'     => 'audience',
									'operator' => 'in',
									'value'    => array( 'vip' ),
								),
							),
						),
					),
				)
			),
			'egp_countries'                   => array(),
		);

		$this->assertSame( '', RWGC_Elementor::filter_document_content( 'Private offer' ) );
	}

	public function test_gutenberg_legacy_country_targeting_still_renders_without_portable_rules(): void {
		$this->assertSame(
			'<div class="rwgc-geo-content">Public offer</div>',
			RWGC_Gutenberg::render_geo_content_block(
				array(
					'showCountries' => array( 'US' ),
				),
				'Public offer'
			)
		);
	}
}
