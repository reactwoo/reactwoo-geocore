<?php
/**
 * Elementor assignment discovery tests.
 *
 * @package ReactWooGeoCore
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single flag.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		unset( $single );
		$meta = isset( $GLOBALS['rwgc_test_post_meta'] ) && is_array( $GLOBALS['rwgc_test_post_meta'] )
			? $GLOBALS['rwgc_test_post_meta']
			: array();
		return $meta[ absint( $post_id ) ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	function get_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return null;
		}
		$post              = new WP_Post();
		$post->ID          = $post_id;
		$post->post_status = 'publish';
		$post->post_title  = 'Rule ' . $post_id;
		return $post;
	}
}

/**
 * RWGC_Elementor_Assignment_Discovery tests.
 */
class RWGCElementorAssignmentDiscoveryTest extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'RWGC_Elementor_Assignment_Discovery', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-rwgc-elementor-assignment-discovery.php';
		}
		$GLOBALS['rwgc_test_post_meta'] = array();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['rwgc_test_post_meta'] );
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_descendant_keeps_nearest_assigned_ancestor_through_unassigned_wrapper() {
		$GLOBALS['rwgc_test_post_meta'][10]['_elementor_data'] = wp_json_encode(
			array(
				array(
					'id'       => 'section-a',
					'elType'   => 'section',
					'settings' => array(
						'rwgc_enable_visibility_rules' => 'yes',
						'rwgc_visibility_rule_library' => '101',
						'rwgc_visibility_rules_mode'   => 'hide_if',
					),
					'elements' => array(
						array(
							'id'       => 'container-b',
							'elType'   => 'container',
							'settings' => array(),
							'elements' => array(
								array(
									'id'         => 'widget-c',
									'elType'     => 'widget',
									'widgetType' => 'heading',
									'settings'   => array(
										'rwgc_enable_visibility_rules' => 'yes',
										'rwgc_visibility_rule_library' => '202',
										'rwgc_visibility_rules_mode'   => 'show_if',
									),
								),
							),
						),
					),
				),
			)
		);

		$result      = RWGC_Elementor_Assignment_Discovery::get_assignments_for_content( 10, 'page' );
		$assignments = $result['assignments'];

		$this->assertCount( 2, $assignments );
		$this->assertSame( 'elementor:section:section-a', $assignments[0]['assignment_id'] );
		$this->assertSame( '', $assignments[0]['parent_assignment_id'] );
		$this->assertSame( 'elementor:widget:widget-c', $assignments[1]['assignment_id'] );
		$this->assertSame( 'elementor:section:section-a', $assignments[1]['parent_assignment_id'] );
	}

	/**
	 * @return void
	 */
	public function test_document_assignment_is_root_ancestor_for_element_assignments() {
		$GLOBALS['rwgc_test_post_meta'][20]['_elementor_page_settings'] = array(
			'rwgc_enable_visibility_rules' => 'yes',
			'rwgc_visibility_rule_library' => '303',
			'rwgc_visibility_rules_mode'   => 'hide_if',
		);
		$GLOBALS['rwgc_test_post_meta'][20]['_elementor_data']          = wp_json_encode(
			array(
				array(
					'id'       => 'container-a',
					'elType'   => 'container',
					'settings' => array(),
					'elements' => array(
						array(
							'id'         => 'widget-b',
							'elType'     => 'widget',
							'widgetType' => 'button',
							'settings'   => array(
								'rwgc_enable_visibility_rules' => 'yes',
								'rwgc_visibility_rule_library' => '404',
								'rwgc_visibility_rules_mode'   => 'show_if',
							),
						),
					),
				),
			)
		);

		$result      = RWGC_Elementor_Assignment_Discovery::get_assignments_for_content( 20, 'page' );
		$assignments = $result['assignments'];

		$this->assertCount( 2, $assignments );
		$this->assertSame( 'elementor:document:20', $assignments[0]['assignment_id'] );
		$this->assertSame( '', $assignments[0]['parent_assignment_id'] );
		$this->assertSame( 'elementor:widget:widget-b', $assignments[1]['assignment_id'] );
		$this->assertSame( 'elementor:document:20', $assignments[1]['parent_assignment_id'] );
	}
}
