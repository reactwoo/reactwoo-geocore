<?php
/**
 * PHPUnit coverage for visibility rule library persistence.
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
		public $post_title;

		/** @var string */
		public $post_status;

		/**
		 * @param array<string, mixed> $data Post fields.
		 */
		public function __construct( array $data = array() ) {
			foreach ( $data as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->{$key} = $value;
				}
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

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return false;
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
	 * @return array<int, WP_Post>
	 */
	function get_posts() {
		return array_values( isset( $GLOBALS['rwgc_test_posts'] ) ? $GLOBALS['rwgc_test_posts'] : array() );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	function get_post_meta( $post_id, $key ) {
		$post_id = (int) $post_id;
		return isset( $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] : '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$post_id = (int) $post_id;
		if ( ! isset( $GLOBALS['rwgc_test_post_meta'][ $post_id ] ) ) {
			$GLOBALS['rwgc_test_post_meta'][ $post_id ] = array();
		}
		$GLOBALS['rwgc_test_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	/**
	 * @param array<string, mixed> $data Post data.
	 * @return int
	 */
	function wp_insert_post( $data ) {
		$id = isset( $GLOBALS['rwgc_test_next_post_id'] ) ? (int) $GLOBALS['rwgc_test_next_post_id'] : 1;
		$GLOBALS['rwgc_test_next_post_id'] = $id + 1;
		$data['ID'] = $id;
		$GLOBALS['rwgc_test_posts'][ $id ] = new WP_Post( $data );
		return $id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * @param array<string, mixed> $data Post data.
	 * @return int
	 */
	function wp_update_post( $data ) {
		$id = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
		if ( $id <= 0 || empty( $GLOBALS['rwgc_test_posts'][ $id ] ) ) {
			return 0;
		}
		foreach ( $data as $key => $value ) {
			$GLOBALS['rwgc_test_posts'][ $id ]->{$key} = $value;
		}
		return $id;
	}
}

require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-target-operators.php';
require_once dirname( __DIR__ ) . '/includes/targeting/class-rwgc-targeting-rule-set-schema.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-cpt.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgc-visibility-rule-repository.php';

/**
 * @covers RWGC_Visibility_Rule_Repository
 */
class VisibilityRuleRepositoryTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rwgc_test_posts']        = array();
		$GLOBALS['rwgc_test_post_meta']    = array();
		$GLOBALS['rwgc_test_next_post_id'] = 1000;
	}

	public function test_rejects_pro_gated_json_that_would_sanitize_to_empty_without_mutating_existing_rule() {
		$post_id = 101;
		$GLOBALS['rwgc_test_posts'][ $post_id ] = new WP_Post(
			array(
				'ID'          => $post_id,
				'post_type'   => RWGC_Visibility_Rule_CPT::POST_TYPE,
				'post_title'  => 'Existing rule',
				'post_status' => 'publish',
			)
		);
		$GLOBALS['rwgc_test_post_meta'][ $post_id ][ RWGC_Visibility_Rule_CPT::META_PORTABLE ] = '{"existing":true}';

		$result = RWGC_Visibility_Rule_Repository::save(
			'Changed rule',
			'draft',
			wp_json_encode(
				array(
					'enabled' => true,
					'mode'    => 'show',
					'match'   => 'all',
					'rules'   => array(
						array(
							'id'         => 'campaign_rule',
							'match'      => 'all',
							'conditions' => array(
								array(
									'type'     => 'campaign',
									'operator' => 'in',
									'value'    => array( 'spring_sale' ),
								),
							),
						),
					),
				)
			),
			$post_id
		);

		$this->assertSame( 0, $result );
		$this->assertSame( 'Existing rule', $GLOBALS['rwgc_test_posts'][ $post_id ]->post_title );
		$this->assertSame( 'publish', $GLOBALS['rwgc_test_posts'][ $post_id ]->post_status );
		$this->assertSame( '{"existing":true}', $GLOBALS['rwgc_test_post_meta'][ $post_id ][ RWGC_Visibility_Rule_CPT::META_PORTABLE ] );
	}

	public function test_rejects_updates_for_non_visibility_rule_posts_without_mutating_them() {
		$post_id = 202;
		$GLOBALS['rwgc_test_posts'][ $post_id ] = new WP_Post(
			array(
				'ID'          => $post_id,
				'post_type'   => 'page',
				'post_title'  => 'Checkout',
				'post_status' => 'publish',
			)
		);

		$result = RWGC_Visibility_Rule_Repository::save(
			'Corrupted title',
			'draft',
			$this->valid_rule_json(),
			$post_id
		);

		$this->assertSame( 0, $result );
		$this->assertSame( 'page', $GLOBALS['rwgc_test_posts'][ $post_id ]->post_type );
		$this->assertSame( 'Checkout', $GLOBALS['rwgc_test_posts'][ $post_id ]->post_title );
		$this->assertSame( 'publish', $GLOBALS['rwgc_test_posts'][ $post_id ]->post_status );
		$this->assertArrayNotHasKey( $post_id, $GLOBALS['rwgc_test_post_meta'] );
	}

	public function test_updates_existing_visibility_rule_when_payload_is_valid() {
		$post_id = 303;
		$GLOBALS['rwgc_test_posts'][ $post_id ] = new WP_Post(
			array(
				'ID'          => $post_id,
				'post_type'   => RWGC_Visibility_Rule_CPT::POST_TYPE,
				'post_title'  => 'Existing rule',
				'post_status' => 'draft',
			)
		);

		$result = RWGC_Visibility_Rule_Repository::save(
			'Published rule',
			'publish',
			$this->valid_rule_json(),
			$post_id
		);

		$this->assertSame( $post_id, $result );
		$this->assertSame( 'Published rule', $GLOBALS['rwgc_test_posts'][ $post_id ]->post_title );
		$this->assertSame( 'publish', $GLOBALS['rwgc_test_posts'][ $post_id ]->post_status );
		$this->assertNotSame( '', $GLOBALS['rwgc_test_post_meta'][ $post_id ][ RWGC_Visibility_Rule_CPT::META_PORTABLE ] );
	}

	/**
	 * @return string
	 */
	private function valid_rule_json() {
		return wp_json_encode(
			array(
				'enabled' => true,
				'mode'    => 'show',
				'match'   => 'all',
				'rules'   => array(
					array(
						'id'         => 'country_rule',
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
