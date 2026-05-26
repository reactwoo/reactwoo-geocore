<?php
/**
 * Regression tests for portable targeting integrations.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-context-snapshot.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-rule-evaluator.php';

if ( ! class_exists( 'RWGC_Context_Resolver', false ) ) {
	/**
	 * Minimal resolver for integration tests.
	 */
	class RWGC_Context_Resolver {
		/**
		 * @return RWGC_Context_Snapshot
		 */
		public static function resolve_current() {
			return new RWGC_Context_Snapshot(
				array(
					'country' => isset( $GLOBALS['rwgc_test_visitor_country'] ) ? (string) $GLOBALS['rwgc_test_visitor_country'] : '',
				)
			);
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/class-rwgc-gutenberg.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-elementor.php';

/**
 * @covers RWGC_Gutenberg
 * @covers RWGC_Elementor
 */
class PortableTargetingIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$_GET                                   = array();
		$GLOBALS['rwgc_test_filters']           = array();
		$GLOBALS['rwgc_test_is_admin']          = false;
		$GLOBALS['rwgc_test_doing_ajax']        = false;
		$GLOBALS['rwgc_test_is_singular']       = true;
		$GLOBALS['rwgc_test_queried_object_id'] = 123;
		$GLOBALS['rwgc_test_visitor_country']   = 'US';
		$GLOBALS['rwgc_test_post_meta']         = array();
		$GLOBALS['rwgc_test_builder_edit_request'] = false;
	}

	public function test_gutenberg_fails_closed_when_portable_enabled_but_rules_sanitize_away() {
		$content = RWGC_Gutenberg::render_geo_content_block(
			array(
				'usePortableTargeting' => true,
				'portableTargeting'    => wp_json_encode(
					array(
						'enabled' => true,
						'mode'    => 'show',
						'rules'   => array(
							array(
								'id'         => 'pro_rule',
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
				'showCountries'        => array( 'US' ),
			),
			'secret'
		);

		$this->assertSame( '', $content );
	}

	public function test_gutenberg_honors_disabled_portable_toggle_even_when_stale_json_exists() {
		$content = RWGC_Gutenberg::render_geo_content_block(
			array(
				'usePortableTargeting' => false,
				'portableTargeting'    => wp_json_encode(
					array(
						'enabled' => true,
						'mode'    => 'show',
						'rules'   => array(
							array(
								'id'         => 'match_all',
								'conditions' => array(),
							),
						),
					)
				),
				'showCountries'        => array( 'GB' ),
			),
			'secret'
		);

		$this->assertSame( '', $content );
	}

	public function test_elementor_preview_parameter_does_not_bypass_geo_rules_for_visitors() {
		$_GET['elementor-preview'] = '123';
		$GLOBALS['rwgc_test_post_meta']['_elementor_page_settings'] = array(
			'egp_enable_geo_targeting' => 'yes',
			'rwgc_geo_mode'            => 'show',
			'egp_countries'            => array( 'GB' ),
		);

		$this->assertSame( '', RWGC_Elementor::filter_document_content( 'secret' ) );
	}

	public function test_elementor_fails_closed_when_portable_enabled_but_json_is_unusable() {
		$GLOBALS['rwgc_test_post_meta']['_elementor_page_settings'] = array(
			'egp_enable_geo_targeting'          => 'yes',
			'rwgc_use_portable_geo_targeting'   => 'yes',
			'rwgc_portable_geo_targeting'       => '{invalid json',
			'egp_countries'                     => array( 'US' ),
		);

		$this->assertSame( '', RWGC_Elementor::filter_document_content( 'secret' ) );
	}
}
