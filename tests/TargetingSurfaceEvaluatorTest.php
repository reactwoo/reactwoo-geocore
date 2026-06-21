<?php
/**
 * PHPUnit coverage for portable targeting surface resolution.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WP_Post', false ) ) {
	class WP_Post {
		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_type = 'post';
		/** @var string */
		public $post_title = '';
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, WP_Post>
	 */
	function get_posts( $args = array() ) {
		return isset( $GLOBALS['rwgc_test_posts'] ) && is_array( $GLOBALS['rwgc_test_posts'] )
			? $GLOBALS['rwgc_test_posts']
			: array();
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value flag.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		return isset( $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] )
			? $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ]
			: '';
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	/**
	 * @param string $post_type Post type.
	 * @return bool
	 */
	function post_type_exists( $post_type = '' ) {
		return false;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * @param int  $length              Length.
	 * @param bool $special_chars       Include special chars.
	 * @param bool $extra_special_chars Include extra special chars.
	 * @return string
	 */
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		return 'testpass';
	}
}

if ( ! function_exists( 'rwgc_normalize_visibility_mode' ) ) {
	/**
	 * @param mixed $mode Raw mode.
	 * @return string
	 */
	function rwgc_normalize_visibility_mode( $mode ) {
		return in_array( sanitize_key( (string) $mode ), array( 'hide', 'hide_if' ), true ) ? 'hide_if' : 'show_if';
	}
}

if ( ! function_exists( 'rwgc_visibility_mode_allows_render' ) ) {
	/**
	 * @param mixed $mode    Raw mode.
	 * @param bool  $matched Whether rules matched.
	 * @return bool
	 */
	function rwgc_visibility_mode_allows_render( $mode, $matched ) {
		return 'hide_if' === rwgc_normalize_visibility_mode( $mode ) ? ! $matched : (bool) $matched;
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-cpt.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-repository.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-rule-registry.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-surface-evaluator.php';

/**
 * @covers RWGC_Targeting_Surface_Evaluator
 * @covers RWGC_Rule_Registry
 */
class TargetingSurfaceEvaluatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_posts']     = array();
		$GLOBALS['rwgc_test_post_meta'] = array();
	}

	/**
	 * @return string
	 */
	private function unsanitizable_rule_json() {
		return (string) wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show_if',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'pro_only',
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'campaign',
								'operator' => 'in',
								'value'    => array( 'launch' ),
							),
						),
					),
				),
			)
		);
	}

	public function test_invalid_library_rule_returns_null_without_recursive_fallback() {
		$post             = new WP_Post();
		$post->ID         = 123;
		$post->post_type  = RWGC_Visibility_Rule_CPT::POST_TYPE;
		$post->post_title = 'Broken rule';

		$GLOBALS['rwgc_test_posts'] = array( $post );
		$GLOBALS['rwgc_test_post_meta'][123][ RWGC_Visibility_Rule_CPT::META_PORTABLE ] = $this->unsanitizable_rule_json();

		$this->assertNull( RWGC_Rule_Registry::get_rule_set_by_id( 123 ) );
	}

	public function test_unsanitizable_inline_portable_rules_fail_closed() {
		$result = RWGC_Targeting_Surface_Evaluator::evaluate(
			array(
				'rwgc_enable_visibility_rules'    => 'yes',
				'rwgc_use_portable_geo_targeting' => 'yes',
				'rwgc_visibility_rules_mode'      => 'show_if',
				'rwgc_portable_geo_targeting'     => $this->unsanitizable_rule_json(),
			)
		);

		$this->assertFalse( $result['portable_match'] );
		$this->assertFalse( $result['rules_match'] );
		$this->assertFalse( $result['should_render'] );
		$this->assertSame( 'visibility_rules_unresolved', $result['reason'] );
	}

	public function test_unsanitizable_hide_mode_portable_rules_still_fail_closed() {
		$result = RWGC_Targeting_Surface_Evaluator::evaluate(
			array(
				'rwgc_enable_visibility_rules'    => 'yes',
				'rwgc_use_portable_geo_targeting' => 'yes',
				'rwgc_visibility_rules_mode'      => 'hide_if',
				'rwgc_portable_geo_targeting'     => $this->unsanitizable_rule_json(),
			)
		);

		$this->assertFalse( $result['portable_match'] );
		$this->assertFalse( $result['rules_match'] );
		$this->assertFalse( $result['should_render'] );
	}
}
