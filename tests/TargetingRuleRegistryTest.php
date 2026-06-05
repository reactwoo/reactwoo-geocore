<?php
/**
 * Regression coverage for visibility rule registry/repository lookups.
 *
 * @package ReactWoo_Geo_Core
 */

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WP_Post', false ) ) {
	/**
	 * Minimal WP_Post test double.
	 */
	class WP_Post {
		/** @var int */
		public $ID;

		/** @var string */
		public $post_type;

		/** @var string */
		public $post_status;

		/** @var string */
		public $post_title;

		/**
		 * @param array<string, mixed> $props Properties.
		 */
		public function __construct( array $props ) {
			foreach ( $props as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	/**
	 * @return string
	 */
	function wp_generate_password() {
		return 'generated';
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	/**
	 * @param string $post_type Post type.
	 * @return bool
	 */
	function post_type_exists( $post_type ) {
		return 'geo_rule' === $post_type;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	function get_post( $post_id ) {
		$post_id = (int) $post_id;
		return isset( $GLOBALS['rwgc_test_posts'][ $post_id ] ) ? $GLOBALS['rwgc_test_posts'][ $post_id ] : null;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, WP_Post|int>
	 */
	function get_posts( $args ) {
		$args   = is_array( $args ) ? $args : array();
		$type   = isset( $args['post_type'] ) ? (string) $args['post_type'] : '';
		$status = isset( $args['post_status'] ) ? $args['post_status'] : array( 'publish' );
		$status = is_array( $status ) ? $status : array( (string) $status );
		$fields = isset( $args['fields'] ) ? (string) $args['fields'] : '';
		$out    = array();

		foreach ( (array) ( $GLOBALS['rwgc_test_posts'] ?? array() ) as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			if ( '' !== $type && $post->post_type !== $type ) {
				continue;
			}
			if ( ! in_array( $post->post_status, $status, true ) ) {
				continue;
			}
			$out[] = 'ids' === $fields ? (int) $post->ID : $post;
		}

		return $out;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key, $single = false ) {
		unset( $single );
		$post_id = (int) $post_id;
		return isset( $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] )
			? $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ]
			: '';
	}
}

$base = dirname( __DIR__ ) . '/includes/';
require_once $base . 'targeting/class-rwgc-target-operators.php';
require_once $base . 'targeting/class-rwgc-targeting-rule-set-schema.php';
require_once $base . 'class-rwgc-visibility-rule-cpt.php';
require_once $base . 'class-rwgc-visibility-rule-repository.php';
require_once $base . 'targeting/class-rwgc-rule-registry.php';
require_once $base . 'targeting/class-rwgc-targeting-surface-evaluator.php';

/**
 * @covers RWGC_Rule_Registry
 * @covers RWGC_Visibility_Rule_Repository
 * @covers RWGC_Targeting_Surface_Evaluator
 */
class TargetingRuleRegistryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['rwgc_test_posts']     = array();
		$GLOBALS['rwgc_test_post_meta'] = array();
	}

	public function test_missing_numeric_rule_id_returns_null_without_recursion() {
		$this->assertNull( RWGC_Rule_Registry::get_rule_set_by_id( 999999 ) );
		$this->assertNull( RWGC_Visibility_Rule_Repository::get_rule_set( 999999 ) );
	}

	public function test_repository_rejects_non_visibility_rule_post() {
		$this->add_post( 17, 'page', 'publish', 'Regular page', array( RWGC_Visibility_Rule_CPT::META_PORTABLE => $this->rule_json() ) );

		$this->assertNull( RWGC_Visibility_Rule_Repository::get_rule_set( 17 ) );
		$this->assertNull( RWGC_Rule_Registry::get_rule_set_by_id( 17 ) );
	}

	public function test_valid_visibility_rule_resolves() {
		$this->add_post( 23, RWGC_Visibility_Rule_CPT::POST_TYPE, 'publish', 'GB rule', array( RWGC_Visibility_Rule_CPT::META_PORTABLE => $this->rule_json() ) );

		$set = RWGC_Rule_Registry::get_rule_set_by_id( 23 );

		$this->assertIsArray( $set );
		$this->assertSame( 'show_if', $set['mode'] );
		$this->assertSame( 'country', $set['rules'][0]['conditions'][0]['type'] );
	}

	public function test_unresolved_visibility_rule_reference_fails_closed() {
		$result = RWGC_Targeting_Surface_Evaluator::evaluate(
			array(
				'rwgc_enable_visibility_rules'   => 'yes',
				'rwgc_visibility_rule_library'   => '999999',
				'rwgc_visibility_rules_mode'     => 'hide_if',
			)
		);

		$this->assertTrue( $result['targeting_enabled'] );
		$this->assertFalse( $result['portable_match'] );
		$this->assertFalse( $result['rules_match'] );
		$this->assertFalse( $result['should_render'] );
		$this->assertSame( 'visibility_rules_unresolved', $result['reason'] );
	}

	/**
	 * @param int                  $id     Post ID.
	 * @param string               $type   Post type.
	 * @param string               $status Post status.
	 * @param string               $title  Post title.
	 * @param array<string, mixed> $meta   Post meta.
	 * @return void
	 */
	private function add_post( $id, $type, $status, $title, array $meta ) {
		$GLOBALS['rwgc_test_posts'][ $id ] = new WP_Post(
			array(
				'ID'          => $id,
				'post_type'   => $type,
				'post_status' => $status,
				'post_title'  => $title,
			)
		);
		$GLOBALS['rwgc_test_post_meta'][ $id ] = $meta;
	}

	/**
	 * @return string
	 */
	private function rule_json() {
		return (string) wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show_if',
				'match'   => 'any',
				'rules'   => array(
					array(
						'id'         => 'country_gb',
						'match'      => 'all',
						'conditions' => array(
							array(
								'type'     => 'country',
								'operator' => 'in',
								'value'    => array( 'GB' ),
							),
						),
					),
				),
			)
		);
	}
}
